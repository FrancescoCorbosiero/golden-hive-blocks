<?php
/**
 * FAQ Schema Block - Render lato server
 */

$title = $attributes['title'] ?? 'Domande Frequenti';
$subtitle = $attributes['subtitle'] ?? '';
$items = $attributes['items'] ?? [];
$allow_multiple = $attributes['allowMultiple'] ?? false;

if (empty($items)) {
    return;
}

// Genera JSON-LD Schema
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => []
];

foreach ($items as $item) {
    if (!empty($item['question']) && !empty($item['answer'])) {
        $schema['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => wp_strip_all_tags($item['answer'])
            ]
        ];
    }
}

// Unique per-block ID prefix so multiple FAQ blocks on the same page never
// emit duplicate answer IDs (aria-controls stays in sync below).
$faq_uid = wp_unique_id('gh-faq-');
?>
<section <?php echo get_block_wrapper_attributes(array('class' => 'gh-block gh-faq')); ?> data-gh-faq<?php echo $allow_multiple ? ' data-gh-faq-multiple="true"' : ''; ?>>
    <div class="gh-section-header gh-faq__header">
        <h2 class="gh-section-title gh-faq__title"><?php echo esc_html($title); ?></h2>
        <?php if (!empty($subtitle)) : ?>
            <p class="gh-faq__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </div>

    <div class="gh-faq__list">
        <?php foreach ($items as $index => $item) : ?>
            <?php if (!empty($item['question']) && !empty($item['answer'])) : ?>
                <div class="gh-faq__item" data-gh-faq-item data-gh-reveal="up" data-gh-reveal-delay="<?php echo $index * 50; ?>" style="--gh-reveal-delay: <?php echo $index * 50; ?>ms">
                    <button class="gh-faq__trigger"
                            data-gh-faq-trigger
                            aria-expanded="false"
                            aria-controls="<?php echo esc_attr($faq_uid); ?>-answer-<?php echo $index; ?>">
                        <span class="gh-faq__question"><?php echo esc_html($item['question']); ?></span>
                        <span class="gh-faq__icon" aria-hidden="true"></span>
                    </button>
                    <div class="gh-faq__content" data-gh-faq-content id="<?php echo esc_attr($faq_uid); ?>-answer-<?php echo $index; ?>">
                        <div class="gh-faq__answer"><?php echo wp_kses_post($item['answer']); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<script type="application/ld+json"><?php echo wp_json_encode($schema); ?></script>
