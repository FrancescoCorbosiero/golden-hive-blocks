(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, ToggleControl } = wp.components;
    const { el, Fragment, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/drop-calendar', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, subtitle, limit, showReleased, emptyText } = attributes;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuto', initialOpen: true },
                        el('p', { style: { marginTop: 0 } },
                            'Elenca da solo i prodotti con una data di drop (Dati prodotto → Generale → Drop), in ordine di uscita.'
                        ),
                        el(TextControl, {
                            label: 'Eyebrow',
                            value: eyebrow,
                            onChange: function(val) { setAttributes({ eyebrow: val }); }
                        }),
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
                        el(RangeControl, {
                            label: 'Drop mostrati',
                            value: limit,
                            min: 1,
                            max: 12,
                            onChange: function(val) { setAttributes({ limit: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostra anche i drop usciti negli ultimi 7 giorni',
                            checked: !!showReleased,
                            onChange: function(val) { setAttributes({ showReleased: val }); }
                        }),
                        el(TextControl, {
                            label: 'Testo se non ci sono drop',
                            help: 'Vuoto: senza drop in programma il blocco non si vede.',
                            value: emptyText,
                            onChange: function(val) { setAttributes({ emptyText: val }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V9h14v11zM7 11h5v5H7z' })
                        ),
                        title: 'Drop Calendar',
                        text: (title || 'Calendario drop') + ' — fino a ' + limit + ' prodotti con una data di drop.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
