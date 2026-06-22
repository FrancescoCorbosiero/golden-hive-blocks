(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, ToggleControl, SelectControl, Button } = wp.components;

    registerBlockType('golden-hive/hero-carousel', {
        edit: function({ attributes, setAttributes }) {
            const { slides, autoplay, showDots, showArrows, layout } = attributes;

            var updateSlide = function(index, field, value) {
                var updated = slides.map(function(slide, i) {
                    if (i === index) {
                        var copy = Object.assign({}, slide);
                        copy[field] = value;
                        return copy;
                    }
                    return slide;
                });
                setAttributes({ slides: updated });
            };

            var removeSlide = function(index) {
                var updated = slides.filter(function(_, i) { return i !== index; });
                setAttributes({ slides: updated });
            };

            var addSlide = function() {
                var updated = slides.concat([{
                    image: '',
                    objectPosition: 'center center',
                    eyebrow: '',
                    title: '',
                    subtitle: '',
                    buttonText: '',
                    buttonUrl: ''
                }]);
                setAttributes({ slides: updated });
            };

            var moveSlide = function(index, direction) {
                var updated = [].concat(slides);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ slides: updated });
            };

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
                                el('strong', {}, 'Slide ' + (index + 1)),
                                el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { updateSlide(index, 'image', media.url); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el('div', { style: { marginTop: '8px', marginBottom: '8px' } },
                                                slide.image
                                                    ? el('div', {},
                                                        el('img', { src: slide.image, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: function() { updateSlide(index, 'image', ''); } }, 'Rimuovi immagine')
                                                    )
                                                    : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                            );
                                        }
                                    })
                                ),
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
                                    onChange: function(val) { updateSlide(index, 'objectPosition', val); }
                                }),
                                el(TextControl, {
                                    label: 'Eyebrow',
                                    value: slide.eyebrow || '',
                                    onChange: function(val) { updateSlide(index, 'eyebrow', val); }
                                }),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: slide.title || '',
                                    onChange: function(val) { updateSlide(index, 'title', val); }
                                }),
                                el(TextControl, {
                                    label: 'Sottotitolo',
                                    value: slide.subtitle || '',
                                    onChange: function(val) { updateSlide(index, 'subtitle', val); }
                                }),
                                el(TextControl, {
                                    label: 'Testo pulsante',
                                    value: slide.buttonText || '',
                                    onChange: function(val) { updateSlide(index, 'buttonText', val); }
                                }),
                                el(TextControl, {
                                    label: 'URL pulsante',
                                    value: slide.buttonUrl || '',
                                    onChange: function(val) { updateSlide(index, 'buttonUrl', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveSlide(index, -1); }, disabled: index === 0 }, '↑'),
                                    el(Button, { isSmall: true, onClick: function() { moveSlide(index, 1); }, disabled: index === slides.length - 1 }, '↓'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeSlide(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addSlide, style: { marginTop: '8px' } }, 'Aggiungi slide')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M4 4h16v12H4V4zm0 14h16v2H4v-2z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Hero Carousel'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        slides.length > 0
                            ? slides.length + ' slide configurate'
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
