(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, Button } = wp.components;

    registerBlockType('golden-hive/social-proof', {
        edit: function({ attributes, setAttributes }) {
            const { notifications, interval, initialDelay, title } = attributes;

            var updateItem = function(index, field, value) {
                var updated = notifications.map(function(item, i) {
                    if (i === index) {
                        var copy = Object.assign({}, item);
                        copy[field] = value;
                        return copy;
                    }
                    return item;
                });
                setAttributes({ notifications: updated });
            };

            var removeItem = function(index) {
                var updated = notifications.filter(function(_, i) { return i !== index; });
                setAttributes({ notifications: updated });
            };

            var addItem = function() {
                var updated = notifications.concat([{ name: '', product: '', image: '', location: '', time: '' }]);
                setAttributes({ notifications: updated });
            };

            var moveItem = function(index, direction) {
                var updated = [].concat(notifications);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ notifications: updated });
            };

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
                        })
                    ),
                    el(PanelBody, { title: 'Notifiche (' + notifications.length + ')', initialOpen: false },
                        notifications.map(function(item, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Notifica ' + (index + 1)),
                                el(TextControl, {
                                    label: 'Nome',
                                    value: item.name || '',
                                    onChange: function(val) { updateItem(index, 'name', val); }
                                }),
                                el(TextControl, {
                                    label: 'Prodotto',
                                    value: item.product || '',
                                    onChange: function(val) { updateItem(index, 'product', val); }
                                }),
                                el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { updateItem(index, 'image', media.url); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el('div', { style: { marginTop: '8px', marginBottom: '8px' } },
                                                item.image
                                                    ? el('div', {},
                                                        el('img', { src: item.image, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: function() { updateItem(index, 'image', ''); } }, 'Rimuovi immagine')
                                                    )
                                                    : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                            );
                                        }
                                    })
                                ),
                                el(TextControl, {
                                    label: 'Localita',
                                    value: item.location || '',
                                    onChange: function(val) { updateItem(index, 'location', val); }
                                }),
                                el(TextControl, {
                                    label: 'Tempo',
                                    value: item.time || '',
                                    onChange: function(val) { updateItem(index, 'time', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveItem(index, -1); }, disabled: index === 0 }, '\u2191'),
                                    el(Button, { isSmall: true, onClick: function() { moveItem(index, 1); }, disabled: index === notifications.length - 1 }, '\u2193'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeItem(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addItem, style: { marginTop: '8px' } }, 'Aggiungi notifica')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Social Proof'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        notifications.length > 0
                            ? notifications.length + ' notifiche configurate'
                            : 'Configura questo blocco nel pannello laterale.'
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
