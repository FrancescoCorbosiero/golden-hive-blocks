(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, MediaField, Placeholder, StoreText, StoreReset, store } = window.ghEditorUtils;

    // Left out, these follow the store (Personalizza → Golden Hive → Negozio).
    const STORE_ATTRS = ['subtitle', 'address', 'vat', 'phone', 'note', 'hours', 'closedOnHolidays', 'closedDates', 'mapEmbed'];

    registerBlockType('golden-hive/store-visit', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();
            var ownHours = Array.isArray(attributes.hours);
            var hours = ownHours ? attributes.hours : (store.hours || []);
            var ops = listOps(hours, function(next) { setAttributes({ hours: next }); });

            function text(label, key, help) {
                return el(TextControl, {
                    label: label,
                    help: help,
                    value: attributes[key] || '',
                    onChange: function(value) { var patch = {}; patch[key] = value; setAttributes(patch); }
                });
            }

            function storeText(attr, label, storeValue, extra) {
                return el(StoreText, Object.assign({
                    attributes: attributes,
                    setAttributes: setAttributes,
                    attr: attr,
                    label: label,
                    storeValue: storeValue
                }, extra || {}));
            }

            var holidays = attributes.closedOnHolidays !== undefined ? attributes.closedOnHolidays : store.holidays !== false;
            var address = attributes.address !== undefined ? attributes.address : (store.address || '');

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        text('Eyebrow', 'eyebrow'),
                        text('Titolo', 'title'),
                        storeText('subtitle', 'Sottotitolo', store.intro, { textarea: true }),
                        storeText('address', 'Indirizzo', store.address, { help: '"Indicazioni" apre Google Maps verso questo indirizzo.' }),
                        storeText('vat', 'Partita IVA (facoltativa)', store.vat, { help: 'Sotto l\'indirizzo.' }),
                        storeText('phone', 'Telefono (facoltativo)', store.phone),
                        storeText('note', 'Nota (facoltativa)', store.hoursNote)
                    ),
                    el(PanelBody, { title: 'WhatsApp', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra il bottone WhatsApp',
                            help: 'Usa il numero di Personalizza → Golden Hive → Negozio.',
                            checked: attributes.showWhatsApp !== false,
                            onChange: function(value) { setAttributes({ showWhatsApp: value }); }
                        }),
                        text('Messaggio già scritto', 'whatsappText')
                    ),
                    el(PanelBody, { title: 'Orari (' + hours.length + ')', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            ownHours
                                ? 'Orari solo di questa pagina. Formato: 10.30 - 13.00 | 15.30 - 19.30, oppure Chiuso. Sul sito il blocco dice se il negozio è aperto adesso.'
                                : (hours.length
                                    ? 'Gli orari del negozio (Personalizza → Golden Hive → Negozio).'
                                    : 'Il negozio non ha orari: impostali in Personalizza → Golden Hive → Negozio, o scrivili qui.')
                        ),
                        ownHours
                            ? hours.map(function(item, index) {
                                return el('div', { key: index, style: { marginBottom: '12px', paddingBottom: '12px', borderBottom: '1px solid #ddd' } },
                                    el(ItemControls, { ops: ops, index: index, count: hours.length, label: item.day || 'Giorno ' + (index + 1) }),
                                    el(TextControl, {
                                        label: 'Giorno',
                                        value: item.day || '',
                                        onChange: function(value) { ops.update(index, 'day', value); }
                                    }),
                                    el(TextControl, {
                                        label: 'Orario',
                                        value: item.time || '',
                                        onChange: function(value) { ops.update(index, 'time', value); }
                                    })
                                );
                            })
                            : hours.map(function(item, index) {
                                return el('p', { key: index, style: { margin: '0 0 4px' } }, (item.day || '') + ': ' + (item.time || ''));
                            }),
                        ownHours
                            ? el(Fragment, {},
                                el(Button, {
                                    variant: 'secondary',
                                    onClick: function() { ops.add({ day: '', time: '' }); }
                                }, 'Aggiungi giorno'),
                                el(Button, {
                                    variant: 'tertiary',
                                    onClick: function() { setAttributes({ hours: undefined }); }
                                }, 'Usa gli orari del negozio')
                            )
                            : el(Button, {
                                variant: 'secondary',
                                onClick: function() { setAttributes({ hours: (store.hours || []).map(function(row) { return { day: row.day, time: row.time }; }) }); }
                            }, 'Scrivi orari solo per questa pagina')
                    ),
                    el(PanelBody, { title: 'Chiusure', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Chiuso nelle festività nazionali',
                            help: attributes.closedOnHolidays === undefined ? 'Come il negozio.' : undefined,
                            checked: holidays,
                            onChange: function(value) { setAttributes({ closedOnHolidays: value }); }
                        }),
                        storeText('closedDates', 'Altre date di chiusura', store.closed, {
                            textarea: true,
                            help: 'Una per riga, es. 24/12/2026 oppure 2026-12-24.'
                        })
                    ),
                    el(PanelBody, { title: 'Mappa', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'La mappa si carica solo quando il visitatore la apre: pagina più leggera e nessun cookie di Google prima.'
                        ),
                        storeText('mapEmbed', 'Codice embed di Google Maps', store.hasMap ? '(la mappa del negozio)' : '', { textarea: true, rows: 5 }),
                        el(MediaField, {
                            label: 'Anteprima (facoltativa)',
                            value: attributes.mapImage || '',
                            onSelect: function(media) { setAttributes({ mapImage: media.url, mapImageId: media.id || 0 }); },
                            onRemove: function() { setAttributes({ mapImage: '', mapImageId: 0 }); },
                            selectLabel: 'Scegli uno screenshot della mappa'
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
                            el('path', { d: 'M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z' })
                        ),
                        title: attributes.title || 'Vieni in negozio',
                        text: (address || 'Indirizzo da inserire') + ' · ' + hours.length + ' giorni di orari',
                        thumbs: [attributes.mapImage]
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
