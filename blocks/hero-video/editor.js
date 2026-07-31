(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, SelectControl, ToggleControl } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/hero-video', {
        edit: function({ attributes, setAttributes }) {
            const { mediaType, videoUrl, imageUrl, posterUrl, badge, title, subtitle, primaryButtonText, primaryButtonUrl, secondaryButtonText, secondaryButtonUrl, showScrollIndicator } = attributes;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Media', initialOpen: true },
                        el(SelectControl, {
                            label: 'Tipo di media',
                            value: mediaType,
                            options: [
                                { label: 'Immagine', value: 'image' },
                                { label: 'Video', value: 'video' }
                            ],
                            onChange: function(val) { setAttributes({ mediaType: val }); }
                        }),
                        mediaType === 'video' && el(TextControl, {
                            label: 'URL Video',
                            value: videoUrl,
                            onChange: function(val) { setAttributes({ videoUrl: val }); }
                        }),
                        mediaType === 'video' && el('div', { style: { marginBottom: '16px' } },
                            el(MediaField, {
                                label: 'Poster Video',
                                value: posterUrl,
                                onSelect: function(media) { setAttributes({ posterUrl: media.url, posterUrlId: media.id || 0 }); },
                                onRemove: function() { setAttributes({ posterUrl: '', posterUrlId: 0 }); },
                                selectLabel: 'Seleziona poster',
                                removeLabel: 'Rimuovi poster'
                            })
                        ),
                        el('div', { style: { marginBottom: '16px' } },
                            el(MediaField, {
                                label: 'Immagine di sfondo',
                                value: imageUrl,
                                onSelect: function(media) { setAttributes({ imageUrl: media.url, imageUrlId: media.id || 0 }); },
                                onRemove: function() { setAttributes({ imageUrl: '', imageUrlId: 0 }); }
                            })
                        )
                    ),
                    el(PanelBody, { title: 'Contenuto', initialOpen: false },
                        el(TextControl, {
                            label: 'Badge',
                            value: badge,
                            onChange: function(val) { setAttributes({ badge: val }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(SelectControl, {
                            label: 'Livello heading del titolo',
                            help: 'Usa H1 se questo hero è il titolo principale della pagina (default), H2 se la pagina ha già un H1.',
                            value: attributes.headingLevel || 'h1',
                            options: [
                                { label: 'H1', value: 'h1' },
                                { label: 'H2', value: 'h2' }
                            ],
                            onChange: function(val) { setAttributes({ headingLevel: val }); }
                        }),
                        el(TextControl, {
                            label: 'Sottotitolo',
                            value: subtitle,
                            onChange: function(val) { setAttributes({ subtitle: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Pulsanti', initialOpen: false },
                        el(TextControl, {
                            label: 'Testo pulsante primario',
                            value: primaryButtonText,
                            onChange: function(val) { setAttributes({ primaryButtonText: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL pulsante primario',
                            value: primaryButtonUrl,
                            onChange: function(val) { setAttributes({ primaryButtonUrl: val }); }
                        }),
                        el(TextControl, {
                            label: 'Testo pulsante secondario',
                            value: secondaryButtonText,
                            onChange: function(val) { setAttributes({ secondaryButtonText: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL pulsante secondario',
                            value: secondaryButtonUrl,
                            onChange: function(val) { setAttributes({ secondaryButtonUrl: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Opzioni', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra indicatore di scroll',
                            checked: showScrollIndicator,
                            onChange: function(val) { setAttributes({ showScrollIndicator: val }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M8 5v14l11-7z' })
                        ),
                        title: 'Hero Video',
                        text: title ? '"' + title + '" — ' + mediaType : 'Configura questo blocco nel pannello laterale.',
                        thumbs: [imageUrl, posterUrl]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
