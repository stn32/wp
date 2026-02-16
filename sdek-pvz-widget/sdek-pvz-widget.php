<?php
/**
 * Plugin Name:       СДЭК ПВЗ — виджет v3 (один файл + js)
 * Description:       Способ доставки СДЭК ПВЗ + официальный виджет @cdek-it/widget@3 в модальном окне.
 * Version:           0.2
 * Author:            Твой ник
 * Text Domain:       sdek-pvz-widget
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================
// 1. Способ доставки (без изменений)
// =============================================
add_filter( 'woocommerce_shipping_methods', 'sdek_pvz_add_shipping_method' );
function sdek_pvz_add_shipping_method( $methods ) {
    $methods['sdek_pvz'] = 'WC_Shipping_SDEK_PVZ';
    return $methods;
}

add_action( 'woocommerce_shipping_init', 'sdek_pvz_init_shipping_class' );
function sdek_pvz_init_shipping_class() {
    class WC_Shipping_SDEK_PVZ extends WC_Shipping_Method {
        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'sdek_pvz';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = 'СДЭК — Пункт выдачи';
            $this->method_description = 'Самовывоз в ПВЗ СДЭК (виджет v3)';
            $this->supports           = array( 'shipping-zones', 'instance-settings' );

            $this->init();
        }

        public function init() {
            $this->enabled = $this->get_option( 'enabled' );
            $this->title   = $this->get_option( 'title', 'СДЭК — Пункт выдачи' );
            $this->cost    = $this->get_option( 'cost', 0 );

            $this->init_form_fields();
            $this->init_settings();

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->instance_form_fields = array(
                'enabled' => array( 'title' => 'Включить', 'type' => 'checkbox', 'default' => 'yes' ),
                'title'   => array( 'title' => 'Название', 'type' => 'text', 'default' => 'СДЭК — Пункт выдачи' ),
                'cost'    => array( 'title' => 'Стоимость', 'type' => 'number', 'default' => 0 ),
            );
        }

        public function calculate_shipping( $package = array() ) {
            $this->add_rate( array(
                'id'    => $this->get_rate_id(),
                'label' => $this->title,
                'cost'  => $this->cost,
            ) );
        }
    }
}

// =============================================
// 2. Поля checkout + сохранение + админка (без изменений)
// =============================================
add_filter( 'woocommerce_checkout_fields', 'sdek_pvz_checkout_fields' );
function sdek_pvz_checkout_fields( $fields ) {
    $fields['shipping']['sdek_pvz_code']    = ['type' => 'hidden'];
    $fields['shipping']['sdek_pvz_address'] = ['type' => 'hidden'];
    $fields['shipping']['sdek_pvz_name']    = ['type' => 'hidden'];
    $fields['shipping']['sdek_pvz_phone']   = ['type' => 'hidden'];
    return $fields;
}

add_action( 'woocommerce_checkout_update_order_meta', 'sdek_pvz_save_order_meta' );
function sdek_pvz_save_order_meta( $order_id ) {
    if ( ! empty( $_POST['sdek_pvz_code'] ) ) {
        update_post_meta( $order_id, '_sdek_pvz_code',    sanitize_text_field( $_POST['sdek_pvz_code'] ) );
        update_post_meta( $order_id, '_sdek_pvz_address', sanitize_text_field( $_POST['sdek_pvz_address'] ) );
        update_post_meta( $order_id, '_sdek_pvz_name',    sanitize_text_field( $_POST['sdek_pvz_name'] ) );
        update_post_meta( $order_id, '_sdek_pvz_phone',   sanitize_text_field( $_POST['sdek_pvz_phone'] ) );
    }
}

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'sdek_pvz_display_in_admin' );
function sdek_pvz_display_in_admin( $order ) {
    $code    = $order->get_meta( '_sdek_pvz_code' );
    $address = $order->get_meta( '_sdek_pvz_address' );
    $name    = $order->get_meta( '_sdek_pvz_name' );
    if ( $code ) {
        echo '<h4>Выбранный ПВЗ СДЭК</h4><p><strong>Код:</strong> ' . esc_html( $code ) . '<br><strong>Адрес:</strong> ' . esc_html( $address ) . '</p>';
    }
}

// =============================================
// 3. Подключаем только наш JS (виджет уже есть в шаблоне)
// =============================================
add_action( 'wp_enqueue_scripts', 'sdek_pvz_enqueue_scripts' );
function sdek_pvz_enqueue_scripts() {
    if ( ! is_checkout() ) return;

    // 1. Виджет v3
    wp_enqueue_script( 'cdek-widget-v3', 'https://cdn.jsdelivr.net/npm/@cdek-it/widget@3', array(), '3', true );

    // 2. Наш JS (с зависимостью от виджета)
    wp_enqueue_script( 'sdek-widget', plugin_dir_url( __FILE__ ) . 'js/sdek-widget.js', array( 'jquery', 'cdek-widget-v3' ), '0.6', true );

    // 3. Параметры — ТЕПЕРЬ ПОСЛЕ enqueue нашего скрипта
    wp_localize_script( 'sdek-widget', 'sdekParams', array(
        'servicePath' => plugin_dir_url( __FILE__ ) . 'service.php',
        'yandexKey'   => 'xxx',   // ← обязательно замени!
    ));
}
