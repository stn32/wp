<?php

class WC_Yandex_Split_Gateway extends WC_Payment_Gateway {
    
    public $merchant_id;
    public $api_key;
    public $test_mode;
    
    public function __construct() {
        $this->id = 'yandex_split';
        $this->method_title = 'Яндекс Сплит';
        $this->method_description = 'Оплата через Яндекс Сплит';
        $this->has_fields = false;
        $this->supports = ['products'];
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->api_key = $this->get_option('api_key');
        $this->test_mode = $this->get_option('test_mode') === 'yes';
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }
    
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Включено',
                'type' => 'checkbox',
                'label' => 'Включить Яндекс Сплит',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Название',
                'type' => 'text',
                'description' => 'Название метода оплаты для покупателя',
                'default' => 'Яндекс Сплит'
            ],
            'description' => [
                'title' => 'Описание',
                'type' => 'textarea',
                'description' => 'Описание метода оплаты',
                'default' => 'Оплата через Яндекс Сплит'
            ],
            'merchant_id' => [
                'title' => 'Merchant ID',
                'type' => 'text',
                'description' => 'Ваш Merchant ID из личного кабинета Яндекс',
                'default' => ''
            ],
            'api_key' => [
                'title' => 'API Key',
                'type' => 'password',
                'description' => 'Ваш API ключ из личного кабинета Яндекс',
                'default' => ''
            ],
            'test_mode' => [
                'title' => 'Тестовый режим',
                'type' => 'checkbox',
                'label' => 'Включить тестовый режим',
                'default' => 'no'
            ]
        ];
    }
    
    public function payment_fields() {
        echo '<div class="yandex-split-payment-fields">';
        echo '<p>' . esc_html($this->description) . '</p>';
        echo '</div>';
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        try {
            $yandex_api = new Yandex_API_Handler($this->merchant_id, $this->api_key, $this->test_mode);
            $order_data = $this->prepare_order_data($order);
            $yandex_order = $yandex_api->create_order($order_data);
            
            $payment_url = $yandex_order['data']['paymentUrl'] ?? '';
            
            if (empty($payment_url)) {
                throw new Exception('Яндекс не вернул paymentUrl');
            }
            
            // Сохраняем ID заказа Яндекс
            $order->update_meta_data('_yandex_order_id', $order_data['orderId']);
            
            // Используем стандартный статус pending
            $order->update_status('pending', __('Ожидание оплаты через Яндекс Сплит', 'yandex-split'));
            $order->add_order_note("Ссылка для оплаты создана. ID заказа Яндекс: " . $order_data['orderId']);
            
            $order->save();
            
            WC()->cart->empty_cart();
            
            return [
                'result' => 'success',
                'redirect' => $payment_url
            ];
            
        } catch (Exception $e) {
            wc_add_notice('Ошибка при создании платежа: ' . $e->getMessage(), 'error');
            $order->update_status('failed', 'Ошибка создания платежа в Яндекс: ' . $e->getMessage());
            return ['result' => 'failure'];
        }
    }
    
    private function prepare_order_data($order) {
        $items = [];
        $total_amount = 0;
        
        // Товары
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $item_total = $item->get_total() + $item->get_total_tax();
            $items[] = [
                'discountedUnitPrice' => number_format($item_total / $item->get_quantity(), 2, '.', ''),
                'productId' => (string) $product->get_id(),
                'title' => $product->get_name(),
                'total' => number_format($item_total, 2, '.', ''),
                'quantity' => ['count' => (string) $item->get_quantity()]
            ];
            $total_amount += $item_total;
        }
        
        // Доставка
        if ($order->get_shipping_total() > 0) {
            $shipping_total = $order->get_shipping_total() + $order->get_shipping_tax();
            $items[] = [
                'discountedUnitPrice' => number_format($shipping_total, 2, '.', ''),
                'productId' => 'shipping',
                'title' => 'Доставка',
                'total' => number_format($shipping_total, 2, '.', ''),
                'quantity' => ['count' => '1']
            ];
            $total_amount += $shipping_total;
        }
        
        $webhook_url = get_rest_url(null, 'yandex-split/v1/webhook');
        
        return [
            'orderId' => 'wc_' . $order->get_id() . '_' . time(),
            'availablePaymentMethods' => ['CARD', 'SPLIT'],
            'purpose' => 'Оплата заказа ' . $order->get_id(),
            'cart' => [
                'items' => $items,
                'total' => ['amount' => number_format($total_amount, 2, '.', '')]
            ],
            'currencyCode' => 'RUB',
            'redirectUrls' => [
                'onError' => wc_get_checkout_url(),
                'onSuccess' => $this->get_return_url($order)
            ],
            'webhookUrl' => $webhook_url,
            'ttl' => 3600
        ];
    }
    
    public function is_available() {
        if (empty($this->merchant_id) || empty($this->api_key)) {
            return false;
        }
        return parent::is_available();
    }
}
