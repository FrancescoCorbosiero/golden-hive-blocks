<?php
/**
 * Map Embed block – front-end render.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

$embed_code   = $attributes['embedCode'] ?? '';
$height       = $attributes['height'] ?? 450;
$border_radius = $attributes['borderRadius'] ?? 12;

if ( empty( trim( $embed_code ) ) ) {
    return;
}

/* Only allow <iframe> with a strict set of safe attributes. */
$allowed_html = array(
    'iframe' => array(
        'src'              => true,
        'width'            => true,
        'height'           => true,
        'style'            => true,
        'allowfullscreen'  => true,
        'loading'          => true,
        'referrerpolicy'   => true,
        'frameborder'      => true,
    ),
);

$safe_embed = wp_kses( $embed_code, $allowed_html );

$container_style = sprintf(
    'width:100%%;height:%dpx;border-radius:%dpx;overflow:hidden;',
    absint( $height ),
    absint( $border_radius )
);
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'gh-block gh-map-embed' ) ); ?> data-gh-reveal="up">
    <div style="<?php echo esc_attr( $container_style ); ?>">
        <?php echo $safe_embed; ?>
    </div>
</section>
