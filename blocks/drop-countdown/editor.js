(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ColorPicker } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;
    const ProductPicker = utils.ProductPicker;

    registerBlockType('golden-hive/drop-countdown', {
        edit: function({ attributes, setAttributes }) {
            const { productId, productName, productImage, releaseDate, buttonText, buttonUrl, eyebrow, backgroundColor } = attributes;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Prodotto del drop', initialOpen: true },
                        el(ProductPicker, {
                            label: 'Prodotto',
                            help: 'Il blocco prende dal prodotto la data di uscita (Dati prodotto → Generale → Drop), l\'immagine, il prezzo e il link. All\'uscita il bottone diventa "Acquista ora".',
                            value: productId,
                            onChange: function(id, product) {
                                setAttributes({
                                    productId: id,
                                    productName: product ? product.name : productName
                                });
                            }
                        })
                    ),
                    el(PanelBody, { title: 'Contenuto', initialOpen: !productId },
                        el(TextControl, {
                            label: 'Eyebrow',
                            value: eyebrow,
                            onChange: function(val) { setAttributes({ eyebrow: val }); }
                        }),
                        el(TextControl, {
                            label: 'Nome Prodotto',
                            value: productName,
                            onChange: function(val) { setAttributes({ productName: val }); }
                        }),
                        el(TextControl, {
                            label: productId ? 'Data (se il prodotto non ha una data di drop)' : 'Data Release (ISO 8601)',
                            help: 'Formato: 2025-12-31T10:00:00, ora italiana',
                            value: releaseDate,
                            onChange: function(val) { setAttributes({ releaseDate: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Immagine Prodotto', initialOpen: false },
                        el(MediaField, {
                            value: productImage,
                            onSelect: function(media) { setAttributes({ productImage: media.url, productImageId: media.id || 0 }); },
                            onRemove: function() { setAttributes({ productImage: '', productImageId: 0 }); }
                        })
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
                    ),
                    el(PanelBody, { title: 'Stile', initialOpen: false },
                        el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '600' } }, 'Colore di sfondo'),
                        el(ColorPicker, {
                            color: backgroundColor,
                            onChangeComplete: function(val) { setAttributes({ backgroundColor: val.hex }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z' })
                        ),
                        title: 'Drop Countdown',
                        text: productName
                            ? productName + (productId ? ' — collegato al prodotto' : (releaseDate ? ' — ' + releaseDate : ''))
                            : 'Configura questo blocco nel pannello laterale.',
                        thumbs: [productImage]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
