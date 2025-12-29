<?php

/**
 * all pages
 * sign-in form v.2.1
 */
function handle_custom_login() {
	// Verify nonce for security
	if (!isset($_POST['auth_nonce']) || !wp_verify_nonce($_POST['auth_nonce'], 'custom_auth_action')) {
		wp_send_json_error(['message' => 'Invalid nonce.']);
	}
	// Validate inputs
	$username = sanitize_text_field($_POST['login'] ?? '');
	$password = sanitize_text_field($_POST['password'] ?? '');
	$remember = isset($_POST['rememberme']) ? true : false;
	if (empty($username) || empty($password)) {
		wp_send_json_error(['message' => 'Username and password are required.']);
	}
	// Attempt to log in
	$credentials = [
		'user_login'    => $username,
		'user_password' => $password,
		'remember'      => $remember,
	];
	$user = wp_signon($credentials, false);
	if (is_wp_error($user)) {
		// Remove the <a>Forgot password</a> link from the error message
		$error_message = $user->get_error_message();
		$clean_message = preg_replace('/<a[^>]*>.*?<\/a>/', '', $error_message); // Remove <a> tag and its content
		wp_send_json_error(['message' => $clean_message]);
	}
	// Success: reload the current page
	wp_send_json_success(['reload' => true]);
}
add_action('wp_ajax_nopriv_custom_login', 'handle_custom_login', 70);
add_action('wp_ajax_custom_login', 'handle_custom_login', 70);