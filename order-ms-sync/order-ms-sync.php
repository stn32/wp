<?php
/**
 * Plugin Name: Order MS Sync - Woo to MS
 * Description: Синхронизирует заказы из WooCommerce в МойСклад (односторонняя: Woo -> MS).
 * Version: 1.0.2
 * Author: stn32
 * Text Domain: moysklad-order-sync-woo-ms
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.0
 * Requires PHP: 7.2
 * WC requires at least: 6.0
 * WC tested up to: 7.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}
// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
class MoySklad_Order_Sync_Woo_MS {
    private static $instance = null;
    private $username;
    private $password;
    private $api_url = 'https://api.moysklad.ru/api/remap/1.2';
    private $logs_option = 'moysklad_order_sync_logs';
    private $last_sync_option = 'moysklad_order_sync_last_sync';
    private $logging_enabled_option = 'moysklad_order_sync_logging_enabled';
    private $excluded_orders_option = 'moysklad_order_sync_excluded';
    private $organization_option = 'moysklad_order_sync_organization';
    private $owner_option = 'moysklad_order_sync_owner';
    private $store_option = 'moysklad_order_sync_store';
    private $project_option = 'moysklad_order_sync_project';
    private $saleschannel_option = 'moysklad_order_sync_saleschannel';
    private $vat_included_option = 'moysklad_order_sync_vat_included';
    private $wc_to_ms_status_mapping_option = 'moysklad_order_sync_wc_to_ms_mapping';
    private $username_option = 'moysklad_order_sync_username';
    private $password_option = 'moysklad_order_sync_password';
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new MoySklad_Order_Sync_Woo_MS();
        }
        return self::$instance;
    }
    private function __construct() {
        $this->username = get_option($this->username_option, '');
        $this->password = get_option($this->password_option, '');
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_moysklad_order_sync_check_connection', array($this, 'ajax_check_connection'));
        add_action('wp_ajax_moysklad_order_sync_view_logs', array($this, 'ajax_view_logs'));
        add_action('wp_ajax_moysklad_order_sync_sync_order', array($this, 'ajax_sync_order'));
        add_action('admin_post_moysklad_order_sync_settings', array($this, 'save_settings'));
        add_action('woocommerce_payment_complete', array($this, 'sync_order_on_payment'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'sync_order_on_status'), 10, 1);
        add_action('add_meta_boxes', array($this, 'add_sync_meta_box'));
        add_filter('manage_edit-shop_order_columns', array($this, 'add_ms_sync_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_ms_sync_column'), 10, 2);
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_ms_sync_column'), 20);
            add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_ms_sync_column_hpos'), 10, 2);
        }
        add_action('admin_notices', array($this, 'order_page_notice'));
    }
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('MoySklad Order Sync Woo to MS', 'moysklad-order-sync-woo-ms'),
            __('MoySklad Order Sync', 'moysklad-order-sync-woo-ms'),
            'manage_woocommerce',
            'moysklad-order-sync-woo-ms',
            array($this, 'admin_page_content')
        );
    }
    public function admin_page_content() {
        $last_sync = get_option($this->last_sync_option, 'Никогда');
        $logging_enabled = get_option($this->logging_enabled_option, true);
        $excluded_orders = get_option($this->excluded_orders_option, '');
        $selected_organization = get_option($this->organization_option, '');
        $selected_owner = get_option($this->owner_option, '');
        $selected_store = get_option($this->store_option, '');
        $selected_project = get_option($this->project_option, '');
        $selected_saleschannel = get_option($this->saleschannel_option, '');
        $vat_included = get_option($this->vat_included_option, true);
        $wc_to_ms_mapping = get_option($this->wc_to_ms_status_mapping_option, array());
        $username = get_option($this->username_option, '');
        $password = get_option($this->password_option, '');
        $organizations = $this->fetch_entity_list('/entity/organization');
        $owners = $this->fetch_entity_list('/entity/employee');
        $stores = $this->fetch_entity_list('/entity/store');
        $projects = $this->fetch_entity_list('/entity/project');
        $saleschannels = $this->fetch_entity_list('/entity/saleschannel');
        $ms_states = $this->fetch_ms_states();
        $wc_statuses = wc_get_order_statuses();
        $errors = array();
        if ($selected_organization && !$this->entity_exists($organizations, $selected_organization)) {
            $errors[] = __('Selected organization no longer exists. Please choose a new one.', 'moysklad-order-sync-woo-ms');
        }
        if ($selected_owner && !$this->entity_exists($owners, $selected_owner)) {
            $errors[] = __('Selected owner no longer exists. Please choose a new one.', 'moysklad-order-sync-woo-ms');
        }
        if ($selected_store && !$this->entity_exists($stores, $selected_store)) {
            $errors[] = __('Selected store no longer exists. Please choose a new one.', 'moysklad-order-sync-woo-ms');
        }
        if ($selected_project && !$this->entity_exists($projects, $selected_project)) {
            $errors[] = __('Selected project no longer exists. Please choose a new one.', 'moysklad-order-sync-woo-ms');
        }
        if ($selected_saleschannel && !$this->entity_exists($saleschannels, $selected_saleschannel)) {
            $errors[] = __('Selected sales channel no longer exists. Please choose a new one.', 'moysklad-order-sync-woo-ms');
        }
        ?>
        <div class="wrap">
            <h1><?php _e('MoySklad Order Sync Settings (Woo -> MS)', 'moysklad-order-sync-woo-ms'); ?></h1>
            <?php if (!empty($errors)): ?>
                <div class="notice notice-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <p><?php _e('Last synchronization: ', 'moysklad-order-sync-woo-ms'); ?><?php echo esc_html($last_sync); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="moysklad_order_sync_settings">
                <?php wp_nonce_field('moysklad_order_sync_settings'); ?>
                <label>
                    <?php _e('API Username:', 'moysklad-order-sync-woo-ms'); ?><br>
                    <input type="text" name="username" value="<?php echo esc_attr($username); ?>">
                </label><br>
                <label>
                    <?php _e('API Password:', 'moysklad-order-sync-woo-ms'); ?><br>
                    <input type="password" name="password" value="<?php echo esc_attr($password); ?>">
                </label><br>
                <label>
                    <input type="checkbox" name="logging_enabled" <?php checked($logging_enabled); ?>>
                    <?php _e('Enable logging', 'moysklad-order-sync-woo-ms'); ?>
                </label><br>
                <label>
                    <?php _e('Excluded Order IDs (one per line):', 'moysklad-order-sync-woo-ms'); ?><br>
                    <textarea name="excluded_orders" rows="5" cols="50"><?php echo esc_textarea($excluded_orders); ?></textarea>
                </label><br>
                <label>
                    <?php _e('Organization:', 'moysklad-order-sync-woo-ms'); ?><br>
                    <select name="organization">
                        <option value=""><?php _e('Select Organization', 'moysklad-order-sync-woo-ms'); ?></option>
                        <?php foreach ($organizations as $org): ?>
                            <option value="<?php echo esc_attr($org['id']); ?>" <?php selected($selected_organization, $org['id']); ?>><?php echo esc_html($org['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <label>
                    <?php _e('Owner (Employee):', 'moysklad-order-sync-woo-ms'); ?><br>
                    <select name="owner">
                        <option value=""><?php _e('Select Owner', 'moysklad-order-sync-woo-ms'); ?></option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?php echo esc_attr($owner['id']); ?>" <?php selected($selected_owner, $owner['id']); ?>><?php echo esc_html($owner['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <label>
                    <?php _e('Store:', 'moysklad-order-sync-woo-ms'); ?><br>
                    <select name="store">
                        <option value=""><?php _e('Select Store', 'moysklad-order-sync-woo-ms'); ?></option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo esc_attr($store['id']); ?>" <?php selected($selected_store, $store['id']); ?>><?php echo esc_html($store['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <label>
                    <?php _e('Project:', 'moysklad-order-sync-woo-ms'); ?><br>
                    <select name="project">
                        <option value=""><?php _e('Select Project', 'moysklad-order-sync-woo-ms'); ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo esc_attr($project['id']); ?>" <?php selected($selected_project, $project['id']); ?>><?php echo esc_html($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <label>
                    <?php _e('Sales Channel:', 'moysklad-order-sync-woo-ms'); ?><br>
                    <select name="saleschannel">
                        <option value=""><?php _e('Select Sales Channel', 'moysklad-order-sync-woo-ms'); ?></option>
                        <?php foreach ($saleschannels as $channel): ?>
                            <option value="<?php echo esc_attr($channel['id']); ?>" <?php selected($selected_saleschannel, $channel['id']); ?>><?php echo esc_html($channel['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <label>
                    <input type="checkbox" name="vat_included" <?php checked($vat_included); ?>>
                    <?php _e('VAT Included', 'moysklad-order-sync-woo-ms'); ?>
                </label><br>
                <div style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                    <h2><?php _e('Status Mapping (WooCommerce to MoySklad)', 'moysklad-order-sync-woo-ms'); ?></h2>
                    <p><?php _e('Map WooCommerce statuses to MoySklad states.', 'moysklad-order-sync-woo-ms'); ?></p>
                    <table class="widefat fixed" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php _e('WooCommerce Status', 'moysklad-order-sync-woo-ms'); ?></th>
                                <th><?php _e('MoySklad Status', 'moysklad-order-sync-woo-ms'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wc_statuses as $wc_key => $wc_label): ?>
                                <tr>
                                    <td><?php echo esc_html($wc_label); ?></td>
                                    <td>
                                        <select name="wc_to_ms_mapping[<?php echo esc_attr($wc_key); ?>]">
                                            <option value=""><?php _e('No Mapping', 'moysklad-order-sync-woo-ms'); ?></option>
                                            <?php foreach ($ms_states as $state): ?>
                                                <option value="<?php echo esc_attr($state['id']); ?>" <?php selected(isset($wc_to_ms_mapping[$wc_key]) ? $wc_to_ms_mapping[$wc_key] : '', $state['id']); ?>><?php echo esc_html($state['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php submit_button(__('Save Settings', 'moysklad-order-sync-woo-ms')); ?>
            </form>
            <button id="moysklad-order-sync-check-connection" class="button"><?php _e('Check API Connection', 'moysklad-order-sync-woo-ms'); ?></button>
            <button id="moysklad-order-sync-view-logs" class="button"><?php _e('View Logs', 'moysklad-order-sync-woo-ms'); ?></button>
            <div id="moysklad-order-sync-result"></div>
            <script>
                jQuery('#moysklad-order-sync-check-connection').click(function() {
                    jQuery.post(ajaxurl, {action: 'moysklad_order_sync_check_connection'}, function(response) {
                        jQuery('#moysklad-order-sync-result').html(response);
                    });
                });
                jQuery('#moysklad-order-sync-view-logs').click(function() {
                    jQuery.post(ajaxurl, {action: 'moysklad_order_sync_view_logs'}, function(response) {
                        jQuery('#moysklad-order-sync-result').html(response);
                    });
                });
            </script>
        </div>
        <?php
    }
    private function fetch_ms_states() {
        $response = $this->make_api_request('/entity/customerorder/metadata');
        if (is_wp_error($response) || empty($response['states'])) {
            return array();
        }
        $states = array();
        foreach ($response['states'] as $state) {
            $states[] = array(
                'id' => basename($state['meta']['href']),
                'name' => $state['name']
            );
        }
        return $states;
    }
    private function fetch_entity_list($endpoint) {
        $response = $this->make_api_request($endpoint, 'GET');
        if (is_wp_error($response) || empty($response['rows'])) {
            return array();
        }
        $list = array();
        foreach ($response['rows'] as $item) {
            $list[] = array(
                'id' => $item['id'],
                'name' => $item['name']
            );
        }
        return $list;
    }
    private function entity_exists($list, $id) {
        foreach ($list as $item) {
            if ($item['id'] === $id) {
                return true;
            }
        }
        return false;
    }
    public function save_settings() {
        check_admin_referer('moysklad_order_sync_settings');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission.', 'moysklad-order-sync-woo-ms'));
        }
        update_option($this->username_option, sanitize_text_field($_POST['username']));
        update_option($this->password_option, sanitize_text_field($_POST['password']));
        $this->username = get_option($this->username_option, '');
        $this->password = get_option($this->password_option, '');
        update_option($this->logging_enabled_option, isset($_POST['logging_enabled']));
        $excluded = sanitize_textarea_field($_POST['excluded_orders']);
        update_option($this->excluded_orders_option, $excluded);
        update_option($this->organization_option, sanitize_text_field($_POST['organization']));
        update_option($this->owner_option, sanitize_text_field($_POST['owner']));
        update_option($this->store_option, sanitize_text_field($_POST['store']));
        update_option($this->project_option, sanitize_text_field($_POST['project']));
        update_option($this->saleschannel_option, sanitize_text_field($_POST['saleschannel']));
        update_option($this->vat_included_option, isset($_POST['vat_included']) ? true : false);
        $wc_to_ms_mapping = array();
        if (isset($_POST['wc_to_ms_mapping']) && is_array($_POST['wc_to_ms_mapping'])) {
            foreach ($_POST['wc_to_ms_mapping'] as $wc_key => $ms_id) {
                if (!empty($ms_id)) {
                    $wc_to_ms_mapping[sanitize_text_field($wc_key)] = sanitize_text_field($ms_id);
                }
            }
        }
        update_option($this->wc_to_ms_status_mapping_option, $wc_to_ms_mapping);
        wp_redirect(admin_url('admin.php?page=moysklad-order-sync-woo-ms'));
        exit;
    }
    public function ajax_check_connection() {
        $response = $this->make_api_request('/entity/counterparty', 'GET');
        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>' . esc_html($response->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . __('Connection successful!', 'moysklad-order-sync-woo-ms') . '</p></div>';
        }
        wp_die();
    }
    public function ajax_view_logs() {
        $logs = get_option($this->logs_option, array());
        echo '<div class="notice notice-info"><ul>';
        foreach (array_slice(array_reverse($logs), 0, 100) as $log) {
            echo '<li>' . esc_html($log) . '</li>';
        }
        echo '</ul></div>';
        wp_die();
    }
    public function add_sync_meta_box() {
        add_meta_box(
            'moysklad-order-sync',
            __('MoySklad Order Sync', 'moysklad-order-sync-woo-ms'),
            array($this, 'sync_meta_box_content'),
            'shop_order',
            'side',
            'high'
        );
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            add_meta_box(
                'moysklad-order-sync',
                __('MoySklad Order Sync', 'moysklad-order-sync-woo-ms'),
                array($this, 'sync_meta_box_content_hpos'),
                'woocommerce_page_wc-orders',
                'side',
                'high'
            );
        }
    }
    public function sync_meta_box_content($post) {
        $order_id = $post->ID;
        ?>
        <button type="button" class="button" id="moysklad-order-sync-order" data-order-id="<?php echo esc_attr($order_id); ?>"><?php _e('Sync this Order', 'moysklad-order-sync-woo-ms'); ?></button>
        <div id="moysklad-order-sync-result"></div>
        <script>
            jQuery('#moysklad-order-sync-order').click(function() {
                var orderId = jQuery(this).data('order-id');
                jQuery.post(ajaxurl, {action: 'moysklad_order_sync_sync_order', order_id: orderId}, function(response) {
                    jQuery('#moysklad-order-sync-result').html(response);
                });
            });
        </script>
        <?php
    }
    public function sync_meta_box_content_hpos($order) {
        $order_id = $order->get_id();
        ?>
        <button type="button" class="button" id="moysklad-order-sync-order" data-order-id="<?php echo esc_attr($order_id); ?>"><?php _e('Sync this Order', 'moysklad-order-sync-woo-ms'); ?></button>
        <div id="moysklad-order-sync-result"></div>
        <script>
            jQuery('#moysklad-order-sync-order').click(function() {
                var orderId = jQuery(this).data('order-id');
                jQuery.post(ajaxurl, {action: 'moysklad_order_sync_sync_order', order_id: orderId}, function(response) {
                    jQuery('#moysklad-order-sync-result').html(response);
                });
            });
        </script>
        <?php
    }
    public function ajax_sync_order() {
        $order_id = intval($_POST['order_id']);
        $result = $this->sync_order($order_id);
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . __('Order synced successfully!', 'moysklad-order-sync-woo-ms') . '</p></div>';
        }
        wp_die();
    }
    public function add_ms_sync_column($columns) {
        $columns['ms_sync'] = __('MS Sync', 'moysklad-order-sync-woo-ms');
        return $columns;
    }
    public function render_ms_sync_column($column, $post_id) {
        if ($column === 'ms_sync') {
            $synced = get_post_meta($post_id, '_ms_synced', true);
            if ($synced) {
                echo '<span class="dashicons dashicons-yes" style="color: green;"></span>';
            } else {
                echo '<span class="dashicons dashicons-no" style="color: red;"></span>';
            }
        }
    }
    public function render_ms_sync_column_hpos($column, $order) {
        if ($column === 'ms_sync') {
            $synced = $order->get_meta('_ms_synced');
            if ($synced) {
                echo '<span class="dashicons dashicons-yes" style="color: green;"></span>';
            } else {
                echo '<span class="dashicons dashicons-no" style="color: red;"></span>';
            }
        }
    }
    public function order_page_notice() {
        global $post_type, $post;
        if ($post_type !== 'shop_order') {
            return;
        }
        $order = wc_get_order($post ? $post->ID : 0);
        if (!$order) {
            return;
        }
        $synced = $order->get_meta('_ms_synced');
        $ms_id = $order->get_meta('_ms_order_id');
        if ($synced) {
            echo '<div class="notice notice-success"><p>' . sprintf(__('Synced to MS: %s', 'moysklad-order-sync-woo-ms'), esc_html($ms_id)) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>' . __('Not synced to MS yet.', 'moysklad-order-sync-woo-ms') . '</p></div>';
        }
    }
    public function sync_order_on_payment($order_id) {
        $this->sync_order($order_id);
    }
    public function sync_order_on_status($order_id) {
        $this->sync_order($order_id);
    }
    private function sync_order($order_id) {
        $excluded = explode("\n", get_option($this->excluded_orders_option, ''));
        $excluded = array_map('trim', $excluded);
        if (in_array($order_id, $excluded)) {
            return new WP_Error('excluded', __('Order excluded from sync.', 'moysklad-order-sync-woo-ms'));
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('no_order', __('Order not found.', 'moysklad-order-sync-woo-ms'));
        }
        $ms_order_id = $order->get_meta('_ms_order_id');
        $method = $ms_order_id ? 'PUT' : 'POST';
        $endpoint = $ms_order_id ? "/entity/customerorder/$ms_order_id" : '/entity/customerorder';
        $organization_id = get_option($this->organization_option, '');
        $owner_id = get_option($this->owner_option, '');
        $store_id = get_option($this->store_option, '');
        $project_id = get_option($this->project_option, '');
        $saleschannel_id = get_option($this->saleschannel_option, '');
        $vat_included = (bool) get_option($this->vat_included_option, true);
        $wc_to_ms_mapping = get_option($this->wc_to_ms_status_mapping_option, array());
        $data = array(
            'name' => (string)$order_id,
            'sum' => $order->get_total() * 100,
            'description' => $order->get_customer_note(),
            'reserve' => true,
            'vatIncluded' => $vat_included,
        );
        $wc_status_key = 'wc-' . $order->get_status();
        if (isset($wc_to_ms_mapping[$wc_status_key])) {
            $ms_state_id = $wc_to_ms_mapping[$wc_status_key];
            $data['state'] = array(
                'meta' => array(
                    'href' => $this->api_url . '/entity/customerorder/metadata/states/' . $ms_state_id,
                    'type' => 'state',
                    'mediaType' => 'application/json'
                )
            );
        }
        if ($organization_id) {
            $organization = $this->get_entity_by_id('/entity/organization/' . $organization_id);
            if (!is_wp_error($organization)) {
                $data['organization'] = array('meta' => $organization['meta']);
            } else {
                $this->log("Organization $organization_id not found.");
            }
        }
        if ($owner_id) {
            $owner = $this->get_entity_by_id('/entity/employee/' . $owner_id);
            if (!is_wp_error($owner)) {
                $data['owner'] = array('meta' => $owner['meta']);
            } else {
                $this->log("Owner $owner_id not found.");
            }
        }
        if ($store_id) {
            $store = $this->get_entity_by_id('/entity/store/' . $store_id);
            if (!is_wp_error($store)) {
                $data['store'] = array('meta' => $store['meta']);
            } else {
                $this->log("Store $store_id not found.");
            }
        }
        if ($project_id) {
            $project = $this->get_entity_by_id('/entity/project/' . $project_id);
            if (!is_wp_error($project)) {
                $data['project'] = array('meta' => $project['meta']);
            } else {
                $this->log("Project $project_id not found.");
            }
        }
        if ($saleschannel_id) {
            $saleschannel = $this->get_entity_by_id('/entity/saleschannel/' . $saleschannel_id);
            if (!is_wp_error($saleschannel)) {
                $data['salesChannel'] = array('meta' => $saleschannel['meta']);
            } else {
                $this->log("Sales channel $saleschannel_id not found.");
            }
        }
        $customer = $this->get_or_create_counterparty($order);
        if (is_wp_error($customer)) {
            return $customer;
        }
        $data['agent'] = array('meta' => $customer['meta']);
        $positions = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $sku = $product->get_sku();
            $assortment = $this->find_assortment_by_external_code($sku);
            if (is_wp_error($assortment)) {
                $this->log("Product SKU $sku not found in MS for order $order_id");
                continue;
            }
            $positions[] = array(
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total() / $item->get_quantity() * 100,
                'assortment' => array('meta' => $assortment['meta']),
                'reserve' => $item->get_quantity()
            );
        }
        if (!empty($positions)) {
            $data['positions'] = $positions;
        }
        $response = $this->make_api_request($endpoint, $method, $data);
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log("Error syncing order $order_id: " . $error_message);
            $order->update_meta_data('_ms_synced', false);
            $order->add_order_note("Не удалось загрузить заказ в МойСклад: " . $error_message);
            $order->save();
            if ($order->get_status() === 'processing') {
                $this->schedule_retries($order_id);
            }
            return $response;
        }
        $ms_id = $response['id'];
        $order->update_meta_data('_ms_order_id', $ms_id);
        $order->update_meta_data('_ms_synced', true);
        $order->add_order_note("Заказ загружен в МойСклад: ID " . $ms_id);
        $order->save();
        $this->log("Order #$order_id synced: MS ID $ms_id");
        update_option($this->last_sync_option, current_time('mysql'));
        return true;
    }
    private function get_entity_by_id($endpoint) {
        $response = $this->make_api_request($endpoint, 'GET');
        if (is_wp_error($response)) {
            return $response;
        }
        return $response;
    }
    private function get_or_create_counterparty($order) {
        $query = array(
            'filter' => "email={$order->get_billing_email()};phone={$order->get_billing_phone()}"
        );
        $response = $this->make_api_request('/entity/counterparty', 'GET', null, $query);
        if (!is_wp_error($response) && !empty($response['rows'])) {
            return $response['rows'][0];
        }
        $data = array(
            'name' => $order->get_formatted_billing_full_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
        );
        return $this->make_api_request('/entity/counterparty', 'POST', $data);
    }
    private function find_assortment_by_external_code($externalCode) {
        $query = array(
            'filter' => "externalCode=$externalCode"
        );
        $response = $this->make_api_request('/entity/assortment', 'GET', null, $query);
        if (is_wp_error($response) || empty($response['rows'])) {
            return new WP_Error('no_assortment', __('Assortment not found.', 'moysklad-order-sync-woo-ms'));
        }
        return $response['rows'][0];
    }
    private function schedule_retries($order_id) {
        for ($i = 1; $i <= 10; $i++) {
            wp_schedule_single_event(time() + ($i * 5 * 60), 'moysklad_order_sync_retry_sync', array($order_id));
        }
        add_action('moysklad_order_sync_retry_sync', array($this, 'retry_sync'), 10, 1);
    }
    public function retry_sync($order_id) {
        $order = wc_get_order($order_id);
        if ($order->get_meta('_ms_synced')) {
            return;
        }
        $this->sync_order($order_id);
    }
    private function make_api_request($endpoint, $method = 'GET', $data = null, $query = null) {
        if (empty($this->username) || empty($this->password)) {
            return new WP_Error('no_auth', __('API credentials not set.', 'moysklad-order-sync-woo-ms'));
        }
        $url = $this->api_url . $endpoint;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'application/json;charset=utf-8',
                'Accept' => 'application/json;charset=utf-8'
            ),
            'timeout' => 30
        );
        if ($data) {
            $args['body'] = json_encode($data);
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            for ($i = 1; $i <= 3; $i++) {
                sleep(2 * $i);
                $response = wp_remote_request($url, $args);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
                    break;
                }
            }
            if (wp_remote_retrieve_response_code($response) >= 400) {
                return new WP_Error('api_error', __('API error: ', 'moysklad-order-sync-woo-ms') . $code . ' - ' . $body);
            }
        }
        return json_decode($body, true);
    }
    private function log($message) {
        if (!get_option($this->logging_enabled_option, true)) {
            return;
        }
        $logs = get_option($this->logs_option, array());
        $logs[] = current_time('mysql') . ' - ' . $message;
        if (count($logs) > 100) {
            array_shift($logs);
        }
        update_option($this->logs_option, $logs);
    }
}
MoySklad_Order_Sync_Woo_MS::get_instance();