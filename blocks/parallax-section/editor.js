(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl } = wp.components;
    const { el, Fragment, MediaField, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/parallax-section', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Immagini', initialOpen: true },
                        el('div', { style: { marginBottom: '16px' } },
                            el(MediaField, {
                                label: 'Immagine di Sfondo',
                                value: attributes.backgroundImage || '',
                                onSelect: function(media) {
                                    setAttributes({ backgroundImage: media.url, backgroundImageId: media.id });
                                },
                                onRemove: function() {
                                    setAttributes({ backgroundImage: '', backgroundImageId: 0 });
                                },
                                selectLabel: 'Seleziona Immagine di Sfondo',
                                removeLabel: 'Rimuovi Immagine'
                            })
                        ),
                        el('div', { style: { marginBottom: '16px' } },
                            el(MediaField, {
                                label: 'Immagine in Primo Piano',
                                value: attributes.foregroundImage || '',
                                onSelect: function(media) {
                                    setAttributes({ foregroundImage: media.url, foregroundImageId: media.id });
                                },
                                onRemove: function() {
                                    setAttributes({ foregroundImage: '', foregroundImageId: 0 });
                                },
                                selectLabel: 'Seleziona Immagine in Primo Piano',
                                removeLabel: 'Rimuovi Immagine'
                            })
                        )
                    ),
                    el(PanelBody, { title: 'Contenuti', initialOpen: false },
                        el(TextControl, {
                            label: 'Titolo',
                            value: attributes.title || '',
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextControl, {
                            label: 'Testo',
                            value: attributes.text || '',
                            onChange: function(value) { setAttributes({ text: value }); }
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
                        el(ToggleControl, {
                            label: 'Abilita Parallax Mouse',
                            checked: !!attributes.enableMouseParallax,
                            onChange: function(value) { setAttributes({ enableMouseParallax: value }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M21 3H3v18h18V3zM5 19V5h14v14H5z' })
                        ),
                        title: 'Parallax Section',
                        text: 'Configura questo blocco nel pannello laterale.',
                        thumbs: [attributes.backgroundImage, attributes.foregroundImage]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
