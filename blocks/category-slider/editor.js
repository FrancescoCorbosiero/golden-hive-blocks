(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl, Button, SelectControl, RangeControl } = wp.components;

    registerBlockType('golden-hive/category-slider', {
        edit: function({ attributes, setAttributes }) {
            const { title, categories, showNav, showDots, autoplay, loop, imageRatio, paddingTop, paddingBottom } = attributes;

            var updateCategory = function(index, field, value) {
                var updated = categories.map(function(cat, i) {
                    if (i === index) {
                        var copy = Object.assign({}, cat);
                        copy[field] = value;
                        return copy;
                    }
                    return cat;
                });
                setAttributes({ categories: updated });
            };

            var removeCategory = function(index) {
                var updated = categories.filter(function(_, i) { return i !== index; });
                setAttributes({ categories: updated });
            };

            var addCategory = function() {
                var updated = categories.concat([{ name: '', image: '', url: '' }]);
                setAttributes({ categories: updated });
            };

            var moveCategory = function(index, direction) {
                var updated = [].concat(categories);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ categories: updated });
            };

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostra navigazione',
                            checked: showNav,
                            onChange: function(val) { setAttributes({ showNav: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostra indicatori',
                            checked: showDots,
                            onChange: function(val) { setAttributes({ showDots: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Autoplay',
                            checked: autoplay,
                            onChange: function(val) { setAttributes({ autoplay: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Loop',
                            checked: loop,
                            onChange: function(val) { setAttributes({ loop: val }); }
                        }),
                        el(SelectControl, {
                            label: 'Proporzione immagine',
                            value: imageRatio || '4 / 5',
                            options: [
                                { label: 'Verticale (3:4)', value: '3 / 4' },
                                { label: 'Standard (4:5)', value: '4 / 5' },
                                { label: 'Alto (2:3)', value: '2 / 3' },
                                { label: 'Molto alto (9:16)', value: '9 / 16' },
                                { label: 'Quadrato (1:1)', value: '1 / 1' }
                            ],
                            onChange: function(val) { setAttributes({ imageRatio: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Padding superiore (px)',
                            value: paddingTop !== undefined ? paddingTop : 40,
                            onChange: function(val) { setAttributes({ paddingTop: val }); },
                            min: 0,
                            max: 120,
                            step: 4
                        }),
                        el(RangeControl, {
                            label: 'Padding inferiore (px)',
                            value: paddingBottom !== undefined ? paddingBottom : 40,
                            onChange: function(val) { setAttributes({ paddingBottom: val }); },
                            min: 0,
                            max: 120,
                            step: 4
                        })
                    ),
                    el(PanelBody, { title: 'Categorie (' + categories.length + ')', initialOpen: false },
                        categories.map(function(cat, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Categoria ' + (index + 1)),
                                el(TextControl, {
                                    label: 'Nome',
                                    value: cat.name || '',
                                    onChange: function(val) { updateCategory(index, 'name', val); }
                                }),
                                el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { updateCategory(index, 'image', media.url); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el('div', { style: { marginTop: '8px', marginBottom: '8px' } },
                                                cat.image
                                                    ? el('div', {},
                                                        el('img', { src: cat.image, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: function() { updateCategory(index, 'image', ''); } }, 'Rimuovi immagine')
                                                    )
                                                    : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                            );
                                        }
                                    })
                                ),
                                el(TextControl, {
                                    label: 'URL',
                                    value: cat.url || '',
                                    onChange: function(val) { updateCategory(index, 'url', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveCategory(index, -1); }, disabled: index === 0 }, '\u2191'),
                                    el(Button, { isSmall: true, onClick: function() { moveCategory(index, 1); }, disabled: index === categories.length - 1 }, '\u2193'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeCategory(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addCategory, style: { marginTop: '8px' } }, 'Aggiungi categoria')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M4 5h16a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm0 8h16a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Category Slider'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        categories.length > 0
                            ? categories.length + ' categorie configurate'
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
