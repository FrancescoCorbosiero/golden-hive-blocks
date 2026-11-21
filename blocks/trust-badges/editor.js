(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, SelectControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, Placeholder, store } = window.ghEditorUtils;

    registerBlockType('golden-hive/trust-badges', {
        edit: function({ attributes, setAttributes }) {
            const { variant } = attributes;
            const own = Array.isArray(attributes.badges);
            const badges = own ? attributes.badges : (store.badges || []);
            const blockProps = useBlockProps();

            var ops = listOps(badges, function(next) {
                setAttributes({ badges: next });
            });

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni', initialOpen: true },
                        el(SelectControl, {
                            label: 'Variante',
                            value: variant,
                            options: [
                                { label: 'Default', value: 'default' },
                                { label: 'Carousel', value: 'carousel' }
                            ],
                            onChange: function(val) { setAttributes({ variant: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Badge (' + badges.length + ')', initialOpen: !own },
                        el('p', { style: { marginTop: 0 } },
                            own
                                ? 'Badge solo di questa pagina.'
                                : (badges.length
                                    ? 'I badge del negozio (Personalizza → Golden Hive → Negozio).'
                                    : 'Il negozio non ha badge: impostali in Personalizza → Golden Hive → Negozio, o scrivili qui.')
                        ),
                        own ? null : badges.map(function(item, index) {
                            return el('p', { key: index, style: { margin: '0 0 4px' } }, '• ' + (item.title || ''));
                        }),
                        own ? null : el(Button, {
                            variant: 'secondary',
                            onClick: function() { setAttributes({ badges: badges.map(function(b) { return { icon: b.icon, title: b.title }; }) }); }
                        }, 'Scrivi badge solo per questa pagina'),
                        !own ? null : badges.map(function(item, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el(ItemControls, {
                                    ops: ops,
                                    index: index,
                                    count: badges.length,
                                    label: 'Badge ' + (index + 1)
                                }),
                                el(SelectControl, {
                                    label: 'Icona',
                                    value: item.icon || 'authentic',
                                    options: [
                                        { label: 'Autentico', value: 'authentic' },
                                        { label: 'Spedizione', value: 'shipping' },
                                        { label: 'Resi', value: 'returns' },
                                        { label: 'Sicuro', value: 'secure' },
                                        { label: 'Supporto', value: 'support' },
                                        { label: 'Qualita', value: 'quality' }
                                    ],
                                    onChange: function(val) { ops.update(index, 'icon', val); }
                                }),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: item.title || '',
                                    onChange: function(val) { ops.update(index, 'title', val); }
                                })
                            );
                        }),
                        !own ? null : el(Button, {
                            variant: 'primary',
                            onClick: function() { ops.add({ icon: 'authentic', title: '' }); },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi badge'),
                        !own ? null : el(Button, {
                            variant: 'tertiary',
                            onClick: function() { setAttributes({ badges: undefined }); },
                            style: { marginTop: '8px' }
                        }, 'Usa i badge del negozio')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z' })
                        ),
                        title: 'Trust Badges',
                        text: badges.length > 0
                            ? badges.length + (own ? ' badge di questa pagina' : ' badge del negozio')
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
