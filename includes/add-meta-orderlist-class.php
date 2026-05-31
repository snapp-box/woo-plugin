<?php

namespace Snappbox;

use Snappbox\SnappBoxOrderAdmin;

if (! defined('ABSPATH')) exit;

class SnappBoxWcOrderColumn
{
    private $column_id          = 'order_status_check';
    private $meta_key           = '_snappbox_last_api_response';
    private $column_label       = 'SnappBox';

    private $date_column_id     = 'snappbox_date';
    private $date_column_label  = 'SnappBox Date';

    private $quick_action_id    = 'snappbox_action';
    private $quick_action_label = 'SnappBox Action';

    public function __construct()
    {
        // HPOS (wc-orders screen)
        \add_action('admin_enqueue_scripts', [$this, 'snappb_order_table_enqueue_assets']);
        \add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'snappb_add_columns'], 20);
        \add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'snappb_render_hpos_column'], 20, 2);

        // Legacy (posts list)
        \add_filter('manage_edit-shop_order_columns', [$this, 'snappb_add_columns'], 20);
        \add_action('manage_shop_order_posts_custom_column', [$this, 'snappb_render_legacy_column'], 20, 2);

        // Legacy sorting
        \add_filter('manage_edit-shop_order_sortable_columns', [$this, 'snappb_make_columns_sortable']);
        \add_action('pre_get_posts', [$this, 'snappb_handle_sorting']);

        \add_action('admin_enqueue_scripts', [$this, 'snappb_order_table_enqueue_assets']);
    }
    public function snappb_order_table_enqueue_assets()
    {
        \wp_enqueue_style(
            'snappbox-admin',
            \trailingslashit(SNAPPBOX_URL) . 'assets/css/admin-snappbox.css',
            ['snappbox-style'],
            \filemtime(\trailingslashit(SNAPPBOX_DIR) . 'assets/css/admin-snappbox.css')
        );

        \wp_enqueue_script(
            'snappbox-admin',
            \trailingslashit(SNAPPBOX_URL) . 'assets/js/admin-snappbox.js',
            ['jquery', 'maplibre-gl'],
            \filemtime(\trailingslashit(SNAPPBOX_DIR) . 'assets/js/admin-snappbox.js'),
            true
        );

        \wp_localize_script('snappbox-admin', 'SNAPPBOX_GLOBAL', [
            'ajaxUrl'   => \admin_url('admin-ajax.php'),
            'nonce'     => \wp_create_nonce('snappbox_admin_actions'),
            'currency'  => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
            'rtlPluginUrl' => \trailingslashit(SNAPPBOX_URL) . 'assets/js/mapbox-gl-rtl-text.js',
            'mapStyleUrl'  => 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
            'i18n'         => [
                'priceFetching' => \__('Receiving price...', 'snappbox'),
                'priceError'    => \__('Error in receiving price', 'snappbox'),
                'requestError'  => \__('Error in sending request', 'snappbox'),
                'unknownError'  => \__('Unknown error', 'snappbox'),
                'cancelError'   => \__('Error cancelling order.', 'snappbox'),
                'orderSendErr'  => \__('Error sending order.', 'snappbox'),
                'popupCustomer' => \__('Customers location', 'snappbox'),
                'close'         => \__('Close', 'snappbox'),
                'created'       => \__('Order created successfully', 'snappbox'),
            ],
        ]);
    }
    public function snappb_add_columns($columns)
    {
        $new = [];

        foreach ($columns as $key => $label) {
            $new[$key] = $label;

            if ('order_total' === $key) {
                $new[$this->date_column_id]  = esc_html__($this->date_column_label, 'snappbox');
                $new[$this->column_id]       = esc_html__($this->column_label, 'snappbox');
                $new[$this->quick_action_id] = esc_html__($this->quick_action_label, 'snappbox');
            }
        }

        return $new;
    }

    public function snappb_render_hpos_column($column, $order)
    {
        if (!($order instanceof \WC_Order)) return;

        if ($column === $this->date_column_id) {
            $this->snappb_echo_snappbox_date_cell($order);
        } elseif ($column === $this->column_id) {
            $this->snappb_echo_snappbox_status_cell($order);
        } elseif ($column === $this->quick_action_id) {
            $this->snappb_quick_action_button($order);
        }
    }

    public function snappb_render_legacy_column($column, $post_id)
    {
        if ($column !== $this->date_column_id && $column !== $this->column_id && $column !== $this->quick_action_id) {
            return;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            echo '—';
            return;
        }

        if ($column === $this->date_column_id) {
            $this->snappb_echo_snappbox_date_cell($order);
        } elseif ($column === $this->column_id) {
            $this->snappb_echo_snappbox_status_cell($order);
        } elseif ($column === $this->quick_action_id) {
            $this->snappb_quick_action_button($order);
        }
    }

    private function snappb_quick_action_button($order)
    {
        $latitude  = \get_post_meta($order->get_id(), '_customer_latitude',  true);
        $longitude = \get_post_meta($order->get_id(), '_customer_longitude', true);
        $orderButton = new SnappBoxOrderAdmin;
        $nonce = \wp_create_nonce('snappbox_admin_actions');
        $snappboxOrder = \get_post_meta($order->get_id(), '_snappbox_order_id', true);
        $getResponse   = $snappboxOrder ? \get_post_meta($snappboxOrder, '_snappbox_last_api_response', true) : null;
        if ($latitude && $longitude) {
            $echoText = '';
            $orderButton->snappb_check_order_status($order, $echoText);
            echo $orderButton->snappb_pricing_modal($snappboxOrder, $getResponse, $order, $nonce);
        }
    }

    private function snappb_echo_snappbox_date_cell(\WC_Order $order)
    {
        $dateIso = $order->get_meta('_snappbox_day');
        $time    = $order->get_meta('_snappbox_time');

        if (empty($dateIso)) {
            echo '—';
            return;
        }

        $ts = strtotime($dateIso . ' 12:00:00');
        $label = $ts ? wp_date('l j F', $ts) : $dateIso;
        echo esc_html($label . '-' . $time);
    }

    private function snappb_echo_snappbox_status_cell(\WC_Order $order)
    {
        $order_id = $order->get_id();
        $status = '';

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
                if ($decoded && isset($decoded->status)) {
                    $status = (string) $decoded->status;
                } elseif (is_scalar($meta)) {
                    $status = (string) $meta;
                }
            } elseif (is_array($meta)) {
                $status = isset($meta['status']) ? (string) $meta['status'] : '';
            } elseif (is_object($meta)) {
                $status = isset($meta->status) ? (string) $meta->status : '';
            }
        }

        echo $status !== '' ? esc_html($status) : '—';
    }

    public function snappb_make_columns_sortable($columns)
    {
        $columns[$this->date_column_id]  = $this->date_column_id;
        $columns[$this->column_id]       = $this->column_id;
        $columns[$this->quick_action_id] = $this->quick_action_id;
        return $columns;
    }

    public function snappb_handle_sorting($query)
    {
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
