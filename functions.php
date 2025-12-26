
/**
 * Custom functions for optimizing the WooCommerce My Account page.
 * Includes dequeuing unnecessary scripts, redirecting specific endpoints, removing unwanted menu items, and AJAX handlers for orders and form saves.
 * Optimized for performance: added transient caching for orders count, better error handling, minimal queries, and excluded 'view-order' from redirects to allow direct order viewing.
 */

// Custom setup for account page: Dequeue trash scripts and enqueue custom assets
function custom_account_setup() {
    if (is_account_page()) {
        // Dequeue unnecessary WooCommerce and jQuery scripts to reduce load
        wp_dequeue_script('woocommerce');
        wp_dequeue_script('wc-single-product');
        wp_dequeue_script('wc-add-to-cart-variation');
        wp_dequeue_script('jquery');

        // Enqueue custom CSS and JS with version for cache busting
        wp_enqueue_style('custom-account-css', get_stylesheet_directory_uri() . '/woocommerce/myaccount/custom-dashboard.css', array(), '1.0');
        wp_enqueue_script('custom-account-js', get_stylesheet_directory_uri() . '/woocommerce/myaccount/custom-dashboard.js', array(), '1.0', true);
        
        // Localize AJAX data with nonce for security
        wp_localize_script('custom-account-js', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('account_ajax_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'custom_account_setup', 100);

// Remove unwanted account menu items (endpoints) to clean up navigation
function remove_account_endpoints($items) {
    unset($items['orders'], $items['edit-address'], $items['edit-account'], $items['downloads'], $items['payment-methods']);
    // Keep 'view-order' and 'customer-logout' if needed
    return $items;
}
add_filter('woocommerce_account_menu_items', 'remove_account_endpoints');

// Redirect specific account sub-endpoints to main /my-account/ (exclude 'view-order' for direct order access)
function redirect_account_endpoints() {
    if (is_account_page()) {
        $endpoint = WC()->query->get_current_endpoint();
        if ($endpoint && $endpoint !== 'view-order') {
            wp_safe_redirect(wc_get_account_endpoint_url(''));
            exit;
        }
    }
}
add_action('template_redirect', 'redirect_account_endpoints');

// AJAX handler for loading orders (optimized with transient caching for total count, limited queries)
function ajax_load_orders() {
    check_ajax_referer('account_ajax_nonce', 'nonce');

    $user_id = get_current_user_id();
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $per_page = 10; // Optimized page size for performance

    // Cache total orders count (refresh every hour)
    $transient_key = 'user_orders_count_' . $user_id;
    $total_orders = get_transient($transient_key);
    if (false === $total_orders) {
        $total_orders = count(wc_get_orders(array('customer' => $user_id, 'return' => 'ids')));
        set_transient($transient_key, $total_orders, HOUR_IN_SECONDS);
    }
    $total_pages = ceil($total_orders / $per_page);

    $orders = wc_get_orders(array(
        'customer' => $user_id,
        'limit' => $per_page,
        'page' => $paged,
        'orderby' => 'date',
        'order' => 'DESC',
    ));

    ob_start();
    if (!empty($orders)) {
        foreach ($orders as $order) {
            $status = $order->get_status();
            $status_name = wc_get_order_status_name($status);
            $date = $order->get_date_created()->date('Y-m-d');
            ?>
            <li class="order-item">
                <div class="order-summary">
                    <span class="order-number">#<?php echo esc_html($order->get_id()); ?></span>
                    <span class="order-status status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_name); ?></span>
                    <span class="order-date">(<?php echo esc_html($date); ?>)</span>
                </div>
                <div class="order-items-preview">
                    <?php
                    $items = $order->get_items();
                    $preview_count = 0;
                    foreach ($items as $item) {
                        if ($preview_count >= 5) break;
                        $product_id = $item->get_product_id();
                        $thumb = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'thumbnail');
                        if ($thumb) {
                            echo '<img src="' . esc_url($thumb[0]) . '" alt="' . esc_attr($item->get_name()) . '" loading="lazy" class="item-thumb">';
                        } else {
                            echo '<span class="item-name">' . esc_html($item->get_name()) . '</span>';
                        }
                        $preview_count++;
                    }
                    ?>
                </div>
                <a href="<?php echo esc_url($order->get_view_order_url()); ?>">Подробнее</a>
            </li>
            <?php
        }
    } else {
        echo '<li>Нет заказов.</li>';
    }
    $html = ob_get_clean();

    wp_send_json_success(array('html' => $html, 'total_pages' => $total_pages));
}
add_action('wp_ajax_load_orders', 'ajax_load_orders');

// AJAX handler for saving address (with error handling and required argument)
function ajax_save_address() {
    try {
        check_ajax_referer('woocommerce-edit_address', 'woocommerce-edit-address-nonce');
        woocommerce_account_edit_address('billing'); // Add 'billing' as the required argument (or 'shipping' if needed)
        wp_send_json_success(array('message' => 'Адрес сохранён.'));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Ошибка сохранения адреса: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_save_address', 'ajax_save_address');

// AJAX handler for saving account
function ajax_save_account() {
    try {
        check_ajax_referer('save_account_details', 'save-account-details-nonce');
        woocommerce_account_save_account_details(); // Woo сохраняет и валидирует (email unique, password strength)
        wp_send_json_success(array('message' => 'Настройки сохранены.'));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Ошибка: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_save_account', 'ajax_save_account');

// validation
add_filter('woocommerce_process_myaccount_field_billing_phone', function($value) {
    if (!preg_match('/^\+7\d{10}$/', $value)) { // Example +7XXXXXXXXXX
        wc_add_notice('Неверный формат телефона.', 'error');
    }
    return $value;
});
