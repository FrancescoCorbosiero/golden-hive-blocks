(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, Button } = wp.components;
    const utils = window.ghEditorUtils;
    const el = utils.el;
    const Fragment = utils.Fragment;
    const listOps = utils.listOps;
    const ItemControls = utils.ItemControls;
    const Placeholder = utils.Placeholder;

    registerBlockType('golden-hive/authenticity-guarantee', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, description, badgeText, steps, buttonText, buttonUrl } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(steps, function(next) { setAttributes({ steps: next }); });

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
                                el(ItemControls, { ops: ops, index: index, count: steps.length, label: 'Passaggio ' + (index + 1) }),
                                el(TextControl, {
                                    label: 'Titolo',
                                    value: step.title || '',
                                    onChange: function(val) { ops.update(index, 'title', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Testo',
                                    value: step.text || '',
                                    onChange: function(val) { ops.update(index, 'text', val); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'primary',
                            onClick: function() { ops.add({ title: '', text: '' }); },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi passaggio')
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
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z' })
                        ),
                        title: 'Authenticity Guarantee',
                        text: steps.length > 0
                            ? steps.length + ' passaggi configurati'
                            : 'Configura questo blocco nel pannello laterale.'
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
