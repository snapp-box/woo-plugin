<?php

class SnappBoxWcOrderColumn {
    private $column_id = 'order_status_check';
    private $meta_key = '_snappbox_last_api_response';
    private $column_label = 'SnappBox';

    public function __construct() {
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_column'], 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_column'] , 20, 2);
        add_filter('manage_edit-shop_order_sortable_columns', [$this, 'make_column_sortable']);
        add_action('pre_get_posts', [$this, 'handle_sorting']);
    }

    public function add_column($columns) {
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            if ($key === 'order_total') {
                $new_columns[$this->column_id] = __($this->column_label, 'textdomain');
            }
        }

        return $new_columns;
    }

    public function render_column($column, $order) {
        if ($column === $this->column_id) {
            $order_id = $order->get_id(); 
            $orderID = get_post_meta($order_id, '_snappbox_order_id', true);
            $value = get_post_meta($orderID, $this->meta_key, true);
            echo $value->status ? esc_html($value->status) : '—';
        }
    }

    public function make_column_sortable($columns) {
        $columns[$this->column_id] = $this->column_id;
        return $columns;
    }

    public function handle_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) return;

        if ($query->get('orderby') === $this->column_id) {
            $query->set('meta_key', $this->meta_key);
            $query->set('orderby', 'meta_value'); 
        }
    }
}
