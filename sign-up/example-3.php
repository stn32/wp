<?php
/**
 * my account
 * bonus getmeback v.2.1
 * display
 */
function get_user_bonuses_by_phone($phone) {
	$phone = normalize_russian_phone($phone);

	if (empty($phone)) {
		error_log('GetMeBack Error: Empty or invalid phone number');
		return false;
	}

	// Optional cache — remove this block if you want real-time data
	$cache_key = 'getmeback_bonuses_' . md5($phone);
	if ($cached = get_transient($cache_key)) {
		return $cached;
	}

	$api_url = 'https://my-account.getmeback.ru/rest/base/v33/validator/client/';
	$api_key = 'mmmmmmmmmmmmmmmmmm'; // xxxxx

	$args = [
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		],
		'body'    => json_encode(['phone' => $phone]),
		'timeout' => 15,
	];

	$response = wp_remote_post($api_url, $args);

	if (is_wp_error($response)) {
		error_log('GetMeBack API Error: ' . $response->get_error_message());
		return false;
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);

	if (empty($data['client'])) {
		error_log('GetMeBack Error: No client data returned');
		return false;
	}

	set_transient($cache_key, $data['client'], HOUR_IN_SECONDS);
	return $data['client'];
}