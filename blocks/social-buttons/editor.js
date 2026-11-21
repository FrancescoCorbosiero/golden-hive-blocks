(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody } = wp.components;
    const { el, Fragment, Placeholder, StoreText, StoreReset, store } = window.ghEditorUtils;

    registerBlockType('golden-hive/social-buttons', {
        edit: function({ attributes, setAttributes }) {
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Link Social', initialOpen: true },
                        [['instagram', 'Instagram URL', 'instagram'], ['facebook', 'Facebook URL', 'facebook'], ['tiktok', 'TikTok URL', 'tiktok'],
                            ['youtube', 'YouTube URL', 'youtube'], ['twitter', 'X (Twitter) URL', 'x']].map(function(f) {
                            return el(StoreText, {
                                key: f[0],
                                attributes: attributes,
                                setAttributes: setAttributes,
                                attr: f[0],
                                label: f[1],
                                storeValue: store[f[2]]
                            });
                        }),
                        el(StoreReset, { attributes: attributes, setAttributes: setAttributes, attrs: ['instagram', 'facebook', 'tiktok', 'youtube', 'twitter'] })
                    )
                ),
                el('div', blockProps,
                    el(Placeholder, {
                        icon: el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z' })
                        ),
                        title: 'Social Buttons',
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
