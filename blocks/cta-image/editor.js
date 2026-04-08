(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/cta-image', {
        edit: function({ attributes, setAttributes }) {
            const { imageUrl, eyebrow, title, text, buttonText, buttonUrl, reverse } = attributes;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Immagine', initialOpen: true },
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
                    el(PanelBody, { title: 'Contenuto', initialOpen: false },
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
                    el(PanelBody, { title: 'Layout', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Inverti layout',
                            checked: reverse,
                            onChange: function(val) { setAttributes({ reverse: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M21 3H3v18h18V3zM5 19V5h6v14H5zm8 0V5h6v14h-6z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'CTA Image Split'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        title ? '"' + title + '"' + (reverse ? ' (invertito)' : '') : 'Configura questo blocco nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
