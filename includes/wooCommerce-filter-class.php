<?php


require_once(SNAPPBOX_DIR . 'includes/cities-class.php');

if (!defined('ABSPATH')) exit;

class SnappBoxWooCommerceFilter {

    public function __construct() {
        add_filter('woocommerce_states', array($this, 'filter_checkout_cities'));
        add_filter('woocommerce_checkout_fields', array($this, 'preselect_checkout_city'));
        add_action('wp_footer', array($this, 'update_checkout_cities_script'));
    }

   
    private function get_cities_mapping() {
        $latitude = get_option('snappbox_latitude', '35.8037761');
        $longitude = get_option('snappbox_longitude', '51.4152466');
        $selected_city = get_option('snappbox_cities', '');
        $citiesObj = new SnappBoxCities();
        $cities = $citiesObj->get_delivery_category($latitude, $longitude);
        $cities_list = [];
        foreach ($cities->cities as $city) {
            if( in_array($city->cityKey, $selected_city) ){
                $cities_list[$city->cityKey] = $city->cityName;
            }
        }
        return $cities_list;
    }   

    
    public function filter_checkout_cities($states) {
        $all_cities = $this->get_cities_mapping();
        $states['IR'] = $all_cities;
        return $states;
    }


    public function preselect_checkout_city($fields) {
        $selected_city = get_option('snappbox_cities', '');
        if (!empty($selected_city)) {
            $fields['billing']['billing_state']['default'] = $selected_city;
        }
        return $fields;
    }

    public function update_checkout_cities_script() {
        if (!is_checkout()) return;
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('select#billing_state').trigger('change');
            });
        </script>
        <?php
    }
}

