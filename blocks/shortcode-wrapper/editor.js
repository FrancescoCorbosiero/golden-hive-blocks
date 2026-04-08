(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, SelectControl } = wp.components;

    registerBlockType('golden-hive/shortcode-wrapper', {
        edit: function({ attributes, setAttributes }) {
            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el(TextControl, {
                            label: 'Eyebrow',
                            value: attributes.eyebrow || '',
                            onChange: function(value) { setAttributes({ eyebrow: value }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: attributes.title || '',
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextareaControl, {
                            label: 'Shortcode',
                            value: attributes.shortcode || '',
                            onChange: function(value) { setAttributes({ shortcode: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Pulsante', initialOpen: false },
                        el(TextControl, {
                            label: 'Testo pulsante',
                            help: 'Es. "Vedi Tutti". Lascia vuoto per nascondere il pulsante.',
                            value: attributes.buttonText || '',
                            onChange: function(value) { setAttributes({ buttonText: value }); }
                        }),
                        el(TextControl, {
                            label: 'URL pulsante',
                            value: attributes.buttonUrl || '',
                            onChange: function(value) { setAttributes({ buttonUrl: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Aspetto', initialOpen: false },
                        el(SelectControl, {
                            label: 'Colore di Sfondo',
                            value: attributes.backgroundColor || 'white',
                            options: [
                                { label: 'Bianco', value: 'white' },
                                { label: 'Grigio', value: 'gray' },
                                { label: 'Nero', value: 'black' }
                            ],
                            onChange: function(value) { setAttributes({ backgroundColor: value }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M8 4l-6 6 6 6 1.4-1.4L4.8 10l4.6-4.6zm8 0l6 6-6 6-1.4-1.4 4.6-4.6-4.6-4.6z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Shortcode Wrapper'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
