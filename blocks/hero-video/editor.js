(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, SelectControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/hero-video', {
        edit: function({ attributes, setAttributes }) {
            const { mediaType, videoUrl, imageUrl, posterUrl, badge, title, subtitle, primaryButtonText, primaryButtonUrl, secondaryButtonText, secondaryButtonUrl, showScrollIndicator } = attributes;

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
                            el('label', { style: { display: 'block', marginBottom: '4px', fontWeight: '500' } }, 'Poster Video'),
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: function(media) { setAttributes({ posterUrl: media.url }); },
                                    allowedTypes: ['image'],
                                    render: function(obj) {
                                        return el('div', {},
                                            posterUrl
                                                ? el('div', {},
                                                    el('img', { src: posterUrl, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                    el(Button, { isSecondary: true, isSmall: true, onClick: function() { setAttributes({ posterUrl: '' }); } }, 'Rimuovi poster')
                                                )
                                                : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona poster')
                                        );
                                    }
                                })
                            )
                        ),
                        el('div', { style: { marginBottom: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '4px', fontWeight: '500' } }, 'Immagine di sfondo'),
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
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M8 5v14l11-7z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Hero Video'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        title ? '"' + title + '" — ' + mediaType : 'Configura questo blocco nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
