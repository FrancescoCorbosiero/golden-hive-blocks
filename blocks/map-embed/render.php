<?php
/**
 * Map Embed block – front-end render.
 *
 * The map left out of the block is the store's (Customizer → Golden Hive →
 * Negozio), and then so is the address on its cover; a map written in the
 * block shows only the address written with it.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

$ghb_from_store = !array_key_exists( 'embedCode', $attributes );
$embed_code     = (string) ghb_store_attr( $attributes, 'golden-hive/map-embed', 'embedCode' );
$height         = $attributes['height'] ?? 450;
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
    'width:100%%;height:%dpx;border-radius:calc(%dpx * var(--gh-round, 1));overflow:hidden;',
    absint( $height ),
    absint( $border_radius )
);

/*
 * Click to load (default): the map loads when the visitor asks, so the page
 * is lighter and Google sets no cookie before then (gh_map_facade). "Apri in
 * Google Maps" opens the address, or the embed itself full-page.
 */
$facade = '';
if ( $attributes['clickToLoad'] ?? true ) {
    $address  = array_key_exists( 'address', $attributes )
        ? trim( (string) $attributes['address'] )
        : ( $ghb_from_store ? trim( (string) ghb_store_attr( $attributes, 'golden-hive/map-embed', 'address' ) ) : '' );
    $open_url = '';
    if ( '' === $address && preg_match( '/\ssrc="([^"]+)"/i', $safe_embed, $m ) ) {
        $open_url = html_entity_decode( $m[1] );
    }
    $facade = gh_map_facade( $safe_embed, array(
        'address'  => $address,
        'open_url' => $open_url,
    ) );
}
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'gh-block gh-map-embed' ) ); ?> data-gh-reveal="up">
    <div style="<?php echo esc_attr( $container_style ); ?>">
        <?php echo '' !== $facade ? $facade : $safe_embed; // Sanitised iframe (wp_kses / gh_map_facade). ?>
    </div>
</section>
