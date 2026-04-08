(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, Button } = wp.components;

    registerBlockType('golden-hive/product-spotlight', {
        edit: function({ attributes, setAttributes }) {
            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el('div', { style: { marginBottom: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '600' } }, 'Immagine Prodotto'),
                            attributes.imageUrl
                                ? el('div', {},
                                    el('img', { src: attributes.imageUrl, style: { width: '100%', height: 'auto', marginBottom: '8px', borderRadius: '4px' } }),
                                    el(Button, {
                                        isDestructive: true,
                                        variant: 'secondary',
                                        onClick: function() { setAttributes({ imageUrl: '' }); },
                                        style: { width: '100%', justifyContent: 'center' }
                                    }, 'Rimuovi Immagine')
                                )
                                : el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { setAttributes({ imageUrl: media.url }); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el(Button, {
                                                variant: 'secondary',
                                                onClick: obj.open,
                                                style: { width: '100%', justifyContent: 'center' }
                                            }, 'Seleziona Immagine');
                                        }
                                    })
                                )
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
                            label: 'Autenticit\u00e0',
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
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Product Spotlight'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
