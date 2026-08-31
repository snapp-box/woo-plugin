<?php

namespace Snappbox\Branches;

if (!defined('ABSPATH')) {
    exit;
}

require_once(SNAPPBOX_DIR . 'includes/map/snappbox-map-class.php');
require_once(SNAPPBOX_DIR . 'includes/api/branches/branches-add.php');

class BranchModal
{
    public static function render()
    {
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
                                <input id="branch-plate" type="number">
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
                            <input id="branch-phone" type="number" pattern="^09[0-9]{9}$" placeholder="09123456789">
                            <input type="hidden" id="polygon" name="woocommerce_snappbox_shipping_method_polygon_coords" />
                        </div>

                        <div class="branch-form-group switch-group gray-section">
                            <label class="branch-switch">
                                <input id="branch-default" type="checkbox">
                                <span class="branch-slider"></span>
                            </label>
                            <label class="default-branch-set">
                                <p><?php \esc_html_e('Default', 'snappbox'); ?></p>
                                <p><?php \esc_html_e('Show this branch as the main branch in site and calls', 'snappbox'); ?></p>
                            </label>
                        </div>
                        <div class="branch-form-group blue-section">
                            <strong><?php \esc_html_e('Guide', 'snappbox'); ?></strong>
                            <ul>
                                <li><?php \esc_html_e('Move the pin on the map to determine the exact location of the store.', 'snappbox'); ?></li>
                                <li><?php \esc_html_e('To draw the coverage area, click the "Start Drawing Area" button.', 'snappbox'); ?></li>
                                <li><?php \esc_html_e('At least 3 points are required to build a range.', 'snappbox'); ?></li>
                                <li><?php \esc_html_e('You can create several different ranges.', 'snappbox'); ?></li>
                            </ul>
                        </div>

                    </div>

                    <div class="branch-modal-map">
                        <?php
                        $map = new \Snappbox\Map\SnappBoxMap();
                        $lat = "35.6656021";
                        $lng = "51.3173993";
                        $map->snappbox_map([
                            'latitude'      => $lat,
                            'longitude'     => $lng,
                            'mapName'       => 'newMap',
                            'latInputName'  => 'latitude',
                            'longInputName' => 'longitude',
                            'width'         => '100%',
                            'height'        => '580px',
                            'showPolygon' => true,
                        ]);
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

<?php
    }
}
