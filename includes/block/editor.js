( function( blocks, element, blockEditor, components, i18n ) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var __ = i18n.__;

    blocks.registerBlockType( 'rapls-ai-chatbot/chatbot', {
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el(
                element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __( 'Chatbot Settings', 'rapls-ai-chatbot' ), initialOpen: true },
                        el( TextControl, {
                            label: __( 'Height', 'rapls-ai-chatbot' ),
                            value: attributes.height,
                            onChange: function( val ) { setAttributes( { height: val } ); },
                            help: __( 'e.g., 500px, 80vh', 'rapls-ai-chatbot' ),
                        } ),
                        el( TextControl, {
                            label: __( 'Theme', 'rapls-ai-chatbot' ),
                            value: attributes.theme,
                            onChange: function( val ) { setAttributes( { theme: val } ); },
                            help: __( 'Leave empty to use the default theme.', 'rapls-ai-chatbot' ),
                        } ),
                        el( TextControl, {
                            label: __( 'Bot ID', 'rapls-ai-chatbot' ),
                            value: attributes.bot,
                            onChange: function( val ) { setAttributes( { bot: val } ); },
                            help: __( 'Leave empty to use the default bot.', 'rapls-ai-chatbot' ),
                        } )
                    )
                ),
                el(
                    'div',
                    Object.assign( {}, blockProps, {
                        style: {
                            padding: '20px',
                            backgroundColor: '#f0f0f0',
                            borderRadius: '8px',
                            textAlign: 'center',
                            border: '1px dashed #ccc',
                        },
                    } ),
                    el( 'span', { style: { fontSize: '32px', display: 'block', marginBottom: '8px' } }, '\uD83E\uDD16' ),
                    el( 'strong', null, 'AI Chatbot' ),
                    el( 'p', { style: { margin: '8px 0 0', color: '#666', fontSize: '13px' } },
                        attributes.height !== '500px'
                            ? __( 'Height:', 'rapls-ai-chatbot' ) + ' ' + attributes.height
                            : __( 'The chatbot will appear here on the frontend.', 'rapls-ai-chatbot' )
                    )
                )
            );
        },

        save: function() {
            // Server-side rendered
            return null;
        },
    } );
} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);
