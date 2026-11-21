/**
 * Golden Hive Blocks — shared editor utilities.
 *
 * Loaded as the `gh-editor-utils` script handle, declared as a dependency in
 * every block's editor.asset.php so it always executes before the block
 * scripts. Plain browser JS (no build step) exposing window.ghEditorUtils.
 */
(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var MediaUpload = wp.blockEditor.MediaUpload;
    var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
    var Button = wp.components.Button;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;

    /**
     * The store's details (Personalizza → Golden Hive → Negozio), printed by
     * includes/store.php. A block attribute the page leaves out shows the
     * store's value; written, even empty, it is the page's own.
     */
    var store = window.ghStore || {};

    /**
     * A text field for an attribute that follows the store. Left out, the
     * store's value is its placeholder; typing writes this page's own value;
     * emptied, the detail is hidden on this page. StoreReset brings the
     * store's value back.
     *
     * props: { attributes, setAttributes, attr, label, storeValue, help, textarea, rows }
     */
    function StoreText(props) {
        var value = props.attributes[props.attr];
        var left = value === undefined || value === null;
        var help = props.help;
        if (left) {
            help = props.storeValue
                ? 'Dal negozio (Personalizza → Golden Hive → Negozio). Scrivi qui per cambiarlo solo su questa pagina.'
                : 'Il negozio non l\'ha impostato: Personalizza → Golden Hive → Negozio.';
        } else if (value === '') {
            help = 'Nascosto su questa pagina.';
        }
        return el(props.textarea ? TextareaControl : TextControl, {
            label: props.label,
            value: left ? '' : String(value),
            placeholder: left ? (props.storeValue || '') : '',
            help: help,
            rows: props.rows,
            onChange: function (next) {
                var patch = {};
                patch[props.attr] = next;
                props.setAttributes(patch);
            }
        });
    }

    /**
     * "Usa i dati del negozio": forgets this page's own values of the given
     * attributes, so they follow the store again. Shown only when the page
     * wrote one of them.
     *
     * props: { attributes, setAttributes, attrs, label }
     */
    function StoreReset(props) {
        var written = props.attrs.some(function (a) { return props.attributes[a] !== undefined; });
        if (!written) return null;
        return el(Button, {
            variant: 'secondary',
            onClick: function () {
                var patch = {};
                props.attrs.forEach(function (a) { patch[a] = undefined; });
                props.setAttributes(patch);
            }
        }, props.label || 'Usa i dati del negozio');
    }

    /**
     * Immutable list operations for repeatable-item blocks (slides, badge
     * lists, FAQ items, …). `items` is the current attribute array and
     * `setItems` persists the next array (typically
     * function (next) { setAttributes({ items: next }); }).
     */
    function listOps(items, setItems) {
        function cloneAll() {
            return items.map(function (item) {
                return Object.assign({}, item);
            });
        }

        return {
            update: function (index, field, value) {
                var next = cloneAll();
                next[index][field] = value;
                setItems(next);
            },
            updateMany: function (index, patch) {
                var next = cloneAll();
                next[index] = Object.assign(next[index], patch);
                setItems(next);
            },
            add: function (item) {
                setItems(cloneAll().concat([item]));
            },
            remove: function (index) {
                setItems(items.filter(function (_, i) {
                    return i !== index;
                }));
            },
            move: function (index, dir) {
                var to = index + dir;
                if (to < 0 || to >= items.length) {
                    return;
                }
                var next = cloneAll();
                var tmp = next[index];
                next[index] = next[to];
                next[to] = tmp;
                setItems(next);
            }
        };
    }

    /**
     * Header row for a repeatable item: "Elemento N" label plus ↑ / ↓ /
     * Elimina buttons wired to listOps.
     */
    function ItemControls(props) {
        var ops = props.ops;
        var index = props.index;
        var count = props.count;

        return el('div', { style: { display: 'flex', gap: '4px', alignItems: 'center', marginBottom: '8px' } },
            el('strong', { style: { flex: '1' } }, props.label || ('Elemento ' + (index + 1))),
            el(Button, {
                variant: 'secondary',
                size: 'small',
                disabled: index === 0,
                'aria-label': 'Sposta su',
                onClick: function () { ops.move(index, -1); }
            }, '↑'),
            el(Button, {
                variant: 'secondary',
                size: 'small',
                disabled: index === count - 1,
                'aria-label': 'Sposta giù',
                onClick: function () { ops.move(index, 1); }
            }, '↓'),
            el(Button, {
                variant: 'secondary',
                size: 'small',
                isDestructive: true,
                onClick: function () { ops.remove(index); }
            }, 'Elimina')
        );
    }

    /**
     * Media picker with preview and Seleziona/Rimuovi buttons.
     *
     * props: {
     *   value:       current image URL ('' when unset),
     *   onSelect:    function (media) — receives the FULL media object;
     *                callers should persist BOTH media.url and media.id,
     *   onRemove:    function () — clear both url and id,
     *   label:       optional heading above the field,
     *   allowedTypes: default ['image'],
     *   selectLabel / removeLabel: button texts (Italian defaults)
     * }
     */
    function MediaField(props) {
        return el(MediaUploadCheck, {},
            el(MediaUpload, {
                onSelect: props.onSelect,
                allowedTypes: props.allowedTypes || ['image'],
                render: function (obj) {
                    return el('div', { className: 'gh-editor-media-field' },
                        props.label ? el('p', { style: { marginBottom: '4px', fontWeight: '600' } }, props.label) : null,
                        props.value
                            ? el(Fragment, {},
                                el('img', {
                                    src: props.value,
                                    style: { maxWidth: '100%', height: 'auto', marginBottom: '4px', display: 'block' }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px' } },
                                    el(Button, { variant: 'secondary', size: 'small', onClick: obj.open }, 'Sostituisci'),
                                    el(Button, { variant: 'secondary', size: 'small', isDestructive: true, onClick: props.onRemove },
                                        props.removeLabel || 'Rimuovi immagine')
                                )
                            )
                            : el(Button, { variant: 'secondary', onClick: obj.open },
                                props.selectLabel || 'Seleziona immagine')
                    );
                }
            })
        );
    }

    /**
     * Canvas placeholder card shown in the editor.
     *
     * props: {
     *   icon:   SVG element (or any element) rendered in the icon slot,
     *   title:  block title,
     *   text:   summary line ("3 slide configurate"),
     *   thumbs: optional array of image URLs previewed as a strip
     * }
     */
    function Placeholder(props) {
        var thumbs = (props.thumbs || []).filter(Boolean).slice(0, 6);

        return el('div', { className: 'gh-editor-placeholder' },
            props.icon ? el('div', { className: 'gh-editor-placeholder__icon' }, props.icon) : null,
            el('div', { className: 'gh-editor-placeholder__title' }, props.title),
            props.text ? el('div', { className: 'gh-editor-placeholder__text' }, props.text) : null,
            thumbs.length
                ? el('div', { className: 'gh-editor-placeholder__thumbs' },
                    thumbs.map(function (src, i) {
                        return el('img', { key: i, src: src, alt: '' });
                    }))
                : null
        );
    }

    /**
     * Product search field (WooCommerce Store API, published products).
     *
     * props: {
     *   value:    product id (0 / undefined when none),
     *   onChange: function (id, product) — product is the Store API object
     *             ({ id, name, permalink, images, prices }) or null on clear,
     *   label:    field label, help: optional help text
     * }
     */
    function ProductPicker(props) {
        var useState = wp.element.useState;
        var useEffect = wp.element.useEffect;
        var useRef = wp.element.useRef;
        var ComboboxControl = wp.components.ComboboxControl;
        var apiFetch = wp.apiFetch;

        var found = useState([]);
        var options = found[0];
        var setOptions = found[1];
        var timer = useRef(null);
        var byId = useRef({});

        function toOption(product) {
            byId.current[String(product.id)] = product;
            return { value: String(product.id), label: product.name };
        }

        // Show the saved product's name before any search.
        useEffect(function () {
            if (!props.value || byId.current[String(props.value)]) return;
            apiFetch({ path: '/wc/store/v1/products/' + props.value })
                .then(function (product) { setOptions([toOption(product)]); })
                .catch(function () {});
        }, [props.value]);

        function search(term) {
            if (timer.current) clearTimeout(timer.current);
            if (!term || term.length < 2) return;
            timer.current = setTimeout(function () {
                apiFetch({ path: '/wc/store/v1/products?per_page=20&search=' + encodeURIComponent(term) })
                    .then(function (list) { setOptions((list || []).map(toOption)); })
                    .catch(function () {});
            }, 250);
        }

        if (!ComboboxControl || !apiFetch) {
            return el('p', {}, 'Ricerca prodotti non disponibile in questa versione di WordPress.');
        }

        return el(ComboboxControl, {
            label: props.label || 'Prodotto',
            help: props.help,
            value: props.value ? String(props.value) : null,
            options: options,
            onFilterValueChange: search,
            onChange: function (value) {
                var id = value ? parseInt(value, 10) : 0;
                props.onChange(id, id ? (byId.current[String(id)] || null) : null);
            }
        });
    }

    window.ghEditorUtils = {
        el: el,
        Fragment: Fragment,
        listOps: listOps,
        ItemControls: ItemControls,
        MediaField: MediaField,
        Placeholder: Placeholder,
        ProductPicker: ProductPicker,
        store: store,
        StoreText: StoreText,
        StoreReset: StoreReset
    };
})(window.wp);
