(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/parallax-section', {
        edit: function({ attributes, setAttributes }) {
            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Immagini', initialOpen: true },
                        el('div', { style: { marginBottom: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '600' } }, 'Immagine di Sfondo'),
                            attributes.backgroundImage
                                ? el('div', {},
                                    el('img', { src: attributes.backgroundImage, style: { width: '100%', height: 'auto', marginBottom: '8px', borderRadius: '4px' } }),
                                    el(Button, {
                                        isDestructive: true,
                                        variant: 'secondary',
                                        onClick: function() { setAttributes({ backgroundImage: '' }); },
                                        style: { width: '100%', justifyContent: 'center' }
                                    }, 'Rimuovi Immagine')
                                )
                                : el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { setAttributes({ backgroundImage: media.url }); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el(Button, {
                                                variant: 'secondary',
                                                onClick: obj.open,
                                                style: { width: '100%', justifyContent: 'center' }
                                            }, 'Seleziona Immagine di Sfondo');
                                        }
                                    })
                                )
                        ),
                        el('div', { style: { marginBottom: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '600' } }, 'Immagine in Primo Piano'),
                            attributes.foregroundImage
                                ? el('div', {},
                                    el('img', { src: attributes.foregroundImage, style: { width: '100%', height: 'auto', marginBottom: '8px', borderRadius: '4px' } }),
                                    el(Button, {
                                        isDestructive: true,
                                        variant: 'secondary',
                                        onClick: function() { setAttributes({ foregroundImage: '' }); },
                                        style: { width: '100%', justifyContent: 'center' }
                                    }, 'Rimuovi Immagine')
                                )
                                : el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { setAttributes({ foregroundImage: media.url }); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el(Button, {
                                                variant: 'secondary',
                                                onClick: obj.open,
                                                style: { width: '100%', justifyContent: 'center' }
                                            }, 'Seleziona Immagine in Primo Piano');
                                        }
                                    })
                                )
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
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M21 3H3v18h18V3zM5 19V5h14v14H5z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Parallax Section'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
