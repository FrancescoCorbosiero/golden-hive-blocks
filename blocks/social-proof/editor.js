(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, ToggleControl, Notice } = wp.components;
    const { useState, useEffect } = wp.element;
    const { el, Fragment, Placeholder } = window.ghEditorUtils;

    // The same feed the toast reads on the site (includes/social-proof.php):
    // the last paid orders, so the preview shows what visitors will see.
    function useFeed(days, limit, showCity) {
        const [state, setState] = useState({ loading: true, items: [] });

        useEffect(function() {
            var cancelled = false;
            var url = (window.ajaxurl || '/wp-admin/admin-ajax.php') +
                '?action=ghb_social_proof&days=' + days + '&limit=' + limit + '&city=' + (showCity ? 1 : 0);
            setState({ loading: true, items: [] });
            window.fetch(url, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    if (cancelled) return;
                    var items = json && json.success && json.data && json.data.items ? json.data.items : [];
                    setState({ loading: false, items: items });
                })
                .catch(function() {
                    if (!cancelled) setState({ loading: false, items: [] });
                });
            return function() { cancelled = true; };
        }, [days, limit, showCity]);

        return state;
    }

    registerBlockType('golden-hive/social-proof', {
        edit: function({ attributes, setAttributes }) {
            const { days, limit, showCity, maxPerVisit, interval, initialDelay, displayDuration, showVerified, title, notifications } = attributes;
            const blockProps = useBlockProps();
            const feed = useFeed(days, limit, showCity);

            var summary = feed.loading
                ? 'Carico gli ultimi ordini…'
                : (feed.items.length
                    ? feed.items.length + ' acquisti reali negli ultimi ' + days + ' giorni: ' +
                        feed.items.slice(0, 3).map(function(item) {
                            return item.product + (item.city ? ' (' + item.city + ')' : '');
                        }).join(', ') + (feed.items.length > 3 ? '…' : '')
                    : 'Nessun ordine pagato negli ultimi ' + days + ' giorni: il popup resta nascosto finché non ne arriva uno.');

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Acquisti mostrati', initialOpen: true },
                        el('p', { style: { marginTop: 0 } },
                            'Il popup mostra gli ultimi ordini pagati (In lavorazione o Completati): prodotto, città di spedizione e da quanto. Mai nomi, email o importi.'
                        ),
                        el(RangeControl, {
                            label: 'Ordini degli ultimi (giorni)',
                            value: days,
                            onChange: function(val) { setAttributes({ days: val }); },
                            min: 1,
                            max: 30
                        }),
                        el(RangeControl, {
                            label: 'Acquisti caricati',
                            value: limit,
                            onChange: function(val) { setAttributes({ limit: val }); },
                            min: 3,
                            max: 20
                        }),
                        el(RangeControl, {
                            label: 'Popup per pagina, al massimo',
                            help: 'Ogni acquisto si vede una sola volta per visita.',
                            value: maxPerVisit,
                            onChange: function(val) { setAttributes({ maxPerVisit: val }); },
                            min: 1,
                            max: 10
                        }),
                        el(ToggleControl, {
                            label: 'Mostra la città',
                            checked: !!showCity,
                            onChange: function(val) { setAttributes({ showCity: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Impostazioni', initialOpen: false },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Intervallo (secondi)',
                            value: (interval || 0) / 1000,
                            onChange: function(val) { setAttributes({ interval: val * 1000 }); },
                            min: 5,
                            max: 90,
                            step: 1
                        }),
                        el(RangeControl, {
                            label: 'Ritardo iniziale (secondi)',
                            value: (initialDelay || 0) / 1000,
                            onChange: function(val) { setAttributes({ initialDelay: val * 1000 }); },
                            min: 1,
                            max: 60,
                            step: 1
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
                    )
                ),
                el('div', blockProps,
                    notifications && notifications.length
                        ? el(Notice, { status: 'warning', isDismissible: false },
                            'Questo blocco aveva ' + notifications.length + ' notifiche scritte a mano: non vengono più mostrate, ora il popup usa solo gli ordini reali.')
                        : null,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z' })
                        ),
                        title: 'Social Proof — acquisti reali',
                        text: summary,
                        thumbs: feed.items.map(function(item) { return item.image; })
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
