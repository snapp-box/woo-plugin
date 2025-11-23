<?php
namespace Snappbox;

class SnappboxActivator {

    const REDIRECT_OPTION = 'snappbox_qs_do_activation_redirect';

    public static function snappbox_activate() {
        update_option(self::REDIRECT_OPTION, 'yes');
        delete_transient('woocommerce_shipping_zones_cache');
    }

    public static function snappbox_deactivate() {
        delete_option(self::REDIRECT_OPTION);
    }

    public static function snappbox_maybe_redirect() {
        if (get_option(self::REDIRECT_OPTION) === 'yes') {
            delete_option(self::REDIRECT_OPTION);

            wp_safe_redirect(admin_url('admin.php?page=snappbox'));
            exit;
        }
    }


    public static function snappbox_goal_script() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'snappbox') {
            return;
        }

        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof ym !== "undefined") {
                    ym(105087875,'reachGoal','activation')
                }
            });
        </script>
        <?php
    }
}
