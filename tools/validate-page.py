#!/usr/bin/env python3
"""
Valida il markup Gutenberg di una pagina contro gli schemi reali dei blocchi.

    python3 tools/validate-page.py pages/**/*.html

Per ogni blocco `<!-- wp:golden-hive/... {...} /-->` controlla:
  • che il blocco esista in blocks/
  • che il JSON degli attributi sia valido
  • che ogni attributo sia dichiarato in block.json e del tipo giusto
  • che le chiavi dentro gli array (slides, categories, badges…) siano
    effettivamente lette dal render.php — le altre vengono ignorate a runtime
  • che le icone di trust-badges esistano nella libreria del blocco
  • che `align` sia fra quelli supportati

Esce con 1 se trova problemi, così è usabile in CI.
"""
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Chiavi lette dai render.php dentro gli attributi di tipo array. Tutto il
# resto viene scartato silenziosamente a runtime, quindi vale segnalarlo.
NESTED = {
    'golden-hive/hero-carousel': {'slides': {
        'image', 'imageId', 'objectPosition', 'eyebrow', 'title', 'subtitle',
        'buttonText', 'buttonUrl'}},
    'golden-hive/category-slider': {'categories': {'name', 'image', 'imageId', 'url'}},
    'golden-hive/brand-marquee': {'brands': {'name', 'logo', 'logoId', 'url'}},
    'golden-hive/trust-badges': {'badges': {'icon', 'title'}},
    'golden-hive/about-hero': {'values': {'title', 'text'}},
    'golden-hive/authenticity-guarantee': {'steps': {'title', 'text'}},
    'golden-hive/faq-schema': {'items': {'question', 'answer'}},
    'golden-hive/legit-check': {'checks': {'area', 'real', 'fake'}},
    'golden-hive/store-hours': {'hours': {'day', 'time'}},
    'golden-hive/instagram-feed': {'images': {'url', 'urlId', 'alt'}},
    'golden-hive/social-proof': {'notifications': {
        'product', 'image', 'imageId', 'location', 'time'}},
}

TRUST_ICONS = {'authentic', 'shipping', 'returns', 'secure', 'support', 'quality'}

# Attributi che WordPress gestisce da sé, non dichiarati in block.json.
CORE_ATTRS = {'className', 'align', 'style', 'lock', 'metadata',
              'backgroundColor', 'textColor', 'fontSize'}

PY_TYPES = {'string': str, 'number': (int, float), 'boolean': bool,
            'array': list, 'object': dict}

BLOCK_RE = re.compile(r'<!--\s*wp:(golden-hive/[a-z-]+)\s*(\{.*?\})?\s*/?-->', re.S)


def load_schemas():
    schemas = {}
    blocks_dir = os.path.join(ROOT, 'blocks')
    for name in sorted(os.listdir(blocks_dir)):
        path = os.path.join(blocks_dir, name, 'block.json')
        if os.path.exists(path):
            with open(path, encoding='utf-8') as handle:
                data = json.load(handle)
            schemas[data['name']] = data
    return schemas


def check(path, schemas):
    with open(path, encoding='utf-8') as handle:
        src = handle.read()

    problems = []
    for match in BLOCK_RE.finditer(src):
        name, raw = match.group(1), match.group(2)
        line = src[:match.start()].count('\n') + 1

        if name not in schemas:
            problems.append((line, name, 'blocco inesistente nel plugin'))
            continue
        if not raw:
            continue
        try:
            attrs = json.loads(raw)
        except ValueError as err:
            problems.append((line, name, 'JSON non valido: %s' % err))
            continue

        schema = schemas[name].get('attributes', {})
        supports = schemas[name].get('supports', {})

        for key, value in attrs.items():
            if key in CORE_ATTRS:
                if key == 'align' and not supports.get('align'):
                    problems.append((line, name,
                                     'align="%s" ma il blocco non supporta align' % value))
                elif key == 'align' and value not in supports['align']:
                    problems.append((line, name,
                                     'align="%s": ammessi %s' % (value, supports['align'])))
                continue

            if key not in schema:
                problems.append((line, name, 'attributo sconosciuto: "%s"' % key))
                continue

            expected = PY_TYPES.get(schema[key].get('type'))
            if expected and not isinstance(value, expected):
                problems.append((line, name, '"%s" dovrebbe essere %s, trovato %s'
                                 % (key, schema[key]['type'], type(value).__name__)))

            allowed = NESTED.get(name, {}).get(key)
            if allowed and isinstance(value, list):
                for index, item in enumerate(value):
                    if not isinstance(item, dict):
                        continue
                    for inner in item:
                        if inner not in allowed:
                            problems.append((line, name, '%s[%d]: chiave ignorata dal render "%s"'
                                             % (key, index, inner)))

            if name == 'golden-hive/trust-badges' and key == 'badges':
                for index, badge in enumerate(value):
                    if isinstance(badge, dict) and badge.get('icon') not in TRUST_ICONS:
                        problems.append((line, name, 'badges[%d]: icona "%s" inesistente'
                                         % (index, badge.get('icon'))))
    return problems


def main(argv):
    if not argv:
        print(__doc__)
        return 2

    schemas = load_schemas()
    failed = False
    for path in sorted(argv):
        problems = check(path, schemas)
        rel = os.path.relpath(path, ROOT)
        print(('✗ ' if problems else '✓ ') + rel)
        for line, name, message in problems:
            print('    L%d %s: %s' % (line, name, message))
        failed = failed or bool(problems)
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
