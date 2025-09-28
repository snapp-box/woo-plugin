<?php 
class SnappBoxScheduleModal {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_save_snappbox_schedule', [ $this, 'save_schedule' ] );
    }

    public function enqueue_assets() {
        wp_enqueue_script(
            'SnappBoxData',
            SNAPPBOX_URL . 'assets/js/snappbox-schedule-script.js', 
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script( 'SnappBoxData', 'SnappBoxData', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'snappbox_schedule_nonce' ),
        ]);

        
    }

    public function render_modal_html() {
        $schedule = $this->get_schedule();
    ?>
    <div class="snappbox-modal-content snappbox-date-content none-class" id="dates">
        <span class="snappbox-close">&times;</span>
        <h2><?php esc_html_e('Select Day and Time', 'sb-delivery');?></h2>
        <div class="selection-wrapper clearfix">
            <div class="snappbox-day-selector">
                <p><?php esc_html_e('Select Day', 'sb-delivery');?></p>
                <select id="snappbox-day" class="snappbox-day-selector">
                    <?php
                    $days = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
                    $day_labels = [
                        'saturday'  => esc_html_x('Saturday',  'weekday name', 'sb-delivery'),
                        'sunday'    => esc_html_x('Sunday',    'weekday name', 'sb-delivery'),
                        'monday'    => esc_html_x('Monday',    'weekday name', 'sb-delivery'),
                        'tuesday'   => esc_html_x('Tuesday',   'weekday name', 'sb-delivery'),
                        'wednesday' => esc_html_x('Wednesday', 'weekday name', 'sb-delivery'),
                        'thursday'  => esc_html_x('Thursday',  'weekday name', 'sb-delivery'),
                        'friday'    => esc_html_x('Friday',    'weekday name', 'sb-delivery'),
                    ];

                    foreach ($days as $day): ?>
                        <option value="<?php echo esc_attr($day); ?>">
                            <?php echo (esc_html($day_labels[$day]) ?? esc_html(ucfirst($day))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <p><?php esc_html_e('Add / Edit Time Slots', 'sb-delivery');?></p>
                <div id="snappbox-time-slots">
                    <?php foreach ($schedule as $day => $slots): ?>
                        <div class="snappbox-day-slots" data-day="<?php echo esc_attr($day); ?>" style="display:none;">
                            <?php foreach ($slots as $slot): ?>
                                <div class="time-slot">
                                    <input type="time" class="start" value="<?php echo esc_attr($slot['start']); ?>" />
                                    <input type="time" class="end" value="<?php echo esc_attr($slot['end']); ?>" />
                                    <button type="button" class="remove-slot button">
                                        <svg xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 48 48" width="48px" height="48px"><path fill="#f44336" d="M44,24c0,11.045-8.955,20-20,20S4,35.045,4,24S12.955,4,24,4S44,12.955,44,24z"/><path fill="#fff" d="M29.656,15.516l2.828,2.828l-14.14,14.14l-2.828-2.828L29.656,15.516z"/><path fill="#fff" d="M32.484,29.656l-2.828,2.828l-14.14-14.14l2.828-2.828L32.484,29.656z"/></svg>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-time-slot" class="add-time button">
                    <svg xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 512 511.99"><path fill="#ffffff" fill-rule="nonzero" d="M256 0c70.68 0 134.69 28.66 181.01 74.98C483.35 121.31 512 185.31 512 255.99c0 70.68-28.66 134.69-74.99 181.02-46.32 46.32-110.33 74.98-181.01 74.98-70.68 0-134.69-28.66-181.02-74.98C28.66 390.68 0 326.67 0 255.99S28.65 121.31 74.99 74.98C121.31 28.66 185.32 0 256 0zm116.73 236.15v39.69c0 9.39-7.72 17.12-17.11 17.12h-62.66v62.66c0 9.4-7.71 17.11-17.11 17.11h-39.7c-9.4 0-17.11-7.69-17.11-17.11v-62.66h-62.66c-9.39 0-17.11-7.7-17.11-17.12v-39.69c0-9.41 7.69-17.11 17.11-17.11h62.66v-62.67c0-9.41 7.7-17.11 17.11-17.11h39.7c9.41 0 17.11 7.71 17.11 17.11v62.67h62.66c9.42 0 17.11 7.76 17.11 17.11zm37.32-134.21c-39.41-39.41-93.89-63.8-154.05-63.8-60.16 0-114.64 24.39-154.05 63.8-39.42 39.42-63.81 93.89-63.81 154.05 0 60.16 24.39 114.64 63.8 154.06 39.42 39.41 93.9 63.8 154.06 63.8s114.64-24.39 154.05-63.8c39.42-39.42 63.81-93.9 63.81-154.06s-24.39-114.63-63.81-154.05z"/></svg> 
                    <?php esc_html_e('Add Time Slot', 'sb-delivery');?>
                </button>
            </div>
        </div>
        

        <div style="margin-top: 15px;text-align:left">
            <button type="button" id="save-snappbox-schedule" class="button button-primary colorful-button snappbox-btn">
                <?php esc_html_e('Save', 'sb-delivery');?>
            </button>
        </div>
    </div>
    <?php
    }

    public function save_schedule() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'snappbox_schedule_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'sb-delivery' ) ], 400 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'sb-delivery' ) ], 403 );
        }

        $day   = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash($_POST['day']) ) : '';
        $slots = isset( $_POST['slots'] ) && is_array( sanitize_text_field(wp_unslash($_POST['slots'])) ) ? sanitize_text_field(wp_unslash($_POST['slots'])) : [];

        $slots_clean = array_map( function( $slot ) {
            return [
                'start' => sanitize_text_field( $slot['start'] ?? '' ),
                'end'   => sanitize_text_field( $slot['end'] ?? '' ),
            ];
        }, $slots );

        $saved_data = get_option( 'snappbox_schedule', [] );
        $saved_data[ $day ] = $slots_clean;
        update_option( 'snappbox_schedule', $saved_data );

        wp_send_json_success( [
            'message' => __( 'Schedule saved successfully!', 'sb-delivery' ),
            'data'    => $saved_data
        ] );
    }

    public function get_schedule() {
        return get_option( 'snappbox_schedule', [] );
    }

    public function get_day_slots( $day ) {
        $schedule = $this->get_schedule();
        return isset( $schedule[ $day ] ) ? $schedule[ $day ] : [];
    }
}
