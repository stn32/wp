/**
 * cloud tags
 * function that creates a taxonomy cloud (categories, tags, and other custom taxonomies)
 */
function display_woocommerce_category_cloud() {
	if (!is_shop() && !is_product_category()) {
		return;
	}

	$output = '<div class="wc_tag_cloud">';
	$output .= '<div class="wс_tag_cloud_term">';

	// Get current category ID
	$current_cat_id = 0;
	if (is_product_category()) {
		$current_term = get_queried_object();
		if ($current_term && !is_wp_error($current_term)) {
			$current_cat_id = $current_term->term_id;
		}
	}

	// 1. Get all product categories
	$all_categories = get_terms(array(
		'taxonomy' => 'product_cat',
		'hide_empty' => true,
	));

	// 2. Filter only descendant categories
	if (!empty($all_categories) && !is_wp_error($all_categories)) {
		foreach ($all_categories as $term) {
			if ($current_cat_id === 0 || term_is_ancestor_of($current_cat_id, $term->term_id, 'product_cat')) {
				$term_link = get_term_link($term);
				$size = 12 + min($term->count / 5, 8);
				$output .= '<a href="' . esc_url($term_link) . '" class="term-link" style="font-size: ' . $size . 'px;" title="' . esc_attr($term->count) . ' products">';
				$output .= esc_html($term->name) . ' (' . $term->count . ')</a> ';
			}
		}
	}

	// 3. Add special product tags (from 'product_tag' taxonomy)
	// $special_tags = array('bestseller', 'choice-profi', 'novinki', 'outlet', 'pro-collection', 'sporty', 'python', 'bazovye-obrazy', 'antistress', 'wild-side', 'antistress', 'vydelyajsya', 'dlya-sebya', 'dlya-hishhnicz', 'set-beginers', 'obrazy-dlya-yarkih', 'podarok-podruge', 'stripani');
	$special_tags = array('bestseller', 'choice-profi', 'novinki', 'outlet');


	$special_terms = get_terms(array(
		'taxonomy' => 'product_tag',
		'hide_empty' => true,
		'slug' => $special_tags,
	));

	if (!empty($special_terms) && !is_wp_error($special_terms)) {
		foreach ($special_terms as $term) {
			$term_link = get_term_link($term);
			$output .= '<a href="' . esc_url($term_link) . '" class="special-term-link" title="' . esc_attr($term->count) . ' products">';
			$output .= esc_html($term->name) . '</a> ';
		}
	}

	$output .= '</div>'; // close .wc_tag_cloud_term
	$output .= '</div>'; // close .wc_tag_cloud
	$output .= '<div class="wс_tag_cloud_arrow"><img src="' . get_template_directory_uri() . '/assets/img/small_arrow.svg" alt="icon"></div>';

	echo $output;
}
add_action('woocommerce_after_shop_loop', 'display_woocommerce_category_cloud', 150);
