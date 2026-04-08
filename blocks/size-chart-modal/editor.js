(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl } = wp.components;

    registerBlockType('golden-hive/size-chart-modal', {
        edit: function({ attributes, setAttributes }) {
            var modalId = attributes.modalId;
            var title = attributes.title;
            var description = attributes.description;
            var triggerText = attributes.triggerText;
            var showTrigger = attributes.showTrigger;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Impostazioni Modale', initialOpen: true },
                        el(TextControl, {
                            label: 'ID Modale',
                            value: modalId,
                            onChange: function(val) { setAttributes({ modalId: val }); }
                        }),
                        el(TextControl, {
                            label: 'Titolo',
                            value: title,
                            onChange: function(val) { setAttributes({ title: val }); }
                        }),
                        el(TextControl, {
                            label: 'Descrizione',
                            value: description,
                            onChange: function(val) { setAttributes({ description: val }); }
                        }),
                        el(TextControl, {
                            label: 'Testo Pulsante',
                            value: triggerText,
                            onChange: function(val) { setAttributes({ triggerText: val }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostra Pulsante',
                            checked: showTrigger,
                            onChange: function(val) { setAttributes({ showTrigger: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M10 10.02h5V21h-5zM17 21h3c1.1 0 2-.9 2-2v-9h-5v11zm3-18H4c-1.1 0-2 .9-2 2v3h20V5c0-1.1-.9-2-2-2zM2 19c0 1.1.9 2 2 2h3V10H2v9z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Size Chart Modal'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
