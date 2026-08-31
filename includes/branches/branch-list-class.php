<?php

namespace Snappbox\Branches;

if (!defined('ABSPATH')) {
    exit;
}
require_once SNAPPBOX_DIR . 'includes/branches/new-branch-modal.php';
require_once SNAPPBOX_DIR . 'includes/api/branches/branches-list.php';
require_once SNAPPBOX_DIR . 'includes/api/branches/branches-edit.php';
require_once SNAPPBOX_DIR . 'includes/api/branches/branches-delete.php';

use Snappbox\Api\Branches\SnappBoxBranchesList;

class BranchListPage
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'snappb_branches_register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'snappb_branches_enqueue_assets']);
        add_action('wp_ajax_snappbox_save_branch', [$this, 'save_branch']);
        add_action('wp_ajax_snappbox_delete_branch', [$this, 'delete_branch']);
    }


    public function snappb_branches_register_menu()
    {
        add_menu_page(
            'Branch Management',
            __('Branches', 'snappbox'),
            'manage_options',
            'branch-management',
            [$this, 'snappb_branches_render_page'],
            'dashicons-building',

            26
        );
    }


    public function snappb_branches_enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_branch-management') {
            return;
        }

        wp_enqueue_style(
            'branch-admin-style',
            SNAPPBOX_URL . 'assets/css/branches.css',
            [],
            '1.0.0'
        );
        wp_enqueue_style(
            'new-branch-modal',
            SNAPPBOX_URL . 'assets/css/new-branch-modal.css',
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'snappbox-branches-admin',
            SNAPPBOX_URL . 'assets/js/branches-admin.js',
            ['jquery'],
            '1.0.1',
            true
        );
        wp_localize_script('snappbox-branches-admin', 'SNAPPBOX_BRANCHES', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('snappbox_branch_nonce'),
            'strings' => [
                'addNewBranch'   => __('Add New Branch', 'snappbox'),
                'addBranch'      => __('Add Branch', 'snappbox'),
                'editBranch'     => __('Edit Branch', 'snappbox'),
                'adding'         => __('Adding...', 'snappbox'),
                'saved'          => __('Your branch has successfully saved', 'snappbox'),
                'deleted'        => __('Your branch has successfully deleted', 'snappbox'),
                'connectionError' => __('Error with establishing connection with server', 'snappbox'),
                'error'          => __('Error', 'snappbox'),
            ],
        ]);
    }


    public function snappb_branches_get_items()
    {

        $branches = new SnappBoxBranchesList();
        $list = $branches->snappb_branches_list();
        return ($list['response'] ?? []);
    }

    public function snappb_branches_render_page()
    {

        $items = $this->snappb_branches_get_items();

        if (class_exists("\Snappbox\Branches\BranchModal")) {
            new \Snappbox\Branches\BranchModal();
            \Snappbox\Branches\BranchModal::render();
        }
        $statusCode = $items['statusCode'] ?? "";
        if ($statusCode && $statusCode == "404") {
            $items = [];
        } else {
            usort($items, function ($a, $b) {
                $order = [
                    'ACTIVE'   => 1,
                    'INACTIVE' => 2,
                ];

                return ($order[$a['status'] ?? ''] ?? 999)
                    <=> ($order[$b['status'] ?? ''] ?? 999);
            });
        }

?>
        <div class="wrap branch-admin-wrap">
            <div class="branch-header">
                <h1><?php \esc_html_e('Branch Management', 'snappbox'); ?></h1>
                <button
                    type="button"
                    class="branch-add-btn"
                    id="open-branch-modal">
                    <?php \esc_html_e('Add branch', 'snappbox'); ?>
                </button>
            </div>
            <!-- <div class="branch-toolbar">
                <div class="branch-search">
                    <input type="text" placeholder="جستجو با شماره شعبه">
                </div>
            </div> -->
            <div class="branch-table-wrapper">
                <table class="branch-table">
                    <thead>
                        <tr>

                            <th><?php \esc_html_e('Branch name', 'snappbox'); ?></th>
                            <th>ID</th>
                            <th><?php \esc_html_e('Address', 'snappbox'); ?></th>
                            <th><?php \esc_html_e('Block', 'snappbox'); ?></th>
                            <th><?php \esc_html_e('Unit', 'snappbox'); ?></th>
                            <th><?php \esc_html_e('Contact phone number', 'snappbox'); ?></th>
                            <th><?php \esc_html_e('Status', 'snappbox'); ?></th>
                            <th><?php \esc_html_e('Default branch', 'snappbox'); ?></th>
                            <th><?php \esc_html_e('Operation', 'snappbox'); ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($items) && is_array($items)): ?>

                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <span href="#" class="branch-name">
                                            <?php echo esc_html($item['name']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo esc_html($item['id']); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($item['address']); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($item['plate']); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($item['unit']); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($item['contactPhoneNumber']); ?>
                                    </td>

                                    <td>
                                        <span class="branch-status "><?php echo esc_attr($item['status']); ?></span>
                                    </td>

                                    <td>
                                        <?php if ($item['defaultAddress'] == true) {
                                        ?>
                                            <svg width="16" height="19" viewBox="0 0 16 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M0 19V0H16L14 5L16 10H2V19H0Z" fill="#00A32A" />
                                            </svg>
                                        <?php
                                        } else {
                                        ?>
                                            <svg width="16" height="19" viewBox="0 0 16 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M0 19V0H16L14 5L16 10H2V19H0Z" fill="#DCDCDE" />
                                            </svg>
                                        <?php
                                        } ?>
                                    </td>
                                    <td>
                                        <div class="branch-actions">
                                            <button
                                                <?php echo (($item['status'] == 'INACTIVE') ? "disabled" : ""); ?>
                                                type="button"
                                                class="branch-btn edit js-edit-branch"
                                                data-branch='<?php echo esc_attr(json_encode($item)); ?>'>
                                                <?php \esc_html_e('Edit Branch', 'snappbox'); ?>
                                            </button>
                                            <button
                                                <?php echo (($item['status'] == 'INACTIVE') ? "disabled" : ""); ?>
                                                type="button"
                                                class="branch-btn delete js-delete-branch"
                                                data-id="<?php echo esc_attr($item['id']); ?>">
                                                <span class="delete-text"><?php \esc_html_e('Remove Branch', 'snappbox'); ?></span>
                                                <div class="loader loading" aria-label="Loading" role="status" hidden>
                                                    <span></span><span></span><span></span>
                                                </div>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:20px;">
                                    <?php \esc_html_e('Nothing found', 'snappbox'); ?>
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        <div id="snappbox-message" class="snappbox-message">
            <div class="snappbox-message-text"></div>

        </div>
        <div id="delete-branch-modal" class="branch-delete-modal">
            <div class="branch-delete-modal-content">
                <button type="button" class="modal-close">×</button>

                <h2><?php esc_html_e('Confirm deleting', 'snappbox'); ?></h2>

                <p>
                    <?php esc_html_e('Are you sure about deleting this branch', 'snappbox'); ?>
                    <strong id="delete-branch-name"></strong>
                </p>

                <span><?php esc_html_e('This operation is Irreturnable', 'snappbox'); ?>.</span>

                <div class="modal-actions">
                    <button type="button" class="cancel-delete">
                        <?php esc_html_e('Cancel', 'snappbox'); ?>
                    </button>

                    <button type="button" class="confirm-delete">
                        <?php esc_html_e('Confirm deleting', 'snappbox'); ?>
                    </button>
                </div>
            </div>
        </div>

<?php
    }
    public function save_branch()
    {
        check_ajax_referer('snappbox_branch_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied'], 403);
        }

        try {

            $token = SNAPPBOX_API_TOKEN;
            $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'create';
            $polygon_json = isset($_POST['polygon']) ? sanitize_text_field(wp_unslash($_POST['polygon'])) : '';
            $polygon = $polygon_json !== '' ? $this->snappb_convert_polygon($polygon_json) : '';
            $existing_branches = (new SnappBoxBranchesList())->snappb_branches_list()['response'] ?? [];
            $existing_branches = is_array($existing_branches) && empty($existing_branches['statusCode']) ? $existing_branches : [];
            $branch_name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            if ($branch_name === '') {
                $branch_name = sprintf(__('Store %d', 'snappbox'), count($existing_branches) + 1);
            }

            $payload = [
                'name' => $branch_name,
                'contactName' => isset($_POST['contactName']) ? sanitize_text_field(wp_unslash($_POST['contactName'])) : '',
                'contactPhoneNumber' => isset($_POST['contactPhoneNumber']) ? sanitize_text_field(wp_unslash($_POST['contactPhoneNumber'])) : '',
                'latitude' => isset($_POST['latitude']) ? (float) sanitize_text_field(wp_unslash($_POST['latitude'])) : 0,
                'longitude' => isset($_POST['longitude']) ? (float) sanitize_text_field(wp_unslash($_POST['longitude'])) : 0,
                'address' => isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '',
                'plate' => isset($_POST['plate']) ? sanitize_text_field(wp_unslash($_POST['plate'])) : '',
                'polygon' => $polygon,
                'unit' => isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : '',
                'comment' => isset($_POST['comment']) ? sanitize_text_field(wp_unslash($_POST['comment'])) : '',
                'defaultAddress' => ($mode === 'create' && empty($existing_branches))
                    || (isset($_POST['defaultAddress']) && filter_var(wp_unslash($_POST['defaultAddress']), FILTER_VALIDATE_BOOLEAN))

            ];

            // =========================
            // CREATE (POST)
            // =========================
            if ($mode === 'create') {
                $api = new \Snappbox\Api\Branches\SnappboxBranchesAdd($token);
                $result = $api->store_address($payload);
                wp_send_json_success($result);
            }

            // =========================
            // EDIT (PUT)
            // =========================
            $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';

            if (empty($id)) {
                wp_send_json_error(['message' => 'Branch ID is required'], 400);
            }

            $api = new \Snappbox\Api\Branches\SnappboxBranchesUpdate($token);
            $result = $api->update_address($id, $payload);

            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function snappb_convert_polygon(string $polygon): string
    {
        $coordinates = json_decode($polygon, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($coordinates)) {
            throw new \InvalidArgumentException('Invalid coordinates JSON.');
        }

        if (count($coordinates) < 3) {
            throw new \InvalidArgumentException(__('A polygon requires at least three points.', 'snappbox'));
        }

        $points = array_map(function (array $point): string {
            if (count($point) !== 2) {
                throw new \InvalidArgumentException(
                    'Each coordinate must contain [longitude, latitude].'
                );
            }

            return "{$point[0]} {$point[1]}";
        }, $coordinates);

        return 'POLYGON ((' . implode(', ', $points) . '))';
    }

    public function delete_branch()
    {
        check_ajax_referer('snappbox_branch_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Access denied'
            ], 403);
        }

        try {

            $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';

            if (empty($id)) {
                wp_send_json_error([
                    'message' => 'Branch ID is required'
                ], 400);
            }

            $token = SNAPPBOX_API_TOKEN;

            $api = new \Snappbox\Api\Branches\SnappboxBranchesDelete($token);

            $result = $api->delete_address($id, []);

            if (!empty($result['success'])) {
                wp_send_json_success($result);
            }

            wp_send_json_error($result);
        } catch (\Throwable $e) {

            wp_send_json_error([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
