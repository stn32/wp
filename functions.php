/**
 * Account page
 * version 2.7.6
 */

// Custom setup for account page: Enqueue custom assets
function custom_account_setup() {
    // Enqueue custom CSS and JS with filemtime for cache busting
    $js_path = get_stylesheet_directory() . '/woocommerce/myaccount/custom-dashboard.js';
    $css_path = get_stylesheet_directory() . '/woocommerce/myaccount/custom-dashboard.css';
    
    wp_enqueue_style('custom-account-css', get_stylesheet_directory_uri() . '/woocommerce/myaccount/custom-dashboard.css', array(), filemtime($css_path));
    wp_enqueue_script('custom-account-js', get_stylesheet_directory_uri() . '/woocommerce/myaccount/custom-dashboard.js', array(), filemtime($js_path), true);
    
    // Localize AJAX data with nonce for security and countries for select
    wp_localize_script('custom-account-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('account_ajax_nonce'),
        'countries' => WC()->countries->get_countries(),
    ));
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
        if ($endpoint && $endpoint !== 'view-order' && $endpoint !== 'customer-logout') {
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
            <div>
                <p>#<?php echo $order->get_id(); ?> (<?php echo $date; ?>)</p>
                <p>Статус: <?php echo $status_name; ?></p>
                <?php
                $items = $order->get_items();
                $preview_count = 0;
                foreach ($items as $item) {
                    if ($preview_count >= 5) break;
                    $product_id = $item->get_product_id();
                    $thumb = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'thumbnail');
                    if ($thumb) {
                        echo '<img src="' . esc_url($thumb[0]) . '" alt="">';
                    } else {
                        echo '<p>' . esc_html($item->get_name()) . '</p>';
                    }
                    $preview_count++;
                }
                ?>
                <a href="<?php echo esc_url($order->get_view_order_url()); ?>">Подробнее</a>
            </div>
            <?php
        }
    } else {
        echo '<p>Нет заказов.</p>';
    }
    $html = ob_get_clean();

    wp_send_json_success(array('html' => $html, 'total_pages' => $total_pages));
}
add_action('wp_ajax_load_orders', 'ajax_load_orders');

// Make billing last name not required
function make_billing_last_name_not_required( $fields ) {
    if ( isset( $fields['billing_last_name'] ) ) {
        $fields['billing_last_name']['required'] = false;
    }
    return $fields;
}
add_filter( 'woocommerce_billing_fields', 'make_billing_last_name_not_required' );

// Make state not required for all countries
add_filter( 'woocommerce_get_country_locale', function( $locale ) {
    foreach ( $locale as $country => $fields ) {
        $locale[ $country ][ 'state' ][ 'required' ] = false;
    }
    return $locale;
} );

// Custom save billing address (non-AJAX)
function custom_save_billing_address() {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'edit_billing_address' && isset( $_POST['woocommerce-edit-address-nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['woocommerce-edit-address-nonce'], 'woocommerce-edit_address' ) ) {
            wc_add_notice( __( 'Nonce verification failed', 'woocommerce' ), 'error' );
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wc_add_notice( __( 'User not logged in', 'woocommerce' ), 'error' );
            return;
        }

        // Validation
        $errors = false;
        if ( empty( $_POST['billing_first_name'] ) ) {
            wc_add_notice( __( 'Имя обязательно.', 'woocommerce' ), 'error' );
            $errors = true;
        }
        if ( empty( $_POST['billing_address_1'] ) ) {
            wc_add_notice( __( 'Адрес обязателен.', 'woocommerce' ), 'error' );
            $errors = true;
        }
        if ( empty( $_POST['billing_city'] ) ) {
            wc_add_notice( __( 'Город обязателен.', 'woocommerce' ), 'error' );
            $errors = true;
        }
        if ( empty( $_POST['billing_postcode'] ) ) {
            wc_add_notice( __( 'Почтовый индекс обязателен.', 'woocommerce' ), 'error' );
            $errors = true;
        }
        if ( empty( $_POST['billing_country'] ) ) {
            wc_add_notice( __( 'Страна обязательна.', 'woocommerce' ), 'error' );
            $errors = true;
        }

        if ( $errors ) {
            return;
        }

        // Update allowed fields
        update_user_meta( $user_id, 'billing_first_name', sanitize_text_field( $_POST['billing_first_name'] ) );
        update_user_meta( $user_id, 'billing_address_1', sanitize_text_field( $_POST['billing_address_1'] ) );
        update_user_meta( $user_id, 'billing_city', sanitize_text_field( $_POST['billing_city'] ) );
        update_user_meta( $user_id, 'billing_postcode', sanitize_text_field( $_POST['billing_postcode'] ) );
        update_user_meta( $user_id, 'billing_country', sanitize_text_field( $_POST['billing_country'] ) );

        wc_add_notice( __( 'Address updated successfully.', 'woocommerce' ), 'success' );
        wp_safe_redirect( wc_get_account_endpoint_url( '' ) );
        exit;
    }
}
add_action( 'template_redirect', 'custom_save_billing_address', 70 );

// Custom save account details (non-AJAX for password change)
function custom_save_account_details() {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'edit_account_details' && isset( $_POST['save-account-details-nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['save-account-details-nonce'], 'save_account_details' ) ) {
            wc_add_notice( __( 'Nonce verification failed', 'woocommerce' ), 'error' );
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wc_add_notice( __( 'User not logged in', 'woocommerce' ), 'error' );
            return;
        }

        // Set POST data for WC handler
        $_POST['account_first_name'] = get_user_meta( $user_id, 'first_name', true );
        $_POST['account_email'] = wp_get_current_user()->user_email;
        $_POST['billing_phone'] = get_user_meta( $user_id, 'billing_phone', true );
        $_POST['account_last_name'] = get_user_meta( $user_id, 'last_name', true );

        WC_Form_Handler::save_account_details();

        $errors = wc_get_notices('error');
        wc_clear_notices();
        if ( ! empty( $errors ) ) {
            return;
        }

        wc_add_notice( __( 'Account details updated successfully.', 'woocommerce' ), 'success' );
        wp_safe_redirect( wc_get_account_endpoint_url( '' ) );
        exit;
    }
}
add_action( 'template_redirect', 'custom_save_account_details', 70 );

// Validation for phone
add_filter('woocommerce_process_myaccount_field_billing_phone', function($value) {
    if (!preg_match('/^\+7\d{10}$/', $value)) {
        wc_add_notice('Неверный формат телефона.', 'error');
    }
    return $value;
});
