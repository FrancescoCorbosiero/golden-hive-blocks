(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/about-hero', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, text, imageUrl, values, reverse } = attributes;

            var updateValue = function(index, field, val) {
                var updated = values.map(function(item, i) {
                    if (i === index) {
                        var copy = Object.assign({}, item);
                        copy[field] = val;
                        return copy;
                    }
                    return item;
                });
                setAttributes({ values: updated });
            };

            var removeValue = function(index) {
                var updated = values.filter(function(_, i) { return i !== index; });
                setAttributes({ values: updated });
            };

            var addValue = function() {
                var updated = values.concat([{ title: '', text: '' }]);
                setAttributes({ values: updated });
            };

            var moveValue = function(index, direction) {
                var updated = [].concat(values);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ values: updated });
            };

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
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
                            label: 'Testo',
                            value: text,
                            onChange: function(val) { setAttributes({ text: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Immagine', initialOpen: false },
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: function(media) { setAttributes({ imageUrl: media.url }); },
                                allowedTypes: ['image'],
                                render: function(obj) {
                                    return el('div', {},
                                        imageUrl
                                            ? el('div', {},
                                                el('img', { src: imageUrl, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                el(Button, { isSecondary: true, isSmall: true, onClick: function() { setAttributes({ imageUrl: '' }); } }, 'Rimuovi immagine')
                                            )
                                            : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                    );
                                }
                            })
                        )
                    ),
                    el(PanelBody, { title: 'Layout', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Inverti layout',
                            checked: reverse,
                            onChange: function(val) { setAttributes({ reverse: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Valori (' + values.length + ')', initialOpen: false },
                        values.map(function(item, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Valore ' + (index + 1)),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: item.title || '',
                                    onChange: function(val) { updateValue(index, 'title', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Testo',
                                    value: item.text || '',
                                    onChange: function(val) { updateValue(index, 'text', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveValue(index, -1); }, disabled: index === 0 }, "\u2191"),
                                    el(Button, { isSmall: true, onClick: function() { moveValue(index, 1); }, disabled: index === values.length - 1 }, "\u2193"),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeValue(index); } }, 'Elimina')
                                )
                            );
                        })
                    ),
                    el(PanelBody, { title: 'Aggiungi Valore', initialOpen: false },
                        el(Button, { isPrimary: true, onClick: addValue, style: { marginTop: '8px' } }, 'Aggiungi valore')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'About Hero'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        title ? '"' + title + '"' + (reverse ? ' (invertito)' : '') + ' \u2014 ' + values.length + ' valori' : 'Configura questo blocco nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
