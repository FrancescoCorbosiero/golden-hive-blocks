(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, Placeholder, StoreText, StoreReset, store } = window.ghEditorUtils;

    // Left out, these follow the store (Personalizza → Golden Hive → Negozio).
    const STORE_ATTRS = ['hours', 'note', 'closedOnHolidays', 'closedDates'];

    registerBlockType('golden-hive/store-hours', {
        edit: function({ attributes, setAttributes }) {
            var ownHours = Array.isArray(attributes.hours);
            var hours = ownHours ? attributes.hours : (store.hours || []);
            var title = attributes.title || '';
            var note = attributes.note !== undefined ? attributes.note : (store.hoursNote || '');
            const blockProps = useBlockProps();

            var ops = listOps(hours, function(next) {
                setAttributes({ hours: next });
            });

            function storeText(attr, label, storeValue, extra) {
                return el(StoreText, Object.assign({
                    attributes: attributes,
                    setAttributes: setAttributes,
                    attr: attr,
                    label: label,
                    storeValue: storeValue
                }, extra || {}));
            }

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Intestazione', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        storeText('note', 'Nota (opzionale)', store.hoursNote)
                    ),
                    el(PanelBody, { title: 'Orari (' + hours.length + ')', initialOpen: !ownHours },
                        el('p', { style: { marginTop: 0 } },
                            ownHours
                                ? 'Orari solo di questa pagina (per esempio quelli del servizio clienti).'
                                : (hours.length
                                    ? 'Gli orari del negozio (Personalizza → Golden Hive → Negozio).'
                                    : 'Il negozio non ha orari: impostali in Personalizza → Golden Hive → Negozio, o scrivili qui.')
                        ),
                        ownHours
                            ? null
                            : hours.map(function(item, index) {
                                return el('p', { key: index, style: { margin: '0 0 4px' } }, (item.day || '') + ': ' + (item.time || ''));
                            }),
                        ownHours
                            ? el(Button, {
                                variant: 'tertiary',
                                onClick: function() { setAttributes({ hours: undefined }); }
                            }, 'Usa gli orari del negozio')
                            : el(Button, {
                                variant: 'secondary',
                                onClick: function() { setAttributes({ hours: (store.hours || []).map(function(row) { return { day: row.day, time: row.time }; }) }); }
                            }, 'Scrivi orari solo per questa pagina')
                    ),
                    ownHours ? hours.map(function(item, index) {
                        return el(PanelBody, {
                            key: index,
                            title: item.day || 'Giorno ' + (index + 1),
                            initialOpen: false
                        },
                            el(ItemControls, {
                                ops: ops,
                                index: index,
                                count: hours.length,
                                label: item.day || 'Giorno ' + (index + 1)
                            }),
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
                    }) : null,
                    el(PanelBody, { title: 'Chiusure', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'Sul sito il blocco dice se il negozio è aperto adesso ("Aperto ora · chiude alle 19:30"). Orari nel formato 10.30 - 13.00 | 15.30 - 19.30, oppure Chiuso.'
                        ),
                        el(ToggleControl, {
                            label: 'Chiuso nelle festività nazionali',
                            help: attributes.closedOnHolidays === undefined ? 'Come il negozio.' : undefined,
                            checked: attributes.closedOnHolidays !== undefined ? attributes.closedOnHolidays : store.holidays !== false,
                            onChange: function(value) { setAttributes({ closedOnHolidays: value }); }
                        }),
                        storeText('closedDates', 'Altre date di chiusura', store.closed, {
                            textarea: true,
                            help: 'Una per riga, es. 24/12/2026 oppure 2026-12-24.'
                        })
                    ),
                    ownHours ? el(PanelBody, { title: 'Aggiungi Giorno', initialOpen: false },
                        el(Button, {
                            variant: 'secondary',
                            onClick: function() { ops.add({ day: '', time: '' }); },
                            style: { width: '100%', justifyContent: 'center' }
                        }, 'Aggiungi Giorno')
                    ) : null,
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
                            el('path', { d: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z' })
                        ),
                        title: 'Store Hours',
                        text: hours.length + ' giorni' + (ownHours ? ' (di questa pagina)' : ' (del negozio)') + (note ? ' · Nota presente' : '') + ' — Configura nel pannello laterale.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
