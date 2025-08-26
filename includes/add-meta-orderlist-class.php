<?php

class SnappBoxWcOrderColumn {
    private $column_id        = 'order_status_check';
    private $meta_key         = '_snappbox_last_api_response';
    private $column_label     = 'SnappBox';
    private $date_column_id   = 'snappbox_date';
    private $date_column_label = 'SnappBox Date';

    public function __construct() {
        add_filter('manage_woocommerce_page_wc-orders_columns',            [$this, 'add_columns'], 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column',      [$this, 'render_hpos_column'], 20, 2);

        add_filter('manage_edit-shop_order_columns',                       [$this, 'add_columns'], 20);
        add_action('manage_shop_order_posts_custom_column',                [$this, 'render_legacy_column'], 20, 2);

        add_filter('manage_edit-shop_order_sortable_columns',              [$this, 'make_columns_sortable']);
        add_action('pre_get_posts',                                        [$this, 'handle_sorting']);
    }


    public function add_columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'order_total') {
                $new[$this->date_column_id]  = __($this->date_column_label, 'sb-delivery');
                $new[$this->column_id]       = __($this->column_label, 'sb-delivery');
            }
        }
        return $new;
    }


    public function render_hpos_column($column, $order) {
        if (!($order instanceof WC_Order)) return;

        if ($column === $this->date_column_id) {
            $this->echo_snappbox_date_cell($order);
        } elseif ($column === $this->column_id) {
            $this->echo_snappbox_status_cell($order);
        }
    }

    public function render_legacy_column($column, $post_id) {
        if ($column !== $this->date_column_id && $column !== $this->column_id) return;
        $order = wc_get_order($post_id);
        if (!$order) { echo '—'; return; }

        if ($column === $this->date_column_id) {
            $this->echo_snappbox_date_cell($order);
        } else {
            $this->echo_snappbox_status_cell($order);
        }
    }

 
    private function echo_snappbox_date_cell(WC_Order $order) {
        $dateIso = $order->get_meta('_snappbox_day'); 
        $time = $order->get_meta('_snappbox_time');

        if (empty($dateIso)) { echo '—'; return; }

        $ts = strtotime($dateIso . ' 12:00:00');       
        $label = $ts ? wp_date('l j F', $ts) : $dateIso; 
        echo esc_html($label. '-'. $time );
    }

   
    private function echo_snappbox_status_cell(WC_Order $order) {
        $order_id = $order->get_id();
        $statusText = '';

        $meta = get_post_meta($order_id, $this->meta_key, true);

        if (empty($meta)) {
            $external_id = get_post_meta($order_id, '_snappbox_order_id', true);
            if ($external_id) {
                $meta = get_post_meta($external_id, $this->meta_key, true);
            }
        }

        if (!empty($meta)) {
            if (is_string($meta)) {
                $decoded = json_decode($meta);
                if ($decoded && isset($decoded->statusText)) {
                    $statusText = (string) $decoded->statusText;
                } elseif (is_scalar($meta)) {
                    $statusText = (string) $meta;
                }
            } elseif (is_array($meta)) {
                $statusText = isset($meta['statusText']) ? (string) $meta['statusText'] : '';
            } elseif (is_object($meta)) {
                $statusText = isset($meta->statusText) ? (string) $meta->statusText : '';
            }
        }

        echo $statusText !== '' ? esc_html($statusText) : '—';
    }

  
    public function make_columns_sortable($columns) {
        $columns[$this->date_column_id] = $this->date_column_id;
        $columns[$this->column_id]      = $this->column_id;
        return $columns;
    }

    public function handle_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) return;

        $orderby = $query->get('orderby');
        if ($orderby === $this->date_column_id) {
            $query->set('meta_key', '_snappbox_day');
            $query->set('orderby', 'meta_value');
            $query->set('meta_type', 'DATE');
        } elseif ($orderby === $this->column_id) {
            $query->set('meta_key', $this->meta_key);
            $query->set('orderby', 'meta_value'); 
        }
    }
}
