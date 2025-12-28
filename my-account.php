<?php
/**
 * My Account page
 *
 * @version 2.7.6
 */

$customer_id = get_current_user_id();
$endpoint = WC()->query->get_current_endpoint();

if ($endpoint === 'view-order') {
    do_action('woocommerce_account_content');
} else {
    // Account data
    $first_name = get_user_meta($customer_id, 'first_name', true);
    $user_email = wp_get_current_user()->user_email;

    // Billing data
    $billing_first_name = get_user_meta($customer_id, 'billing_first_name', true);
    $billing_phone = get_user_meta($customer_id, 'billing_phone', true);
    $billing_address_1 = get_user_meta($customer_id, 'billing_address_1', true);
    $billing_city = get_user_meta($customer_id, 'billing_city', true);
    $billing_postcode = get_user_meta($customer_id, 'billing_postcode', true);
    $billing_country = get_user_meta($customer_id, 'billing_country', true);
    $billing_address_2 = get_user_meta($customer_id, 'billing_address_2', true);
    $billing_state = get_user_meta($customer_id, 'billing_state', true);

    // Bonuses
    $phone = $billing_phone;
    $bonus_data = $phone ? get_user_bonuses_by_phone($phone) : false;

    // Recent orders for dashboard
    $recent_orders = wc_get_orders(array(
        'customer' => $customer_id,
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
?>
    <?php wc_print_notices(); ?>

    <nav>
        <ul class="myaccount_navigation_s33">
            <li data-section="dashboard" class="active">Личный кабинет</li>
            <li data-section="orders">Заказы</li>
            <li data-section="edit-account">Настройки аккаунта</li>
            <li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'customer-logout' ) . ( strpos( wc_get_account_endpoint_url( 'customer-logout' ), '?' ) === false ? '?' : '&' ) . '_wpnonce=' . wp_create_nonce( 'customer-logout' ) ); ?>">Выход</a></li>
        </ul>
    </nav>

    <div id="dashboard-section" class="account-section">
        <form method="post" class="edit-address-form">
            <input type="hidden" name="action" value="edit_billing_address">
            <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>

            <p>Имя<br>
            <span data-field="billing_first_name"><?php echo esc_html($billing_first_name); ?></span></p>

            <p>Электронная почта и номер телефона<br>
            <?php echo esc_html($user_email); ?>
            <?php echo esc_html($billing_phone); ?></p>

            <p>Адрес для доставки<br>
            <span data-field="billing_address_1"><?php echo esc_html($billing_address_1); ?></span>,
            <span data-field="billing_city"><?php echo esc_html($billing_city); ?></span>,
            <span data-field="billing_postcode"><?php echo esc_html($billing_postcode); ?></span>,
            <span data-field="billing_country"><?php echo esc_html($billing_country); ?></span></p>

            <a class="address-edit-btn">редактировать</a>
            <button type="submit" class="address-save-btn" style="display:none;">сохранить</button>
        </form>

        <p>Мои бонусы:</p>
        <?php if ($bonus_data) : ?>
            <p>Баланс: <?php echo esc_html($bonus_data['balance']); ?></p>
            <p>Максимальное списание: <?php echo esc_html($bonus_data['maxPayBonusK']); ?>% от суммы заказа</p>
            
        <?php else : ?>
            <p>Информация о бонусах недоступна. Проверьте номер телефона или попробуйте позже.</p>
        <?php endif; ?>

        <p>Последние заказы</p>
        <?php if (!empty($recent_orders)) : ?>
            <ul>
                <?php foreach ($recent_orders as $order) : ?>
                    <li>
                        <p>Заказ #<?php echo $order->get_id(); ?></p>
                        <p>Дата: <?php echo $order->get_date_created()->date('Y-m-d'); ?></p>
                        <p class="order-status <?php echo esc_attr($order->get_status()); ?>">Статус: <?php echo wc_get_order_status_name($order->get_status()); ?></p>
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
                                echo esc_html($item->get_name());
                            }
                            $preview_count++;
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>Нет заказов.</p>
        <?php endif; ?>
    </div>

    <div id="orders-section" class="account-section" style="display:none;">
        <div class="orders-list"></div>
        <div class="orders-pagination"></div>
    </div>

    <div id="edit-account-section" class="account-section" style="display:none;">
        <form method="post" class="edit-account-form">
            <input type="hidden" name="action" value="edit_account_details">
            <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>

            <p>Имя: <?php echo esc_html($first_name); ?></p>
            <p>Email: <?php echo esc_html($user_email); ?></p>
            <p>Телефон: <?php echo esc_html($billing_phone); ?></p>

            <div class="password-fields">
                <p>Текущий пароль:<br><input type="password" name="password_current"></p>
                <p>Новый пароль:<br><input type="password" name="password_1"></p>
                <p>Подтвердите пароль:<br><input type="password" name="password_2"></p>
            </div>

            <button type="submit" class="account-save-btn">сохранить</button>
        </form>
    </div>
<?php } ?>
