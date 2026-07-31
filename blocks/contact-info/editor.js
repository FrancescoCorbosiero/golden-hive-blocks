(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/contact-info', {
        edit: function({ attributes, setAttributes }) {
            const { title, subtitle, address, vat, phone, email, mapUrl, mapEmbed } = attributes;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(TextControl, {
                            label: 'Sottotitolo',
                            value: subtitle,
                            onChange: function(val) { setAttributes({ subtitle: val }); }
                        }),
                        el(TextControl, {
                            label: 'Indirizzo',
                            value: address,
                            onChange: function(val) { setAttributes({ address: val }); }
                        }),
                        el(TextControl, {
                            label: 'P.IVA',
                            value: vat,
                            onChange: function(val) { setAttributes({ vat: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Contatti', initialOpen: false },
                        el(TextControl, {
                            label: 'Telefono',
                            value: phone,
                            onChange: function(val) { setAttributes({ phone: val }); }
                        }),
                        el(TextControl, {
                            label: 'Email',
                            value: email,
                            onChange: function(val) { setAttributes({ email: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Mappa', initialOpen: false },
                        el(TextControl, {
                            label: 'URL Mappa (link esterno)',
                            value: mapUrl,
                            onChange: function(val) { setAttributes({ mapUrl: val }); }
                        }),
                        el(TextareaControl, {
                            label: 'Codice Embed Mappa',
                            help: 'Incolla il codice iframe di Google Maps.',
                            value: mapEmbed,
                            onChange: function(val) { setAttributes({ mapEmbed: val }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z' })
                        ),
                        title: 'Contact Info',
                        text: address ? address : 'Configura questo blocco nel pannello laterale.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
