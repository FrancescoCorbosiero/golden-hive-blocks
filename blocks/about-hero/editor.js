(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const listOps = utils.listOps;
    const ItemControls = utils.ItemControls;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/about-hero', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, text, imageUrl, values, reverse } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(values, function(next) { setAttributes({ values: next }); });

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
                        el(MediaField, {
                            value: imageUrl,
                            onSelect: function(media) { setAttributes({ imageUrl: media.url, imageUrlId: media.id || 0 }); },
                            onRemove: function() { setAttributes({ imageUrl: '', imageUrlId: 0 }); }
                        })
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
                                el(ItemControls, { ops: ops, index: index, count: values.length, label: 'Valore ' + (index + 1) }),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: item.title || '',
                                    onChange: function(val) { ops.update(index, 'title', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Testo',
                                    value: item.text || '',
                                    onChange: function(val) { ops.update(index, 'text', val); }
                                })
                            );
                        })
                    ),
                    el(PanelBody, { title: 'Aggiungi Valore', initialOpen: false },
                        el(Button, {
                            variant: 'primary',
                            onClick: function() { ops.add({ title: '', text: '' }); },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi valore')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' })
                        ),
                        title: 'About Hero',
                        text: title ? '"' + title + '"' + (reverse ? ' (invertito)' : '') + ' — ' + values.length + ' valori' : 'Configura questo blocco nel pannello laterale.',
                        thumbs: [imageUrl]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
