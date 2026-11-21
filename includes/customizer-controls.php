<?php
/**
 * Golden Hive — the two Customizer controls WordPress lacks.
 *
 * Loaded by includes/customizer.php when the Customizer starts (the class they
 * extend exists only then); their behaviour is js/customizer-controls.js.
 *
 *   GHB_Customize_List_Control       rows of fields kept as one setting, an
 *                                    array of objects: the trust badges, the
 *                                    «Acquista con fiducia» tiles, the shop
 *                                    filters. Fixed rows (the filters) can be
 *                                    switched, renamed and moved, not added or
 *                                    removed.
 *   GHB_Customize_Checklist_Control  checkboxes kept as an array of values.
 *
 * Lists are passed to the script as [value, label] pairs, not objects, so
 * numeric keys (term ids) keep their order.
 *
 * @package Golden_Hive_Blocks
 * @since   5.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GHB_Customize_List_Control extends WP_Customize_Control
{
    public $type = 'ghb_list';

    /**
     * A row's fields: list of {key, label, type (text | textarea | select |
     * checkbox), choices: [[value, label], …] for a select}.
     *
     * @var array<int, array>
     */
    public $fields = array();

    /** Fixed rows, [[key, title], …]: shown always, never added or removed. */
    public $fixed = array();

    /** Most rows; 0 for no limit. */
    public $max = 0;

    /** The add button's text. */
    public $add_label = '';

    public function to_json()
    {
        parent::to_json();
        $this->json['fields'] = array_values($this->fields);
        $this->json['fixed']  = array_values($this->fixed);
        $this->json['max']    = (int) $this->max;
        $this->json['i18n']   = array(
            'up'     => __('Sposta su', 'golden-hive-blocks'),
            'down'   => __('Sposta giù', 'golden-hive-blocks'),
            'remove' => __('Rimuovi', 'golden-hive-blocks'),
            'row'    => __('Riga %d', 'golden-hive-blocks'),
        );
    }

    public function render_content()
    {
        if ($this->label) {
            echo '<span class="customize-control-title">' . esc_html($this->label) . '</span>';
        }
        if ($this->description) {
            echo '<span class="description customize-control-description">' . esc_html($this->description) . '</span>';
        }
        echo '<div class="ghb-list" data-ghb-list></div>';
        if (!$this->fixed) {
            echo '<button type="button" class="button ghb-list__add" data-ghb-list-add>'
                . esc_html('' !== $this->add_label ? $this->add_label : __('Aggiungi', 'golden-hive-blocks'))
                . '</button>';
        }
    }
}

class GHB_Customize_Checklist_Control extends WP_Customize_Control
{
    public $type = 'ghb_checklist';

    /** The checkboxes, [[value, label], …]. */
    public $options = array();

    public function to_json()
    {
        parent::to_json();
        $this->json['options'] = array_values($this->options);
        $this->json['i18n']    = array('empty' => __('Niente da scegliere.', 'golden-hive-blocks'));
    }

    public function render_content()
    {
        if ($this->label) {
            echo '<span class="customize-control-title">' . esc_html($this->label) . '</span>';
        }
        if ($this->description) {
            echo '<span class="description customize-control-description">' . esc_html($this->description) . '</span>';
        }
        echo '<div class="ghb-checklist" data-ghb-checklist></div>';
    }
}
