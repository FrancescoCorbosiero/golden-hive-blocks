(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/cta-image', {
        edit: function({ attributes, setAttributes }) {
            const { imageUrl, eyebrow, title, text, buttonText, buttonUrl, reverse } = attributes;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Immagine', initialOpen: true },
                        el(MediaField, {
                            value: imageUrl,
                            onSelect: function(media) { setAttributes({ imageUrl: media.url, imageUrlId: media.id || 0 }); },
                            onRemove: function() { setAttributes({ imageUrl: '', imageUrlId: 0 }); }
                        })
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
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M21 3H3v18h18V3zM5 19V5h6v14H5zm8 0V5h6v14h-6z' })
                        ),
                        title: 'CTA Image Split',
                        text: title ? '"' + title + '"' + (reverse ? ' (invertito)' : '') : 'Configura questo blocco nel pannello laterale.',
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
