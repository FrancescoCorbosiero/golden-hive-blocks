(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/store-hours', {
        edit: function({ attributes, setAttributes }) {
            var hours = attributes.hours || [];
            var title = attributes.title || '';
            var note = attributes.note || '';
            const blockProps = useBlockProps();

            var ops = listOps(hours, function(next) {
                setAttributes({ hours: next });
            });

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Intestazione', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextControl, {
                            label: 'Nota (opzionale)',
                            value: note,
                            onChange: function(value) { setAttributes({ note: value }); }
                        })
                    ),
                    hours.map(function(item, index) {
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
                    }),
                    el(PanelBody, { title: 'Chiusure', initialOpen: false },
                        el('p', { style: { marginTop: 0 } },
                            'Sul sito il blocco dice se il negozio è aperto adesso ("Aperto ora · chiude alle 19:30"). Orari nel formato 10.30 - 13.00 | 15.30 - 19.30, oppure Chiuso.'
                        ),
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
                    el(PanelBody, { title: 'Aggiungi Giorno', initialOpen: false },
                        el(Button, {
                            variant: 'secondary',
                            onClick: function() { ops.add({ day: '', time: '' }); },
                            style: { width: '100%', justifyContent: 'center' }
                        }, 'Aggiungi Giorno')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z' })
                        ),
                        title: 'Store Hours',
                        text: hours.length + ' giorni configurati' + (note ? ' · Nota presente' : '') + ' — Configura nel pannello laterale.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
