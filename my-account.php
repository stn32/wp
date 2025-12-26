<?php
/**
 * My Account page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 2.6.0
 */

defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$customer_id = $current_user->ID;
$endpoint = WC()->query->get_current_endpoint();

if ($endpoint === 'view-order') {
    do_action('woocommerce_account_content');
} else {
    // Billing data with try-catch
    try {
        $billing_first_name = get_user_meta($customer_id, 'billing_first_name', true);
        $billing_last_name = get_user_meta($customer_id, 'billing_last_name', true);
        $billing_email = get_user_meta($customer_id, 'billing_email', true);
        $billing_phone = get_user_meta($customer_id, 'billing_phone', true);
        $billing_address_1 = get_user_meta($customer_id, 'billing_address_1', true);
        $billing_city = get_user_meta($customer_id, 'billing_city', true);
        $billing_postcode = get_user_meta($customer_id, 'billing_postcode', true);
        $billing_country = get_user_meta($customer_id, 'billing_country', true);
        $billing_address_2 = get_user_meta($customer_id, 'billing_address_2', true);
        $billing_state = get_user_meta($customer_id, 'billing_state', true);
    } catch (Exception $e) {
        error_log('Billing meta error: ' . $e->getMessage());
        $billing_first_name = $billing_last_name = $billing_email = $billing_phone = $billing_address_1 = $billing_city = $billing_postcode = $billing_country = $billing_address_2 = $billing_state = '';
    }

    // Bonuses with try-catch
    try {
        $phone = $billing_phone;
        $bonus_data = $phone ? get_user_bonuses_by_phone($phone) : false;
        error_log('Bonuses data: ' . print_r($bonus_data, true));
    } catch (Exception $e) {
        error_log('Bonuses error: ' . $e->getMessage());
        $bonus_data = false;
    }

    // Recent orders for dashboard with try-catch
    try {
        $recent_orders = wc_get_orders(array(
            'customer' => $customer_id,
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        error_log('Recent orders count: ' . count($recent_orders));
    } catch (Exception $e) {
        error_log('Orders error: ' . $e->getMessage());
        $recent_orders = array();
    }
    ?>
    <div class="custom-my-account">
        <!-- Custom navigation -->
        <nav class="myaccount_navigation_s33">
            <ul>
                <li data-section="dashboard" class="active">Главная</li>
                <li data-section="orders">Мои заказы</li>
                <li data-section="edit-address">Для доставки</li>
                <li data-section="edit-account">Настройки</li>
                <li class="myaccount_navigation_s33_exit"><a href="<?php echo esc_url(wp_logout_url(wc_get_page_permalink('myaccount'))); ?>">Выйти</a></li>
            </ul>
        </nav>

        <!-- Dashboard section -->
        <section id="dashboard-section" class="account-section" style="display: block;">
            <div class="account_page_s32">
                <div class="account_page_s32_col_1">
                    <div class="account_page_s32_col_1_line_1">
                        <p>Имя и фамилия</p>
                        <span data-field="billing_first_name"><?php echo esc_html($billing_first_name . ' ' . $billing_last_name); ?></span>
                    </div>
                    <div class="account_page_s32_col_1_line_2">
                        <p>Электронная почта и номер телефона</p>
                        <div data-field="billing_email"><?php echo esc_html($billing_email); ?></div>
                        <div data-field="billing_phone"><?php echo esc_html($billing_phone); ?></div>
                    </div>
                    <div class="account_page_s32_col_1_line_3">
                        <p>Адрес</p>
                        <div data-field="billing_address_1"><?php echo esc_html($billing_address_1); ?></div>
                        <div data-field="billing_city"><?php echo esc_html($billing_city); ?></div>
                        <div data-field="billing_postcode"><?php echo esc_html($billing_postcode); ?></div>
                        <div data-field="billing_country"><?php echo esc_html($billing_country); ?></div>
                    </div>
                    <div class="account_page_s32_col_1_line_4">
                        <button class="account_page_s32_col_1_line_4_edit">редактировать</button>
                        <button class="account_page_s32_col_1_line_4_save" style="display:none;">сохранить</button>
                    </div>
                </div>
                <div class="account_page_s32_col_2">
                    <div class="account_page_s32_col_2_bonus_data <?php echo (! $bonus_data || ! is_array($bonus_data) || ! isset($bonus_data['balance']) || empty($bonus_data['balance'])) ? 'bonus-error' : ''; ?>">
                        <?php if ($bonus_data && is_array($bonus_data) && isset($bonus_data['balance']) && !empty($bonus_data['balance'])) : ?>
                            <p>Мои бонусы: <?php echo esc_html($bonus_data['balance']); ?></p>
                            <?php if (isset($bonus_data['maxPayBonusK'])) : ?>
                                <p>Максимальное списание: <?php echo esc_html($bonus_data['maxPayBonusK']); ?>% от суммы заказа</p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p>Информация о бонусах недоступна. Проверьте номер телефона или попробуйте позже.</p>
                        <?php endif; ?>
                    </div>
                    <div class="account_page_s32_col_2_orders">
                        <p>Последние заказы</p>
                        <ul>
                            <?php if (!empty($recent_orders)) : ?>
                                <?php foreach ($recent_orders as $order) : ?>
                                    <?php
                                    $status = $order->get_status();
                                    $status_name = wc_get_order_status_name($status);
                                    $date = $order->get_date_created()->date('Y-m-d');
                                    ?>
                                    <li class="order-item">
                                        <div class="order-summary">
                                            <span class="order-number">Заказ #<?php echo esc_html($order->get_id()); ?></span>
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
                                    </li>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <li>Нет заказов.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Orders section -->
        <section id="orders-section" class="account-section" style="display:none;">
            <h2>Мои заказы</h2>
            <ul class="orders-list"></ul>
            <div class="orders-pagination"></div>
        </section>

        <!-- Edit address section -->
        <section id="edit-address-section" class="account-section" style="display:none;">
            <form id="edit-address-form" method="post" class="woocommerce-address-form wс_address_form_s32" <?php do_action('woocommerce_address_form_tag'); ?>>
                <h3>Данные для доставки</h3>
                <div class="woocommerce-address-fields">
                    <div class="woocommerce-address-fields__field-wrapper">
                        <p class="form-row form-row-first">
                            <label for="billing_first_name">Имя <span class="required">*</span></label>
                            <input type="text" name="billing_first_name" id="billing_first_name" value="<?php echo esc_attr($billing_first_name); ?>" required>
                        </p>
                        <!-- <p class="form-row form-row-last">
                            <label for="billing_last_name">Фамилия <span class="required">*</span></label>
                            <input type="text" name="billing_last_name" id="billing_last_name" value="<?php echo esc_attr($billing_last_name); ?>" required>
                        </p> -->
                        <p class="form-row">
                            <label for="billing_phone">Телефон <span class="required">*</span></label>
                            <input type="tel" name="billing_phone" id="billing_phone" value="<?php echo esc_attr($billing_phone); ?>" required pattern="[0-9+\-() ]{7,20}" title="Введите valid номер телефона">
                        </p>
                        <p class="form-row">
                            <label for="billing_email">Email <span class="required">*</span></label>
                            <input type="email" name="billing_email" id="billing_email" value="<?php echo esc_attr($billing_email); ?>" required>
                        </p>
                        <p class="form-row">
                            <label for="billing_address_1">Адрес <span class="required">*</span></label>
                            <input type="text" name="billing_address_1" id="billing_address_1" value="<?php echo esc_attr($billing_address_1); ?>" required>
                        </p>
                        <!-- <p class="form-row">
                            <label for="billing_address_2">Адрес 2</label>
                            <input type="text" name="billing_address_2" id="billing_address_2" value="<?php echo esc_attr($billing_address_2); ?>">
                        </p> -->
                        <p class="form-row">
                            <label for="billing_city">Город <span class="required">*</span></label>
                            <input type="text" name="billing_city" id="billing_city" value="<?php echo esc_attr($billing_city); ?>" required>
                        </p>
                        <!-- <p class="form-row">
                            <label for="billing_state">Регион</label>
                            <input type="text" name="billing_state" id="billing_state" value="<?php echo esc_attr($billing_state); ?>">
                        </p> -->
                        <p class="form-row">
                            <label for="billing_postcode">Индекс <span class="required">*</span></label>
                            <input type="text" name="billing_postcode" id="billing_postcode" value="<?php echo esc_attr($billing_postcode); ?>" required pattern="\d{5,6}" title="Введите valid индекс (5-6 цифр)">
                        </p>
                        <p class="form-row">
                            <label for="billing_country">Страна <span class="required">*</span></label>
                            <select name="billing_country" id="billing_country" required>
                                <?php
                                $countries = WC()->countries->get_countries();
                                foreach ($countries as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '" ' . selected($billing_country, $code, false) . '>' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                        </p>
                    </div>
                    <p>
                        <button type="submit" class="woocommerce-button button">Сохранить</button>
                        <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>
                        <input type="hidden" name="action" value="save_address"> <!-- Для AJAX -->
                    </p>
                </div>
            </form>
        </section>

        <!-- Edit account section -->
        <section id="edit-account-section" class="account-section" style="display:none;">
            <form id="edit-account-form" class="woocommerce-EditAccountForm edit-account edit_account_s32" method="post" <?php do_action('woocommerce_edit_account_form_tag'); ?>>
                <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                    <label for="account_first_name">Имя <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr($current_user->first_name); ?>" required>
                </p>
                <!-- <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                    <label for="account_last_name">Фамилия <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr($current_user->last_name); ?>" required>
                </p> -->
                <div class="clear"></div>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="account_display_name">Имя/Логин <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" value="<?php echo esc_attr($current_user->display_name); ?>" required>
                </p>
                <div class="clear"></div>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="account_email">Email <span class="required">*</span></label>
                    <input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                </p>
                <br><br>
                <p><strong>Смена пароля</strong> (оставьте пустым, если не меняете)</p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="password_current">Текущий пароль <span class="required">*</span> (для подтверждения)</label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off">
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="password_1">Новый пароль</label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off" minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Пароль должен содержать минимум 8 символов, включая цифру, строчную и заглавную букву">
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="password_2">Подтверждение нового пароля</label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off">
                </p>
                <div class="clear"></div>
                <p>
                    <button type="submit" class="woocommerce-Button button">Сохранить</button>
                    <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
                    <input type="hidden" name="action" value="save_account"> <!-- Для AJAX -->
                </p>
            </form>
        </section>

    </div>
    <?php
}
?>
