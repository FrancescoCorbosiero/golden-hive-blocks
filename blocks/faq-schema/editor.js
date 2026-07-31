(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, Button } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const listOps = utils.listOps;
    const ItemControls = utils.ItemControls;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/faq-schema', {
        edit: function({ attributes, setAttributes }) {
            var items = attributes.items || [];
            const blockProps = useBlockProps();

            var ops = listOps(items, function(next) { setAttributes({ items: next }); });

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
                                el(ItemControls, { ops: ops, index: index, count: items.length, label: 'Domanda ' + (index + 1) }),
                                el(TextControl, {
                                    label: 'Domanda',
                                    value: item.question || '',
                                    onChange: function(value) { ops.update(index, 'question', value); }
                                }),
                                el(TextareaControl, {
                                    label: 'Risposta',
                                    value: item.answer || '',
                                    onChange: function(value) { ops.update(index, 'answer', value); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'secondary',
                            onClick: function() { ops.add({ question: '', answer: '' }); },
                            style: { width: '100%', justifyContent: 'center' }
                        }, 'Aggiungi Domanda')
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z' })
                        ),
                        title: 'FAQ Schema',
                        text: 'Configura questo blocco nel pannello laterale.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
