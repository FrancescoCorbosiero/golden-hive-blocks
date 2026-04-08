(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, Button } = wp.components;

    registerBlockType('golden-hive/instagram-feed', {
        edit: function({ attributes, setAttributes }) {
            const { title, subtitle, instagramUrl, buttonText, images, columns } = attributes;

            var updateImage = function(index, field, value) {
                var updated = images.map(function(item, i) {
                    if (i === index) {
                        var copy = Object.assign({}, item);
                        copy[field] = value;
                        return copy;
                    }
                    return item;
                });
                setAttributes({ images: updated });
            };

            var removeImage = function(index) {
                var updated = images.filter(function(_, i) { return i !== index; });
                setAttributes({ images: updated });
            };

            var addImage = function() {
                var updated = images.concat([{ url: '', alt: '' }]);
                setAttributes({ images: updated });
            };

            var moveImage = function(index, direction) {
                var updated = [].concat(images);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ images: updated });
            };

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(TextControl, {
                            label: 'Sottotitolo',
                            value: subtitle,
                            onChange: function(val) { setAttributes({ subtitle: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL Instagram',
                            value: instagramUrl,
                            onChange: function(val) { setAttributes({ instagramUrl: val }); }
                        }),
                        el(TextControl, {
                            label: 'Testo pulsante',
                            value: buttonText,
                            onChange: function(val) { setAttributes({ buttonText: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Colonne',
                            value: columns,
                            min: 2,
                            max: 6,
                            onChange: function(val) { setAttributes({ columns: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Immagini (' + images.length + ')', initialOpen: false },
                        images.map(function(image, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Immagine ' + (index + 1)),
                                el(MediaUploadCheck, {},
                                    el(MediaUpload, {
                                        onSelect: function(media) { updateImage(index, 'url', media.url); },
                                        allowedTypes: ['image'],
                                        render: function(obj) {
                                            return el('div', { style: { marginTop: '8px', marginBottom: '8px' } },
                                                image.url
                                                    ? el('div', {},
                                                        el('img', { src: image.url, style: { maxWidth: '100%', height: 'auto', marginBottom: '4px' } }),
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: function() { updateImage(index, 'url', ''); } }, 'Rimuovi immagine')
                                                    )
                                                    : el(Button, { isSecondary: true, onClick: obj.open }, 'Seleziona immagine')
                                            );
                                        }
                                    })
                                ),
                                el(TextControl, {
                                    label: 'Testo alternativo',
                                    value: image.alt || '',
                                    onChange: function(val) { updateImage(index, 'alt', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveImage(index, -1); }, disabled: index === 0 }, '\u2191'),
                                    el(Button, { isSmall: true, onClick: function() { moveImage(index, 1); }, disabled: index === images.length - 1 }, '\u2193'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeImage(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addImage, style: { marginTop: '8px' } }, 'Aggiungi immagine')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Instagram Feed'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        images.length > 0
                            ? images.length + ' immagini configurate | ' + columns + ' colonne'
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
