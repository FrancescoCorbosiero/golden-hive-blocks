(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;
    const { el, Fragment, MediaField, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/product-spotlight', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el('div', { style: { marginBottom: '16px' } },
                            el(MediaField, {
                                label: 'Immagine Prodotto',
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
                            label: 'Categoria',
                            value: attributes.category || '',
                            onChange: function(value) { setAttributes({ category: value }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: attributes.title || '',
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextControl, {
                            label: 'Descrizione',
                            value: attributes.description || '',
                            onChange: function(value) { setAttributes({ description: value }); }
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
                        })
                    ),
                    el(PanelBody, { title: 'Dettagli Prodotto', initialOpen: false },
                        el(TextControl, {
                            label: 'Condizione',
                            value: attributes.condition || '',
                            onChange: function(value) { setAttributes({ condition: value }); }
                        }),
                        el(TextControl, {
                            label: 'Taglia',
                            value: attributes.size || '',
                            onChange: function(value) { setAttributes({ size: value }); }
                        }),
                        el(TextControl, {
                            label: 'Autenticità',
                            value: attributes.authenticity || '',
                            onChange: function(value) { setAttributes({ authenticity: value }); }
                        }),
                        el(TextControl, {
                            label: 'Prezzo Attuale',
                            value: attributes.currentPrice || '',
                            onChange: function(value) { setAttributes({ currentPrice: value }); }
                        }),
                        el(TextControl, {
                            label: 'Prezzo Originale',
                            value: attributes.originalPrice || '',
                            onChange: function(value) { setAttributes({ originalPrice: value }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z' })
                        ),
                        title: 'Product Spotlight',
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
