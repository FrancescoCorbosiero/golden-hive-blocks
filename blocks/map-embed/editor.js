(function (wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextareaControl = wp.components.TextareaControl;
    var RangeControl = wp.components.RangeControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var el = window.ghEditorUtils.el;
    var Fragment = window.ghEditorUtils.Fragment;
    var Placeholder = window.ghEditorUtils.Placeholder;
    var StoreText = window.ghEditorUtils.StoreText;
    var StoreReset = window.ghEditorUtils.StoreReset;
    var store = window.ghEditorUtils.store;

    registerBlockType('golden-hive/map-embed', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var fromStore = attributes.embedCode === undefined;
            var hasEmbed = fromStore ? !!store.hasMap : attributes.embedCode.trim().length > 0;

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Mappa', initialOpen: true },
                        el(StoreText, {
                            attributes: attributes,
                            setAttributes: setAttributes,
                            attr: 'embedCode',
                            label: 'Codice Embed',
                            storeValue: store.hasMap ? '(la mappa del negozio)' : '',
                            help: 'Incolla il codice embed iframe di Google Maps.',
                            textarea: true,
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
                        }),
                        el(ToggleControl, {
                            label: 'Carica la mappa al clic',
                            help: 'Consigliato: la pagina è più leggera e Google non imposta cookie finché il visitatore non apre la mappa.',
                            checked: attributes.clickToLoad !== false,
                            onChange: function (value) {
                                setAttributes({ clickToLoad: value });
                            },
                        }),
                        attributes.clickToLoad !== false ? el(StoreText, {
                            attributes: attributes,
                            setAttributes: setAttributes,
                            attr: 'address',
                            label: 'Indirizzo (facoltativo)',
                            storeValue: fromStore ? store.address : '',
                            help: 'Mostrato sulla copertina della mappa; "Apri in Google Maps" lo cerca.',
                        }) : null,
                        el(StoreReset, { attributes: attributes, setAttributes: setAttributes, attrs: ['embedCode', 'address'] })
                    )
                ),
                el(
                    'div',
                    blockProps,
                    el(Placeholder, {
                        icon: el('span', { className: 'dashicons dashicons-location' }),
                        title: 'Map Embed',
                        text: hasEmbed
                            ? (fromStore ? 'La mappa del negozio' : 'Mappa configurata')
                            : 'Incolla il codice embed di Google Maps, o impostala in Personalizza → Golden Hive → Negozio',
                    })
                )
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
