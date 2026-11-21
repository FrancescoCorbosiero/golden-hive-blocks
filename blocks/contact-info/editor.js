(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const Placeholder = utils.Placeholder;
    const StoreText = utils.StoreText;
    const StoreReset = utils.StoreReset;
    const store = utils.store;

    // Left out, these follow the store (Personalizza → Golden Hive → Negozio).
    const STORE_ATTRS = ['subtitle', 'address', 'vat', 'phone', 'email', 'mapEmbed'];

    registerBlockType('golden-hive/contact-info', {
        edit: function({ attributes, setAttributes }) {
            const { title, mapUrl, clickToLoad } = attributes;
            const blockProps = useBlockProps();

            function storeText(attr, label, storeValue, extra) {
                return el(StoreText, Object.assign({
                    attributes: attributes,
                    setAttributes: setAttributes,
                    attr: attr,
                    label: label,
                    storeValue: storeValue
                }, extra || {}));
            }

            const address = attributes.address !== undefined ? attributes.address : (store.address || '');

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title || '',
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        storeText('subtitle', 'Sottotitolo', store.intro),
                        storeText('address', 'Indirizzo', store.address),
                        storeText('vat', 'P.IVA', store.vat)
                    ),
                    el(PanelBody, { title: 'Contatti', initialOpen: false },
                        storeText('phone', 'Telefono', store.phone),
                        storeText('email', 'Email', store.email)
                    ),
                    el(PanelBody, { title: 'Mappa', initialOpen: false },
                        el(TextControl, {
                            label: 'URL Mappa (link esterno)',
                            value: mapUrl || '',
                            onChange: function(val) { setAttributes({ mapUrl: val }); }
                        }),
                        storeText('mapEmbed', 'Codice Embed Mappa', store.hasMap ? '(la mappa del negozio)' : '', {
                            textarea: true,
                            help: 'Incolla il codice iframe di Google Maps.'
                        }),
                        el(ToggleControl, {
                            label: 'Carica la mappa al clic',
                            help: 'Consigliato: la pagina è più leggera e Google non imposta cookie finché il visitatore non apre la mappa.',
                            checked: clickToLoad !== false,
                            onChange: function(val) { setAttributes({ clickToLoad: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Dati del negozio', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'I campi lasciati vuoti mostrano i dati del negozio, da Personalizza → Golden Hive → Negozio.'
                        ),
                        el(StoreReset, { attributes: attributes, setAttributes: setAttributes, attrs: STORE_ATTRS })
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
