(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, Button } = wp.components;
    const { el, Fragment, listOps, ItemControls, Placeholder } = window.ghEditorUtils;

    registerBlockType('golden-hive/legit-check', {
        edit: function({ attributes, setAttributes }) {
            const { eyebrow, title, subtitle, checks, buttonText, buttonUrl } = attributes;
            const blockProps = useBlockProps();

            var ops = listOps(checks, function(next) {
                setAttributes({ checks: next });
            });

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
                            label: 'Sottotitolo',
                            value: subtitle,
                            onChange: function(val) { setAttributes({ subtitle: val }); }
                        })
                    ),
                    el(PanelBody, { title: 'Verifiche (' + checks.length + ')', initialOpen: false },
                        checks.map(function(check, index) {
                            return el('div', { key: index, style: { marginBottom: '16px', paddingBottom: '16px', borderBottom: '1px solid #ddd' } },
                                el(ItemControls, {
                                    ops: ops,
                                    index: index,
                                    count: checks.length,
                                    label: 'Verifica ' + (index + 1)
                                }),
                                el(TextControl, {
                                    label: 'Area',
                                    value: check.area || '',
                                    onChange: function(val) { ops.update(index, 'area', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Autentico',
                                    value: check.real || '',
                                    onChange: function(val) { ops.update(index, 'real', val); }
                                }),
                                el(TextareaControl, {
                                    label: 'Falso',
                                    value: check.fake || '',
                                    onChange: function(val) { ops.update(index, 'fake', val); }
                                })
                            );
                        }),
                        el(Button, {
                            variant: 'primary',
                            onClick: function() { ops.add({ area: '', real: '', fake: '' }); },
                            style: { marginTop: '8px' }
                        }, 'Aggiungi verifica')
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
                            el('path', { d: 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z' })
                        ),
                        title: 'Legit Check',
                        text: checks.length > 0
                            ? checks.length + ' verifiche configurate'
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
