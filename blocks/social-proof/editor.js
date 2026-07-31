(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, ToggleControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, MediaField, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/social-proof', {
        edit: function({ attributes, setAttributes }) {
            const { notifications, interval, initialDelay, displayDuration, showVerified, title } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(notifications, function(next) {
                setAttributes({ notifications: next });
            });

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Intervallo (ms)',
                            value: interval,
                            onChange: function(val) { setAttributes({ interval: val }); },
                            min: 3000,
                            max: 30000,
                            step: 500
                        }),
                        el(RangeControl, {
                            label: 'Ritardo iniziale (ms)',
                            value: initialDelay,
                            onChange: function(val) { setAttributes({ initialDelay: val }); },
                            min: 1000,
                            max: 30000,
                            step: 500
                        }),
                        el(RangeControl, {
                            label: 'Durata visualizzazione (secondi)',
                            value: (displayDuration || 0) / 1000,
                            onChange: function(val) { setAttributes({ displayDuration: val * 1000 }); },
                            min: 2,
                            max: 20,
                            step: 1
                        }),
                        el(ToggleControl, {
                            label: 'Mostra badge "Acquisto verificato"',
                            checked: !!showVerified,
                            onChange: function(val) { setAttributes({ showVerified: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Notifiche (' + notifications.length + ')', initialOpen: false },
                        notifications.map(function(item, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el(ItemControls, {
                                    ops: ops,
                                    index: index,
                                    count: notifications.length,
                                    label: 'Notifica ' + (index + 1)
                                }),
                                el(TextControl, {
                                    label: 'Nome',
                                    value: item.name || '',
                                    onChange: function(val) { ops.update(index, 'name', val); }
                                }),
                                el(TextControl, {
                                    label: 'Prodotto',
                                    value: item.product || '',
                                    onChange: function(val) { ops.update(index, 'product', val); }
                                }),
                                el('div', { style: { marginTop: '8px', marginBottom: '8px' } },
                                    el(MediaField, {
                                        value: item.image || '',
                                        onSelect: function(media) {
                                            ops.updateMany(index, { image: media.url, imageId: media.id });
                                        },
                                        onRemove: function() {
                                            ops.updateMany(index, { image: '', imageId: 0 });
                                        },
                                        selectLabel: 'Seleziona immagine',
                                        removeLabel: 'Rimuovi immagine'
                                    })
                                ),
                                el(TextControl, {
                                    label: 'Localita',
                                    value: item.location || '',
                                    onChange: function(val) { ops.update(index, 'location', val); }
                                }),
                                el(TextControl, {
                                    label: 'Tempo',
                                    value: item.time || '',
                                    onChange: function(val) { ops.update(index, 'time', val); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'primary',
                            onClick: function() {
                                ops.add({ name: '', product: '', image: '', imageId: 0, location: '', time: '' });
                            },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi notifica')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z' })
                        ),
                        title: 'Social Proof',
                        text: notifications.length > 0
                            ? notifications.length + ' notifiche configurate'
                            : 'Configura questo blocco nel pannello laterale.',
                        thumbs: notifications.map(function(item) { return item.image; })
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
