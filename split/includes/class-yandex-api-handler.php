<?php

class Yandex_API_Handler {
    
    private $merchant_id;
    private $api_key;
    private $test_mode;
    private $base_url;
    
    public function __construct($merchant_id, $api_key, $test_mode = false) {
        $this->merchant_id = $merchant_id;
        $this->api_key = $api_key;
        $this->test_mode = $test_mode;
        $this->base_url = $test_mode 
            ? 'https://sandbox.pay.yandex.ru/api/merchant/v1'
            : 'https://pay.yandex.ru/api/merchant/v1';
        
        $this->log("API Handler initialized. Test mode: " . ($test_mode ? 'YES' : 'NO'));
    }
    
    /**
     * Создание заказа в Яндекс
     */
    public function create_order($order_data) {
        $this->log("Creating order in Yandex");
        $this->log("Order data: " . json_encode($order_data));
        
        return $this->make_request('/orders', 'POST', $order_data);
    }
    
    /**
     * Получение информации о заказе
     */
    public function get_order($order_id) {
        $this->log("Getting order info: " . $order_id);
        
        return $this->make_request("/orders/{$order_id}", 'GET');
    }
    
    /**
     * Основной метод для выполнения запросов к API
     */
    private function make_request($endpoint, $method = 'GET', $data = []) {
        $url = $this->base_url . $endpoint;
        
        $this->log("Making {$method} request to: " . $url);
        
        $args = [
            'headers' => [
                'Authorization' => 'API-Key ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $args['method'] = 'POST';
            $this->log("Request body: " . $args['body']);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_msg = 'WP Error: ' . $response->get_error_message();
            $this->log("ERROR: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log("Response Code: " . $response_code);
        $this->log("Response Body: " . $response_body);
        
        $decoded = json_decode($response_body, true);
        
        if ($response_code === 409) {
            $error_msg = 'Заказ с таким ID уже существует. ID: ' . ($data['orderId'] ?? 'unknown');
            $this->log("409 Conflict: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        if ($response_code === 401) {
            $error_msg = 'Ошибка авторизации (401). Проверьте Merchant ID и API Key';
            $this->log("ERROR: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        if ($response_code !== 200) {
            $error_msg = $decoded['message'] ?? 'Unknown error (HTTP ' . $response_code . ')';
            $this->log("ERROR: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        $this->log("Request successful");
        
        return $decoded;
    }
    
    /**
     * Логирование в файл
     */
    private function log($message) {
        $log_file = WP_CONTENT_DIR . '/yandex-api.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        
        // Также логируем в debug.log если включен
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Yandex API] ' . $message);
        }
    }
    
    /**
     * Проверка подключения к API
     */
    public function test_connection() {
        $this->log("Testing connection to Yandex API");
        
        try {
            // Пробуем получить информацию о несуществующем заказе
            // Это вызовет 404 ошибку, но проверит авторизацию
            $this->get_order('test_connection_' . time());
            $this->log("Connection test: SUCCESS");
            return true;
        } catch (Exception $e) {
            // Если ошибка 401/403 - проблемы с авторизацией
            // Если 404 - подключение работает
            if (strpos($e->getMessage(), '404') !== false) {
                $this->log("Connection test: SUCCESS (404 is expected)");
                return true;
            }
            $this->log("Connection test: FAILED - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Форматирование суммы для API Яндекс
     */
    public function format_amount($amount) {
        return number_format($amount, 2, '.', '');
    }
    
    /**
     * Получение информации о текущем режиме работы
     */
    public function get_mode_info() {
        return [
            'test_mode' => $this->test_mode,
            'base_url' => $this->base_url,
            'merchant_id' => $this->merchant_id,
        ];
    }

    /**
     * Проверка статуса заказа в Яндекс
     */
    public function get_payment_status($order_id) {
        $this->log("Checking payment status for order: " . $order_id);
        
        try {
            $order_info = $this->get_order($order_id);
            
            // Логируем всю структуру ответа для отладки
            $this->log("Full order response: " . json_encode($order_info));
            
            // Яндекс возвращает статус здесь: data.order.paymentStatus
            $status = $order_info['data']['order']['paymentStatus'] ?? 'unknown';
            
            $this->log("Order {$order_id} paymentStatus: " . $status);
            
            // Преобразуем статусы Яндекс в наши
            $status_map = [
                'CAPTURED' => 'succeeded',    // Средства захвачены (оплачено)
                'AUTHORIZED' => 'waiting',    // Средства заблокированы
                'CANCELED' => 'canceled',     // Отменено
                'REFUNDED' => 'refunded',     // Возвращено
            ];
            
            $mapped_status = $status_map[$status] ?? $status;
            $this->log("Mapped status: {$mapped_status}");
            
            return $mapped_status;
            
        } catch (Exception $e) {
            $this->log("ERROR getting order status: " . $e->getMessage());
            return 'error';
        }
    }
}
