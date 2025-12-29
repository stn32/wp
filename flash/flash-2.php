<?php
/*
Plugin Name: Flash Call Authentication
Description: Integrate a custom sign-in form with Flash Call authentication in WooCommerce
Version: 1.3
Author: stn32
*/

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

// Define API Key
// mmm
define('FLASH_CALL_API_KEY', '......');
date_default_timezone_set('UTC');

/**
* Register REST API Endpoints
*/
add_action('rest_api_init', function() {
  register_rest_route('custom-auth/v1', '/send-code/', array(
      'methods' => 'POST',
      'callback' => 'flash_call_send_code',
      'permission_callback' => '__return_true'
  ));

  register_rest_route('custom-auth/v1', '/verify-code/', array(
      'methods' => 'POST',
      'callback' => 'flash_call_verify_code',
      'permission_callback' => '__return_true'
  ));
});


function flash_call_send_code(WP_REST_Request $request) {
    global $wpdb;

    $params    = $request->get_json_params();
    $raw_phone = preg_replace('/\D/', '', $params['phone'] ?? '');

    if (strlen($raw_phone) !== 11) {
        return new WP_REST_Response(['error' => 'Invalid phone number'], 400);
    }

    // DB search: check both 7XXXXXXXXXX and 8XXXXXXXXXX versions
    $alt_phone = ($raw_phone[0] === '7') ? '8' . substr($raw_phone, 1) : '7' . substr($raw_phone, 1);

    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $wpdb->usermeta 
         WHERE meta_key = 'billing_phone' 
         AND (meta_value = %s OR meta_value = %s)",
        $raw_phone, $alt_phone
    ));

    if (!$user_id) {
        return new WP_REST_Response(['error' => 'Этот номер не зарегистрирован'], 404);
    }

    // Send code via API (use '7XXXXXXXXXX')
    $phone_for_provider = ($raw_phone[0] === '8') ? '7' . substr($raw_phone, 1) : $raw_phone;

    $response = wp_remote_post(
        'https://vp.voicepassword.ru/api/voice-password/send/',
        [
            'body'    => wp_json_encode([
                'security' => ['apiKey' => FLASH_CALL_API_KEY],
                'number'   => $phone_for_provider,
                'capacity' => 4,
            ]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15,
        ]
    );

    if (is_wp_error($response)) {
        return new WP_REST_Response(['error' => 'API request failed'], 500);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['code']) || $body['result'] !== 'ok') {
        return new WP_REST_Response(['error' => 'Ошибка при отправке кода'], 500);
    }

    set_transient('flash_call_code_' . $raw_phone, $body['code'], 5 * MINUTE_IN_SECONDS);

    return new WP_REST_Response([
        'success' => true,
        'id'      => $body['id'],
    ], 200);
}


/**
* Verify the received code
*/
function flash_call_verify_code(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $raw_phone = preg_replace('/\D/', '', $params['phone'] ?? '');
    $entered_code = trim($params['code'] ?? '');

    if (empty($raw_phone) || empty($entered_code)) {
        return new WP_REST_Response(['error' => 'Phone number and code are required'], 400);
    }

    $stored_code = get_transient('flash_call_code_' . $raw_phone);
    if (!$stored_code) {
        return new WP_REST_Response(['error' => 'No verification code found. Request a new code.'], 403);
    }

    if ($entered_code === $stored_code) {
        delete_transient('flash_call_code_' . $raw_phone);
        return new WP_REST_Response(['success' => true, 'message' => 'Login successful!'], 200);
    } else {
        return new WP_REST_Response(['error' => 'Invalid code. Try again.'], 403);
    }
}


/**
 * Enqueue scripts and styles
 */
function flash_call__s32_enqueue_scripts() {
  // Get the plugin directory URL
  $plugin_url = plugin_dir_url(__FILE__);
  // Enqueue the JavaScript file
  wp_enqueue_script(
    'flash-call-s32-script',
    $plugin_url . 'src-09/flash.js',
    array('jquery'), // Dependency on jQuery (if needed, or replace with [] if not)
    '1.0',
    true // Load in footer
  );
  // Enqueue the CSS file
  wp_enqueue_style(
    'flash-call-s32-style',
    $plugin_url . 'src-09/flash.css',
    array(), // Dependencies
    '1.0'
  );
}
add_action('wp_enqueue_scripts', 'flash_call__s32_enqueue_scripts', 60);


/**
 * Authorisation
 */
add_action('rest_api_init', function() {
    register_rest_route('custom-auth/v1', '/registration-or-authorization', [
        'methods'  => 'POST',
        'callback' => 'registrationOrAuthorisation',
        'permission_callback' => '__return_true', // Allow public access
    ]);
});
function registrationOrAuthorisation(WP_REST_Request $request) {
    $phone_number = $request->get_param('phone'); // Get phone number from request
    if (!$phone_number) {
        return new WP_REST_Response(['error' => 'Введите номер'], 400);
    }
    // Normalize phone number
    $normalized_phone = normalizePhoneNumber($phone_number);
    if (!$normalized_phone) {
        return new WP_REST_Response(['error' => 'Неверный формат номера'], 400);
    }
    // Generate possible database values (7XXXXXXXXXX and 8XXXXXXXXXX)
    $alt_phone = '8' . substr($normalized_phone, 1);
    // Search for user by billing_phone
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare("
        SELECT user_id FROM $wpdb->usermeta 
        WHERE meta_key = 'billing_phone' 
        AND (meta_value = %s OR meta_value = %s)
    ", $normalized_phone, $alt_phone));
    if (!$user_id) {
        return new WP_REST_Response(['error' => 'Данный номер не найден'], 404);
    }
    // Log in the user
    wp_set_auth_cookie($user_id);
    return new WP_REST_Response(['success' => 'Вход выполняется'], 200);
}

/**
 * Normalize phone number:
 * - Remove "+" if present
 * - Convert 8XXXXXXXXXX to 7XXXXXXXXXX
 * - Only allow numbers starting with 7
 */
function normalizePhoneNumber($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    // Ensure the first digit is always 7
    if (strpos($phone, '8') === 0) {
        $phone = '7' . substr($phone, 1);
    } elseif (strpos($phone, '7') !== 0) {
        return false; // Invalid format
    }
    // Ensure number has 11 digits
    if (strlen($phone) !== 11) {
        return false;
    }
    return $phone;
}

?>