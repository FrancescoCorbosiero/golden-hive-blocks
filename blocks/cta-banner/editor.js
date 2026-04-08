(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/cta-banner', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, text, buttonText, buttonUrl, backgroundImage, showGlow } = attributes;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuto', initialOpen: true },
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
                    el(PanelBody, { title: 'Immagine di sfondo', initialOpen: false },
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: function(media) { setAttributes({ backgroundImage: media.url }); },
                                allowedTypes: ['image'],
                                render: function(obj) {
                                    return el('div', {},
                                        backgroundImage
                                            ? el('div', {},
                                                el('img', { src: backgroundImage, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                el(Button, { isSecondary: true, isSmall: true, onClick: function() { setAttributes({ backgroundImage: '' }); } }, 'Rimuovi immagine')
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
                    el(PanelBody, { title: 'Opzioni', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra effetto glow',
                            checked: showGlow,
                            onChange: function(val) { setAttributes({ showGlow: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0013 3.06V1h-2v2.06A8.994 8.994 0 003.06 11H1v2h2.06A8.994 8.994 0 0011 20.94V23h2v-2.06A8.994 8.994 0 0020.94 13H23v-2h-2.06z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'CTA Banner'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        title ? '"' + title + '"' : 'Configura questo blocco nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
