(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, SelectControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/brand-marquee', {
        edit: function({ attributes, setAttributes }) {
            const { title, brands, speed, direction, pauseOnHover } = attributes;

            var updateBrand = function(index, field, value) {
                var updated = brands.map(function(brand, i) {
                    if (i === index) {
                        var copy = Object.assign({}, brand);
                        copy[field] = value;
                        return copy;
                    }
                    return brand;
                });
                setAttributes({ brands: updated });
            };

            var removeBrand = function(index) {
                var updated = brands.filter(function(_, i) { return i !== index; });
                setAttributes({ brands: updated });
            };

            var addBrand = function() {
                var updated = brands.concat([{ name: '', logo: '', url: '' }]);
                setAttributes({ brands: updated });
            };

            var moveBrand = function(index, direction) {
                var updated = [].concat(brands);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ brands: updated });
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
                            label: 'Velocita (px/s)',
                            value: speed,
                            min: 1,
                            max: 200,
                            step: 5,
                            onChange: function(val) { setAttributes({ speed: val }); }
                        }),
                        el(SelectControl, {
                            label: 'Direzione',
                            value: direction,
                            options: [
                                { label: 'Sinistra', value: 'left' },
                                { label: 'Destra', value: 'right' }
                            ],
                            onChange: function(val) { setAttributes({ direction: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Pausa al passaggio del mouse',
                            checked: pauseOnHover,
                            onChange: function(val) { setAttributes({ pauseOnHover: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Brand (' + brands.length + ')', initialOpen: false },
                        brands.map(function(brand, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Brand ' + (index + 1)),
                                el(TextControl, {
                                    label: 'Nome',
                                    value: brand.name || '',
                                    onChange: function(val) { updateBrand(index, 'name', val); }
                                }),
                                el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { updateBrand(index, 'logo', media.url); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el('div', { style: { marginTop: '8px', marginBottom: '8px' } },
                                                brand.logo
                                                    ? el('div', {},
                                                        el('img', { src: brand.logo, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: function() { updateBrand(index, 'logo', ''); } }, 'Rimuovi logo')
                                                    )
                                                    : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona logo')
                                            );
                                        }
                                    })
                                ),
                                el(TextControl, {
                                    label: 'URL',
                                    value: brand.url || '',
                                    onChange: function(val) { updateBrand(index, 'url', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveBrand(index, -1); }, disabled: index === 0 }, '↑'),
                                    el(Button, { isSmall: true, onClick: function() { moveBrand(index, 1); }, disabled: index === brands.length - 1 }, '↓'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeBrand(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addBrand, style: { marginTop: '8px' } }, 'Aggiungi brand')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M22 16V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2zm-11-4l2.03 2.71L16 11l4 5H8l3-4zM2 6v14c0 1.1.9 2 2 2h14v-2H4V6H2z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Brand Marquee'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        brands.length > 0
                            ? brands.length + ' brand configurati'
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
