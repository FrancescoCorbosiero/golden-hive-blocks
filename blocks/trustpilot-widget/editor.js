(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl } = wp.components;

    registerBlockType('golden-hive/trustpilot-widget', {
        edit: function({ attributes, setAttributes }) {
            var rating = attributes.rating;
            var reviewCount = attributes.reviewCount;
            var label = attributes.label;
            var trustpilotUrl = attributes.trustpilotUrl;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni Trustpilot', initialOpen: true },
                        el(RangeControl, {
                            label: 'Valutazione',
                            value: rating,
                            onChange: function(val) { setAttributes({ rating: val }); },
                            min: 1,
                            max: 5,
                            step: 0.1
                        }),
                        el(TextControl, {
                            label: 'Numero Recensioni',
                            type: 'number',
                            value: reviewCount,
                            onChange: function(val) { setAttributes({ reviewCount: parseInt(val) || 0 }); }
                        }),
                        el(TextControl, {
                            label: 'Etichetta',
                            value: label,
                            onChange: function(val) { setAttributes({ label: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL Trustpilot',
                            value: trustpilotUrl,
                            onChange: function(val) { setAttributes({ trustpilotUrl: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon', style: { color: '#00b67a' } },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Trustpilot Widget'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
