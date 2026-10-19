(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl } = wp.components;
    const { el, Fragment, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/newsletter', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
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
                            label: 'Placeholder',
                            value: attributes.placeholder || '',
                            onChange: function(value) { setAttributes({ placeholder: value }); }
                        }),
                        el(TextControl, {
                            label: 'Testo Pulsante',
                            value: attributes.buttonText || '',
                            onChange: function(value) { setAttributes({ buttonText: value }); }
                        }),
                        el(TextControl, {
                            label: 'Disclaimer',
                            value: attributes.disclaimer || '',
                            onChange: function(value) { setAttributes({ disclaimer: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Avvisi taglia', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'Chi si iscrive può scegliere fino a 3 taglie: quando arriva un paio disponibile in quella taglia riceve un\'email. Gli avvisi partono solo dopo la conferma dall\'email (doppio opt-in) e ogni email ha il link per disattivarli.'
                        ),
                        el(ToggleControl, {
                            label: 'Chiedi la taglia',
                            checked: attributes.askSize !== false,
                            onChange: function(value) { setAttributes({ askSize: value }); }
                        }),
                        attributes.askSize !== false ? el(TextControl, {
                            label: 'Testo della scelta taglia',
                            value: attributes.sizeLabel || '',
                            onChange: function(value) { setAttributes({ sizeLabel: value }); }
                        }) : null,
                        attributes.askSize !== false ? el(ToggleControl, {
                            label: 'Chiedi anche i marchi preferiti',
                            help: 'Gli avvisi arrivano solo per quei marchi.',
                            checked: !!attributes.askBrands,
                            onChange: function(value) { setAttributes({ askBrands: value }); }
                        }) : null
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z' })
                        ),
                        title: 'Newsletter',
                        text: attributes.askSize !== false
                            ? 'Iscrizione + avvisi "è arrivata la tua taglia".'
                            : 'Configura questo blocco nel pannello laterale.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
