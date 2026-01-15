<?php
/**
 * Plugin Name: Яндекс Сплит Payment Gateway
 * Description: Платежный шлюз Яндекс Сплит для WooCommerce
 * Version: 1.1.0
 * Author: stn32
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Инициализация плагина после загрузки WooCommerce
add_action('woocommerce_loaded', 'yandex_split_init', 90);

function yandex_split_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Подключаем классы
    require_once plugin_dir_path(__FILE__) . 'includes/class-yandex-api-handler.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-yandex-split-gateway.php';

    // Регистрируем платежный шлюз
    add_filter('woocommerce_payment_gateways', 'yandex_split_add_gateway');
    function yandex_split_add_gateway($gateways) {
        $gateways[] = 'WC_Yandex_Split_Gateway';
        return $gateways;
    }

    // Регистрируем REST API routes
    add_action('rest_api_init', 'yandex_split_register_rest_routes');
    
    // Запускаем систему проверки статусов
    yandex_split_init_status_checker();
}

// Регистрация REST API routes
function yandex_split_register_rest_routes() {
    register_rest_route('yandex-split/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'yandex_split_webhook_handler',
        'permission_callback' => '__return_true'
    ));
    
    // Добавляем endpoint для ручной проверки статуса
    register_rest_route('yandex-split/v1', '/check-status/(?P<order_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'yandex_split_check_status_handler',
        'permission_callback' => '__return_true'
    ));
}

// Инициализация системы проверки статусов
function yandex_split_init_status_checker() {
    // Проверяем статус при возврате с оплаты
    add_action('template_redirect', 'yandex_split_check_on_return');
    
    // Периодическая проверка статусов ожидающих оплаты заказов
    add_action('init', 'yandex_split_periodic_status_check');
}


// Проверка статуса при возврате пользователя с Яндекс
function yandex_split_check_on_return() {
    if (!is_wc_endpoint_url('order-received')) {
        return;
    }
    
    $order_id = absint(get_query_var('order-received'));
    $order_key = sanitize_text_field($_GET['key'] ?? '');
    
    if (!$order_id || !$order_key) {
        return;
    }
    
    $order = wc_get_order($order_id);
    
    // Проверяем что это наш заказ и статус еще "ожидание оплаты"
    if (!$order || $order->get_payment_method() !== 'yandex_split' || $order->get_status() !== 'pending') {
        return;
    }
    
    // Проверяем не обрабатывали ли мы уже этот заказ
    if (get_transient('yandex_split_checking_' . $order_id)) {
        return;
    }
    
    // Ставим блокировку на 2 минуты
    set_transient('yandex_split_checking_' . $order_id, true, 2 * MINUTE_IN_SECONDS);
    
    yandex_split_log("Checking payment status for order #{$order_id} on user return");
    
    // Проверяем статус платежа (без увеличения счетчика для возврата)
    yandex_split_check_payment_status_on_return($order_id);
}

// Отдельная функция для проверки при возврате (без увеличения счетчика)
function yandex_split_check_payment_status_on_return($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    $yandex_order_id = $order->get_meta('_yandex_order_id');
    
    if (!$yandex_order_id) {
        return;
    }
    
    try {
        $gateway = new WC_Yandex_Split_Gateway();
        $yandex_api = new Yandex_API_Handler($gateway->merchant_id, $gateway->api_key, $gateway->test_mode);
        
        $status = $yandex_api->get_payment_status($yandex_order_id);
        
        yandex_split_log("Order #{$order_id} (return check) status: {$status}");
        
        if ($status === 'succeeded') {
            // Платеж успешен - завершаем заказ
            yandex_split_complete_order($yandex_order_id, [
                'event' => 'payment.succeeded',
                'object' => [
                    'id' => 'api_check_return_' . time(),
                    'orderId' => $yandex_order_id
                ]
            ]);
            
            $order->add_order_note("Статус оплаты подтвержден при возврате пользователя");
            yandex_split_log("SUCCESS: Order #{$order_id} marked as paid on user return");
        }
        
    } catch (Exception $e) {
        yandex_split_log("ERROR checking status on return for order #{$order_id}: " . $e->getMessage(), 'error');
    }
}

// Периодическая проверка статусов
function yandex_split_periodic_status_check() {
    // Проверяем раз в 5 минут
    if (!get_transient('yandex_split_status_check')) {
        yandex_split_check_all_pending_orders();
        set_transient('yandex_split_status_check', true, 5 * MINUTE_IN_SECONDS);
    }
}

// Проверка всех ожидающих оплаты заказов
function yandex_split_check_all_pending_orders() {
    // Ищем ТОЛЬКО заказы Яндекс Сплит за последние 2 часа
    $args = array(
        'limit' => 20,
        'status' => 'pending',
        'payment_method' => 'yandex_split', // Только Яндекс Сплит!
        'date_created' => '>' . (time() - 2 * HOUR_IN_SECONDS)
    );
    
    $orders = wc_get_orders($args);
    
    if (empty($orders)) {
        return;
    }
    
    yandex_split_log("Periodic check: found " . count($orders) . " Yandex Split pending orders (last 2 hours)");
    
    foreach ($orders as $order) {
        yandex_split_check_payment_status($order->get_id());
    }
}

// Основная функция проверки статуса платежа
function yandex_split_check_payment_status($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    // ВАЖНО: Проверяем что это именно наш заказ Яндекс Сплит
    if ($order->get_payment_method() !== 'yandex_split') {
        return; // Не трогаем заказы других платежных систем!
    }
    
    // ВАЖНО: Проверяем что заказ еще в ожидании оплаты
    if ($order->get_status() !== 'pending') {
        yandex_split_log("Order #{$order_id} is not pending (current: " . $order->get_status() . "). Skipping check.");
        return; // Не трогаем заказы с другими статусами!
    }
    
    // Проверяем сколько раз уже проверяли этот заказ
    $check_count = $order->get_meta('_yandex_status_check_count') ?: 0;
    
    // Лимит: максимум 12 проверок (1 час при проверке каждые 5 минут)
    if ($check_count >= 12) {
        yandex_split_log("Order #{$order_id} reached check limit (12 attempts). Stopping checks.");
        
        // Добавляем заметку о достижении лимита
        if ($order->get_status() === 'pending') {
            $order->add_order_note("Достигнут лимит автоматических проверок статуса оплаты (12 попыток). Заказ остается в ожидании.");
        }
        return;
    }
    
    $yandex_order_id = $order->get_meta('_yandex_order_id');
    
    if (!$yandex_order_id) {
        yandex_split_log("Order #{$order_id} has no Yandex Order ID. Stopping checks.");
        return;
    }
    
    try {
        $gateway = new WC_Yandex_Split_Gateway();
        $yandex_api = new Yandex_API_Handler($gateway->merchant_id, $gateway->api_key, $gateway->test_mode);
        
        $status = $yandex_api->get_payment_status($yandex_order_id);
        
        // Увеличиваем счетчик проверок
        $check_count++;
        $order->update_meta_data('_yandex_status_check_count', $check_count);
        $order->save();
        
        yandex_split_log("Order #{$order_id} (check #{$check_count}) status: {$status}");
        
        if ($status === 'succeeded') {
            // 🔒 Двойная проверка: убеждаемся что заказ еще в ожидании
            if ($order->get_status() === 'pending') {
                // Платеж успешен - завершаем заказ
                yandex_split_complete_order($yandex_order_id, [
                    'event' => 'payment.succeeded',
                    'object' => [
                        'id' => 'api_check_' . time(),
                        'orderId' => $yandex_order_id
                    ]
                ]);
                
                $order->add_order_note("Статус оплаты подтвержден через API Яндекс (автопроверка #{$check_count})");
                yandex_split_log("SUCCESS: Order #{$order_id} marked as paid after {$check_count} checks");
            } else {
                yandex_split_log("Order #{$order_id} status changed during check. Current: " . $order->get_status() . ". Skipping update.");
            }
            
        } elseif ($status === 'canceled' || $status === 'refunded') {
            // Если заказ отменен или возвращен, останавливаем проверки
            yandex_split_log("Order #{$order_id} has final status: {$status}. Stopping checks.");
            $order->update_meta_data('_yandex_status_check_count', 12); // Достигаем лимита
            
        } elseif ($check_count >= 12) {
            // Достигли лимита проверок
            if ($order->get_status() === 'pending') {
                $order->add_order_note("Достигнут лимит проверок статуса оплаты (12 попыток). Заказ остается в ожидании.");
            }
            yandex_split_log("Order #{$order_id} reached check limit. Final status: {$status}");
        }
        
    } catch (Exception $e) {
        yandex_split_log("ERROR checking status for order #{$order_id}: " . $e->getMessage(), 'error');
    }
}

// Обработчик вебхука (на случай если Яндекс начнет отправлять)
function yandex_split_webhook_handler($request) {
    yandex_split_log("Webhook received");
    
    $input = file_get_contents('php://input');
    yandex_split_log("Raw webhook data: " . $input);
    
    $data = json_decode($input, true);
    
    if (!$data) {
        yandex_split_log("ERROR: No data or JSON parse error", 'error');
        return new WP_REST_Response(['status' => 'error', 'message' => 'No data'], 400);
    }
    
    $event = $data['event'] ?? '';
    $yandex_order_id = $data['object']['orderId'] ?? '';
    
    yandex_split_log("Webhook event: {$event}, Yandex Order ID: {$yandex_order_id}");
    
    if ($event === 'payment.succeeded') {
        $result = yandex_split_complete_order($yandex_order_id, $data);
        yandex_split_log("Order update result: " . ($result ? 'SUCCESS' : 'FAILED'));
    }
    
    return new WP_REST_Response(['status' => 'success'], 200);
}

// Ручная проверка статуса через REST API
function yandex_split_check_status_handler($request) {
    $order_id = $request['order_id'];
    
    if (!$order_id) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Order ID required'], 400);
    }
    
    yandex_split_log("Manual status check for order #{$order_id}");
    
    $result = yandex_split_check_payment_status($order_id);
    
    return new WP_REST_Response([
        'status' => 'success',
        'checked' => true
    ], 200);
}

// Завершение заказа после успешной оплаты
function yandex_split_complete_order($yandex_order_id, $webhook_data) {
    global $wpdb;
    
    // Ищем заказ WooCommerce по ID Яндекс
    $order_id = null;
    
    // Пробуем новые таблицы HPOS
    $order_id = $wpdb->get_var($wpdb->prepare("
        SELECT order_id 
        FROM {$wpdb->prefix}wc_orders_meta 
        WHERE meta_key = '_yandex_order_id' 
        AND meta_value = %s
    ", $yandex_order_id));
    
    // Если не нашли, пробуем старые таблицы
    if (!$order_id) {
        $order_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_yandex_order_id' 
            AND meta_value = %s
        ", $yandex_order_id));
    }
    
    yandex_split_log("Found WC Order ID: {$order_id} for Yandex Order: {$yandex_order_id}");
    
    if (!$order_id) {
        yandex_split_log("ERROR: Order not found", 'error');
        return false;
    }
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        yandex_split_log("ERROR: Order object not found", 'error');
        return false;
    }
    
    // ВАЖНО: Проверяем что это наш заказ Яндекс и он еще в ожидании
    if ($order->get_payment_method() !== 'yandex_split') {
        yandex_split_log("ERROR: Order #{$order_id} is not Yandex Split order. Payment method: " . $order->get_payment_method(), 'error');
        return false;
    }
    
    if ($order->get_status() !== 'pending') {
        yandex_split_log("Order #{$order_id} is already processed. Current status: " . $order->get_status() . ". Skipping update.");
        return false;
    }
    
    // Обновляем статус заказа
    $order->update_status('processing', __('Платеж успешно завершен через Яндекс Сплит', 'yandex-split'));
    
    // Добавляем заметку
    $payment_id = $webhook_data['object']['id'] ?? 'unknown';
    $order->add_order_note("Платеж подтвержден Яндекс. ID: " . $payment_id);
    
    // Уменьшаем запасы
    wc_reduce_stock_levels($order_id);
    
    // Сохраняем метаданные
    $order->update_meta_data('_yandex_payment_status', 'succeeded');
    $order->update_meta_data('_yandex_webhook_data', $webhook_data);
    
    // Помечаем что заказ обработан нами
    $order->update_meta_data('_yandex_auto_processed', 'yes');
    $order->update_meta_data('_yandex_processed_at', current_time('mysql'));
    
    $order->save();
    
    yandex_split_log("SUCCESS: Order {$order_id} updated to processing");
    
    return true;
}

// Функция логирования
function yandex_split_log($message, $type = 'info') {
    $log_file = WP_CONTENT_DIR . '/yandex-split-plugin.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Yandex Split Plugin] ' . $message);
    }
}

// // Объявляем совместимость с HPOS
// add_action('before_woocommerce_init', function() {
//     if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
//         \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
//     }
// });

// Объявляем совместимость с HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}, 90);