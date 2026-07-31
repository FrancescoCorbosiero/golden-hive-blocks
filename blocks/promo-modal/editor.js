(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, RangeControl } = wp.components;
    const { el, Fragment, MediaField, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/promo-modal', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el(TextControl, {
                            label: 'ID Modale',
                            value: attributes.modalId || '',
                            onChange: function(value) { setAttributes({ modalId: value }); }
                        }),
                        el('div', { style: { marginBottom: '16px' } },
                            el(MediaField, {
                                label: 'Immagine',
                                value: attributes.imageUrl || '',
                                onSelect: function(media) {
                                    setAttributes({ imageUrl: media.url, imageUrlId: media.id });
                                },
                                onRemove: function() {
                                    setAttributes({ imageUrl: '', imageUrlId: 0 });
                                },
                                selectLabel: 'Seleziona Immagine',
                                removeLabel: 'Rimuovi Immagine'
                            })
                        ),
                        el(TextControl, {
                            label: 'Badge',
                            value: attributes.badge || '',
                            onChange: function(value) { setAttributes({ badge: value }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: attributes.title || '',
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextareaControl, {
                            label: 'Testo',
                            value: attributes.text || '',
                            onChange: function(value) { setAttributes({ text: value }); }
                        }),
                        el(TextControl, {
                            label: 'Codice Coupon',
                            value: attributes.couponCode || '',
                            onChange: function(value) { setAttributes({ couponCode: value }); }
                        }),
                        el(TextControl, {
                            label: 'Testo Pulsante',
                            value: attributes.buttonText || '',
                            onChange: function(value) { setAttributes({ buttonText: value }); }
                        }),
                        el(TextControl, {
                            label: 'URL Pulsante',
                            value: attributes.buttonUrl || '',
                            onChange: function(value) { setAttributes({ buttonUrl: value }); }
                        }),
                        el(TextControl, {
                            label: 'Disclaimer',
                            value: attributes.disclaimer || '',
                            onChange: function(value) { setAttributes({ disclaimer: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Comportamento', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra automaticamente',
                            checked: !!attributes.autoShow,
                            onChange: function(value) { setAttributes({ autoShow: value }); }
                        }),
                        el(RangeControl, {
                            label: 'Ritardo visualizzazione (secondi)',
                            value: (attributes.showDelay || 0) / 1000,
                            onChange: function(value) { setAttributes({ showDelay: value * 1000 }); },
                            min: 0,
                            max: 60,
                            step: 1
                        }),
                        el(ToggleControl, {
                            label: 'Mostra solo una volta',
                            checked: !!attributes.showOnce,
                            onChange: function(value) { setAttributes({ showOnce: value }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z' })
                        ),
                        title: 'Promo Modal',
                        text: 'Configura questo blocco nel pannello laterale.',
                        thumbs: [attributes.imageUrl]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
