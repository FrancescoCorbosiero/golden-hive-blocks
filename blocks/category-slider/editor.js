(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl, Button, SelectControl, RangeControl } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const listOps = utils.listOps;
    const ItemControls = utils.ItemControls;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/category-slider', {
        edit: function({ attributes, setAttributes }) {
            const { title, categories, showNav, imageRatio, paddingTop, paddingBottom } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(categories, function(next) { setAttributes({ categories: next }); });

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
                        el('p', { style: { fontSize: '12px', fontStyle: 'italic', color: '#757575' } },
                            'Le slide scorrono manualmente (scroll orizzontale).'
                        ),
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
                                el(ItemControls, { ops: ops, index: index, count: categories.length, label: 'Categoria ' + (index + 1) }),
                                el(TextControl, {
                                    label: 'Nome',
                                    value: cat.name || '',
                                    onChange: function(val) { ops.update(index, 'name', val); }
                                }),
                                el(MediaField, {
                                    value: cat.image || '',
                                    onSelect: function(media) { ops.updateMany(index, { image: media.url, imageId: media.id || 0 }); },
                                    onRemove: function() { ops.updateMany(index, { image: '', imageId: 0 }); }
                                }),
                                el(TextControl, {
                                    label: 'URL',
                                    value: cat.url || '',
                                    onChange: function(val) { ops.update(index, 'url', val); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'primary',
                            onClick: function() { ops.add({ name: '', image: '', url: '' }); },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi categoria')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M4 5h16a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm0 8h16a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z' })
                        ),
                        title: 'Category Slider',
                        text: categories.length > 0
                            ? categories.length + ' categorie configurate'
                            : 'Configura questo blocco nel pannello laterale.',
                        thumbs: categories.map(function(cat) { return cat.image; })
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
