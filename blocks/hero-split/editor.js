(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, SelectControl, Button } = wp.components;

    registerBlockType('golden-hive/hero-split', {
        edit: function({ attributes, setAttributes }) {
            const {
                eyebrow, title, subtitle, imageUrl, imagePosition,
                mediaSide, theme, height, buttonText, buttonUrl, buttonStyle
            } = attributes;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuto', initialOpen: true },
                        el(TextControl, {
                            label: 'Eyebrow',
                            value: eyebrow || '',
                            onChange: function(v) { setAttributes({ eyebrow: v }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: title || '',
                            onChange: function(v) { setAttributes({ title: v }); }
                        }),
                        el(TextareaControl, {
                            label: 'Sottotitolo',
                            value: subtitle || '',
                            onChange: function(v) { setAttributes({ subtitle: v }); }
                        }),
                        el(TextControl, {
                            label: 'Testo pulsante',
                            value: buttonText || '',
                            onChange: function(v) { setAttributes({ buttonText: v }); }
                        }),
                        el(TextControl, {
                            label: 'URL pulsante',
                            value: buttonUrl || '',
                            onChange: function(v) { setAttributes({ buttonUrl: v }); }
                        })
                    ),
                    el(PanelBody, { title: 'Immagine', initialOpen: false },
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: function(media) { setAttributes({ imageUrl: media.url }); },
                                allowedTypes: ['image'],
                                render: function(obj) {
                                    return el('div', { style: { marginBottom: '8px' } },
                                        imageUrl
                                            ? el('div', {},
                                                el('img', { src: imageUrl, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                el(Button, { isSecondary: true, isSmall: true, onClick: function() { setAttributes({ imageUrl: '' }); } }, 'Rimuovi immagine')
                                            )
                                            : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                    );
                                }
                            })
                        ),
                        el(SelectControl, {
                            label: 'Posizione immagine',
                            value: imagePosition || 'center center',
                            options: [
                                { label: 'Centro', value: 'center center' },
                                { label: 'Sinistra', value: 'left center' },
                                { label: 'Destra', value: 'right center' },
                                { label: 'Alto', value: 'center top' },
                                { label: 'Basso', value: 'center bottom' }
                            ],
                            onChange: function(v) { setAttributes({ imagePosition: v }); }
                        })
                    ),
                    el(PanelBody, { title: 'Layout', initialOpen: false },
                        el(SelectControl, {
                            label: 'Lato immagine (desktop)',
                            value: mediaSide || 'left',
                            options: [
                                { label: 'Sinistra', value: 'left' },
                                { label: 'Destra', value: 'right' }
                            ],
                            onChange: function(v) { setAttributes({ mediaSide: v }); }
                        }),
                        el(SelectControl, {
                            label: 'Tema pannello testo',
                            value: theme || 'light',
                            options: [
                                { label: 'Chiaro', value: 'light' },
                                { label: 'Scuro', value: 'dark' }
                            ],
                            onChange: function(v) { setAttributes({ theme: v }); }
                        }),
                        el(SelectControl, {
                            label: 'Altezza',
                            value: height || 'tall',
                            options: [
                                { label: 'Automatica', value: 'auto' },
                                { label: 'Alta (70vh)', value: 'tall' },
                                { label: 'Schermo intero', value: 'full' }
                            ],
                            onChange: function(v) { setAttributes({ height: v }); }
                        }),
                        el(SelectControl, {
                            label: 'Stile pulsante',
                            value: buttonStyle || 'solid',
                            options: [
                                { label: 'Solido', value: 'solid' },
                                { label: 'Contorno', value: 'outline' }
                            ],
                            onChange: function(v) { setAttributes({ buttonStyle: v }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M3 4h8v16H3V4zm10 0h8v16h-8V4z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Hero Split'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        (title || 'Senza titolo') + ' — immagine a ' + (mediaSide === 'right' ? 'destra' : 'sinistra') + ', tema ' + (theme === 'dark' ? 'scuro' : 'chiaro')
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
