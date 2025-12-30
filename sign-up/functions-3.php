<?php

/**
 * sign-up form v.2.3
 * Custom registration function
 */
function custom_register_user() {

	if (!isset($_POST['auth_nonce']) || !wp_verify_nonce($_POST['auth_nonce'], 'custom_auth_action')) {
		wp_send_json_error(['message' => 'Security check failed.']);
	}

	$username = sanitize_text_field($_POST['username']);
	$email    = sanitize_email($_POST['email']);
	$password = sanitize_text_field($_POST['password']);
	$phone    = sanitize_text_field($_POST['phone']);

	if (empty($username) || empty($email) || empty($password) || empty($phone)) {
		wp_send_json_error(['message' => 'All fields are required.']);
	}

	$username = transliterate_cyrillic_to_latin($username);
	$username = sanitize_user($username, true);

	if (empty($username)) {
		wp_send_json_error(['message' => 'Имя пользователя не может быть пустым после обработки.']);
	}

	if (username_exists($username)) {
		wp_send_json_error(['message' => 'Такое имя уже занято']);
	}

	if (email_exists($email)) {
		wp_send_json_error(['message' => 'Email уже используется.']);
	}

	$phone = preg_replace('/\D/', '', $phone);

	// If phone number starts with 8, replace with 7
	if (strlen($phone) > 0 && $phone[0] === '8') {
		$phone[0] = '7';
	}

	$user_id = wp_create_user($username, $password, $email);

	if (is_wp_error($user_id)) {
		wp_send_json_error(['message' => $user_id->get_error_message()]);
	}

	// WooCommerce meta
	update_user_meta($user_id, 'billing_phone', $phone);
	update_user_meta($user_id, 'billing_country', 'RU');
	update_user_meta($user_id, 'shipping_country', 'RU');
	update_user_meta($user_id, 'billing_state', '');
	update_user_meta($user_id, 'shipping_state', '');

	// Auto-login
	wp_clear_auth_cookie();
	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id);

	// ----------------------------------------------------
	// email with bonus message
	// mmm
	// ----------------------------------------------------
	$subject = 'Спасибо за регистрацию на сайте Fitpole';
	$message = '
			<html>
			<body style="font-family: Arial; font-size: 16px; color: #333;">
					<h2>💗🪽 Привет, Fitpole girl!</h2>
					<p style="margin: 10px 0 0 0";>Теперь ты часть комьюнити Fitpole - места, где танец раскрывает энергию и грацию, превращая каждое движение в удовольствие.</p>
					<p>Всё, что нужно танцору - в одном месте🫦</p>
					<p>Мы начислили тебе 500 приветственных баллов -  используй их для покупок в течение 1 месяца.</p>
					<p>🎀 Зайди в <a href="https://fitpole-store.ru/account/">личный кабинет</a>, чтобы заполнить профиль.</p>
					<p>Добро пожаловать в Fitpole. Потанцуй с нами🫦</p>
			</body>
			</html>
	';
	$headers = [
		'Content-Type: text/html; charset=UTF-8'
	];
	wp_mail($email, $subject, $message, $headers);
	// ----------------------------------------------------

	wp_send_json_success(['message' => 'Регистрация успешна.']);
}
add_action('wp_ajax_custom_register', 'custom_register_user', 70);
add_action('wp_ajax_nopriv_custom_register', 'custom_register_user', 70);