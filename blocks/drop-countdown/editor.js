(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, Button, ColorPicker } = wp.components;

    registerBlockType('golden-hive/drop-countdown', {
        edit: function({ attributes, setAttributes }) {
            const { productName, productImage, releaseDate, buttonText, buttonUrl, eyebrow, backgroundColor } = attributes;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuto', initialOpen: true },
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
                            label: 'Data Release (ISO 8601)',
                            help: 'Formato: 2025-12-31T10:00:00',
                            value: releaseDate,
                            onChange: function(val) { setAttributes({ releaseDate: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Immagine Prodotto', initialOpen: false },
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: function(media) { setAttributes({ productImage: media.url }); },
                                allowedTypes: ['image'],
                                render: function(obj) {
                                    return el('div', {},
                                        productImage
                                            ? el('div', {},
                                                el('img', { src: productImage, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                el(Button, { isSecondary: true, isSmall: true, onClick: function() { setAttributes({ productImage: '' }); } }, 'Rimuovi immagine')
                                            )
                                            : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                    );
                                }
                            })
                        )
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
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Drop Countdown'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        productName
                            ? productName + (releaseDate ? ' \u2014 ' + releaseDate : '')
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
