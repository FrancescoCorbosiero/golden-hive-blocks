(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, SelectControl, Button } = wp.components;

    registerBlockType('golden-hive/trust-badges', {
        edit: function({ attributes, setAttributes }) {
            const { badges, variant } = attributes;

            var updateItem = function(index, field, value) {
                var updated = badges.map(function(item, i) {
                    if (i === index) {
                        var copy = Object.assign({}, item);
                        copy[field] = value;
                        return copy;
                    }
                    return item;
                });
                setAttributes({ badges: updated });
            };

            var removeItem = function(index) {
                var updated = badges.filter(function(_, i) { return i !== index; });
                setAttributes({ badges: updated });
            };

            var addItem = function() {
                var updated = badges.concat([{ icon: 'authentic', title: '' }]);
                setAttributes({ badges: updated });
            };

            var moveItem = function(index, direction) {
                var updated = [].concat(badges);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ badges: updated });
            };

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
                    el(PanelBody, { title: 'Badge (' + badges.length + ')', initialOpen: false },
                        badges.map(function(item, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Badge ' + (index + 1)),
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
                                    onChange: function(val) { updateItem(index, 'icon', val); }
                                }),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: item.title || '',
                                    onChange: function(val) { updateItem(index, 'title', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveItem(index, -1); }, disabled: index === 0 }, '\u2191'),
                                    el(Button, { isSmall: true, onClick: function() { moveItem(index, 1); }, disabled: index === badges.length - 1 }, '\u2193'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeItem(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addItem, style: { marginTop: '8px' } }, 'Aggiungi badge')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Trust Badges'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        badges.length > 0
                            ? badges.length + ' badge configurati'
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
