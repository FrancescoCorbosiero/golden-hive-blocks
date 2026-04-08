(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, Button } = wp.components;

    registerBlockType('golden-hive/legit-check', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, subtitle, checks, buttonText, buttonUrl } = attributes;

            var updateCheck = function(index, field, value) {
                var updated = checks.map(function(check, i) {
                    if (i === index) {
                        var copy = Object.assign({}, check);
                        copy[field] = value;
                        return copy;
                    }
                    return check;
                });
                setAttributes({ checks: updated });
            };

            var removeCheck = function(index) {
                var updated = checks.filter(function(_, i) { return i !== index; });
                setAttributes({ checks: updated });
            };

            var addCheck = function() {
                var updated = checks.concat([{ area: '', real: '', fake: '' }]);
                setAttributes({ checks: updated });
            };

            var moveCheck = function(index, direction) {
                var updated = [].concat(checks);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ checks: updated });
            };

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuto', initialOpen: true },
                        el(TextControl, {
                            label: 'Eyebrow',
                            value: eyebrow,
                            onChange: function(val) { setAttributes({ eyebrow: val }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(TextareaControl, {
                            label: 'Sottotitolo',
                            value: subtitle,
                            onChange: function(val) { setAttributes({ subtitle: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Verifiche (' + checks.length + ')', initialOpen: false },
                        checks.map(function(check, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Verifica ' + (index + 1)),
                                el(TextControl, {
                                    label: 'Area',
                                    value: check.area || '',
                                    onChange: function(val) { updateCheck(index, 'area', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Autentico',
                                    value: check.real || '',
                                    onChange: function(val) { updateCheck(index, 'real', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Falso',
                                    value: check.fake || '',
                                    onChange: function(val) { updateCheck(index, 'fake', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveCheck(index, -1); }, disabled: index === 0 }, '\u2191'),
                                    el(Button, { isSmall: true, onClick: function() { moveCheck(index, 1); }, disabled: index === checks.length - 1 }, '\u2193'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeCheck(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addCheck, style: { marginTop: '8px' } }, 'Aggiungi verifica')
                    ),
                    el(PanelBody, { title: 'Pulsante', initialOpen: false },
                        el(TextControl, {
                            label: 'Testo pulsante',
                            value: buttonText,
                            onChange: function(val) { setAttributes({ buttonText: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL pulsante',
                            value: buttonUrl,
                            onChange: function(val) { setAttributes({ buttonUrl: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Legit Check'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        checks.length > 0
                            ? checks.length + ' verifiche configurate'
                            : 'Configura questo blocco nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
