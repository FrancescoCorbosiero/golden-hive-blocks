(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, RangeControl, Button } = wp.components;

    registerBlockType('golden-hive/promo-modal', {
        edit: function({ attributes, setAttributes }) {
            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el(TextControl, {
                            label: 'ID Modale',
                            value: attributes.modalId || '',
                            onChange: function(value) { setAttributes({ modalId: value }); }
                        }),
                        el('div', { style: { marginBottom: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '600' } }, 'Immagine'),
                            attributes.imageUrl
                                ? el('div', {},
                                    el('img', { src: attributes.imageUrl, style: { width: '100%', height: 'auto', marginBottom: '8px', borderRadius: '4px' } }),
                                    el(Button, {
                                        isDestructive: true,
                                        variant: 'secondary',
                                        onClick: function() { setAttributes({ imageUrl: '' }); },
                                        style: { width: '100%', justifyContent: 'center' }
                                    }, 'Rimuovi Immagine')
                                )
                                : el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { setAttributes({ imageUrl: media.url }); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el(Button, {
                                                variant: 'secondary',
                                                onClick: obj.open,
                                                style: { width: '100%', justifyContent: 'center' }
                                            }, 'Seleziona Immagine');
                                        }
                                    })
                                )
                        ),
                        el(TextControl, {
                            label: 'Badge',
                            value: attributes.badge || '',
                            onChange: function(value) { setAttributes({ badge: value }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: attributes.title || '',
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextareaControl, {
                            label: 'Testo',
                            value: attributes.text || '',
                            onChange: function(value) { setAttributes({ text: value }); }
                        }),
                        el(TextControl, {
                            label: 'Codice Coupon',
                            value: attributes.couponCode || '',
                            onChange: function(value) { setAttributes({ couponCode: value }); }
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
                        el(TextControl, {
                            label: 'Disclaimer',
                            value: attributes.disclaimer || '',
                            onChange: function(value) { setAttributes({ disclaimer: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Comportamento', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra automaticamente',
                            checked: !!attributes.autoShow,
                            onChange: function(value) { setAttributes({ autoShow: value }); }
                        }),
                        el(RangeControl, {
                            label: 'Ritardo visualizzazione (secondi)',
                            value: attributes.showDelay || 0,
                            onChange: function(value) { setAttributes({ showDelay: value }); },
                            min: 0,
                            max: 60,
                            step: 1
                        }),
                        el(ToggleControl, {
                            label: 'Mostra solo una volta',
                            checked: !!attributes.showOnce,
                            onChange: function(value) { setAttributes({ showOnce: value }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Promo Modal'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
