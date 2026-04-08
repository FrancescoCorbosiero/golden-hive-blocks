(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, Button } = wp.components;

    registerBlockType('golden-hive/store-hours', {
        edit: function({ attributes, setAttributes }) {
            var hours = attributes.hours || [];
            var title = attributes.title || '';
            var note = attributes.note || '';

            function updateItem(index, field, value) {
                var newHours = hours.map(function(item, i) {
                    if (i === index) {
                        var updated = {};
                        for (var key in item) { updated[key] = item[key]; }
                        updated[field] = value;
                        return updated;
                    }
                    return item;
                });
                setAttributes({ hours: newHours });
            }

            function removeItem(index) {
                var newHours = hours.filter(function(_, i) { return i !== index; });
                setAttributes({ hours: newHours });
            }

            function addItem() {
                var newHours = hours.concat([{ day: '', time: '' }]);
                setAttributes({ hours: newHours });
            }

            function moveItem(index, direction) {
                var newHours = hours.slice();
                var target = index + direction;
                if (target < 0 || target >= newHours.length) return;
                var temp = newHours[index];
                newHours[index] = newHours[target];
                newHours[target] = temp;
                setAttributes({ hours: newHours });
            }

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
                            el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } },
                                el('strong', {}, item.day || 'Giorno ' + (index + 1)),
                                el('div', {},
                                    el(Button, {
                                        isSmall: true,
                                        icon: 'arrow-up-alt2',
                                        label: 'Sposta su',
                                        disabled: index === 0,
                                        onClick: function() { moveItem(index, -1); }
                                    }),
                                    el(Button, {
                                        isSmall: true,
                                        icon: 'arrow-down-alt2',
                                        label: 'Sposta gi\u00f9',
                                        disabled: index === hours.length - 1,
                                        onClick: function() { moveItem(index, 1); }
                                    }),
                                    el(Button, {
                                        isSmall: true,
                                        isDestructive: true,
                                        icon: 'trash',
                                        label: 'Rimuovi',
                                        onClick: function() { removeItem(index); }
                                    })
                                )
                            ),
                            el(TextControl, {
                                label: 'Giorno',
                                value: item.day || '',
                                onChange: function(value) { updateItem(index, 'day', value); }
                            }),
                            el(TextControl, {
                                label: 'Orario',
                                value: item.time || '',
                                onChange: function(value) { updateItem(index, 'time', value); }
                            })
                        );
                    }),
                    el(PanelBody, { title: 'Aggiungi Giorno', initialOpen: false },
                        el(Button, {
                            variant: 'secondary',
                            onClick: addItem,
                            style: { width: '100%', justifyContent: 'center' }
                        }, 'Aggiungi Giorno')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Store Hours'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        hours.length + ' giorni configurati' + (note ? ' \u00b7 Nota presente' : '') + ' \u2014 Configura nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
