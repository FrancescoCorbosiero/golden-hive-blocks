(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, Button } = wp.components;

    registerBlockType('golden-hive/authenticity-guarantee', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, description, badgeText, steps, buttonText, buttonUrl } = attributes;

            var updateStep = function(index, field, value) {
                var updated = steps.map(function(step, i) {
                    if (i === index) {
                        var copy = Object.assign({}, step);
                        copy[field] = value;
                        return copy;
                    }
                    return step;
                });
                setAttributes({ steps: updated });
            };

            var removeStep = function(index) {
                var updated = steps.filter(function(_, i) { return i !== index; });
                setAttributes({ steps: updated });
            };

            var addStep = function() {
                var updated = steps.concat([{ title: '', text: '' }]);
                setAttributes({ steps: updated });
            };

            var moveStep = function(index, direction) {
                var updated = [].concat(steps);
                var target = index + direction;
                if (target < 0 || target >= updated.length) return;
                var temp = updated[index];
                updated[index] = updated[target];
                updated[target] = temp;
                setAttributes({ steps: updated });
            };

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuto', initialOpen: true },
                        el(TextControl, {
                            label: 'Eyebrow',
                            value: eyebrow,
                            onChange: function(val) { setAttributes({ eyebrow: val }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(TextareaControl, {
                            label: 'Descrizione',
                            value: description,
                            onChange: function(val) { setAttributes({ description: val }); }
                        }),
                        el(TextControl, {
                            label: 'Testo badge',
                            value: badgeText,
                            onChange: function(val) { setAttributes({ badgeText: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Passaggi (' + steps.length + ')', initialOpen: false },
                        steps.map(function(step, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el('strong', {}, 'Passaggio ' + (index + 1)),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: step.title || '',
                                    onChange: function(val) { updateStep(index, 'title', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Testo',
                                    value: step.text || '',
                                    onChange: function(val) { updateStep(index, 'text', val); }
                                }),
                                el('div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
                                    el(Button, { isSmall: true, onClick: function() { moveStep(index, -1); }, disabled: index === 0 }, '↑'),
                                    el(Button, { isSmall: true, onClick: function() { moveStep(index, 1); }, disabled: index === steps.length - 1 }, '↓'),
                                    el(Button, { isSmall: true, isDestructive: true, onClick: function() { removeStep(index); } }, 'Elimina')
                                )
                            );
                        }),
                        el(Button, { isPrimary: true, onClick: addStep, style: { marginTop: '8px' } }, 'Aggiungi passaggio')
                    ),
                    el(PanelBody, { title: 'Pulsante', initialOpen: false },
                        el(TextControl, {
                            label: 'Testo pulsante',
                            value: buttonText,
                            onChange: function(val) { setAttributes({ buttonText: val }); }
                        }),
                        el(TextControl, {
                            label: 'URL pulsante',
                            value: buttonUrl,
                            onChange: function(val) { setAttributes({ buttonUrl: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Authenticity Guarantee'),
                    el('div', { className: 'gh-editor-placeholder__text' },
                        steps.length > 0
                            ? steps.length + ' passaggi configurati'
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
