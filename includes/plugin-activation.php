<?php

namespace Snappbox;

use Snappbox\Api\SnappBoxConfig;

class SnappboxActivator
{

    const REDIRECT_OPTION = 'snappbox_qs_do_activation_redirect';

    public static function snappbox_activate()
    {
        update_option(self::REDIRECT_OPTION, 'yes');
        delete_transient('woocommerce_shipping_zones_cache');
    }

    public static function snappbox_deactivate()
    {
        delete_option(self::REDIRECT_OPTION);
    }

    public static function snappbox_maybe_redirect()
    {
        if (get_option(self::REDIRECT_OPTION) === 'yes') {
            delete_option(self::REDIRECT_OPTION);

            wp_safe_redirect(admin_url('admin.php?page=snappbox'));
            exit;
        }
        self::snappbox_updater();
    }


    public static function snappbox_updater()
    {

        if (! is_admin() || ! current_user_can('update_plugins')) {
            return;
        }


        $forcUpdateObject = new SnappBoxConfig();
        $forceUpdateResult = $forcUpdateObject->snappb_get_config();
        if (isset($forceUpdateResult->forceUpdate) && $forceUpdateResult->forceUpdate == 'yes') {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/misc.php';
            wp_update_plugins();
            $plugin_file = 'snappbox/snappbox.php';
            $was_active = is_plugin_active($plugin_file);
            $updates = get_site_transient('update_plugins');
            if (isset($updates->response[$plugin_file])) {
                $skin     = new \Automatic_Upgrader_Skin();
                $upgrader = new \Plugin_Upgrader($skin);
                $result = $upgrader->upgrade($plugin_file);
                if (! is_wp_error($result) && $was_active && ! is_plugin_active($plugin_file)) {
                    activate_plugin($plugin_file, '', false, true);
                }
            }
        }
    }


    public static function snappbox_goal_script()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'snappbox') {
            return;
        }

?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof ym !== "undefined") {
                    ym(105087875, 'reachGoal', 'activation')
                }
            });
        </script>
<?php
    }
}
