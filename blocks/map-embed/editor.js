(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextareaControl = wp.components.TextareaControl;
    var RangeControl = wp.components.RangeControl;
    var useBlockProps = wp.blockEditor.useBlockProps;

    registerBlockType('golden-hive/map-embed', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var hasEmbed = attributes.embedCode && attributes.embedCode.trim().length > 0;

            return el(
                'div',
                blockProps,
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
                    { className: 'gh-editor-placeholder' },
                    el(
                        'span',
                        { className: 'dashicons dashicons-location' }
                    ),
                    el(
                        'p',
                        null,
                        hasEmbed
                            ? 'Mappa configurata'
                            : 'Incolla il codice embed di Google Maps'
                    )
                )
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
