(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;

    registerBlockType('golden-hive/faq-schema', {
        edit: function({ attributes, setAttributes }) {
            var items = attributes.items || [];

            function updateItem(index, field, value) {
                var newItems = items.map(function(item, i) {
                    if (i === index) {
                        var updated = {};
                        for (var key in item) { updated[key] = item[key]; }
                        updated[field] = value;
                        return updated;
                    }
                    return item;
                });
                setAttributes({ items: newItems });
            }

            function removeItem(index) {
                var newItems = items.filter(function(_, i) { return i !== index; });
                setAttributes({ items: newItems });
            }

            function addItem() {
                var newItems = items.concat([{ question: '', answer: '' }]);
                setAttributes({ items: newItems });
            }

            function moveItem(index, direction) {
                var newItems = items.slice();
                var target = index + direction;
                if (target < 0 || target >= newItems.length) return;
                var temp = newItems[index];
                newItems[index] = newItems[target];
                newItems[target] = temp;
                setAttributes({ items: newItems });
            }

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Contenuti', initialOpen: true },
                        el(TextControl, {
                            label: 'Titolo',
                            value: attributes.title || '',
                            onChange: function(value) { setAttributes({ title: value }); }
                        }),
                        el(TextControl, {
                            label: 'Sottotitolo',
                            value: attributes.subtitle || '',
                            onChange: function(value) { setAttributes({ subtitle: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Consenti apertura multipla',
                            checked: !!attributes.allowMultiple,
                            onChange: function(value) { setAttributes({ allowMultiple: value }); }
                        })
                    ),
                    el(PanelBody, { title: 'Domande e Risposte', initialOpen: false },
                        items.map(function(item, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', padding: '12px', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
                                el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } },
                                    el('strong', {}, 'Domanda ' + (index + 1)),
                                    el('div', {},
                                        el(Button, {
                                            isSmall: true,
                                            icon: 'arrow-up-alt2',
                                            label: 'Sposta su',
                                            disabled: index === 0,
                                            onClick: function() { moveItem(index, -1); }
                                        }),
                                        el(Button, {
                                            isSmall: true,
                                            icon: 'arrow-down-alt2',
                                            label: 'Sposta gi\u00f9',
                                            disabled: index === items.length - 1,
                                            onClick: function() { moveItem(index, 1); }
                                        }),
                                        el(Button, {
                                            isSmall: true,
                                            isDestructive: true,
                                            icon: 'trash',
                                            label: 'Rimuovi',
                                            onClick: function() { removeItem(index); }
                                        })
                                    )
                                ),
                                el(TextControl, {
                                    label: 'Domanda',
                                    value: item.question || '',
                                    onChange: function(value) { updateItem(index, 'question', value); }
                                }),
                                el(TextareaControl, {
                                    label: 'Risposta',
                                    value: item.answer || '',
                                    onChange: function(value) { updateItem(index, 'answer', value); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'secondary',
                            onClick: addItem,
                            style: { width: '100%', justifyContent: 'center' }
                        }, 'Aggiungi Domanda')
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'FAQ Schema'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
