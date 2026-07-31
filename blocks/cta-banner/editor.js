(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/cta-banner', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, text, buttonText, buttonUrl, backgroundImage, showGlow } = attributes;
            const blockProps = useBlockProps();

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
                        el(MediaField, {
                            value: backgroundImage,
                            onSelect: function(media) { setAttributes({ backgroundImage: media.url, backgroundImageId: media.id || 0 }); },
                            onRemove: function() { setAttributes({ backgroundImage: '', backgroundImageId: 0 }); }
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
                    el(PanelBody, { title: 'Opzioni', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra effetto glow',
                            checked: showGlow,
                            onChange: function(val) { setAttributes({ showGlow: val }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0013 3.06V1h-2v2.06A8.994 8.994 0 003.06 11H1v2h2.06A8.994 8.994 0 0011 20.94V23h2v-2.06A8.994 8.994 0 0020.94 13H23v-2h-2.06z' })
                        ),
                        title: 'CTA Banner',
                        text: title ? '"' + title + '"' : 'Configura questo blocco nel pannello laterale.',
                        thumbs: [backgroundImage]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
