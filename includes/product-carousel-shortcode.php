<?php
/**
 * Product Carousel Shortcode
 *
 * WooCommerce product carousels using Swiper.js
 *
 * @package Golden_Hive_Blocks
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 1. ENQUEUE ASSETS
 * ═══════════════════════════════════════════════════════════════
 */
/**
 * Register Swiper assets but only enqueue when a carousel shortcode is present.
 */
add_action('wp_enqueue_scripts', 'ghb_register_carousel_assets');

function ghb_register_carousel_assets() {
    wp_register_style(
        'swiper-css',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        array(),
        '11.0.0'
    );

    wp_register_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        array(),
        '11.0.0',
        true
    );

    wp_register_style(
        'ghb-carousel-styles',
        false,
        array('swiper-css'),
        GOLDEN_HIVE_BLOCKS_VERSION
    );
    wp_add_inline_style('ghb-carousel-styles', ghb_get_carousel_styles());
}

/**
 * Enqueue carousel assets — called from the shortcode callback itself,
 * so assets only load on pages that actually render a carousel.
 */
function ghb_enqueue_carousel_assets() {
    wp_enqueue_style('swiper-css');
    wp_enqueue_style('ghb-carousel-styles');
    wp_enqueue_script('swiper-js');
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 2. COMPREHENSIVE CSS STYLES
 * ═══════════════════════════════════════════════════════════════
 */
function ghb_get_carousel_styles() {
    return '
    /* ═══════════════════════════════════════════════════════════
       CSS CUSTOM PROPERTIES (Theme Variables)
       ═══════════════════════════════════════════════════════════ */
    :root {
        --ghb-primary: #0a0a0a;
        --ghb-accent: #721124;
        --ghb-danger: #e53e3e;
        --ghb-success: #38a169;
        --ghb-light: #f5f5f5;
        --ghb-border: #e5e5e5;
        --ghb-text: #0a0a0a;
        --ghb-text-muted: #666666;
        --ghb-white: #ffffff;
        --ghb-shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
        --ghb-shadow-md: 0 4px 12px rgba(0,0,0,0.1);
        --ghb-shadow-lg: 0 12px 40px rgba(0,0,0,0.12);
        --ghb-radius-sm: 6px;
        --ghb-radius-md: 12px;
        --ghb-radius-lg: 20px;
        --ghb-radius-xl: 28px;
        --ghb-transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ═══════════════════════════════════════════════════════════
       BASE SECTION STYLES
       ═══════════════════════════════════════════════════════════ */
    .ghb-carousel-section {
        position: relative;
        padding: 1rem 0;
        overflow: hidden;
    }

    .ghb-carousel-section__container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Full-width layout */
    .ghb-carousel-section--layout-full-width .ghb-carousel-section__container {
        max-width: 100%;
        padding: 0 40px;
    }

    /* Peek layout - show partial slides on edges */
    .ghb-carousel-section--layout-peek .ghb-carousel-section__container {
        max-width: 100%;
        padding: 0;
    }
    .ghb-carousel-section--layout-peek .ghb-carousel__wrapper {
        padding: 0 5%;
    }

    /* ═══════════════════════════════════════════════════════════
       HEADER STYLES
       ═══════════════════════════════════════════════════════════ */
    .ghb-carousel-section__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .ghb-carousel-section__header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }

    .ghb-carousel-section__header-right {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .ghb-carousel-section__title {
        font-size: clamp(1.4rem, 4vw, 1.75rem);
        font-weight: 700 !important;
        margin: 0;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }

    .ghb-carousel-section__subtitle {
        font-size: 0.9rem;
        color: var(--ghb-text-muted);
        margin: 0.25rem 0 0;
    }

    /* ─────────────────────────────────────────────────────────
       TITLE SIZE VARIATIONS
       ───────────────────────────────────────────────────────── */
    .ghb-carousel-section--title-sm .ghb-carousel-section__title {
        font-size: clamp(1rem, 2.5vw, 1.25rem);
    }
    .ghb-carousel-section--title-md .ghb-carousel-section__title {
        font-size: clamp(1.25rem, 3vw, 1.5rem);
    }
    .ghb-carousel-section--title-lg .ghb-carousel-section__title {
        font-size: clamp(1.5rem, 4vw, 2rem);
    }
    .ghb-carousel-section--title-xl .ghb-carousel-section__title {
        font-size: clamp(1.75rem, 5vw, 2.5rem);
    }
    .ghb-carousel-section--title-xxl .ghb-carousel-section__title {
        font-size: clamp(2rem, 6vw, 3rem);
    }

    /* ─────────────────────────────────────────────────────────
       SUBTITLE SIZE VARIATIONS
       ───────────────────────────────────────────────────────── */
    .ghb-carousel-section--subtitle-sm .ghb-carousel-section__subtitle {
        font-size: 0.8rem;
    }
    .ghb-carousel-section--subtitle-md .ghb-carousel-section__subtitle {
        font-size: 0.95rem;
    }
    .ghb-carousel-section--subtitle-lg .ghb-carousel-section__subtitle {
        font-size: 1.1rem;
    }

    .ghb-carousel-section__link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: var(--ghb-transition);
    }

    .ghb-carousel-section__link:hover {
        gap: 0.75rem;
    }

    .ghb-carousel-section__link svg {
        width: 16px;
        height: 16px;
        transition: transform 0.2s ease;
    }

    .ghb-carousel-section__link:hover svg {
        transform: translateX(3px);
    }

    /* ─────────────────────────────────────────────────────────
       HEADER ALIGN: Center
       ───────────────────────────────────────────────────────── */
    .ghb-carousel-section--header-center .ghb-carousel-section__header {
        flex-direction: column;
        text-align: center;
        justify-content: center;
    }

    .ghb-carousel-section--header-center .ghb-carousel-section__header-left {
        justify-content: center;
        flex: none;
        width: 100%;
    }

    .ghb-carousel-section--header-center .ghb-carousel-section__title {
        text-align: center;
    }

    .ghb-carousel-section--header-center .ghb-carousel-section__subtitle {
        text-align: center;
    }

    .ghb-carousel-section--header-center .ghb-carousel-section__header-right {
        justify-content: center;
        width: 100%;
    }

    .ghb-carousel-section--header-center .ghb-carousel-section__link {
        margin-top: 0.5rem;
    }

    /* Hide link when header is centered (cleaner look) - can be overridden */
    .ghb-carousel-section--header-center.ghb-carousel-section--hide-link .ghb-carousel-section__link {
        display: none;
    }

    /* ═══════════════════════════════════════════════════════════
       STYLE VARIATIONS
       ═══════════════════════════════════════════════════════════ */

    /* Default (Light) */
    .ghb-carousel-section--default {
        background: var(--ghb-white);
    }
    .ghb-carousel-section--default .ghb-carousel-section__title { color: var(--ghb-text); }
    .ghb-carousel-section--default .ghb-carousel-section__link { color: var(--ghb-text); }

    /* Dark */
    .ghb-carousel-section--dark {
        background: var(--ghb-primary);
    }
    .ghb-carousel-section--dark .ghb-carousel-section__title { color: var(--ghb-white); }
    .ghb-carousel-section--dark .ghb-carousel-section__link { color: var(--ghb-accent); }
    .ghb-carousel-section--dark .ghb-product-card { background: #1a1a1a; }
    .ghb-carousel-section--dark .ghb-product-card__title { color: var(--ghb-white); }
    .ghb-carousel-section--dark .ghb-product-card__price { color: var(--ghb-white); }
    .ghb-carousel-section--dark .ghb-carousel__nav-btn {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: var(--ghb-white);
    }
    .ghb-carousel-section--dark .ghb-carousel__nav-btn:hover:not(:disabled) {
        background: var(--ghb-accent);
        border-color: var(--ghb-accent);
        color: var(--ghb-white);
    }
    .ghb-carousel-section--dark .ghb-carousel__pagination .swiper-pagination-bullet { background: rgba(255,255,255,0.3); }
    .ghb-carousel-section--dark .ghb-carousel__pagination .swiper-pagination-bullet-active { background: var(--ghb-accent); }
    .ghb-carousel-section--dark .ghb-carousel__progressbar .swiper-pagination-progressbar-fill { background: var(--ghb-accent); }

    /* Minimal */
    .ghb-carousel-section--minimal {
        background: transparent;
        padding: 2rem 0;
        border-top: 1px solid var(--ghb-border);
    }
    .ghb-carousel-section--minimal .ghb-carousel-section__title {
        font-weight: 600;
        font-size: clamp(1rem, 3vw, 1.25rem);
    }
    .ghb-carousel-section--minimal .ghb-carousel-section__link { color: var(--ghb-text-muted); }
    .ghb-carousel-section--minimal .ghb-carousel__nav-btn {
        background: transparent;
        border-color: var(--ghb-border);
    }

    /* ═══════════════════════════════════════════════════════════
       SWIPER CAROUSEL BASE
       ═══════════════════════════════════════════════════════════ */
    .ghb-carousel {
        position: relative;
    }

    .ghb-carousel__wrapper {
        position: relative;
    }

    .ghb-carousel .swiper {
        overflow: hidden;
        padding: 10px 0;
    }

    .ghb-carousel .swiper-wrapper {
        align-items: stretch;
    }

    .ghb-carousel .swiper-slide {
        height: auto;
    }

    /* Centered layout */
    .ghb-carousel--layout-centered .swiper-slide {
        opacity: 0.5;
        transform: scale(0.9);
        transition: var(--ghb-transition);
    }
    .ghb-carousel--layout-centered .swiper-slide-active {
        opacity: 1;
        transform: scale(1);
    }

    /* ═══════════════════════════════════════════════════════════
       NAVIGATION STYLES
       ═══════════════════════════════════════════════════════════ */

    /* Base Navigation Button */
    .ghb-carousel__nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid var(--ghb-border);
        background: var(--ghb-white);
        color: var(--ghb-text);
        cursor: pointer;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .ghb-carousel__nav-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }

    .ghb-carousel__nav-btn:hover:not(:disabled) {
        background: var(--ghb-accent);
        border-color: var(--ghb-accent);
        color: var(--ghb-white);
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(114,17,36,0.3);
    }

    .ghb-carousel__nav-btn svg {
        stroke-width: 2;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .ghb-carousel__nav-btn:hover:not(:disabled) svg {
        transform: scale(1.15);
    }

    /* Navigation Shapes */
    .ghb-carousel__nav-btn--circle { border-radius: 50%; }
    .ghb-carousel__nav-btn--square { border-radius: var(--ghb-radius-sm); }
    .ghb-carousel__nav-btn--pill { border-radius: 100px; }

    /* Navigation Sizes */
    .ghb-carousel__nav-btn--sm { width: 36px; height: 36px; }
    .ghb-carousel__nav-btn--sm svg { width: 14px; height: 14px; }

    .ghb-carousel__nav-btn--md { width: 44px; height: 44px; }
    .ghb-carousel__nav-btn--md svg { width: 18px; height: 18px; }

    .ghb-carousel__nav-btn--lg { width: 56px; height: 56px; }
    .ghb-carousel__nav-btn--lg svg { width: 22px; height: 22px; }

    /* ─────────────────────────────────────────────────────────
       NAV STYLE: Bottom (arrows flanking pagination)
       ───────────────────────────────────────────────────────── */
    .ghb-carousel__footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    /* ─────────────────────────────────────────────────────────
       NAV STYLE: Sides (floating on left/right)
       ───────────────────────────────────────────────────────── */
    .ghb-carousel--nav-sides .ghb-carousel__wrapper {
        position: relative;
    }

    .ghb-carousel--nav-sides .ghb-carousel__nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        box-shadow: var(--ghb-shadow-md);
    }

    .ghb-carousel--nav-sides .ghb-carousel__nav-prev {
        left: -22px;
    }

    .ghb-carousel--nav-sides .ghb-carousel__nav-next {
        right: -22px;
    }

    @media (max-width: 768px) {
        .ghb-carousel--nav-sides .ghb-carousel__nav-prev { left: 10px; }
        .ghb-carousel--nav-sides .ghb-carousel__nav-next { right: 10px; }
    }

    /* ─────────────────────────────────────────────────────────
       NAV STYLE: Top-Right (in header area)
       ───────────────────────────────────────────────────────── */
    .ghb-carousel-section--nav-top-right .ghb-carousel__header-nav {
        display: flex;
        gap: 0.5rem;
    }

    /* ─────────────────────────────────────────────────────────
       NAV STYLE: Integrated (inside pagination area)
       ───────────────────────────────────────────────────────── */
    .ghb-carousel--nav-integrated .ghb-carousel__footer {
        background: var(--ghb-light);
        padding: 0.75rem 1.5rem;
        border-radius: 100px;
        gap: 1.5rem;
        width: fit-content;
        margin: 1.5rem auto 0;
    }

    /* ═══════════════════════════════════════════════════════════
       PAGINATION STYLES
       ═══════════════════════════════════════════════════════════ */

    /* Base Pagination Container */
    .ghb-carousel__pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-width: 80px;
    }

    /* Dots - Default */
    .ghb-carousel__pagination .swiper-pagination-bullet {
        width: 8px;
        height: 8px;
        background: #ccc;
        opacity: 1;
        border-radius: 50%;
        transition: var(--ghb-transition);
        margin: 0 !important;
    }

    .ghb-carousel__pagination .swiper-pagination-bullet-active {
        background: var(--ghb-primary);
        width: 24px;
        border-radius: 4px;
    }

    /* Dots - Line Style */
    .ghb-carousel__pagination--line .swiper-pagination-bullet {
        width: 20px;
        height: 3px;
        border-radius: 2px;
    }
    .ghb-carousel__pagination--line .swiper-pagination-bullet-active {
        width: 40px;
        background: var(--ghb-primary);
    }

    /* Dots - Dash Style */
    .ghb-carousel__pagination--dash .swiper-pagination-bullet {
        width: 16px;
        height: 4px;
        border-radius: 2px;
    }
    .ghb-carousel__pagination--dash .swiper-pagination-bullet-active {
        width: 32px;
    }

    /* Dots - Dynamic (scale nearby bullets) */
    .ghb-carousel__pagination--dynamic .swiper-pagination-bullet {
        transform: scale(0.7);
    }
    .ghb-carousel__pagination--dynamic .swiper-pagination-bullet-active {
        transform: scale(1);
        width: 10px;
    }
    .ghb-carousel__pagination--dynamic .swiper-pagination-bullet-active-prev,
    .ghb-carousel__pagination--dynamic .swiper-pagination-bullet-active-next {
        transform: scale(0.85);
    }

    /* Fraction Pagination */
    .ghb-carousel__fraction {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--ghb-text);
        min-width: 60px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
    }

    .ghb-carousel__fraction-current {
        font-size: 1.1rem;
    }

    .ghb-carousel__fraction-divider {
        opacity: 0.5;
    }

    .ghb-carousel__fraction-total {
        opacity: 0.7;
    }

    /* Progress Bar Pagination */
    .ghb-carousel__progressbar {
        position: relative;
        width: 100%;
        max-width: 200px;
        height: 3px;
        background: var(--ghb-border);
        border-radius: 2px;
        overflow: hidden;
    }

    .ghb-carousel__progressbar .swiper-pagination-progressbar-fill {
        background: var(--ghb-primary);
        height: 100%;
        border-radius: 2px;
        transition: transform 0.3s ease;
    }

    /* ═══════════════════════════════════════════════════════════
       AUTOPLAY PROGRESS BAR
       ═══════════════════════════════════════════════════════════ */
    .ghb-carousel__autoplay-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--ghb-border);
        z-index: 10;
    }

    .ghb-carousel__autoplay-progress-bar {
        height: 100%;
        background: var(--ghb-accent);
        width: 0%;
        transition: width 0.1s linear;
    }

    /* ═══════════════════════════════════════════════════════════
       PRODUCT CARD STYLES
       ═══════════════════════════════════════════════════════════ */

    /* Base Card */
    .ghb-product-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        background: var(--ghb-white);
        overflow: hidden;
        text-decoration: none;
        transition: var(--ghb-transition);
    }

    /* Card Radius Variations */
    .ghb-product-card--radius-none { border-radius: 0; }
    .ghb-product-card--radius-sm { border-radius: var(--ghb-radius-sm); }
    .ghb-product-card--radius-md { border-radius: var(--ghb-radius-md); }
    .ghb-product-card--radius-lg { border-radius: var(--ghb-radius-lg); }
    .ghb-product-card--radius-xl { border-radius: var(--ghb-radius-xl); }

    /* Card Hover Effects */
    .ghb-product-card--hover-lift:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.10);
    }

    .ghb-product-card--hover-zoom:hover .ghb-product-card__image {
        transform: scale(1.08);
    }

    .ghb-product-card--hover-glow:hover {
        box-shadow: 0 0 0 1px var(--ghb-accent), 0 8px 24px rgba(114, 17, 36, 0.15);
    }

    .ghb-product-card--hover-border:hover {
        border-color: var(--ghb-primary);
    }

    /* ─────────────────────────────────────────────────────────
       CARD STYLE: Default
       ───────────────────────────────────────────────────────── */
    .ghb-product-card--default {
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(0, 0, 0, 0.04);
    }

    .ghb-product-card--default .ghb-product-card__image-wrapper {
        aspect-ratio: 4/3;
        overflow: hidden;
        background: var(--ghb-light);
    }

    .ghb-product-card--default .ghb-product-card__content {
        padding: 1rem;
    }

    /* ─────────────────────────────────────────────────────────
       CARD STYLE: Minimal
       ───────────────────────────────────────────────────────── */
    .ghb-product-card--minimal {
        background: transparent;
    }

    .ghb-product-card--minimal .ghb-product-card__image-wrapper {
        aspect-ratio: 4/3;
        overflow: hidden;
        background: var(--ghb-light);
        border-radius: var(--ghb-radius-md);
    }

    .ghb-product-card--minimal .ghb-product-card__content {
        padding: 0.875rem 0.125rem;
    }

    .ghb-product-card--minimal .ghb-product-card__title {
        font-size: 0.85rem;
    }

    /* ─────────────────────────────────────────────────────────
       CARD STYLE: Overlay (text on image)
       ───────────────────────────────────────────────────────── */
    .ghb-product-card--overlay {
        position: relative;
    }

    .ghb-product-card--overlay .ghb-product-card__image-wrapper {
        aspect-ratio: 3/4;
        overflow: hidden;
    }

    .ghb-product-card--overlay .ghb-product-card__content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 3rem 1rem 1.125rem;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.82) 0%, rgba(0, 0, 0, 0.35) 55%, transparent 100%);
        color: var(--ghb-white);
    }

    .ghb-product-card--overlay .ghb-product-card__title {
        color: var(--ghb-white);
    }

    .ghb-product-card--overlay .ghb-product-card__price {
        color: var(--ghb-white);
    }

    .ghb-product-card--overlay .ghb-product-card__brand {
        color: rgba(255,255,255,0.7);
    }

    /* ─────────────────────────────────────────────────────────
       CARD STYLE: Detailed (with more info)
       ───────────────────────────────────────────────────────── */
    .ghb-product-card--detailed {
        border: 1px solid var(--ghb-border);
    }

    .ghb-product-card--detailed .ghb-product-card__image-wrapper {
        aspect-ratio: 4/3;
        overflow: hidden;
        background: var(--ghb-light);
    }

    .ghb-product-card--detailed .ghb-product-card__content {
        padding: 1rem;
        border-top: 1px solid var(--ghb-border);
    }

    .ghb-product-card--detailed .ghb-product-card__meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--ghb-border);
    }

    /* ─────────────────────────────────────────────────────────
       CARD STYLE: Horizontal
       ───────────────────────────────────────────────────────── */
    .ghb-product-card--horizontal {
        flex-direction: row;
        border: 1px solid var(--ghb-border);
    }

    .ghb-product-card--horizontal .ghb-product-card__image-wrapper {
        width: 40%;
        aspect-ratio: 4/3;
        flex-shrink: 0;
    }

    .ghb-product-card--horizontal .ghb-product-card__content {
        padding: 1rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* ═══════════════════════════════════════════════════════════
       CARD COMPONENTS
       ═══════════════════════════════════════════════════════════ */
    .ghb-product-card__image-wrapper {
        position: relative;
        overflow: hidden;
        background: var(--ghb-light);
    }

    .ghb-product-card__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    /* Badges */
    .ghb-product-card__badges {
        position: absolute;
        top: 10px;
        left: 10px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        z-index: 2;
    }

    .ghb-product-card__badge {
        display: inline-block;
        padding: 4px 10px;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        border-radius: 4px;
        line-height: 1.3;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .ghb-product-card__badge--sale { background: var(--ghb-danger); color: var(--ghb-white); }
    .ghb-product-card__badge--new { background: var(--ghb-primary); color: var(--ghb-white); }
    .ghb-product-card__badge--featured { background: var(--ghb-accent); color: var(--ghb-white); }
    .ghb-product-card__badge--out { background: rgba(113, 128, 150, 0.9); color: var(--ghb-white); }
    .ghb-product-card__badge--discount { background: var(--ghb-success); color: var(--ghb-white); }

    /* Quick Actions */
    .ghb-product-card__actions {
        position: absolute;
        bottom: 10px;
        left: 10px;
        right: 10px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transform: translateY(8px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 2;
    }

    .ghb-product-card:hover .ghb-product-card__actions {
        opacity: 1;
        transform: translateY(0);
    }

    .ghb-product-card__action-btn {
        flex: 1;
        padding: 11px 16px;
        background: rgba(10, 10, 10, 0.92);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        color: var(--ghb-white);
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        border: none;
        border-radius: var(--ghb-radius-sm);
        cursor: pointer;
        transition: var(--ghb-transition);
        text-align: center;
    }

    .ghb-product-card__action-btn:hover {
        background: var(--ghb-accent);
        color: var(--ghb-white);
    }

    .ghb-product-card__action-btn--icon {
        flex: 0;
        width: 40px;
        padding: 10px;
    }

    /* Quick View Button */
    .ghb-quick-view-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 3;
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #333;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0;
        transform: translateY(4px);
        pointer-events: none;
    }
    .ghb-product-card:hover .ghb-quick-view-btn {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    @media (hover: none) {
        .ghb-quick-view-btn {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
    }
    .ghb-quick-view-btn:hover {
        background: var(--ghb-accent, #721124);
        color: #fff;
        box-shadow: 0 4px 16px rgba(114, 17, 36, 0.25);
    }
    .ghb-quick-view-btn:active {
        transform: scale(0.92);
    }

    /* Content */
    .ghb-product-card__content {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .ghb-product-card__brand {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--ghb-text-muted);
        margin-bottom: 6px;
    }

    .ghb-product-card__title {
        font-size: 0.9rem;
        font-weight: 600 !important;
        color: var(--ghb-text);
        margin: 0 0 auto;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .ghb-product-card__rating {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 0.5rem;
    }

    .ghb-product-card__stars {
        display: flex;
        gap: 1px;
    }

    .ghb-product-card__star {
        width: 13px;
        height: 13px;
        fill: #e0e0e0;
    }

    .ghb-product-card__star--filled {
        fill: #f59e0b;
    }

    .ghb-product-card__rating-count {
        font-size: 0.7rem;
        color: var(--ghb-text-muted);
        margin-left: 2px;
    }

    .ghb-product-card__price-wrapper {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-top: 0.75rem;
        flex-wrap: wrap;
    }

    .ghb-product-card__price {
        font-size: 1rem;
        font-weight: 700;
        color: var(--ghb-text);
    }

    .ghb-product-card__price--sale {
        color: var(--ghb-danger);
        font-weight: 700;
    }

    .ghb-product-card__price--regular {
        font-size: 0.8rem;
        font-weight: 400;
        color: #a0a0a0;
        text-decoration: line-through;
    }

    .ghb-product-card__discount-tag {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--ghb-white);
        background: var(--ghb-danger);
        padding: 2px 7px;
        border-radius: 3px;
        letter-spacing: 0.02em;
    }

    /* Sizes Preview */
    .ghb-product-card__sizes {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 0.5rem;
    }

    .ghb-product-card__size {
        font-size: 0.65rem;
        font-weight: 500;
        padding: 3px 7px;
        background: var(--ghb-light);
        border: 1px solid var(--ghb-border);
        border-radius: 4px;
        color: var(--ghb-text-muted);
    }

    /* Add to Cart Button */
    .ghb-product-card__cart-btn {
        margin-top: 0.75rem;
        padding: 11px;
        background: var(--ghb-primary);
        color: var(--ghb-white);
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        border: none;
        border-radius: var(--ghb-radius-sm);
        cursor: pointer;
        transition: var(--ghb-transition);
        width: 100%;
    }

    .ghb-product-card__cart-btn:hover {
        background: var(--ghb-accent);
        color: var(--ghb-white);
    }

    .ghb-product-card__cart-btn:active {
        transform: scale(0.98);
    }

    /* ─────────────────────────────────────────────────────────
       CARD TEXT: Centered (bigger & centered)
       Higher specificity to ensure it overrides other styles
       ───────────────────────────────────────────────────────── */
    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__content {
        text-align: center !important;
        align-items: center !important;
        width: 100% !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__brand {
        font-size: 0.8rem !important;
        text-align: center !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__title {
        font-size: 1.15rem !important;
        font-weight: 700 !important;
        text-align: center !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__price {
        font-size: 1.25rem !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__price--sale {
        font-size: 1.25rem !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__price--regular {
        font-size: 1rem !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__price-wrapper {
        justify-content: center !important;
        width: 100% !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__sizes {
        justify-content: center !important;
    }

    .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__rating {
        justify-content: center !important;
    }

    @media (max-width: 768px) {
        .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__title {
            font-size: 1rem !important;
        }
        .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__price,
        .ghb-carousel-section .ghb-product-card.ghb-product-card--text-centered .ghb-product-card__price--sale {
            font-size: 1.1rem !important;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       EFFECT-SPECIFIC STYLES
       ═══════════════════════════════════════════════════════════ */

    /* Coverflow Effect */
    .ghb-carousel--effect-coverflow .swiper {
        padding: 40px 0;
    }

    /* ═══════════════════════════════════════════════════════════
       GAP VARIATIONS
       ═══════════════════════════════════════════════════════════ */
    .ghb-carousel--gap-none .swiper-slide { padding: 0; }
    .ghb-carousel--gap-sm .swiper { --swiper-spacing: 8px; }
    .ghb-carousel--gap-md .swiper { --swiper-spacing: 16px; }
    .ghb-carousel--gap-lg .swiper { --swiper-spacing: 24px; }
    .ghb-carousel--gap-xl .swiper { --swiper-spacing: 32px; }

    /* ═══════════════════════════════════════════════════════════
       SCROLLBAR (Optional)
       ═══════════════════════════════════════════════════════════ */
    .ghb-carousel__scrollbar {
        margin-top: 1rem;
        height: 4px;
        background: var(--ghb-border);
        border-radius: 2px;
    }

    .ghb-carousel__scrollbar .swiper-scrollbar-drag {
        background: var(--ghb-primary);
        border-radius: 2px;
    }

    /* ═══════════════════════════════════════════════════════════
       RESPONSIVE
       ═══════════════════════════════════════════════════════════ */
    @media (max-width: 768px) {
        .ghb-carousel-section {
            padding: 0.75rem 0;
        }

        .ghb-carousel-section__container {
            padding: 0 16px;
        }

        .ghb-carousel-section__header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .ghb-carousel-section__header-right {
            width: 100%;
            justify-content: space-between;
        }

        /* KEY FIX: generous content padding on mobile product cards */
        .ghb-product-card__content {
            padding: 0.875rem 0.75rem 1rem;
        }

        .ghb-product-card__title {
            font-size: 0.85rem;
            line-height: 1.35;
        }

        .ghb-product-card__price {
            font-size: 0.95rem;
        }

        .ghb-product-card__price--sale {
            font-size: 0.95rem;
        }

        .ghb-product-card__price--regular {
            font-size: 0.75rem;
        }

        .ghb-product-card__brand {
            font-size: 0.65rem;
            margin-bottom: 4px;
        }

        .ghb-product-card__discount-tag {
            font-size: 0.6rem;
            padding: 2px 5px;
        }

        .ghb-product-card__price-wrapper {
            gap: 6px;
            margin-top: 0.5rem;
        }

        .ghb-product-card__rating {
            margin-top: 0.35rem;
        }

        .ghb-product-card__star {
            width: 11px;
            height: 11px;
        }

        .ghb-product-card__badge {
            font-size: 0.58rem;
            padding: 3px 7px;
        }

        .ghb-product-card__sizes {
            margin-top: 0.4rem;
            gap: 3px;
        }

        .ghb-product-card__size {
            font-size: 0.6rem;
            padding: 2px 5px;
        }

        .ghb-product-card__cart-btn {
            padding: 9px;
            font-size: 0.68rem;
            margin-top: 0.5rem;
        }

        /* Keep same aspect ratio on mobile to avoid side-cropping */
        .ghb-product-card--default .ghb-product-card__image-wrapper,
        .ghb-product-card--minimal .ghb-product-card__image-wrapper {
            aspect-ratio: 4/3;
        }

        /* Overlay cards: keep 3/4 portrait on mobile */
        .ghb-product-card--overlay .ghb-product-card__image-wrapper {
            aspect-ratio: 3/4;
        }

        .ghb-product-card--overlay .ghb-product-card__content {
            padding: 2.5rem 0.875rem 0.875rem;
        }

        .ghb-product-card--overlay .ghb-product-card__title {
            font-size: 0.85rem;
            font-weight: 700;
        }

        .ghb-product-card--overlay .ghb-product-card__price {
            font-size: 1rem;
        }

        .ghb-product-card__actions {
            display: none;
        }

        .ghb-carousel__footer {
            gap: 0.75rem;
            margin-top: 1rem;
        }

        /* Smaller side nav on mobile */
        .ghb-carousel--nav-sides .ghb-carousel__nav-btn {
            width: 34px;
            height: 34px;
        }

        .ghb-carousel--nav-sides .ghb-carousel__nav-btn svg {
            width: 14px;
            height: 14px;
        }

        /* Detailed card adjustments */
        .ghb-product-card--detailed .ghb-product-card__content {
            padding: 0.75rem;
        }

        /* Horizontal card collapses on mobile */
        .ghb-product-card--horizontal {
            flex-direction: column;
        }

        .ghb-product-card--horizontal .ghb-product-card__image-wrapper {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .ghb-carousel-section__title {
            font-size: 1.1rem;
        }

        .ghb-carousel-section__subtitle {
            font-size: 0.8rem;
        }

        /* Tighter but still readable cards */
        .ghb-product-card__content {
            padding: 0.75rem 0.625rem 0.875rem;
        }

        .ghb-product-card__title {
            font-size: 0.8rem;
            line-height: 1.3;
        }

        .ghb-product-card__price {
            font-size: 0.9rem;
        }

        .ghb-product-card__brand {
            font-size: 0.6rem;
        }

        /* Overlay stays impactful on small screens */
        .ghb-product-card--overlay .ghb-product-card__image-wrapper {
            aspect-ratio: 3/4;
        }

        .ghb-product-card--overlay .ghb-product-card__content {
            padding: 2rem 0.75rem 0.75rem;
        }
    }
    ';
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 3. MAIN SHORTCODE
 * ═══════════════════════════════════════════════════════════════
 */
add_shortcode('carousel_section', 'ghb_carousel_section_shortcode');
add_shortcode('product_carousel', 'ghb_carousel_section_shortcode'); // Alias

function ghb_carousel_section_shortcode($atts) {
    // Check WooCommerce
    if (!class_exists('WooCommerce')) {
        return '<p style="text-align:center;padding:2rem;color:#666;">WooCommerce is required for the product carousel.</p>';
    }

    // Enqueue assets only when the shortcode is actually rendered
    ghb_enqueue_carousel_assets();

    $atts = shortcode_atts(array(
        // Section settings
        'title'          => '',
        'subtitle'       => '',
        'link'           => '',
        'link_text'      => 'Vedi Tutti',
        'style'          => 'default',      // default|dark|minimal
        'header_align'   => 'left',         // left|center
        'title_size'     => '',             // sm|md|lg|xl|xxl (empty = default)
        'subtitle_size'  => '',             // sm|md|lg (empty = default)

        // Navigation
        'nav_style'      => 'bottom',       // bottom|sides|top-right|integrated|none
        'nav_shape'      => 'circle',       // circle|square|pill
        'nav_size'       => 'md',           // sm|md|lg
        'show_nav'       => 'true',

        // Pagination
        'pagination'     => 'dots',         // dots|fraction|progressbar|none
        'dots_style'     => 'default',      // default|line|dash|dynamic

        // Effect
        'effect'         => 'slide',        // slide|fade|coverflow

        // Card style
        'card_style'     => 'default',      // default|minimal|overlay|detailed|horizontal
        'card_hover'     => 'lift',         // lift|zoom|glow|border|none
        'card_radius'    => 'md',           // none|sm|md|lg|xl
        'card_text'      => 'default',      // default|centered (centered = bigger & centered text)

        // Layout
        'layout'         => 'standard',     // standard|centered|peek|full-width
        'columns'        => 5,              // Desktop columns
        'columns_tablet' => 3,
        'columns_mobile' => 2,
        'gap'            => 'md',           // none|sm|md|lg|xl
        'rows'           => 1,

        // Behavior
        'autoplay'       => 'false',
        'speed'          => 4000,
        'loop'           => 'true',
        'free_mode'      => 'false',
        'mousewheel'     => 'false',
        'keyboard'       => 'true',
        'grab_cursor'    => 'true',
        'autoplay_bar'   => 'false',

        // Product query
        'type'           => 'recent',
        'limit'          => 8,
        'category'       => '',
        'tag'            => '',
        'brand'          => '',
        'ids'            => '',

        // Card content
        'show_brand'     => 'true',
        'show_sizes'     => 'false',
        'show_badges'    => 'true',
        'show_rating'    => 'false',
        'show_cart_btn'  => 'true',
        'show_discount'  => 'true',
        'show_quick_view' => 'true',
        'show_details_btn' => 'true',
    ), $atts);

    $carousel_id = 'ghb_carousel_' . uniqid();
    $products = ghb_get_carousel_products($atts);

    if (!$products->have_posts()) {
        return '<p style="text-align:center;padding:2rem;color:#666;">Nessun prodotto trovato.</p>';
    }

    $total_products = $products->post_count;

    // Build CSS classes - nav style class added to section for top-right targeting
    $section_classes = array(
        'ghb-carousel-section',
        'ghb-carousel-section--' . esc_attr($atts['style']),
        'ghb-carousel-section--layout-' . esc_attr($atts['layout']),
        'ghb-carousel-section--nav-' . esc_attr($atts['nav_style']),
    );

    // Header alignment
    if ($atts['header_align'] === 'center') {
        $section_classes[] = 'ghb-carousel-section--header-center';
    }

    // Title size
    if (!empty($atts['title_size'])) {
        $section_classes[] = 'ghb-carousel-section--title-' . esc_attr($atts['title_size']);
    }

    // Subtitle size
    if (!empty($atts['subtitle_size'])) {
        $section_classes[] = 'ghb-carousel-section--subtitle-' . esc_attr($atts['subtitle_size']);
    }

    $carousel_classes = array(
        'ghb-carousel',
        'ghb-carousel--nav-' . esc_attr($atts['nav_style']),
        'ghb-carousel--effect-' . esc_attr($atts['effect']),
        'ghb-carousel--gap-' . esc_attr($atts['gap']),
    );

    if ($atts['layout'] === 'centered') {
        $carousel_classes[] = 'ghb-carousel--layout-centered';
    }

    // Navigation button classes
    $nav_btn_classes = array(
        'ghb-carousel__nav-btn',
        'ghb-carousel__nav-btn--' . esc_attr($atts['nav_shape']),
        'ghb-carousel__nav-btn--' . esc_attr($atts['nav_size']),
    );
    $nav_btn_class = implode(' ', $nav_btn_classes);

    ob_start();
    ?>
    <section class="<?php echo esc_attr(implode(' ', $section_classes)); ?>" data-section-id="<?php echo esc_attr($carousel_id); ?>">
        <div class="ghb-carousel-section__container">

            <?php
            $has_header = !empty($atts['title']) || !empty($atts['subtitle']) || !empty($atts['link']) || ($atts['nav_style'] === 'top-right' && $atts['show_nav'] === 'true');
            if ($has_header) : ?>
            <!-- Header -->
            <div class="ghb-carousel-section__header">
                <div class="ghb-carousel-section__header-left">
                    <div>
                        <?php if (!empty($atts['title'])) : ?>
                            <h2 class="ghb-carousel-section__title"><?php echo esc_html($atts['title']); ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($atts['subtitle'])) : ?>
                            <p class="ghb-carousel-section__subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ghb-carousel-section__header-right">
                    <?php if ($atts['nav_style'] === 'top-right' && $atts['show_nav'] === 'true') : ?>
                        <div class="ghb-carousel__header-nav">
                            <button type="button" class="<?php echo esc_attr($nav_btn_class); ?> ghb-carousel__nav-prev" aria-label="Previous">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button type="button" class="<?php echo esc_attr($nav_btn_class); ?> ghb-carousel__nav-next" aria-label="Next">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($atts['link'])) : ?>
                        <a href="<?php echo esc_url($atts['link']); ?>" class="ghb-carousel-section__link">
                            <?php echo esc_html($atts['link_text']); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Carousel -->
            <div class="<?php echo esc_attr(implode(' ', $carousel_classes)); ?>" data-carousel-id="<?php echo esc_attr($carousel_id); ?>">
                <div class="ghb-carousel__wrapper">

                    <?php if ($atts['nav_style'] === 'sides' && $atts['show_nav'] === 'true') : ?>
                        <button type="button" class="<?php echo esc_attr($nav_btn_class); ?> ghb-carousel__nav-prev" aria-label="Previous">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                    <?php endif; ?>

                    <div id="<?php echo esc_attr($carousel_id); ?>" class="swiper">
                        <div class="swiper-wrapper">
                            <?php
                            while ($products->have_posts()) : $products->the_post();
                                global $product;
                                echo '<div class="swiper-slide">';
                                echo ghb_render_product_card($product, $atts);
                                echo '</div>';
                            endwhile;
                            ?>
                        </div>

                        <?php if ($atts['autoplay'] === 'true' && $atts['autoplay_bar'] === 'true') : ?>
                            <div class="ghb-carousel__autoplay-progress">
                                <div class="ghb-carousel__autoplay-progress-bar"></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($atts['nav_style'] === 'sides' && $atts['show_nav'] === 'true') : ?>
                        <button type="button" class="<?php echo esc_attr($nav_btn_class); ?> ghb-carousel__nav-next" aria-label="Next">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    <?php endif; ?>

                </div>

                <!-- Footer Navigation (for bottom/integrated styles) -->
                <?php if (in_array($atts['nav_style'], array('bottom', 'integrated')) || ($atts['pagination'] !== 'none' && $atts['nav_style'] !== 'top-right')) : ?>
                    <div class="ghb-carousel__footer">

                        <?php if (in_array($atts['nav_style'], array('bottom', 'integrated')) && $atts['show_nav'] === 'true') : ?>
                            <button type="button" class="<?php echo esc_attr($nav_btn_class); ?> ghb-carousel__nav-prev" aria-label="Previous">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                        <?php endif; ?>

                        <?php if ($atts['pagination'] === 'dots') : ?>
                            <div class="ghb-carousel__pagination ghb-carousel__pagination--<?php echo esc_attr($atts['dots_style']); ?>"></div>
                        <?php elseif ($atts['pagination'] === 'fraction') : ?>
                            <div class="ghb-carousel__fraction">
                                <span class="ghb-carousel__fraction-current">1</span>
                                <span class="ghb-carousel__fraction-divider">/</span>
                                <span class="ghb-carousel__fraction-total"><?php echo esc_html($total_products); ?></span>
                            </div>
                        <?php elseif ($atts['pagination'] === 'progressbar') : ?>
                            <div class="ghb-carousel__progressbar"></div>
                        <?php endif; ?>

                        <?php if (in_array($atts['nav_style'], array('bottom', 'integrated')) && $atts['show_nav'] === 'true') : ?>
                            <button type="button" class="<?php echo esc_attr($nav_btn_class); ?> ghb-carousel__nav-next" aria-label="Next">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

            </div>

        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php echo ghb_generate_swiper_config($atts, $carousel_id, $total_products); ?>
    });
    </script>

    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 4. SWIPER CONFIGURATION GENERATOR
 * ═══════════════════════════════════════════════════════════════
 */
function ghb_generate_swiper_config($atts, $carousel_id, $total_products) {
    $config = array();

    // Base config
    $config['slidesPerView'] = intval($atts['columns_mobile']);
    $config['spaceBetween'] = ghb_get_gap_value($atts['gap']);
    $config['loop'] = $atts['loop'] === 'true';
    $config['grabCursor'] = $atts['grab_cursor'] === 'true';
    $config['keyboard'] = array('enabled' => $atts['keyboard'] === 'true');

    // Grid (multi-row)
    if (intval($atts['rows']) > 1) {
        $config['grid'] = array(
            'rows' => intval($atts['rows']),
            'fill' => 'row'
        );
    }

    // Effect
    $effect = $atts['effect'];
    if ($effect !== 'slide') {
        $config['effect'] = $effect;

        switch ($effect) {
            case 'fade':
                $config['fadeEffect'] = array('crossFade' => true);
                break;
            case 'coverflow':
                $config['coverflowEffect'] = array(
                    'rotate'       => 30,
                    'stretch'      => 0,
                    'depth'        => 100,
                    'modifier'     => 1,
                    'slideShadows' => true
                );
                $config['centeredSlides'] = true;
                break;
        }
    }

    // Layout: centered
    if ($atts['layout'] === 'centered') {
        $config['centeredSlides'] = true;
        $config['slidesPerView'] = 'auto';
    }

    // Autoplay
    if ($atts['autoplay'] === 'true') {
        $config['autoplay'] = array(
            'delay'                => intval($atts['speed']),
            'disableOnInteraction' => false,
            'pauseOnMouseEnter'    => true
        );
    }

    // Free mode
    if ($atts['free_mode'] === 'true') {
        $config['freeMode'] = array(
            'enabled'  => true,
            'sticky'   => false,
            'momentum' => true
        );
    }

    // Mousewheel
    if ($atts['mousewheel'] === 'true') {
        $config['mousewheel'] = array(
            'forceToAxis' => true,
            'sensitivity' => 1
        );
    }

    // Navigation - always use unique section ID to avoid conflicts with multiple carousels
    if ($atts['show_nav'] === 'true' && $atts['nav_style'] !== 'none') {
        // Use section ID for all nav styles to ensure uniqueness
        $section_selector = "[data-section-id=\"{$carousel_id}\"]";
        $config['navigation'] = array(
            'nextEl' => "{$section_selector} .ghb-carousel__nav-next",
            'prevEl' => "{$section_selector} .ghb-carousel__nav-prev"
        );
    }

    // Pagination - use section selector for consistency
    $section_selector = "[data-section-id=\"{$carousel_id}\"]";
    if ($atts['pagination'] === 'dots') {
        $config['pagination'] = array(
            'el'             => "{$section_selector} .ghb-carousel__pagination",
            'clickable'      => true,
            'dynamicBullets' => $atts['dots_style'] === 'dynamic'
        );
    } elseif ($atts['pagination'] === 'progressbar') {
        $config['pagination'] = array(
            'el'   => "{$section_selector} .ghb-carousel__progressbar",
            'type' => 'progressbar'
        );
    }

    // Breakpoints
    $config['breakpoints'] = array(
        0 => array(
            'slidesPerView' => intval($atts['columns_mobile']),
            'spaceBetween'  => max(8, ghb_get_gap_value($atts['gap']) - 8)
        ),
        640 => array(
            'slidesPerView' => intval($atts['columns_mobile']),
            'spaceBetween'  => ghb_get_gap_value($atts['gap'])
        ),
        768 => array(
            'slidesPerView' => intval($atts['columns_tablet']),
            'spaceBetween'  => ghb_get_gap_value($atts['gap'])
        ),
        1024 => array(
            'slidesPerView' => intval($atts['columns']) - 1,
            'spaceBetween'  => ghb_get_gap_value($atts['gap'])
        ),
        1280 => array(
            'slidesPerView' => intval($atts['columns']),
            'spaceBetween'  => ghb_get_gap_value($atts['gap'])
        )
    );

    // Generate JavaScript
    $json_config = wp_json_encode($config, JSON_UNESCAPED_SLASHES);
    $js = "var swiper_{$carousel_id} = new Swiper('#{$carousel_id}', {$json_config});";

    // Fraction pagination update
    if ($atts['pagination'] === 'fraction') {
        $js .= "
        swiper_{$carousel_id}.on('slideChange', function() {
            var section = document.querySelector('[data-section-id=\"{$carousel_id}\"]');
            if (section) {
                var current = section.querySelector('.ghb-carousel__fraction-current');
                if (current) current.textContent = this.realIndex + 1;
            }
        });";
    }

    // Autoplay progress bar
    if ($atts['autoplay'] === 'true' && $atts['autoplay_bar'] === 'true') {
        $js .= "
        swiper_{$carousel_id}.on('autoplayTimeLeft', function(s, time, progress) {
            var section = document.querySelector('[data-section-id=\"{$carousel_id}\"]');
            if (section) {
                var bar = section.querySelector('.ghb-carousel__autoplay-progress-bar');
                if (bar) bar.style.width = ((1 - progress) * 100) + '%';
            }
        });";
    }

    return $js;
}

/**
 * Helper: Get gap pixel value
 */
function ghb_get_gap_value($gap) {
    $gaps = array(
        'none' => 0,
        'sm'   => 8,
        'md'   => 16,
        'lg'   => 24,
        'xl'   => 32
    );
    return isset($gaps[$gap]) ? $gaps[$gap] : 16;
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 5. PRODUCT QUERY BUILDER
 * ═══════════════════════════════════════════════════════════════
 */
function ghb_get_carousel_products($atts) {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['limit']),
        'post_status'    => 'publish',
    );

    $tax_query = array();

    $tax_query[] = array(
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => 'exclude-from-catalog',
        'operator' => 'NOT IN',
    );

    switch ($atts['type']) {
        case 'best_selling':
            $args['meta_key'] = 'total_sales';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'featured':
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'featured',
            );
            $args['orderby'] = 'menu_order title';
            $args['order']   = 'ASC';
            break;
        case 'sale':
            $args['meta_query'][] = array(
                'key'     => '_sale_price',
                'value'   => '',
                'compare' => '!=',
            );
            break;
        case 'top_rated':
            $args['meta_key'] = '_wc_average_rating';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'recent':
        default:
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
            break;
    }

    // When filtering by category, use menu_order to preserve the manual
    // sort order from WooCommerce admin (matching the category page).
    // Only skip if an explicit metric-based sort (best_selling, top_rated) is active.
    if ((!empty($atts['category']) || !empty($atts['brand'])) && !in_array($atts['type'], array('best_selling', 'top_rated'), true)) {
        $args['orderby'] = 'menu_order title';
        $args['order']   = 'ASC';
    }

    if (!empty($atts['category'])) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => array_map('trim', explode(',', $atts['category'])),
        );
    }

    if (!empty($atts['tag'])) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => array_map('trim', explode(',', $atts['tag'])),
        );
    }

    if (!empty($atts['brand'])) {
        // Resolve the brand slug(s) to terms in WHICHEVER brand taxonomy holds
        // them (a site can have several), instead of only querying the first
        // registered one. Match across all of them with OR + include_children,
        // so nested/child brand terms (e.g. nike-travis-scott) work too.
        $brand_slugs = array_filter(array_map('trim', explode(',', $atts['brand'])));
        $brand_taxonomies = array('product_brand', 'pwb-brand', 'pa_brand');

        $brand_clauses = array();
        foreach ($brand_taxonomies as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $ids = array();
            foreach ($brand_slugs as $slug) {
                $term = get_term_by('slug', $slug, $tax);
                if ($term && !is_wp_error($term)) {
                    $ids[] = (int) $term->term_id;
                }
            }
            if (!empty($ids)) {
                $brand_clauses[] = array(
                    'taxonomy'         => $tax,
                    'field'            => 'term_id',
                    'terms'            => array_unique($ids),
                    'include_children' => true,
                );
            }
        }

        if (count($brand_clauses) === 1) {
            $tax_query[] = $brand_clauses[0];
        } elseif (count($brand_clauses) > 1) {
            $tax_query[] = array_merge(array('relation' => 'OR'), $brand_clauses);
        } else {
            // Slug didn't resolve in any brand taxonomy — fall back to the
            // original slug query against the first existing one.
            foreach ($brand_taxonomies as $tax) {
                if (taxonomy_exists($tax)) {
                    $tax_query[] = array(
                        'taxonomy' => $tax,
                        'field'    => 'slug',
                        'terms'    => $brand_slugs,
                    );
                    break;
                }
            }
        }
    }

    if (!empty($tax_query)) {
        $args['tax_query'] = array_merge(array('relation' => 'AND'), $tax_query);
    }

    if (!empty($atts['ids'])) {
        $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
        $args['orderby']  = 'post__in';
    }

    return new WP_Query($args);
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 6. PRODUCT CARD RENDERER
 * ═══════════════════════════════════════════════════════════════
 */
function ghb_render_product_card($product, $atts) {
    $product_id = $product->get_id();
    $permalink  = get_permalink($product_id);
    $title      = $product->get_name();
    $image_id   = $product->get_image_id();
    $image_url  = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src();
    $image_alt  = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $title;

    // Card classes
    $card_classes = array(
        'ghb-product-card',
        'ghb-product-card--' . $atts['card_style'],
        'ghb-product-card--radius-' . $atts['card_radius'],
        'ghb-product-card--hover-' . $atts['card_hover'],
        'ghb-product-card--text-' . $atts['card_text'],
    );

    // Brand
    $brand = '';
    if ($atts['show_brand'] === 'true') {
        // Support multiple brand taxonomies
        $brand_taxonomies = array('pa_brand', 'pwb-brand', 'product_brand');
        foreach ($brand_taxonomies as $tax) {
            $brand_terms = get_the_terms($product_id, $tax);
            if ($brand_terms && !is_wp_error($brand_terms)) {
                $brand = $brand_terms[0]->name;
                break;
            }
        }
    }

    // Prices
    $is_on_sale    = $product->is_on_sale();
    $regular_price = '';
    $sale_price    = '';
    $discount_pct  = 0;

    if ($product->is_type('variable')) {
        $prices = $product->get_variation_prices(true);
        if (!empty($prices['regular_price']) && !empty($prices['sale_price'])) {
            // Calculate per-variation discount and use the maximum real discount.
            // The old approach (max regular vs min sale) mixed different variations
            // and produced inflated/impossible percentages (e.g. -70% when real is -20%).
            $best_discount = 0;
            $best_regular  = 0;
            $best_sale     = 0;
            foreach ($prices['regular_price'] as $vid => $reg) {
                $sal = $prices['sale_price'][$vid] ?? $reg;
                if ($reg > 0 && $sal > 0 && $sal < $reg) {
                    $pct = round((($reg - $sal) / $reg) * 100);
                    if ($pct > $best_discount) {
                        $best_discount = $pct;
                        $best_regular  = $reg;
                        $best_sale     = $sal;
                    }
                }
            }
            if ($best_discount > 0) {
                $regular_price = $best_regular;
                $sale_price    = $best_sale;
                $discount_pct  = $best_discount;
            }
        }
    } else {
        $regular_price = $product->get_regular_price();
        $sale_price    = $product->get_sale_price();
        if ($is_on_sale && $regular_price && $sale_price) {
            $discount_pct = round((($regular_price - $sale_price) / $regular_price) * 100);
        }
    }

    // Badges
    $badges = array();
    if ($atts['show_badges'] === 'true') {
        if (!$product->is_in_stock()) {
            $badges = array(array('type' => 'out', 'text' => 'Esaurito'));
        } else {
            if ($is_on_sale && $discount_pct > 0) {
                $badges[] = array('type' => 'sale', 'text' => "-{$discount_pct}%");
            }

            $post_date = get_the_date('U', $product_id);
            if ((time() - $post_date) < (30 * DAY_IN_SECONDS)) {
                $badges[] = array('type' => 'new', 'text' => 'New');
            }

            if ($product->is_featured()) {
                $badges[] = array('type' => 'featured', 'text' => 'Featured');
            }
        }
    }

    // Rating
    $rating       = 0;
    $rating_count = 0;
    if ($atts['show_rating'] === 'true') {
        $rating       = $product->get_average_rating();
        $rating_count = $product->get_rating_count();
    }

    // Sizes
    $sizes       = array();
    $total_sizes = 0;
    if ($atts['show_sizes'] === 'true' && $product->is_type('variable')) {
        $size_attribute = $product->get_attribute('pa_misura') ?: $product->get_attribute('pa_size') ?: $product->get_attribute('pa_taglia');
        if ($size_attribute) {
            $all_sizes   = explode(', ', $size_attribute);
            $sizes       = array_slice($all_sizes, 0, 4);
            $total_sizes = count($all_sizes);
        }
    }

    ob_start();
    ?>
    <a href="<?php echo esc_url($permalink); ?>" class="<?php echo esc_attr(implode(' ', $card_classes)); ?>">

        <div class="ghb-product-card__image-wrapper">
            <img
                src="<?php echo esc_url($image_url); ?>"
                alt="<?php echo esc_attr($image_alt); ?>"
                class="ghb-product-card__image"
                loading="lazy"
            />

            <?php if (!empty($badges)) : ?>
                <div class="ghb-product-card__badges">
                    <?php foreach ($badges as $badge) : ?>
                        <span class="ghb-product-card__badge ghb-product-card__badge--<?php echo esc_attr($badge['type']); ?>">
                            <?php echo esc_html($badge['text']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($atts['show_quick_view'] === 'true') : ?>
                <button type="button" class="ghb-quick-view-btn" data-product-id="<?php echo esc_attr($product_id); ?>" aria-label="Quick View">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </button>
            <?php endif; ?>

            <?php if ($atts['card_style'] !== 'overlay' && $atts['show_details_btn'] === 'true') : ?>
                <div class="ghb-product-card__actions">
                    <span class="ghb-product-card__action-btn">Vedi Dettagli</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="ghb-product-card__content">

            <?php if ($brand) : ?>
                <span class="ghb-product-card__brand"><?php echo esc_html($brand); ?></span>
            <?php endif; ?>

            <h3 class="ghb-product-card__title"><?php echo esc_html($title); ?></h3>

            <?php if ($atts['show_rating'] === 'true' && $rating > 0) : ?>
                <div class="ghb-product-card__rating">
                    <div class="ghb-product-card__stars">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <svg class="ghb-product-card__star <?php echo $i <= round($rating) ? 'ghb-product-card__star--filled' : ''; ?>" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span class="ghb-product-card__rating-count">(<?php echo esc_html($rating_count); ?>)</span>
                </div>
            <?php endif; ?>

            <div class="ghb-product-card__price-wrapper">
                <?php if ($is_on_sale && $sale_price && !$product->is_type('variable')) : ?>
                    <span class="ghb-product-card__price ghb-product-card__price--sale">
                        <?php echo wc_price($sale_price); ?>
                    </span>
                    <span class="ghb-product-card__price ghb-product-card__price--regular">
                        <?php echo wc_price($regular_price); ?>
                    </span>
                    <?php if ($atts['show_discount'] === 'true' && $discount_pct > 0) : ?>
                        <span class="ghb-product-card__discount-tag">-<?php echo esc_html($discount_pct); ?>%</span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="ghb-product-card__price">
                        <?php echo $product->get_price_html(); ?>
                    </span>
                    <?php if ($is_on_sale && $atts['show_discount'] === 'true' && $discount_pct > 0) : ?>
                        <span class="ghb-product-card__discount-tag">-<?php echo esc_html($discount_pct); ?>%</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($sizes)) : ?>
                <div class="ghb-product-card__sizes">
                    <?php foreach ($sizes as $size) : ?>
                        <span class="ghb-product-card__size"><?php echo esc_html($size); ?></span>
                    <?php endforeach; ?>
                    <?php if ($total_sizes > 4) : ?>
                        <span class="ghb-product-card__size">+<?php echo ($total_sizes - 4); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($atts['show_cart_btn'] === 'true' && $product->is_in_stock()) : ?>
                <?php if ($product->is_type('variable')) : ?>
                    <button type="button" class="ghb-product-card__cart-btn ghb-quick-add-btn" data-product-id="<?php echo esc_attr($product_id); ?>">
                        Aggiungi al Carrello
                    </button>
                <?php else : ?>
                    <button type="button" class="ghb-product-card__cart-btn ghb-simple-add-btn" data-product-id="<?php echo esc_attr($product_id); ?>">
                        Aggiungi al Carrello
                    </button>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </a>
    <?php
    return ob_get_clean();
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 7. CONVENIENCE SHORTCODES
 * ═══════════════════════════════════════════════════════════════
 */

// Best Sellers
add_shortcode('bestsellers', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'Best Sellers', 'style' => 'default'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"best_selling\" limit=\"{$atts['limit']}\" style=\"{$atts['style']}\" link=\"/shop?orderby=popularity\"]");
});

// New Arrivals
add_shortcode('new_arrivals', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'Nuovi Arrivi', 'style' => 'default'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"recent\" limit=\"{$atts['limit']}\" style=\"{$atts['style']}\" link=\"/shop?orderby=date\"]");
});

// On Sale
add_shortcode('on_sale', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'In Saldo', 'style' => 'default'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"sale\" limit=\"{$atts['limit']}\" style=\"{$atts['style']}\" link=\"/shop?on_sale=1\"]");
});

// Featured
add_shortcode('featured_products', function($atts) {
    $atts = shortcode_atts(array('limit' => 8, 'title' => 'In Evidenza', 'effect' => 'coverflow'), $atts);
    return do_shortcode("[carousel_section title=\"{$atts['title']}\" type=\"featured\" limit=\"{$atts['limit']}\" effect=\"{$atts['effect']}\" nav_style=\"sides\" link=\"/shop\"]");
});


/**
 * ═══════════════════════════════════════════════════════════════
 * 9. QUICK ADD TO CART - AJAX HANDLERS
 * ═══════════════════════════════════════════════════════════════
 */

// AJAX: get variations for variable products
add_action('wp_ajax_ghb_get_variations', 'ghb_get_variations_handler');
add_action('wp_ajax_nopriv_ghb_get_variations', 'ghb_get_variations_handler');

function ghb_get_variations_handler() {
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error('Prodotto non trovato');
    }

    $attributes = [];
    foreach ($product->get_variation_attributes() as $attr_name => $options) {
        $label = wc_attribute_label($attr_name, $product);
        $attributes[] = [
            'name'    => $attr_name,
            'label'   => $label,
            'options' => array_values($options),
        ];
    }

    $variations = [];
    foreach ($product->get_available_variations() as $v) {
        $variations[] = [
            'variation_id' => $v['variation_id'],
            'attributes'   => $v['attributes'],
            'price_html'   => html_entity_decode(strip_tags($v['price_html'])),
            'is_in_stock'  => $v['is_in_stock'],
            'image'        => $v['image']['thumb_src'] ?? '',
        ];
    }

    wp_send_json_success([
        'title'      => $product->get_name(),
        'price'      => html_entity_decode(strip_tags($product->get_price_html())),
        'image'      => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
        'attributes' => $attributes,
        'variations' => $variations,
    ]);
}

// AJAX: add to cart (variable + simple)
add_action('wp_ajax_ghb_add_to_cart', 'ghb_add_to_cart_handler');
add_action('wp_ajax_nopriv_ghb_add_to_cart', 'ghb_add_to_cart_handler');

function ghb_add_to_cart_handler() {
    $product_id   = intval($_POST['product_id'] ?? 0);
    $variation_id = intval($_POST['variation_id'] ?? 0);
    $quantity     = max(1, intval($_POST['quantity'] ?? 1));

    if (!$product_id) {
        wp_send_json_error('Dati mancanti');
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Prodotto non trovato');
    }

    if ($product->is_type('variable')) {
        if (!$variation_id) {
            wp_send_json_error('Seleziona una variante');
        }
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            wp_send_json_error('Variante non trovata');
        }
        $attributes = $variation->get_variation_attributes();
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $attributes);
    } else {
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
    }

    if ($cart_item_key) {
        wp_send_json_success([
            'message'    => 'Prodotto aggiunto al carrello!',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total(),
            'cart_url'   => wc_get_cart_url(),
        ]);
    } else {
        wp_send_json_error('Impossibile aggiungere al carrello');
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 9b. AJAX: Quick View product data
 * ═══════════════════════════════════════════════════════════════
 */
add_action('wp_ajax_ghb_quick_view', 'ghb_quick_view_handler');
add_action('wp_ajax_nopriv_ghb_quick_view', 'ghb_quick_view_handler');

function ghb_quick_view_handler() {
    $product_id = intval($_GET['product_id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error('Prodotto non trovato');
    }

    // Images
    $images = [];
    $main_img = wp_get_attachment_image_url($product->get_image_id(), 'medium_large');
    if ($main_img) $images[] = $main_img;

    $gallery_ids = $product->get_gallery_image_ids();
    foreach (array_slice($gallery_ids, 0, 4) as $gid) {
        $url = wp_get_attachment_image_url($gid, 'medium_large');
        if ($url) $images[] = $url;
    }

    // Attributes
    $attributes = [];
    foreach ($product->get_attributes() as $attr) {
        $attributes[] = [
            'label' => wc_attribute_label($attr->get_name()),
            'value' => $product->get_attribute($attr->get_name()),
        ];
    }

    // Categories
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

    wp_send_json_success([
        'title'       => $product->get_name(),
        'url'         => get_permalink($product_id),
        'price'       => html_entity_decode(strip_tags($product->get_price_html())),
        'short_desc'  => wpautop($product->get_short_description()),
        'images'      => $images,
        'in_stock'    => $product->is_in_stock(),
        'stock_text'  => $product->is_in_stock() ? 'Disponibile' : 'Esaurito',
        'attributes'  => $attributes,
        'categories'  => implode(', ', is_array($categories) ? $categories : []),
        'sku'         => $product->get_sku(),
    ]);
}

/**
 * ═══════════════════════════════════════════════════════════════
 * 10. QUICK ADD TO CART - FRONTEND (MODAL + JS + CSS)
 * ═══════════════════════════════════════════════════════════════
 */
add_action('wp_footer', 'ghb_quick_add_to_cart_frontend');

function ghb_quick_add_to_cart_frontend() {
    // Only load if WooCommerce is active AND a carousel was rendered on this page
    if (!class_exists('WooCommerce')) return;
    if (!wp_style_is('ghb-carousel-styles', 'enqueued')) return;
    ?>
    <style>
        /* ═══════════════════════════════════════════════════════════
           GHB Quick Add Modal Styles
           ═══════════════════════════════════════════════════════════ */

        /* Overlay */
        .ghb-qa-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 99998;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .ghb-qa-overlay.active { display: block; }

        /* Modal */
        .ghb-qa-modal {
            display: none;
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            max-height: 85vh;
            overflow-y: auto;
            background: var(--ghb-white, #fff);
            border-radius: 20px 20px 0 0;
            z-index: 99999;
            box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.15);
            animation: ghb-qa-slide-up 0.3s ease;
        }
        .ghb-qa-modal.active { display: block; }

        @keyframes ghb-qa-slide-up {
            from { transform: translateX(-50%) translateY(100%); }
            to { transform: translateX(-50%) translateY(0); }
        }

        /* Handle bar */
        .ghb-qa-handle {
            width: 40px;
            height: 4px;
            background: #ddd;
            border-radius: 4px;
            margin: 10px auto 0;
        }

        .ghb-qa-close {
            position: absolute;
            top: 10px;
            right: 14px;
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #999;
            line-height: 1;
            transition: color 0.2s;
        }
        .ghb-qa-close:hover { color: var(--ghb-accent, #721124); }

        /* Product header */
        .ghb-qa-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 20px 20px 12px;
        }
        .ghb-qa-header img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 12px;
            background: var(--ghb-light, #f5f5f5);
            flex-shrink: 0;
        }
        .ghb-qa-header-info { min-width: 0; }
        .ghb-qa-title {
            font-size: 15px;
            font-weight: 700;
            color: #222;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ghb-qa-price {
            font-size: 17px;
            font-weight: 700;
            color: var(--ghb-accent, #721124);
            margin-top: 2px;
        }

        /* Attribute selectors */
        .ghb-qa-attributes {
            padding: 0 20px;
        }
        .ghb-qa-attr-group {
            margin-bottom: 16px;
        }
        .ghb-qa-attr-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .ghb-qa-attr-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ghb-qa-attr-option {
            padding: 8px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fff;
            color: #333;
            user-select: none;
            text-align: center;
            min-width: 44px;
        }
        .ghb-qa-attr-option:hover {
            border-color: var(--ghb-accent, #721124);
            color: var(--ghb-accent, #721124);
            background: rgba(114, 17, 36, 0.04);
        }
        .ghb-qa-attr-option.selected {
            border-color: var(--ghb-accent, #721124);
            background: var(--ghb-accent, #721124);
            color: #fff;
            font-weight: 600;
        }
        .ghb-qa-attr-option.unavailable {
            opacity: 0.25;
            cursor: not-allowed;
            text-decoration: line-through;
            pointer-events: none;
        }

        /* Quantity */
        .ghb-qa-quantity-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px;
            margin-bottom: 16px;
        }
        .ghb-qa-qty-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ghb-qa-qty-wrap {
            display: flex;
            align-items: center;
            border: 1.5px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
        }
        .ghb-qa-qty-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: #f9f9f9;
            cursor: pointer;
            font-size: 18px;
            color: #555;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ghb-qa-qty-btn:hover {
            background: var(--ghb-accent, #721124);
            color: #fff;
        }
        .ghb-qa-qty-value {
            width: 40px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            border: none;
            outline: none;
            color: #333;
        }

        /* Add to cart button */
        .ghb-qa-footer {
            padding: 16px 20px 24px;
        }
        .ghb-qa-add-to-cart {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #d4d4d4;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            cursor: not-allowed;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
        }
        .ghb-qa-add-to-cart.ready {
            background: var(--ghb-accent, #721124);
            cursor: pointer;
            pointer-events: auto;
        }
        .ghb-qa-add-to-cart.ready:hover {
            background: var(--ghb-accent-dark, #520c1a);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(114, 17, 36, 0.25);
        }
        .ghb-qa-add-to-cart.ready:active {
            transform: translateY(0);
        }
        .ghb-qa-add-to-cart.adding {
            background: #999;
            pointer-events: none;
        }
        .ghb-qa-add-to-cart.added {
            background: var(--ghb-success, #38a169);
            pointer-events: none;
        }

        /* Error */
        .ghb-qa-error {
            font-size: 12px;
            color: #c62828;
            text-align: center;
            padding: 0 20px 8px;
            display: none;
        }
        .ghb-qa-error.visible { display: block; }

        /* Loading */
        .ghb-qa-loading {
            padding: 40px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }

        /* Simple product adding state on the card button */
        .ghb-simple-add-btn.adding {
            opacity: 0.6;
            pointer-events: none;
        }
        .ghb-simple-add-btn.added {
            background: var(--ghb-success, #38a169) !important;
            color: #fff !important;
            pointer-events: none;
        }

        /* Desktop: center modal */
        @media (min-width: 641px) {
            .ghb-qa-modal {
                bottom: auto;
                top: 50%;
                transform: translate(-50%, -50%);
                border-radius: 20px;
                animation: ghb-qa-fade-in 0.25s ease;
            }
            @keyframes ghb-qa-fade-in {
                from { opacity: 0; transform: translate(-50%, -48%); }
                to { opacity: 1; transform: translate(-50%, -50%); }
            }
        }
    </style>

    <!-- GHB Quick Add Modal -->
    <div class="ghb-qa-overlay"></div>
    <div class="ghb-qa-modal">
        <div class="ghb-qa-handle"></div>
        <button class="ghb-qa-close" aria-label="Chiudi">&times;</button>
        <div class="ghb-qa-content"></div>
    </div>

    <script>
    (function() {
        var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
        var overlay = document.querySelector('.ghb-qa-overlay');
        var modal = document.querySelector('.ghb-qa-modal');
        var content = modal ? modal.querySelector('.ghb-qa-content') : null;
        var currentVariations = [];
        var selectedAttrs = {};
        var matchedVariation = null;

        if (!overlay || !modal || !content) return;

        function closeModal() {
            overlay.classList.remove('active');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        overlay.addEventListener('click', closeModal);
        modal.querySelector('.ghb-qa-close').addEventListener('click', closeModal);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // ─── Simple product: direct AJAX add to cart ───
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-simple-add-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var productId = btn.getAttribute('data-product-id');
            var originalText = btn.textContent;
            btn.classList.add('adding');
            btn.textContent = 'Aggiunta...';

            var formData = new FormData();
            formData.append('action', 'ghb_add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (response.success) {
                        btn.classList.remove('adding');
                        btn.classList.add('added');
                        btn.textContent = 'Aggiunto! (' + response.data.cart_count + ')';
                        // Trigger WooCommerce cart fragment refresh
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                        setTimeout(function() {
                            btn.classList.remove('added');
                            btn.textContent = originalText;
                        }, 2000);
                    } else {
                        btn.classList.remove('adding');
                        btn.textContent = 'Errore - Riprova';
                        setTimeout(function() { btn.textContent = originalText; }, 2000);
                    }
                })
                .catch(function() {
                    btn.classList.remove('adding');
                    btn.textContent = 'Errore - Riprova';
                    setTimeout(function() { btn.textContent = originalText; }, 2000);
                });
        });

        // ─── Variable product: open modal ───
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-quick-add-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var productId = btn.getAttribute('data-product-id');
            selectedAttrs = {};
            matchedVariation = null;
            content.innerHTML = '<div class="ghb-qa-loading">Caricamento varianti...</div>';
            overlay.classList.add('active');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            fetch(ajaxUrl + '?action=ghb_get_variations&product_id=' + encodeURIComponent(productId))
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (!response.success) {
                        content.innerHTML = '<div class="ghb-qa-loading">Prodotto non trovato</div>';
                        return;
                    }
                    renderModal(response.data, productId);
                })
                .catch(function() {
                    content.innerHTML = '<div class="ghb-qa-loading">Errore di caricamento</div>';
                });
        });

        function renderModal(data, productId) {
            currentVariations = data.variations;
            selectedAttrs = {};

            var html = '';

            // Header
            html += '<div class="ghb-qa-header">';
            html += '<img src="' + escHtml(data.image) + '" alt="' + escHtml(data.title) + '">';
            html += '<div class="ghb-qa-header-info">';
            html += '<div class="ghb-qa-title">' + escHtml(data.title) + '</div>';
            html += '<div class="ghb-qa-price">' + data.price + '</div>';
            html += '</div></div>';

            // Attributes
            html += '<div class="ghb-qa-attributes">';
            data.attributes.forEach(function(attr) {
                html += '<div class="ghb-qa-attr-group" data-attr="' + escHtml(attr.name) + '">';
                html += '<div class="ghb-qa-attr-label">' + escHtml(attr.label) + '</div>';
                html += '<div class="ghb-qa-attr-options">';
                attr.options.forEach(function(opt) {
                    html += '<div class="ghb-qa-attr-option" data-attr="' + escHtml(attr.name) + '" data-value="' + escHtml(opt) + '">' + escHtml(opt) + '</div>';
                });
                html += '</div></div>';
            });
            html += '</div>';

            // Quantity
            html += '<div class="ghb-qa-quantity-row">';
            html += '<span class="ghb-qa-qty-label">Quantit&agrave;</span>';
            html += '<div class="ghb-qa-qty-wrap">';
            html += '<button type="button" class="ghb-qa-qty-btn ghb-qa-qty-minus">&minus;</button>';
            html += '<input type="text" class="ghb-qa-qty-value" value="1" readonly>';
            html += '<button type="button" class="ghb-qa-qty-btn ghb-qa-qty-plus">+</button>';
            html += '</div></div>';

            // Error + ATC
            html += '<div class="ghb-qa-error">Seleziona tutte le opzioni</div>';
            html += '<div class="ghb-qa-footer">';
            html += '<button type="button" class="ghb-qa-add-to-cart" data-product-id="' + escHtml(productId) + '">Seleziona le opzioni</button>';
            html += '</div>';

            content.innerHTML = html;
            updateAvailability();
        }

        // Attribute selection
        document.addEventListener('click', function(e) {
            var option = e.target.closest('.ghb-qa-attr-option:not(.unavailable)');
            if (!option || !modal.contains(option)) return;

            var attrName = option.getAttribute('data-attr');
            var val = option.getAttribute('data-value');

            // Toggle
            if (selectedAttrs[attrName] === val) {
                delete selectedAttrs[attrName];
                option.classList.remove('selected');
            } else {
                selectedAttrs[attrName] = val;
                var siblings = option.parentElement.querySelectorAll('.ghb-qa-attr-option');
                siblings.forEach(function(s) { s.classList.remove('selected'); });
                option.classList.add('selected');
            }

            updateAvailability();
            updateButton();
        });

        function updateAvailability() {
            var groups = modal.querySelectorAll('.ghb-qa-attr-group');
            groups.forEach(function(group) {
                var groupAttr = group.getAttribute('data-attr');
                var options = group.querySelectorAll('.ghb-qa-attr-option');

                options.forEach(function(opt) {
                    var optValue = opt.getAttribute('data-value');
                    var testAttrs = Object.assign({}, selectedAttrs);
                    testAttrs[groupAttr] = optValue;

                    var possible = currentVariations.some(function(v) {
                        return Object.keys(testAttrs).every(function(key) {
                            var vKey = 'attribute_' + key;
                            return !v.attributes[vKey] || v.attributes[vKey] === testAttrs[key];
                        }) && v.is_in_stock;
                    });

                    if (possible) {
                        opt.classList.remove('unavailable');
                    } else {
                        opt.classList.add('unavailable');
                    }
                });
            });
        }

        function updateButton() {
            var btn = modal.querySelector('.ghb-qa-add-to-cart');
            var errorEl = modal.querySelector('.ghb-qa-error');
            if (!btn) return;

            var totalAttrs = modal.querySelectorAll('.ghb-qa-attr-group').length;
            var selectedCount = Object.keys(selectedAttrs).length;

            if (selectedCount < totalAttrs) {
                btn.classList.remove('ready');
                btn.textContent = 'Seleziona le opzioni';
                if (errorEl) errorEl.classList.remove('visible');
                matchedVariation = null;
                return;
            }

            // Find matching variation
            matchedVariation = currentVariations.find(function(v) {
                return Object.keys(selectedAttrs).every(function(key) {
                    var vKey = 'attribute_' + key;
                    return !v.attributes[vKey] || v.attributes[vKey] === selectedAttrs[key];
                });
            }) || null;

            if (matchedVariation && matchedVariation.is_in_stock) {
                var priceText = matchedVariation.price_html ? ' \u2013 ' + matchedVariation.price_html : '';
                btn.classList.add('ready');
                btn.textContent = 'Aggiungi al Carrello' + priceText;
                if (errorEl) errorEl.classList.remove('visible');

                if (matchedVariation.image) {
                    var img = modal.querySelector('.ghb-qa-header img');
                    if (img) img.src = matchedVariation.image;
                }
            } else if (matchedVariation && !matchedVariation.is_in_stock) {
                btn.classList.remove('ready');
                btn.textContent = 'Esaurito';
                if (errorEl) errorEl.classList.remove('visible');
            } else {
                btn.classList.remove('ready');
                btn.textContent = 'Combinazione non disponibile';
                if (errorEl) errorEl.classList.add('visible');
            }
        }

        // Quantity controls
        document.addEventListener('click', function(e) {
            if (e.target.closest('.ghb-qa-qty-minus')) {
                var input = e.target.closest('.ghb-qa-qty-wrap').querySelector('.ghb-qa-qty-value');
                var val = parseInt(input.value);
                if (val > 1) input.value = val - 1;
            }
            if (e.target.closest('.ghb-qa-qty-plus')) {
                var input = e.target.closest('.ghb-qa-qty-wrap').querySelector('.ghb-qa-qty-value');
                var val = parseInt(input.value);
                input.value = val + 1;
            }
        });

        // Add to cart from modal
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-qa-add-to-cart.ready');
            if (!btn || !modal.contains(btn)) return;

            var productId = btn.getAttribute('data-product-id');
            var qtyInput = modal.querySelector('.ghb-qa-qty-value');
            var quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

            if (!matchedVariation) return;

            btn.classList.remove('ready');
            btn.classList.add('adding');
            btn.textContent = 'Aggiunta in corso...';

            var formData = new FormData();
            formData.append('action', 'ghb_add_to_cart');
            formData.append('product_id', productId);
            formData.append('variation_id', matchedVariation.variation_id);
            formData.append('quantity', quantity);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (response.success) {
                        btn.classList.remove('adding');
                        btn.classList.add('added');
                        btn.textContent = 'Aggiunto! (' + response.data.cart_count + ' nel carrello)';
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                        setTimeout(closeModal, 1500);
                    } else {
                        btn.classList.remove('adding');
                        btn.classList.add('ready');
                        btn.textContent = 'Errore \u2013 Riprova';
                    }
                })
                .catch(function() {
                    btn.classList.remove('adding');
                    btn.classList.add('ready');
                    btn.textContent = 'Errore \u2013 Riprova';
                });
        });

        function escHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    })();
    </script>

    <!-- ═══ GHB Quick View Modal ═══ -->
    <style>
        .ghb-qv-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 99998;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .ghb-qv-overlay.active { display: block; }

        .ghb-qv-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 95%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 16px;
            z-index: 99999;
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.2);
        }
        .ghb-qv-modal.active { display: block; }

        .ghb-qv-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            z-index: 2;
            transition: color 0.2s;
            line-height: 1;
        }
        .ghb-qv-close:hover { color: var(--ghb-accent, #721124); }

        .ghb-qv-body {
            display: flex;
            gap: 0;
        }

        /* Gallery */
        .ghb-qv-gallery {
            flex: 1;
            min-width: 0;
            background: #f9f9f9;
            border-radius: 16px 0 0 16px;
            overflow: hidden;
        }
        .ghb-qv-main-img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
        }
        .ghb-qv-thumbs {
            display: flex;
            gap: 6px;
            padding: 8px;
            overflow-x: auto;
        }
        .ghb-qv-thumb {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
            flex-shrink: 0;
        }
        .ghb-qv-thumb:hover,
        .ghb-qv-thumb.active {
            border-color: var(--ghb-accent, #721124);
        }

        /* Info */
        .ghb-qv-info {
            flex: 1;
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .ghb-qv-cats {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ghb-qv-title {
            font-size: 20px;
            font-weight: 700;
            color: #222;
            line-height: 1.3;
        }
        .ghb-qv-price {
            font-size: 22px;
            font-weight: 700;
            color: var(--ghb-accent, #721124);
        }
        .ghb-qv-stock {
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            width: fit-content;
        }
        .ghb-qv-stock.in-stock {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .ghb-qv-stock.out-of-stock {
            background: #fbe9e7;
            color: #c62828;
        }
        .ghb-qv-desc {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
        .ghb-qv-desc p { margin: 0 0 8px; }
        .ghb-qv-attrs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .ghb-qv-attr {
            font-size: 12px;
            padding: 4px 10px;
            background: #f5f5f5;
            border-radius: 6px;
            color: #555;
        }
        .ghb-qv-attr strong {
            color: #333;
        }
        .ghb-qv-sku {
            font-size: 11px;
            color: #bbb;
        }
        .ghb-qv-view-full {
            display: inline-block;
            margin-top: auto;
            padding: 12px 24px;
            background: var(--ghb-accent, #721124);
            color: #fff;
            text-decoration: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            transition: background 0.2s;
        }
        .ghb-qv-view-full:hover {
            background: var(--ghb-accent-dark, #5a0d1d);
            color: #fff;
        }

        .ghb-qv-loading {
            padding: 60px;
            text-align: center;
            color: #999;
            font-size: 14px;
            width: 100%;
        }

        @media (max-width: 640px) {
            .ghb-qv-body { flex-direction: column; }
            .ghb-qv-gallery { border-radius: 16px 16px 0 0; }
            .ghb-qv-info { padding: 20px 16px; }
            .ghb-qv-title { font-size: 18px; }
        }
    </style>

    <div class="ghb-qv-overlay"></div>
    <div class="ghb-qv-modal">
        <button class="ghb-qv-close" aria-label="Chiudi">&#10005;</button>
        <div class="ghb-qv-content"></div>
    </div>

    <script>
    (function() {
        var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
        var qvOverlay = document.querySelector('.ghb-qv-overlay');
        var qvModal = document.querySelector('.ghb-qv-modal');
        var qvContent = qvModal ? qvModal.querySelector('.ghb-qv-content') : null;

        if (!qvOverlay || !qvModal || !qvContent) return;

        function closeQvModal() {
            qvOverlay.classList.remove('active');
            qvModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        qvOverlay.addEventListener('click', closeQvModal);
        qvModal.querySelector('.ghb-qv-close').addEventListener('click', closeQvModal);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && qvModal.classList.contains('active')) closeQvModal();
        });

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-quick-view-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var productId = btn.getAttribute('data-product-id');
            qvContent.innerHTML = '<div class="ghb-qv-loading">Caricamento...</div>';
            qvOverlay.classList.add('active');
            qvModal.classList.add('active');
            document.body.style.overflow = 'hidden';

            fetch(ajaxUrl + '?action=ghb_quick_view&product_id=' + encodeURIComponent(productId))
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (!response.success) {
                        qvContent.innerHTML = '<div class="ghb-qv-loading">Prodotto non trovato</div>';
                        return;
                    }
                    var p = response.data;
                    var html = '<div class="ghb-qv-body">';

                    // Gallery
                    html += '<div class="ghb-qv-gallery">';
                    if (p.images.length) {
                        html += '<img src="' + esc(p.images[0]) + '" class="ghb-qv-main-img" alt="' + esc(p.title) + '">';
                        if (p.images.length > 1) {
                            html += '<div class="ghb-qv-thumbs">';
                            p.images.forEach(function(img, i) {
                                html += '<img src="' + esc(img) + '" class="ghb-qv-thumb' + (i === 0 ? ' active' : '') + '" data-src="' + esc(img) + '">';
                            });
                            html += '</div>';
                        }
                    }
                    html += '</div>';

                    // Info
                    html += '<div class="ghb-qv-info">';
                    if (p.categories) html += '<div class="ghb-qv-cats">' + esc(p.categories) + '</div>';
                    html += '<div class="ghb-qv-title">' + esc(p.title) + '</div>';
                    html += '<div class="ghb-qv-price">' + esc(p.price) + '</div>';
                    html += '<div class="ghb-qv-stock ' + (p.in_stock ? 'in-stock' : 'out-of-stock') + '">' + esc(p.stock_text) + '</div>';
                    if (p.short_desc) html += '<div class="ghb-qv-desc">' + p.short_desc + '</div>';

                    if (p.attributes && p.attributes.length) {
                        html += '<div class="ghb-qv-attrs">';
                        p.attributes.forEach(function(a) {
                            html += '<span class="ghb-qv-attr"><strong>' + esc(a.label) + ':</strong> ' + esc(a.value) + '</span>';
                        });
                        html += '</div>';
                    }

                    if (p.sku) html += '<div class="ghb-qv-sku">SKU: ' + esc(p.sku) + '</div>';
                    html += '<a href="' + esc(p.url) + '" class="ghb-qv-view-full">Vedi Prodotto Completo</a>';
                    html += '</div></div>';

                    qvContent.innerHTML = html;
                })
                .catch(function() {
                    qvContent.innerHTML = '<div class="ghb-qv-loading">Errore di caricamento</div>';
                });
        });

        // Thumbnail click
        document.addEventListener('click', function(e) {
            var thumb = e.target.closest('.ghb-qv-thumb');
            if (!thumb || !qvModal.contains(thumb)) return;
            var src = thumb.getAttribute('data-src');
            var siblings = thumb.parentElement.querySelectorAll('.ghb-qv-thumb');
            siblings.forEach(function(s) { s.classList.remove('active'); });
            thumb.classList.add('active');
            thumb.closest('.ghb-qv-gallery').querySelector('.ghb-qv-main-img').src = src;
        });

        function esc(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    })();
    </script>
    <?php
}
