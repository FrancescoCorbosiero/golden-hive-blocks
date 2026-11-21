<?php
/**
 * Golden Hive — the shop filters' facets, pure core.
 *
 * Plain PHP, no WordPress call: which facets the filters panel shows, in what
 * order and under what name, from the store's setting (Customizer → Golden
 * Hive → Prodotti e filtri → Filtri, read in filters.php) and the product
 * attributes the store actually has. Unit-tested in tests/filters-test.php.
 *
 * A setting row is {key, label, on}: key is price, cat, stock or an
 * attribute's taxonomy (pa_*). Nothing saved: price, category, stock and
 * every attribute, in WooCommerce's order, named as in WooCommerce. The size
 * attribute always gets the size chips and matches in-stock variations.
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The facets that aren't attributes: key => name. */
const GHB_FILTERS_BASE = array('price' => 'Prezzo', 'cat' => 'Categoria', 'stock' => 'Disponibilità');

/**
 * The panel as it was configured in code before 5.20. The one-time move saves
 * the part of it a store has (filters.php), so its filters stay as they were.
 */
const GHB_FILTERS_ROWS_BEFORE_520 = array(
    array('key' => 'price', 'label' => 'Prezzo'),
    array('key' => 'cat', 'label' => 'Categoria'),
    array('key' => 'stock', 'label' => 'Stato'),
    array('key' => 'pa_modello', 'label' => 'Filtra per Modello'),
    array('key' => 'pa_taglia', 'label' => 'Filtra per Taglia'),
    array('key' => 'pa_anno', 'label' => 'Filtra per Anno'),
    array('key' => 'pa_marca', 'label' => 'Filtra per Marca'),
    array('key' => 'pa_colorway', 'label' => 'Filtra per Colorway'),
);

/** Every facet on, in order, named as in the shop: what a store gets before it chooses. */
function ghb_filters_default_rows(array $attributes): array
{
    $rows = array();
    foreach (GHB_FILTERS_BASE as $key => $name) {
        $rows[] = array('key' => $key, 'label' => $name, 'on' => true);
    }
    foreach ($attributes as $taxonomy => $name) {
        $rows[] = array('key' => (string) $taxonomy, 'label' => (string) $name, 'on' => true);
    }
    return $rows;
}

/** The pre-5.20 panel, limited to what this store has: the one-time move's value. */
function ghb_filters_rows_before_520(array $attributes): array
{
    $rows = array();
    foreach (GHB_FILTERS_ROWS_BEFORE_520 as $row) {
        if (isset(GHB_FILTERS_BASE[$row['key']]) || isset($attributes[$row['key']])) {
            $rows[] = $row + array('on' => true);
        }
    }
    return $rows;
}

/**
 * Setting rows, cleaned: known keys only (the base facets and the store's
 * attributes), each once, a label of at most 60 characters (empty: the
 * facet's own name), on as a boolean.
 */
function ghb_filters_clean_rows($raw, array $attributes): array
{
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    $rows = array();
    $seen = array();
    foreach (is_array($raw) ? $raw : array() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = (string) ($row['key'] ?? '');
        if (isset($seen[$key]) || (!isset(GHB_FILTERS_BASE[$key]) && !isset($attributes[$key]))) {
            continue;
        }
        $seen[$key] = true;
        $label      = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($row['label'] ?? ''))));
        $on         = $row['on'] ?? false;
        $rows[]     = array(
            'key'   => $key,
            'label' => function_exists('mb_substr') ? mb_substr($label, 0, 60) : substr($label, 0, 60),
            'on'    => true === $on || 1 === $on || '1' === $on || 'yes' === $on || 'true' === $on,
        );
    }
    return $rows;
}

/**
 * The panel's facets, as ghb_filters_config's 'facets': the rows that are on,
 * in their order. $rows null: nothing saved (every facet). $attributes:
 * taxonomy => name, only the store's own. $size_taxonomy: the size attribute.
 */
function ghb_filters_facets(?array $rows, array $attributes, string $size_taxonomy): array
{
    $rows   = null === $rows ? ghb_filters_default_rows($attributes) : ghb_filters_clean_rows($rows, $attributes);
    $facets = array();
    foreach ($rows as $row) {
        if (!$row['on']) {
            continue;
        }
        $key = $row['key'];
        switch ($key) {
            case 'price':
                $facets['price'] = array('type' => 'price', 'label' => '' !== $row['label'] ? $row['label'] : GHB_FILTERS_BASE['price'], 'chip' => GHB_FILTERS_BASE['price']);
                break;
            case 'cat':
                $facets['cat'] = array('type' => 'category', 'label' => '' !== $row['label'] ? $row['label'] : GHB_FILTERS_BASE['cat'], 'chip' => GHB_FILTERS_BASE['cat']);
                break;
            case 'stock':
                $facets['stock'] = array('type' => 'stock', 'label' => '' !== $row['label'] ? $row['label'] : GHB_FILTERS_BASE['stock'], 'chip' => '', 'text' => 'Disponibile');
                break;
            default:
                $name  = (string) $attributes[$key];
                $facet = array(
                    'type'        => 'attribute',
                    'taxonomy'    => $key,
                    'label'       => '' !== $row['label'] ? $row['label'] : $name,
                    'chip'        => $name,
                    'collapsible' => true,
                );
                if ($key === $size_taxonomy) {
                    $facet['variation'] = true;
                    $facet['ui']        = 'chips';
                }
                // The facet's key is its URL suffix: filter_taglia for pa_taglia.
                $facets[0 === strpos($key, 'pa_') ? substr($key, 3) : $key] = $facet;
        }
    }
    return $facets;
}
