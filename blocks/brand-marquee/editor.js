(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, SelectControl, ToggleControl, Button } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const listOps = utils.listOps;
    const ItemControls = utils.ItemControls;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/brand-marquee', {
        edit: function({ attributes, setAttributes }) {
            const { title, brands, speed, direction, pauseOnHover } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(brands, function(next) { setAttributes({ brands: next }); });

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
                                el(ItemControls, { ops: ops, index: index, count: brands.length, label: 'Brand ' + (index + 1) }),
                                el(TextControl, {
                                    label: 'Nome',
                                    value: brand.name || '',
                                    onChange: function(val) { ops.update(index, 'name', val); }
                                }),
                                el(MediaField, {
                                    value: brand.logo || '',
                                    onSelect: function(media) { ops.updateMany(index, { logo: media.url, logoId: media.id || 0 }); },
                                    onRemove: function() { ops.updateMany(index, { logo: '', logoId: 0 }); },
                                    selectLabel: 'Seleziona logo',
                                    removeLabel: 'Rimuovi logo'
                                }),
                                el(TextControl, {
                                    label: 'URL',
                                    value: brand.url || '',
                                    onChange: function(val) { ops.update(index, 'url', val); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'primary',
                            onClick: function() { ops.add({ name: '', logo: '', url: '' }); },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi brand')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M22 16V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2zm-11-4l2.03 2.71L16 11l4 5H8l3-4zM2 6v14c0 1.1.9 2 2 2h14v-2H4V6H2z' })
                        ),
                        title: 'Brand Marquee',
                        text: brands.length > 0
                            ? brands.length + ' brand configurati'
                            : 'Configura questo blocco nel pannello laterale.',
                        thumbs: brands.map(function(brand) { return brand.logo; })
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
