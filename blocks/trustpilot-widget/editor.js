(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl } = wp.components;
    const { el, Fragment, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/trustpilot-widget', {
        edit: function({ attributes, setAttributes }) {
            var rating = attributes.rating;
            var reviewCount = attributes.reviewCount;
            var label = attributes.label;
            var trustpilotUrl = attributes.trustpilotUrl;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni Trustpilot', initialOpen: true },
                        el('p', { style: { marginTop: 0 } },
                            'Scrivi la valutazione e il numero di recensioni come sono sulla tua pagina Trustpilot: senza valutazione il blocco non si vede. Il widget ufficiale TrustBox (in un blocco HTML) resta sempre aggiornato da solo.'
                        ),
                        el(RangeControl, {
                            label: 'Valutazione',
                            value: rating,
                            allowReset: true,
                            onChange: function(val) { setAttributes({ rating: val }); },
                            min: 1,
                            max: 5,
                            step: 0.1
                        }),
                        el(TextControl, {
                            label: 'Numero Recensioni',
                            type: 'number',
                            value: reviewCount === undefined ? '' : reviewCount,
                            onChange: function(val) { setAttributes({ reviewCount: parseInt(val) || 0 }); }
                        }),
                        el(TextControl, {
                            label: 'Etichetta',
                            help: 'Il giudizio di Trustpilot, es. «Eccezionale». Facoltativa.',
                            value: label || '',
                            onChange: function(val) { setAttributes({ label: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL Trustpilot',
                            value: trustpilotUrl || '',
                            onChange: function(val) { setAttributes({ trustpilotUrl: val }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor', style: { color: '#00b67a' } },
                            el('path', { d: 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z' })
                        ),
                        title: 'Trustpilot Widget',
                        text: rating ? rating + ' / 5' : 'Nessuna valutazione: il blocco non si vede finché non la scrivi.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
