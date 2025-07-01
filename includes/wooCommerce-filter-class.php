<?php
if (!defined('ABSPATH')) exit;

class SnappBoxWooCommerceFilter {

    public function __construct() {
        add_filter('woocommerce_states', array($this, 'register_all_cities'));
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_action('wp_footer', array($this, 'city_switch_script'));
    }

    private function get_saved_snappbox_cities() {
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);

        $cities = [];
        if (!empty($settings['snappbox_cities']) && is_array($settings['snappbox_cities'])) {
            $raw_cities = $settings['snappbox_cities'];
            if (array_values($raw_cities) === $raw_cities) {
                $cities = array_combine($raw_cities, $raw_cities);
            } else {
                $cities = $raw_cities;
            }
        }
        return $cities;
    }

    private function get_woocommerce_default_cities() {
        return [
            'THR' => 'تهران',
            'MHD' => 'مشهد',
            'ESF' => 'اصفهان',
            'TBZ' => 'تبریز',
            'SHZ' => 'شیراز',
            'AHW' => 'اهواز',
            'KRN' => 'کرمان',
            'KRJ' => 'کرج',
            'RSH' => 'رشت',
            'YAZ' => 'یزد',
            'ARD' => 'اردبیل',
            'HMD' => 'همدان',
            'KHN' => 'خرم‌آباد',
            'BJN' => 'بجنورد',
            'ZAH' => 'زاهدان',
            'BND' => 'بندرعباس',
            'BIR' => 'بیرجند',
            'ILM' => 'ایلام',
            'QOM' => 'قم',
            'SML' => 'سمنان',
            'GRM' => 'گرگان',
            'KSH' => 'کاشان',
            'KRM' => 'کرمانشاه',
            'NSH' => 'نیشابور',
            'QAZ' => 'قزوین',
            'ZAN' => 'زنجان',
            'ABH' => 'آباده',
            'DEZ' => 'دزفول',
            'MAS' => 'ماکو',
            'NAJ' => 'نجف‌آباد',
            'BAH' => 'بابل',
            'KHA' => 'خوی',
            'BOR' => 'بروجرد',
            'BAM' => 'بم',
            'SIR' => 'سیرجان',
        ];
    }

    public function register_all_cities($states) {
        $woo = $this->get_woocommerce_default_cities();
        $snapp = $this->get_saved_snappbox_cities();
        
        if (!empty($snapp) && array_values($snapp) === $snapp) {
            $snapp = array_combine($snapp, $snapp);
        }

        $merged = array_merge($woo, $snapp);
        $states['IR'] = $merged;
        return $states;
    }

    public function customize_checkout_fields($fields) {
        unset($fields['billing']['billing_city']); 
        return $fields;
    }

    public function city_switch_script() {
        if (!is_checkout()) return;

        $snappbox_cities = $this->get_saved_snappbox_cities();
        $woocommerce_cities = $this->get_woocommerce_default_cities();
        ?>
       <script type="text/javascript">
        jQuery(function($) {
            const snappboxCities = <?php echo json_encode($snappbox_cities); ?>;
            const wooCities = <?php echo json_encode($woocommerce_cities); ?>;
            const $state = $('#billing_state');

            function populateCities(cities) {
                const selected = $state.val();
                $state.empty();
                $.each(cities, function(key, name) {
                    $state.append(new Option(name, key));
                });
                if (selected && cities[selected]) {
                    $state.val(selected);
                }
                $state.trigger('change');
            }

            function getSelectedShippingMethod() {
                return $('input[name^="shipping_method"]:checked').val();
            }

            function selectSnappboxMethod() {
                $('input[name^="shipping_method"]').each(function() {
                    if ($(this).val().includes('snappbox')) {
                        $(this).prop('checked', true);
                        $('body').trigger('update_checkout');
                        return false;
                    }
                });
            }

            function selectFirstNonSnappboxMethod() {
                $('input[name^="shipping_method"]').each(function() {
                    if (!$(this).val().includes('snappbox')) {
                        $(this).prop('checked', true);
                        $('body').trigger('update_checkout');
                        return false;
                    }
                });
            }

            $('form.checkout').on('change', 'input[name^="shipping_method"]', function() {
                const selected = getSelectedShippingMethod();
                if (selected && selected.includes('snappbox')) {
                    populateCities(snappboxCities);
                } else {
                    populateCities(wooCities);
                }
            });

            $('form.checkout').on('change', '#billing_state', function() {
                const selectedCity = $(this).val();

                if (snappboxCities.hasOwnProperty(selectedCity)) {
                    selectSnappboxMethod();
                } else {
                    selectFirstNonSnappboxMethod();
                }
            });

            populateCities(wooCities);
        });
        </script>
        <?php
    }
}