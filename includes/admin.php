<?php

if ( ! defined( 'ABSPATH' ) ) exit; 
class SnappBoxAdminPage {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_page() {
        add_submenu_page(
            'options-general.php',          
            'SnappBox',                
            'SnappBox',               
            'manage_options',               
            'snappbox-page',                
            array( $this, 'admin_page_content' ) 
        );
    }

    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SnappBox Admin Page', 'sb-delivery' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'snappbox-settings' );
                do_settings_sections( 'snappbox-page' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'snappbox-settings', 'snappbox_api' );
        add_settings_section(
            'snappbox-page-section',
            __( 'SnappBox Settings Section', 'sb-delivery' ),
            null,
            'snappbox-page'
        );
    
        add_settings_field(
            'snappbox_api',
            __( 'SnappBox API Key', 'sb-delivery' ),
            array( $this, 'snappbox_api_callback' ),
            'snappbox-page',
            'snappbox-page-section'
        );
    }
    
    public function snappbox_api_callback() {
        $value = get_option( 'snappbox_api', '' );
        echo '<input type="text" name="snappbox_api" value="' . esc_attr( $value ) . '" />';
    }
}
