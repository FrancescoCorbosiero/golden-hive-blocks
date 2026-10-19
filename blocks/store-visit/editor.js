(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, MediaField, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/store-visit', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();
            var hours = attributes.hours || [];
            var ops = listOps(hours, function(next) { setAttributes({ hours: next }); });

            function text(label, key, help) {
                return el(TextControl, {
                    label: label,
                    help: help,
                    value: attributes[key] || '',
                    onChange: function(value) { var patch = {}; patch[key] = value; setAttributes(patch); }
                });
            }

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        text('Eyebrow', 'eyebrow'),
                        text('Titolo', 'title'),
                        el(TextareaControl, {
                            label: 'Sottotitolo',
                            value: attributes.subtitle || '',
                            onChange: function(value) { setAttributes({ subtitle: value }); }
                        }),
                        text('Indirizzo', 'address', '"Indicazioni" apre Google Maps verso questo indirizzo.'),
                        text('Partita IVA (facoltativa)', 'vat', 'Es. P.IVA: 01875650333, sotto l\'indirizzo.'),
                        text('Telefono (facoltativo)', 'phone'),
                        text('Nota (facoltativa)', 'note')
                    ),
                    el(PanelBody, { title: 'WhatsApp', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Mostra il bottone WhatsApp',
                            help: 'Usa il numero di Personalizza → Golden Hive — Pagina prodotto.',
                            checked: attributes.showWhatsApp !== false,
                            onChange: function(value) { setAttributes({ showWhatsApp: value }); }
                        }),
                        text('Messaggio già scritto', 'whatsappText')
                    ),
                    el(PanelBody, { title: 'Orari (' + hours.length + ')', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'Formato: 10.30 - 13.00 | 15.30 - 19.30, oppure Chiuso. Sul sito il blocco dice se il negozio è aperto adesso.'
                        ),
                        hours.map(function(item, index) {
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
                        }),
                        el(Button, {
                            variant: 'secondary',
                            onClick: function() { ops.add({ day: '', time: '' }); }
                        }, 'Aggiungi giorno')
                    ),
                    el(PanelBody, { title: 'Chiusure', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Chiuso nelle festività nazionali',
                            checked: attributes.closedOnHolidays !== false,
                            onChange: function(value) { setAttributes({ closedOnHolidays: value }); }
                        }),
                        el(TextareaControl, {
                            label: 'Altre date di chiusura',
                            help: 'Una per riga, es. 24/12/2026 oppure 2026-12-24.',
                            value: attributes.closedDates || '',
                            onChange: function(value) { setAttributes({ closedDates: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Mappa', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'La mappa si carica solo quando il visitatore la apre: pagina più leggera e nessun cookie di Google prima.'
                        ),
                        el(TextareaControl, {
                            label: 'Codice embed di Google Maps',
                            value: attributes.mapEmbed || '',
                            rows: 5,
                            onChange: function(value) { setAttributes({ mapEmbed: value }); }
                        }),
                        el(MediaField, {
                            label: 'Anteprima (facoltativa)',
                            value: attributes.mapImage || '',
                            onSelect: function(media) { setAttributes({ mapImage: media.url, mapImageId: media.id || 0 }); },
                            onRemove: function() { setAttributes({ mapImage: '', mapImageId: 0 }); },
                            selectLabel: 'Scegli uno screenshot della mappa'
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z' })
                        ),
                        title: attributes.title || 'Vieni in negozio',
                        text: (attributes.address || 'Indirizzo da inserire') + ' · ' + hours.length + ' giorni di orari',
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
