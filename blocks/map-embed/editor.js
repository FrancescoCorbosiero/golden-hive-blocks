(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextareaControl = wp.components.TextareaControl;
    var RangeControl = wp.components.RangeControl;
    var el = window.ghEditorUtils.el;
    var Fragment = window.ghEditorUtils.Fragment;
    var Placeholder = window.ghEditorUtils.Placeholder;

    registerBlockType('golden-hive/map-embed', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var hasEmbed = attributes.embedCode && attributes.embedCode.trim().length > 0;

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Mappa', initialOpen: true },
                        el(TextareaControl, {
                            label: 'Codice Embed',
                            help: 'Incolla il codice embed iframe di Google Maps.',
                            value: attributes.embedCode,
                            onChange: function (value) {
                                setAttributes({ embedCode: value });
                            },
                            rows: 6,
                        }),
                        el(RangeControl, {
                            label: 'Altezza (px)',
                            value: attributes.height,
                            onChange: function (value) {
                                setAttributes({ height: value });
                            },
                            min: 200,
                            max: 800,
                            step: 50,
                        }),
                        el(RangeControl, {
                            label: 'Raggio bordi (px)',
                            value: attributes.borderRadius,
                            onChange: function (value) {
                                setAttributes({ borderRadius: value });
                            },
                            min: 0,
                            max: 24,
                        })
                    )
                ),
                el(
                    'div',
                    blockProps,
                    el(Placeholder, {
                        icon: el('span', { className: 'dashicons dashicons-location' }),
                        title: 'Map Embed',
                        text: hasEmbed
                            ? 'Mappa configurata'
                            : 'Incolla il codice embed di Google Maps',
                    })
                )
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
