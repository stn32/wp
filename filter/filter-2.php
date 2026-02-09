<?php
/**
 * Plugin Name: filter-s32-v3
 * Description: Plugin for filtering products. For product loop.
 * Author:      stn32
 * Requires at least: 5.7
 * Requires PHP: 8.2
 * Version:     4.4
 */

if (!defined('ABSPATH')) exit;

// Create container for filters
function add_container_for_filters() {
    echo '<div class="filters_stn32_2024_cover"></div>';
    echo '<div class="filters_2024 filters_stn32_2024">';
    echo '<form method="get" class="filters_form">';

    if (is_shop() || is_product_category() || is_product_tag() || is_search()) {
        $available_tags = array();
        $available_colors = array();
        $available_sizes = array();
        $available_materials = array();
        $available_heights = array();
        $available_heights_protect = array();
        $available_brend = array();
        $available_categories = array();
        $available_heels = array(); // NEW: for vysota-kabluka
        $stock_statuses = array();

        $current_category_slug = '';
        if (is_product_category()) {
            $current_category = get_queried_object();
            $current_category_slug = $current_category->slug;
        }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids', // IMPROVED: only IDs for performance
        );

        $tax_query = array('relation' => 'AND');

        if (isset($_GET['tag']) && is_array($_GET['tag'])) {
            $tag_filter = array_map('sanitize_text_field', $_GET['tag']);
            if (!empty($tag_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_filter,
                );
            }
        }

        if (isset($_GET['czvet']) && is_array($_GET['czvet'])) { // FIXED: uncommented to apply color filter for available terms
            $color_filter = array_map('sanitize_text_field', $_GET['czvet']);
            if (!empty($color_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_czvet',
                    'field'    => 'slug',
                    'terms'    => $color_filter,
                );
            }
        }

        if (isset($_GET['razmer']) && is_array($_GET['razmer'])) {
            $size_filter = array_map('sanitize_text_field', $_GET['razmer']);
            if (!empty($size_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_razmer',
                    'field'    => 'slug',
                    'terms'    => $size_filter,
                );
            }
        }

        if (isset($_GET['material']) && is_array($_GET['material'])) {
            $material_filter = array_map('sanitize_text_field', $_GET['material']);
            if (!empty($material_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_material',
                    'field'    => 'slug',
                    'terms'    => $material_filter,
                );
            }
        }

        if (isset($_GET['vysota']) && is_array($_GET['vysota'])) {
            $height_filter = array_map('sanitize_text_field', $_GET['vysota']);
            if (!empty($height_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_vysota',
                    'field'    => 'slug',
                    'terms'    => $height_filter,
                );
            }
        }

        if (isset($_GET['vysota-kabluka']) && is_array($_GET['vysota-kabluka'])) { // NEW: apply filter for new attribute
            $heel_filter = array_map('sanitize_text_field', $_GET['vysota-kabluka']);
            if (!empty($heel_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_vysota-kabluka',
                    'field'    => 'slug',
                    'terms'    => $heel_filter,
                );
            }
        }

        if (isset($_GET['vysota-zashhity']) && is_array($_GET['vysota-zashhity'])) {
            $height_protect_filter = array_map('sanitize_text_field', $_GET['vysota-zashhity']);
            if (!empty($height_protect_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_vysota-zashhity',
                    'field'    => 'slug',
                    'terms'    => $height_protect_filter,
                );
            }
        }

        if (isset($_GET['brend']) && is_array($_GET['brend'])) {
            $brend_filter = array_map('sanitize_text_field', $_GET['brend']);
            if (!empty($brend_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_brend',
                    'field'    => 'slug',
                    'terms'    => $brend_filter,
                );
            }
        }

        if (isset($_GET['category']) && is_array($_GET['category'])) {
            $category_filter = array_map('sanitize_text_field', $_GET['category']);
            if (!empty($category_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category_filter,
                );
            }
        } elseif (!empty($current_category_slug)) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $current_category_slug,
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        // IMPROVED: add transient caching (key based on filters)
        $cache_key = 'filter_available_terms_' . md5(serialize($_GET));
        $product_ids = get_transient($cache_key);
        if (false === $product_ids) {
            $query = new WP_Query($args);
            $product_ids = $query->posts; // Already IDs due to fields=ids
            set_transient($cache_key, $product_ids, 300); // 5 min cache
        }

        if (!empty($product_ids)) {
            $available_tags = wp_get_object_terms($product_ids, 'product_tag', array('hide_empty' => true, 'orderby' => 'name')); // IMPROVED: sorted
            $available_colors = wp_get_object_terms($product_ids, 'pa_czvet', array('hide_empty' => true, 'orderby' => 'name'));
            $available_sizes = wp_get_object_terms($product_ids, 'pa_razmer', array('hide_empty' => true, 'orderby' => 'name'));
            $available_materials = wp_get_object_terms($product_ids, 'pa_material', array('hide_empty' => true, 'orderby' => 'name'));
            $available_heights = wp_get_object_terms($product_ids, 'pa_vysota', array('hide_empty' => true, 'orderby' => 'name'));
            $available_heights_protect = wp_get_object_terms($product_ids, 'pa_vysota-zashhity', array('hide_empty' => true, 'orderby' => 'name'));
            $available_brend = wp_get_object_terms($product_ids, 'pa_brend', array('hide_empty' => true, 'orderby' => 'name'));
            $available_categories = wp_get_object_terms($product_ids, 'product_cat', array('hide_empty' => true, 'orderby' => 'name'));
            $available_heels = wp_get_object_terms($product_ids, 'pa_vysota-kabluka', array('hide_empty' => true, 'orderby' => 'name')); // NEW

            // IMPROVED: make stock dynamic based on filtered products
            $stock_statuses = array();
            $unique_stocks = array_unique(array_filter(array_map(function($id) {
                return get_post_meta($id, '_stock_status', true);
            }, $product_ids)));
            foreach ($unique_stocks as $status) {
                if ($status === 'instock') { // Keep only instock as per original logic; expand if needed
                    $stock_statuses[$status] = 'В наличии';
                }
            }
        }

        if (!empty($available_tags)) {
            echo '<div class="filter-option filter-option-tags">';
            echo '<p>Теги</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_tags as $tag) {
                if (is_object($tag)) {
                    $checked = isset($_GET['tag']) && in_array($tag->slug, (array)$_GET['tag']) ? 'checked' : '';
                    echo '<label class="tag-' . esc_attr($tag->slug) . '"><input type="checkbox" name="tag[]" value="' . esc_attr($tag->slug) . '" ' . $checked . '><span>' . esc_html($tag->name) . '</span></label>';
                }
            }
            echo '</div>';
        }

        if (!empty($available_categories)) {
            echo '<div class="filter-option filter-option-category">';
            echo '<p>Категория</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_categories as $category) {
                if (is_object($category)) {
                    $checked = (isset($_GET['category']) && in_array($category->slug, (array)$_GET['category'])) || ($current_category_slug === $category->slug) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="category[]" value="' . esc_attr($category->slug) . '" ' . $checked . '><span>' . esc_html($category->name) . '</span></label>';
                }
            }
            echo '</div>';
        }

        if (!empty($available_colors)) {
            echo '<div class="filter-option filter-option-color">';
            echo '<p>Цвет</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_colors as $color) {
                $checked = isset($_GET['czvet']) && in_array($color->slug, (array)$_GET['czvet']) ? 'checked' : '';
                echo '<label><input type="checkbox" name="czvet[]" value="' . esc_attr($color->slug) . '" ' . $checked . '><b></b><span>' . esc_html($color->name) . '</span></label>';
            }
            echo '</div>';
        }

        if (!empty($available_sizes)) {
            echo '<div class="filter-option filter-option-size">';
            echo '<p>Размер</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_sizes as $size) {
                if (is_object($size)) {
                    $checked = isset($_GET['razmer']) && in_array($size->slug, (array)$_GET['razmer']) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="razmer[]" value="' . esc_attr($size->slug) . '" ' . $checked . '><span>' . esc_html($size->name) . '</span></label>';
                }
            }
            echo '</div>';
        }    

        if (!empty($available_materials)) {
            echo '<div class="filter-option filter-option-material">';
            echo '<p>Материал</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_materials as $material) {
                if (is_object($material)) {
                    $checked = isset($_GET['material']) && in_array($material->slug, (array)$_GET['material']) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="material[]" value="' . esc_attr($material->slug) . '" ' . $checked . '><span>' . esc_html($material->name) . '</span></label>';
                }
            }
            echo '</div>';
        }        

        if (!empty($available_heights)) {
            echo '<div class="filter-option filter-option-height">';
            echo '<p>Высота (ботильоны, стрипы)</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_heights as $height) {
                if (is_object($height)) {
                    $checked = isset($_GET['vysota']) && in_array($height->slug, (array)$_GET['vysota']) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="vysota[]" value="' . esc_attr($height->slug) . '" ' . $checked . '><span>' . esc_html($height->name) . '</span></label>';
                }
            }
            echo '</div>';
        }

        if (!empty($available_heels)) { // NEW: echo for vysota-kabluka
            echo '<div class="filter-option filter-option-heel">';
            echo '<p>Высота каблука (хилсы)</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_heels as $heel) {
                if (is_object($heel)) {
                    $checked = isset($_GET['vysota-kabluka']) && in_array($heel->slug, (array)$_GET['vysota-kabluka']) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="vysota-kabluka[]" value="' . esc_attr($heel->slug) . '" ' . $checked . '><span>' . esc_html($heel->name) . '</span></label>';
                }
            }
            echo '</div>';
        }

        if (!empty($available_heights_protect)) {
            echo '<div class="filter-option filter-option-height-protect">';
            echo '<p>Высота защиты</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_heights_protect as $height_protect) {
                if (is_object($height_protect)) {
                    $checked = isset($_GET['vysota-zashhity']) && in_array($height_protect->slug, (array)$_GET['vysota-zashhity']) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="vysota-zashhity[]" value="' . esc_attr($height_protect->slug) . '" ' . $checked . '><span>' . esc_html($height_protect->name) . '</span></label>';
                }
            }
            echo '</div>';
        }

        if (!empty($available_brend)) {
            echo '<div class="filter-option filter-option-brend">';
            echo '<p>Бренд</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($available_brend as $brend) {
                if (is_object($brend)) {
                    $checked = isset($_GET['brend']) && in_array($brend->slug, (array)$_GET['brend']) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="brend[]" value="' . esc_attr($brend->slug) . '" ' . $checked . '><span>' . esc_html($brend->name) . '</span></label>';
                }
            }
            echo '</div>';
        }

        // Display stock status filter options
        if (!empty($stock_statuses)) {
            echo '<div class="filter-option filter-option-stock">';
            echo '<p>Статус</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            foreach ($stock_statuses as $status => $label) {
                $checked = isset($_GET['stock_status']) && in_array($status, (array)$_GET['stock_status']) ? 'checked' : '';
                echo '<label><input type="checkbox" name="stock_status[]" value="' . esc_attr($status) . '" ' . $checked . '>' . esc_html($label) . '</label>';
            }
            echo '</div>';
        }
    }

    echo '<div class="filter-button"><button class="filters_2024_apply" type="submit">Применить</button></div>';
    echo '<div class="filter-button"><button class="filters_2024_reset" type="button" name="filters_2024_clear_btn">Очистить</button></div>';
    echo '</form>';

    // Check if we are on the main shop page with exact "/shop/" URL
    if (is_shop() && strpos($_SERVER['REQUEST_URI'], '/shop/') !== false && strlen($_SERVER['REQUEST_URI']) == strlen('/shop/')) {
        // Create container for backorder filters
        echo '<div class="filters_2024_backorder">';
        echo '<form method="get" class="backorder_form">';
        echo '<div class="filter-option">';
        echo '<label><input type="checkbox" name="backorder" value="1" ' . (isset($_GET['backorder']) ? 'checked' : '') . '>Товары для предзаказа</label>';
        echo '</div>';
        echo '<div class="filter-button"><button type="submit">Показать</button></div>';
        echo '</form>';
        echo '</div>';
    }

    echo '</div>';
}
add_action('woocommerce_before_shop_loop', 'add_container_for_filters', 160);

// Redirect to shop
add_action('template_redirect', 'redirect_filter_to_shop', 160);
function redirect_filter_to_shop() {
    $current_url = home_url(add_query_arg([]));
    $shop_url = get_permalink(wc_get_page_id('shop'));

    $filters = ['tag', 'czvet', 'razmer', 'material', 'vysota', 'vysota-zashhity', 'brend', 'stock_status', 'category', 'vysota-kabluka']; // NEW: added

    $has_filters = false;
    $query_args = [];

    foreach ($_GET as $key => $value) {
        if (in_array($key, $filters) && !empty($value)) {
            $has_filters = true;
        }
        $query_args[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
    }

    if ($has_filters && strpos($current_url, '/shop/') === false) {
        $redirect_url = add_query_arg($query_args, $shop_url);
        wp_redirect($redirect_url);
        exit;
    }
}

// Filter process
add_action('woocommerce_product_query', 'filter_products_by_attributes', 160);
function filter_products_by_attributes($q) {
    if (is_shop() || is_product_category() || is_search()) {
        $tax_query = array('relation' => 'AND');
        $meta_query = array();

        if (isset($_GET['tag']) && is_array($_GET['tag'])) {
            $tag_filter = array_map('sanitize_text_field', $_GET['tag']);
            if (!empty($tag_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_filter,
                );
            }
        }

        if (isset($_GET['czvet']) && is_array($_GET['czvet'])) {
            $color_filter = array_map('sanitize_text_field', $_GET['czvet']);
            if (!empty($color_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_czvet',
                    'hide_empty' => false,
                    'field'    => 'slug',
                    'terms'    => $color_filter,
                );
            }
        }

        if (isset($_GET['razmer']) && is_array($_GET['razmer'])) {
            $size_filter = array_map('sanitize_text_field', $_GET['razmer']);
            if (!empty($size_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_razmer',
                    'field'    => 'slug',
                    'terms'    => $size_filter,
                );
            }
        }

        if (isset($_GET['material']) && is_array($_GET['material'])) {
            $material_filter = array_map('sanitize_text_field', $_GET['material']);
            if (!empty($material_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_material',
                    'field'    => 'slug',
                    'terms'    => $material_filter,
                );
            }
        }

        if (isset($_GET['vysota']) && is_array($_GET['vysota'])) {
            $height_filter = array_map('sanitize_text_field', $_GET['vysota']);
            if (!empty($height_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_vysota',
                    'field'    => 'slug',
                    'terms'    => $height_filter,
                );
            }
        }

        if (isset($_GET['vysota-zashhity']) && is_array($_GET['vysota-zashhity'])) {
            $height_filter_protect = array_map('sanitize_text_field', $_GET['vysota-zashhity']);
            if (!empty($height_filter_protect)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_vysota-zashhity',
                    'field'    => 'slug',
                    'terms'    => $height_filter_protect,
                );
            }
        }

        if (isset($_GET['brend']) && is_array($_GET['brend'])) {
            $filter_brend_a = array_map('sanitize_text_field', $_GET['brend']);
            if (!empty($filter_brend_a)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_brend',
                    'field'    => 'slug',
                    'terms'    => $filter_brend_a,
                );
            }
        }

        if (isset($_GET['vysota-kabluka']) && is_array($_GET['vysota-kabluka'])) { // NEW
            $heel_filter = array_map('sanitize_text_field', $_GET['vysota-kabluka']);
            if (!empty($heel_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_vysota-kabluka',
                    'field'    => 'slug',
                    'terms'    => $heel_filter,
                );
            }
        }

        if (isset($_GET['category']) && is_array($_GET['category'])) {
            $category_filter = array_map('sanitize_text_field', $_GET['category']);
            if (!empty($category_filter)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category_filter,
                );
            }
        }

        if (isset($_GET['stock_status']) && is_array($_GET['stock_status'])) {
            $stock_status_filter = array_map('sanitize_text_field', $_GET['stock_status']);
            if (!empty($stock_status_filter)) {
                $meta_query[] = array(
                    'key'     => '_stock_status',
                    'value'   => $stock_status_filter,
                    'compare' => 'IN',
                );
            }
        }

        if (is_product_category()) {
            $current_category = get_queried_object();
            $current_category_slug = $current_category->slug;
            if (!empty($current_category_slug)) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $current_category_slug,
                );
            }
        }

        if (!empty($tax_query)) {
            $q->set('tax_query', $tax_query);
        }

        if (!empty($meta_query)) {
            $q->set('meta_query', $meta_query);
        }
    }
}

// Redirect if no products
add_action('template_redirect', 'redirect_if_no_filtered_products_with_alert', 170);
function redirect_if_no_filtered_products_with_alert() {
    if (is_shop() && !is_admin() && (isset($_GET['czvet']) || isset($_GET['razmer']) || isset($_GET['tag']) || isset($_GET['material']) || isset($_GET['vysota']) || isset($_GET['vysota-zashhity']) || isset($_GET['brend']) || isset($_GET['category']) || isset($_GET['stock_status']) || isset($_GET['vysota-kabluka']))) { // NEW: added
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 1,
            'tax_query' => array('relation' => 'AND')
        );

        if (isset($_GET['tag']) && is_array($_GET['tag'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['tag']),
            );
        }

        if (isset($_GET['czvet']) && is_array($_GET['czvet'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_czvet',
                'hide_empty' => false,
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['czvet']),
            );
        }

        if (isset($_GET['razmer']) && is_array($_GET['razmer'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_razmer',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['razmer']),
            );
        }

        if (isset($_GET['material']) && is_array($_GET['material'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_material',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['material']),
            );
        }

        if (isset($_GET['vysota']) && is_array($_GET['vysota'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_vysota',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['vysota']),
            );
        }

        if (isset($_GET['vysota-zashhity']) && is_array($_GET['vysota-zashhity'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_vysota-zashhity',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['vysota-zashhity']),
            );
        }

        if (isset($_GET['brend']) && is_array($_GET['brend'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_brend',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['brend']),
            );
        }

        if (isset($_GET['vysota-kabluka']) && is_array($_GET['vysota-kabluka'])) { // NEW
            $args['tax_query'][] = array(
                'taxonomy' => 'pa_vysota-kabluka',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['vysota-kabluka']),
            );
        }

        if (isset($_GET['category']) && is_array($_GET['category'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_text_field', $_GET['category']),
            );
        }

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            add_action('wp_footer', 'no_products_alert_and_back_js');
        }

        wp_reset_postdata();
    }
}
function no_products_alert_and_back_js() {
    ?>
    <script type="text/javascript">
        setTimeout(() => {
            let siteMainBlock = document.querySelector('.site-main');
            let newBtnBack = document.createElement('div');
            newBtnBack.classList.add('filter-back-box');
            let btnContent = `
                <div class="filter-back-box-cover"></div>
                <div class="filter-back-box-content">
                    <p>По данному запросу товаров не найдено</p>
                    <button>Назад</button>
                </div>
            `;
            newBtnBack.innerHTML = btnContent;
            siteMainBlock.appendChild(newBtnBack);
        }, 100);
        setTimeout(() => {
            let newBtnBackAgain = document.querySelector('.filter-back-box-content button');
            if (newBtnBackAgain) {
                newBtnBackAgain.addEventListener('click', () => {
                    window.history.back();
                })
            }
        }, 1000);
    </script>
    <?php
}

// backorder filters process
add_action('woocommerce_product_query', 'filter_products_by_backorder', 170);
function filter_products_by_backorder($q) {
    if ((is_shop() || is_product_category()) && isset($_GET['backorder']) && $_GET['backorder'] == '1') {
        global $wpdb;

        $query = "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->posts} v ON p.ID = v.post_parent
            INNER JOIN {$wpdb->postmeta} pm ON v.ID = pm.post_id
            WHERE p.post_type = 'product'
              AND v.post_type = 'product_variation'
              AND pm.meta_key = '_stock_status'
              AND pm.meta_value = 'onbackorder'
              AND p.post_status = 'publish'
        ";

        $product_ids = $wpdb->get_col($query);

        if (!empty($product_ids)) {
            $q->set('post__in', $product_ids);
        } else {
            $q->set('post__in', array(0));
        }
    }
}

/**
 * Enqueue JS
 */
function filter_s32_enqueue_scripts() {
    wp_enqueue_script(
        'filter-js',
        plugin_dir_url(__FILE__) . 'src-8/filter.js',
        array(),
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'filter_s32_enqueue_scripts', 170);