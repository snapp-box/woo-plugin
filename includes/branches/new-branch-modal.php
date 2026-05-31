<?php

namespace Snappbox\Branches;

if (!defined('ABSPATH')) {
    exit;
}

require_once(SNAPPBOX_DIR . 'includes/map/snappbox-map-class.php');
require_once(SNAPPBOX_DIR . 'includes/api/branches/branches-add.php');

class BranchModal
{
    public function __construct()
    {
        add_action('wp_ajax_snappbox_save_branch', [$this, 'save_branch']);
        add_action('wp_ajax_nopriv_snappbox_save_branch', [$this, 'save_branch']);
    }

    public static function render()
    {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('snappbox_branch_nonce');
?>

        <div id="branch-modal" class="branch-modal">
            <div class="branch-modal-content">

                <div class="branch-modal-header">
                    <h2 id="modal-title"><?php \esc_html_e('Add New Branch', 'snappbox'); ?></h2>

                    <button type="button" class="branch-modal-close">×</button>
                </div>

                <div class="branch-modal-body">

                    <div class="branch-modal-form">

                        <input type="hidden" id="branch-id">
                        <input type="hidden" id="form-mode" value="create">

                        <div class="branch-form-row">
                            <div class="branch-form-group">
                                <label><?php \esc_html_e('Branch name', 'snappbox'); ?></label>
                                <input id="branch-name" type="text">
                            </div>

                            <div class="branch-form-group">
                                <label><?php \esc_html_e('Contact name', 'snappbox'); ?></label>
                                <input id="contact-name" type="text">
                            </div>
                        </div>

                        <div class="branch-form-group">
                            <label><?php \esc_html_e('Address', 'snappbox'); ?></label>
                            <input id="branch-address" type="text">
                        </div>

                        <div class="branch-form-row">
                            <div class="branch-form-group">
                                <label><?php \esc_html_e('Block No', 'snappbox'); ?></label>
                                <input id="branch-plate" type="text">
                            </div>

                            <div class="branch-form-group">
                                <label><?php \esc_html_e('Unit No', 'snappbox'); ?></label>
                                <input id="branch-unit" type="text">
                            </div>

                            <div class="branch-form-group none-visible">
                                <input id="latitude" name="latitude" type="text">
                            </div>

                            <div class="branch-form-group none-visible">
                                <input id="longitude" name="longitude" type="text">
                            </div>
                        </div>

                        <div class="branch-form-group">
                            <label><?php \esc_html_e('Contact phone number', 'snappbox'); ?></label>
                            <input id="branch-phone" type="text">
                        </div>

                        <div class="branch-form-group switch-group">
                            <label><?php \esc_html_e('Default', 'snappbox'); ?></label>
                            <label class="branch-switch">
                                <input id="branch-default" type="checkbox">
                                <span class="branch-slider"></span>
                            </label>
                        </div>

                    </div>

                    <div class="branch-modal-map">
                        <?php
                        $map = new \Snappbox\Map\SnappBoxMap();
                        $lat = "35.6656021";
                        $lng = "51.3173993";
                        $map->snappbox_map($lat, $lng, 'newMap', 'latitude', 'longitude');
                        ?>
                    </div>

                </div>

                <div class="branch-modal-footer">

                    <button type="button" class="button branch-modal-close">
                        <?php \esc_html_e('Cancel', 'snappbox'); ?>
                    </button>
                    <button id="save-branch-btn" class="button button-primary" type="button">
                        <?php \esc_html_e('Add branch', 'snappbox'); ?>
                    </button>


                </div>

            </div>
        </div>

        <div id="snappbox-message" class="snappbox-message">
            <div class="snappbox-message-text"></div>
        </div>

        <script>
            const SNAPPBOX_AJAX = {
                ajax_url: "<?php echo esc_url($ajax_url); ?>",
                nonce: "<?php echo esc_attr($nonce); ?>"
            };
        </script>

        <script>
            jQuery(document).ready(function($) {

                const modal = $('#branch-modal');

                function openModal(branch = null) {

                    modal.addClass('active');

                    if (branch) {
                        $('#form-mode').val('edit');
                        $('#modal-title').text('<?php \esc_html_e('Edit Branch', 'snappbox'); ?>');

                        $('#branch-id').val(branch.id || '');
                        $('#branch-name').val(branch.name || '');
                        $('#contact-name').val(branch.contactName || '');
                        $('#branch-phone').val(branch.contactPhoneNumber || '');
                        $('#branch-address').val(branch.address || '');
                        $('#branch-plate').val(branch.plate || '');
                        $('#branch-unit').val(branch.unit || '');
                        $('#latitude').val(branch.latitude || '');
                        $('#longitude').val(branch.longitude || '');
                        $('#branch-default').prop('checked', branch.defaultAddress === true);

                        $('#save-branch-btn').text('<?php \esc_html_e('Edit Branch', 'snappbox'); ?>');
                        const lat = parseFloat(branch.latitude);
                        const lng = parseFloat(branch.longitude);
                        const mapObj = window.snappboxMaps["newMap"];
                        if (mapObj) {
                            setTimeout(() => {
                                mapObj.map.resize();
                                mapObj.map.setCenter([lng, lat]);
                                mapObj.map.setZoom(15);
                                mapObj.marker.setLngLat([lng, lat]);
                            }, 10);
                        }


                        $('#latitude').val(lat);
                        $('#longitude').val(lng);

                    } else {
                        $('#form-mode').val('create');
                        $('#modal-title').text('<?php \esc_html_e('Add New Branch', 'snappbox'); ?>');

                        $('#branch-id').val('');
                        $('#branch-name').val('');
                        $('#contact-name').val('');
                        $('#branch-phone').val('');
                        $('#branch-plate').val('');
                        $('#branch-unit').val('');
                        $('#latitude').val('');
                        $('#longitude').val('');
                        $('#branch-default').prop('checked', false);

                        $('#save-branch-btn').text('<?php \esc_html_e('Add Branch', 'snappbox'); ?>');
                    }
                }

                $('#open-branch-modal').on('click', function() {
                    openModal(null);
                });

                $(document).on('click', '.js-edit-branch', function() {
                    const data = $(this).data('branch');
                    openModal(data);
                });

                $('.branch-modal-close').on('click', function() {
                    modal.removeClass('active');
                });

                modal.on('click', function(e) {
                    if ($(e.target).is('#branch-modal')) {
                        modal.removeClass('active');
                    }
                });

                function showMessage(type, text) {
                    const box = $('#snappbox-message');
                    box.removeClass('success error');
                    box.addClass(type);
                    box.find('.snappbox-message-text').text(text);
                    box.addClass('show');
                    setTimeout(() => box.removeClass('show'), 3000);
                }

                $('#save-branch-btn').on('click', function() {

                    const $btn = $(this);
                    $btn.prop('disabled', true).text('<?php \esc_html_e('Adding...', 'snappbox'); ?>');

                    $.ajax({
                        url: SNAPPBOX_AJAX.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'snappbox_save_branch',
                            nonce: SNAPPBOX_AJAX.nonce,

                            mode: $('#form-mode').val(),
                            id: $('#branch-id').val(),

                            name: $('#branch-name').val(),
                            contactName: $('#contact-name').val(),
                            contactPhoneNumber: $('#branch-phone').val(),
                            latitude: $('#latitude').val(),
                            longitude: $('#longitude').val(),
                            address: $('#branch-address').val(),
                            plate: $('#branch-plate').val(),
                            unit: $('#branch-unit').val(),
                            defaultAddress: $('#branch-default').is(':checked')
                        },

                        success: function(response) {

                            $btn.prop('disabled', false);
                            if (response.success) {
                                if (response.data.message) {
                                    showMessage('error', response.data.message);
                                } else {
                                    showMessage('success', '<?php \esc_html_e('Your branch has successfully saved', 'snappbox'); ?>');
                                }
                                modal.removeClass('active');
                                location.reload(); // simple refresh
                            } else {
                                showMessage('error', response.data?.message || 'خطا');
                            }
                        },

                        error: function() {
                            $btn.prop('disabled', false);
                            showMessage('error', '<?php \esc_html_e('Error with stablishing the connection with server', 'snappbox'); ?>');
                        }
                    });
                });

            });
        </script>

<?php
    }
}
