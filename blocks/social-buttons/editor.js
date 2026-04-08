(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;

    registerBlockType('golden-hive/social-buttons', {
        edit: function({ attributes, setAttributes }) {
            var instagram = attributes.instagram;
            var facebook = attributes.facebook;
            var tiktok = attributes.tiktok;
            var youtube = attributes.youtube;
            var twitter = attributes.twitter;

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Link Social', initialOpen: true },
                        el(TextControl, {
                            label: 'Instagram URL',
                            value: instagram,
                            onChange: function(val) { setAttributes({ instagram: val }); }
                        }),
                        el(TextControl, {
                            label: 'Facebook URL',
                            value: facebook,
                            onChange: function(val) { setAttributes({ facebook: val }); }
                        }),
                        el(TextControl, {
                            label: 'TikTok URL',
                            value: tiktok,
                            onChange: function(val) { setAttributes({ tiktok: val }); }
                        }),
                        el(TextControl, {
                            label: 'YouTube URL',
                            value: youtube,
                            onChange: function(val) { setAttributes({ youtube: val }); }
                        }),
                        el(TextControl, {
                            label: 'Twitter URL',
                            value: twitter,
                            onChange: function(val) { setAttributes({ twitter: val }); }
                        })
                    )
                ),
                el('div', { className: 'gh-editor-placeholder' },
                    el('div', { className: 'gh-editor-placeholder__icon' },
                        el('svg', { viewBox: '0 0 24 24', fill: 'currentColor' },
                            el('path', { d: 'M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z' })
                        )
                    ),
                    el('div', { className: 'gh-editor-placeholder__title' }, 'Social Buttons'),
                    el('div', { className: 'gh-editor-placeholder__text' }, 'Configura questo blocco nel pannello laterale.')
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
