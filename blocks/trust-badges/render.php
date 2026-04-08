<?php
/**
 * Trust Badges Block - Render lato server
 * Supports: default (inline strip) and carousel (marquee scroll)
 */

$badges = $attributes['badges'] ?? [];
$variant = $attributes['variant'] ?? 'default';

$icons = [
    'authentic' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
    'shipping' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    'returns' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>',
    'secure' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
    'support' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>',
    'quality' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
];

if (empty($badges)) {
    return;
}

$variant_class = $variant === 'carousel' ? ' gh-trust-badges--carousel' : '';

// For carousel, we duplicate badges for seamless infinite scroll
$render_badges = $variant === 'carousel' ? array_merge($badges, $badges) : $badges;
?>
<section class="gh-block gh-trust-badges<?php echo esc_attr($variant_class); ?>">
    <div class="gh-trust-badges__strip">
        <?php foreach ($render_badges as $badge) : ?>
            <div class="gh-trust-badge">
                <span class="gh-trust-badge__icon">
                    <?php
                    $icon_key = $badge['icon'] ?? 'authentic';
                    echo isset($icons[$icon_key]) ? $icons[$icon_key] : $icons['authentic'];
                    ?>
                </span>
                <?php if (!empty($badge['title'])) : ?>
                    <span class="gh-trust-badge__label"><?php echo esc_html($badge['title']); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
