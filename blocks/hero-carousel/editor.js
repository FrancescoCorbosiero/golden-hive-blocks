(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, ToggleControl, SelectControl, Button } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const listOps = utils.listOps;
    const ItemControls = utils.ItemControls;
    const MediaField = utils.MediaField;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/hero-carousel', {
        edit: function({ attributes, setAttributes }) {
            const { slides, autoplay, showDots, showArrows, layout } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(slides, function(next) { setAttributes({ slides: next }); });

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni Carousel', initialOpen: true },
                        el(SelectControl, {
                            label: 'Layout',
                            help: 'Centrato applica il layout simmetrico e il pulsante "glass" più estetico.',
                            value: layout || 'left',
                            options: [
                                { label: 'Classico (allineato a sinistra)', value: 'left' },
                                { label: 'Centrato (aesthetic)', value: 'centered' }
                            ],
                            onChange: function(val) { setAttributes({ layout: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Autoplay (ms)',
                            value: autoplay,
                            min: 0,
                            max: 15000,
                            step: 500,
                            onChange: function(val) { setAttributes({ autoplay: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostra indicatori',
                            checked: showDots,
                            onChange: function(val) { setAttributes({ showDots: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostra frecce',
                            checked: showArrows,
                            onChange: function(val) { setAttributes({ showArrows: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Slides (' + slides.length + ')', initialOpen: false },
                        slides.map(function(slide, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el(ItemControls, { ops: ops, index: index, count: slides.length, label: 'Slide ' + (index + 1) }),
                                el(MediaField, {
                                    value: slide.image || '',
                                    onSelect: function(media) { ops.updateMany(index, { image: media.url, imageId: media.id || 0 }); },
                                    onRemove: function() { ops.updateMany(index, { image: '', imageId: 0 }); }
                                }),
                                el(SelectControl, {
                                    label: 'Posizione immagine',
                                    help: 'Punto focale dell\'immagine (visibile soprattutto su mobile)',
                                    value: slide.objectPosition || 'center center',
                                    options: [
                                        { label: 'Centro', value: 'center center' },
                                        { label: 'Sinistra', value: 'left center' },
                                        { label: 'Destra', value: 'right center' },
                                        { label: 'Alto centro', value: 'center top' },
                                        { label: 'Basso centro', value: 'center bottom' },
                                        { label: 'Alto sinistra', value: 'left top' },
                                        { label: 'Alto destra', value: 'right top' },
                                        { label: 'Basso sinistra', value: 'left bottom' },
                                        { label: 'Basso destra', value: 'right bottom' }
                                    ],
                                    onChange: function(val) { ops.update(index, 'objectPosition', val); }
                                }),
                                el(TextControl, {
                                    label: 'Eyebrow',
                                    value: slide.eyebrow || '',
                                    onChange: function(val) { ops.update(index, 'eyebrow', val); }
                                }),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: slide.title || '',
                                    onChange: function(val) { ops.update(index, 'title', val); }
                                }),
                                el(TextControl, {
                                    label: 'Sottotitolo',
                                    value: slide.subtitle || '',
                                    onChange: function(val) { ops.update(index, 'subtitle', val); }
                                }),
                                el(TextControl, {
                                    label: 'Testo pulsante',
                                    value: slide.buttonText || '',
                                    onChange: function(val) { ops.update(index, 'buttonText', val); }
                                }),
                                el(TextControl, {
                                    label: 'URL pulsante',
                                    value: slide.buttonUrl || '',
                                    onChange: function(val) { ops.update(index, 'buttonUrl', val); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'primary',
                            onClick: function() {
                                ops.add({
                                    image: '',
                                    objectPosition: 'center center',
                                    eyebrow: '',
                                    title: '',
                                    subtitle: '',
                                    buttonText: '',
                                    buttonUrl: ''
                                });
                            },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi slide')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M4 4h16v12H4V4zm0 14h16v2H4v-2z' })
                        ),
                        title: 'Hero Carousel',
                        text: slides.length > 0
                            ? slides.length + ' slide configurate'
                            : 'Configura questo blocco nel pannello laterale.',
                        thumbs: slides.map(function(slide) { return slide.image; })
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
