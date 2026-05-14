<?php
/**
 * Plugin Name: filter-s32
 * Description: Simplified plugin for filtering WooCommerce products by attributes, with stock check for sizes.
 * Author:      stn32
 * Requires at least: 5.7
 * Requires PHP: 8.2
 * Version:     5.5.1
 */

if (!defined('ABSPATH')) exit;

// Enqueue JS (kept as is)
function filter_s32_enqueue_scripts() {
    if (! (is_shop() || is_product_category() || is_product_tag() || is_search())) return;

    wp_enqueue_script(
        'filter-js',
        plugin_dir_url(__FILE__) . 'src-10/filter-10.js',
        array(),
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'filter_s32_enqueue_scripts', 999);

// Display filters
function add_container_for_filters() {
    if (! (is_shop() || is_product_category() || is_product_tag() || is_search())) return;

    echo '<div class="filters_stn32_2024_cover"></div>';
    echo '<div class="filters_2024 filters_stn32_2024">';
    echo '<form method="get" class="filters_form">';

    $available_terms = array(
        'tags' => array(),
        'colors' => array(),
        'sizes' => array(),
        'materials' => array(),
        'heights' => array(),
        'heights_protect' => array(),
        'brends' => array(),
        'categories' => array(),
        'heels' => array(),
        'stock' => array(),
    );

    $current_category_slug = is_product_category() ? get_queried_object()->slug : '';

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
    );

    $tax_query = array('relation' => 'AND');
    $filters = ['tag', 'czvet', 'razmer', 'material', 'vysota', 'vysota-zashhity', 'brend', 'vysota-kabluka', 'category'];

    foreach ($filters as $filter) {
        if (isset($_GET[$filter]) && is_array($_GET[$filter])) {
            $terms = array_map('sanitize_text_field', $_GET[$filter]);
            if (!empty($terms)) {
                $taxonomy = ($filter === 'tag') ? 'product_tag' : (($filter === 'category') ? 'product_cat' : "pa_$filter");
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $terms,
                );
            }
        }
    }

    if (empty($_GET['category']) && $current_category_slug) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $current_category_slug,
        );
    }

    if (!empty($tax_query)) $args['tax_query'] = $tax_query;



    // $query = new WP_Query($args);
    // if ($query->have_posts()) {
    //     $product_ids = $query->posts;

    //     $available_terms['tags'] = wp_get_object_terms($product_ids, 'product_tag', ['hide_empty' => true]);
    //     $available_terms['colors'] = wp_get_object_terms($product_ids, 'pa_czvet', ['hide_empty' => true]);
    //     $available_terms['sizes'] = wp_get_object_terms($product_ids, 'pa_razmer', ['hide_empty' => true]);
    //     $available_terms['materials'] = wp_get_object_terms($product_ids, 'pa_material', ['hide_empty' => true]);
    //     $available_terms['heights'] = wp_get_object_terms($product_ids, 'pa_vysota', ['hide_empty' => true]);
    //     $available_terms['heights_protect'] = wp_get_object_terms($product_ids, 'pa_vysota-zashhity', ['hide_empty' => true]);
    //     $available_terms['brends'] = wp_get_object_terms($product_ids, 'pa_brend', ['hide_empty' => true]);
    //     $available_terms['categories'] = wp_get_object_terms($product_ids, 'product_cat', ['hide_empty' => true]);
    //     $available_terms['heels'] = wp_get_object_terms($product_ids, 'pa_vysota-kabluka', ['hide_empty' => true]);

    //     // Stock (simplified to 'instock')
    //     $stock_args = $args;
    //     $stock_args['meta_query'] = array(
    //         array(
    //             'key' => '_stock_status',
    //             'value' => 'instock',
    //             'compare' => '=',
    //         ),
    //     );
    //     $stock_query = new WP_Query($stock_args);
    //     if ($stock_query->have_posts()) {
    //         $available_terms['stock']['instock'] = 'В наличии';
    //     }
    // }
    // wp_reset_postdata();


    /**
     * Всегда показывать все термы таксономий
     * filter-option-height (vysota)
     * filter-option-heel (vysota-kabluka)
     * Убрал две строки с $available_terms['heights'] и $available_terms['heels'] из общего списка
     * Добавил блок get_terms(...) для обеих таксономий (т.е. только те, у которых есть хотя бы один привязанный товар в каталоге — мёртвые термы не всплывут).
     */
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $product_ids = $query->posts;

        $available_terms['tags'] = wp_get_object_terms($product_ids, 'product_tag', ['hide_empty' => true]);
        $available_terms['colors'] = wp_get_object_terms($product_ids, 'pa_czvet', ['hide_empty' => true]);
        $available_terms['sizes'] = wp_get_object_terms($product_ids, 'pa_razmer', ['hide_empty' => true]);
        $available_terms['materials'] = wp_get_object_terms($product_ids, 'pa_material', ['hide_empty' => true]);
        $available_terms['heights_protect'] = wp_get_object_terms($product_ids, 'pa_vysota-zashhity', ['hide_empty' => true]);
        $available_terms['brends'] = wp_get_object_terms($product_ids, 'pa_brend', ['hide_empty' => true]);
        $available_terms['categories'] = wp_get_object_terms($product_ids, 'product_cat', ['hide_empty' => true]);

        // --- Исключение: height и heel показываем всегда из всего каталога ---
        // Чтобы товары с этими атрибутами всегда можно было добавить к уже применённым фильтрам.
        $all_heights = get_terms(array(
            'taxonomy'   => 'pa_vysota',
            'hide_empty' => true,
        ));
        $available_terms['heights'] = (!is_wp_error($all_heights) && !empty($all_heights)) ? $all_heights : array();

        $all_heels = get_terms(array(
            'taxonomy'   => 'pa_vysota-kabluka',
            'hide_empty' => true,
        ));
        $available_terms['heels'] = (!is_wp_error($all_heels) && !empty($all_heels)) ? $all_heels : array();

        // Stock (simplified to 'instock')
        $stock_args = $args;
        $stock_args['meta_query'] = array(
            array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '=',
            ),
        );
        $stock_query = new WP_Query($stock_args);
        if ($stock_query->have_posts()) {
            $available_terms['stock']['instock'] = 'В наличии';
        }
    }
    wp_reset_postdata();



    // Render filter sections in the specified order: Теги, Категория, Размер, Цвет, Материал, Высота, Высота каблука, Высота защиты, Бренд, Статус
    $order = [
        'tags' => ['title' => 'Теги', 'name' => 'tag', 'class' => 'filter-option-tags'],
        'categories' => ['title' => 'Категория', 'name' => 'category', 'class' => 'filter-option-category'],
        'sizes' => ['title' => 'Размер', 'name' => 'razmer', 'class' => 'filter-option-size'],
        'colors' => ['title' => 'Цвет', 'name' => 'czvet', 'class' => 'filter-option-color'],
        'materials' => ['title' => 'Материал', 'name' => 'material', 'class' => 'filter-option-material'],
        'heights' => ['title' => 'Высота (стрипы, ботильоны)', 'name' => 'vysota', 'class' => 'filter-option-height'],
        'heels' => ['title' => 'Высота каблука (хилсы)', 'name' => 'vysota-kabluka', 'class' => 'filter-option-heel'],
        'heights_protect' => ['title' => 'Высота защиты', 'name' => 'vysota-zashhity', 'class' => 'filter-option-height-protect'],
        'brends' => ['title' => 'Бренд', 'name' => 'brend', 'class' => 'filter-option-brend'],
        'stock' => ['title' => 'Статус', 'name' => 'stock_status', 'class' => 'filter-option-stock'],
    ];

    foreach ($order as $key => $section) {
        if (!empty($available_terms[$key])) {
            echo '<div class="filter-option ' . esc_attr($section['class']) . '">';
            echo '<p>' . esc_html($section['title']) . '</p>';
            echo '<span class="arrow-icon"><img src="' . esc_url(get_template_directory_uri() . '/assets/img/arrow_right.svg') . '" alt="arrow"></span>';
            if ($key === 'stock') {
                foreach ($available_terms[$key] as $status => $label) {
                    $checked = isset($_GET[$section['name']]) && in_array($status, (array)$_GET[$section['name']]) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="' . esc_attr($section['name']) . '[]" value="' . esc_attr($status) . '" ' . $checked . '><span>' . esc_html($label) . '</span></label>';
                }
            } elseif ($key === 'colors') {
                foreach ($available_terms[$key] as $term) {
                    $checked = isset($_GET[$section['name']]) && in_array($term->slug, (array)$_GET[$section['name']]) ? 'checked' : '';
                    echo '<label><input type="checkbox" name="' . esc_attr($section['name']) . '[]" value="' . esc_attr($term->slug) . '" ' . $checked . '><b></b><span>' . esc_html($term->name) . '</span></label>';
                }
            } else {
                foreach ($available_terms[$key] as $term) {
                    $checked = (isset($_GET[$section['name']]) && in_array($term->slug, (array)$_GET[$section['name']])) 
                        || ($key === 'categories' && $current_category_slug === $term->slug) ? 'checked' : '';
                    $extra_class = ($key === 'tags') ? 'tag-' . esc_attr($term->slug) : '';
                    echo '<label class="' . $extra_class . '"><input type="checkbox" name="' . esc_attr($section['name']) . '[]" value="' . esc_attr($term->slug) . '" ' . $checked . '><span>' . esc_html($term->name) . '</span></label>';
                }
            }
            echo '</div>';
        }
    }

    echo '<div class="filter-button"><button class="filters_2024_apply" type="submit">Применить</button></div>';
    echo '<div class="filter-button"><button class="filters_2024_reset" type="button" name="filters_2024_clear_btn">Очистить</button></div>';
    echo '</form>';

    // Backorder filter (restored)
    if (is_shop() && strpos($_SERVER['REQUEST_URI'], '/shop/') !== false && strlen($_SERVER['REQUEST_URI']) == strlen('/shop/')) {
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
add_action('woocommerce_before_shop_loop', 'add_container_for_filters', 999);

// Redirect to shop if filters applied outside /shop/
add_action('template_redirect', 'redirect_filter_to_shop', 999);
function redirect_filter_to_shop() {

    if (is_admin() || is_singular() || is_front_page()) return;

    $current_url = home_url(add_query_arg([]));
    $shop_url = get_permalink(wc_get_page_id('shop'));
    $filters = ['tag', 'czvet', 'razmer', 'material', 'vysota', 'vysota-zashhity', 'brend', 'stock_status', 'category', 'vysota-kabluka'];

    $has_filters = false;
    $query_args = $_GET;

    foreach ($filters as $filter) {
        if (!empty($_GET[$filter])) $has_filters = true;
    }

    if ($has_filters && strpos($current_url, '/shop/') === false) {
        wp_redirect(add_query_arg($query_args, $shop_url));
        exit;
    }
}

// Apply filters
add_action('woocommerce_product_query', 'filter_products_by_attributes', 999);
function filter_products_by_attributes($q) {
    if (! (is_shop() || is_product_category() || is_search())) return;

    $tax_query = $q->get('tax_query') ?: array('relation' => 'AND');
    $meta_query = $q->get('meta_query') ?: array();

    $filters = [
        'tag' => 'product_tag',
        'czvet' => 'pa_czvet',
        'razmer' => 'pa_razmer',
        'material' => 'pa_material',
        'vysota' => 'pa_vysota',
        'vysota-zashhity' => 'pa_vysota-zashhity',
        'brend' => 'pa_brend',
        'vysota-kabluka' => 'pa_vysota-kabluka',
        'category' => 'product_cat',
    ];

    foreach ($filters as $get_key => $taxonomy) {
        if (isset($_GET[$get_key]) && is_array($_GET[$get_key])) {
            $terms = array_map('sanitize_text_field', $_GET[$get_key]);
            if (!empty($terms)) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $terms,
                    'operator' => 'IN',
                );
            }
        }
    }

    if (isset($_GET['stock_status']) && is_array($_GET['stock_status'])) {
        $stock_filter = array_map('sanitize_text_field', $_GET['stock_status']);
        if (!empty($stock_filter)) {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => $stock_filter,
                'compare' => 'IN',
            );
        }
    }

    if (is_product_category() && empty($_GET['category'])) {
        $current_category_slug = get_queried_object()->slug;
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $current_category_slug,
        );
    }

    $q->set('tax_query', $tax_query);
    $q->set('meta_query', $meta_query);

    // Custom size filter with stock check for variations
    if (isset($_GET['razmer']) && !empty($_GET['razmer'])) {
        $q->set('razmer_filter_active', true);
        $q->set('razmer_terms', array_map('sanitize_text_field', (array)$_GET['razmer']));
        add_filter('posts_clauses', 'filter_variations_by_size_and_stock_clauses', 999, 2);
    }
}

// SQL clauses for size stock filter
function filter_variations_by_size_and_stock_clauses($clauses, $query) {
    global $wpdb;

    if (!$query->get('razmer_filter_active')) return $clauses;

    $size_terms = $query->get('razmer_terms', array());
    if (empty($size_terms)) return $clauses;

    $size_terms_sql = implode("','", array_map('esc_sql', $size_terms));

    $clauses['join'] .= "
        INNER JOIN {$wpdb->posts} AS variations ON ({$wpdb->posts}.ID = variations.post_parent AND variations.post_type = 'product_variation')
        INNER JOIN {$wpdb->postmeta} AS variation_stock ON (variations.ID = variation_stock.post_id AND variation_stock.meta_key = '_stock_status')
        INNER JOIN {$wpdb->postmeta} AS variation_size ON (variations.ID = variation_size.post_id AND variation_size.meta_key = 'attribute_pa_razmer')
    ";

    $clauses['where'] .= " AND variation_size.meta_value IN ('$size_terms_sql') AND variation_stock.meta_value IN ('instock', 'onbackorder')";

    $clauses['groupby'] = "{$wpdb->posts}.ID";

    $clauses['having'] = 'COUNT(DISTINCT variations.ID) > 0';

    remove_filter('posts_clauses', 'filter_variations_by_size_and_stock_clauses', 999);

    return $clauses;
}

// Backorder filter process (restored)
add_action('woocommerce_product_query', 'filter_products_by_backorder', 999);
function filter_products_by_backorder($q) {
    if ((is_shop() || is_product_category()) && isset($_GET['backorder']) && $_GET['backorder'] == '1') {
        global $wpdb;

        // Query to get product IDs with at least one variation on backorder
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
            // Если нет товаров — гарантированно получить "0 результатов" без "пустой страницы"
            $q->set('post__in', array(-1));
            $q->set('posts_per_page', 0);
        }
    }
}

// Redirect if no products
add_action('template_redirect', 'redirect_if_no_filtered_products_with_alert', 999);
function redirect_if_no_filtered_products_with_alert() {
    if (is_admin()) {
        return;
    }

    // Works on shop and product category pages
    if (!is_shop() && !is_product_category()) {
        return;
    }

    $filter_map = array(
        'tag'              => 'product_tag',
        'czvet'            => 'pa_czvet',
        'razmer'           => 'pa_razmer',
        'material'         => 'pa_material',
        'vysota'           => 'pa_vysota',
        'vysota-zashhity'  => 'pa_vysota-zashhity',
        'brend'            => 'pa_brend',
        'category'         => 'product_cat',
        'vysota-kabluka'   => 'pa_vysota-kabluka',
    );

    $has_filters = false;

    foreach ($filter_map as $get_key => $taxonomy) {
        if (isset($_GET[$get_key]) && is_array($_GET[$get_key]) && !empty($_GET[$get_key])) {
            $has_filters = true;
            break;
        }
    }

    if (isset($_GET['stock_status']) && is_array($_GET['stock_status']) && !empty($_GET['stock_status'])) {
        $has_filters = true;
    }

    if (!$has_filters) {
        return;
    }

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'tax_query'      => array(
            'relation' => 'AND'
        ),
    );

    // Keep current category context if user is on category archive
    // and category[] is not explicitly passed in URL
    if (is_product_category() && (empty($_GET['category']) || !is_array($_GET['category']))) {
        $current_category = get_queried_object();

        if (!empty($current_category) && !empty($current_category->slug)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => array($current_category->slug),
            );
        }
    }

    foreach ($filter_map as $get_key => $taxonomy) {
        if (isset($_GET[$get_key]) && is_array($_GET[$get_key])) {
            $terms = array_filter(array_map('sanitize_text_field', $_GET[$get_key]));

            if (!empty($terms)) {
                $args['tax_query'][] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $terms,
                );
            }
        }
    }

    // Stock status support
    if (isset($_GET['stock_status']) && is_array($_GET['stock_status'])) {
        $stock_terms = array_filter(array_map('sanitize_text_field', $_GET['stock_status']));

        if (!empty($stock_terms)) {
            // If only "instock" is used in your form, this is enough
            $args['meta_query'] = array(
                array(
                    'key'     => '_stock_status',
                    'value'   => $stock_terms,
                    'compare' => 'IN',
                ),
            );
        }
    }

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        add_action('wp_footer', 'no_products_alert_and_back_js', 999);
    }

    wp_reset_postdata();
}

function no_products_alert_and_back_js() {
    $shop_url = wc_get_page_permalink('shop');
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            const siteMainBlock = document.querySelector('.site-main');
            if (!siteMainBlock) return;
            if (document.querySelector('.filter-back-box')) return;

            const newBtnBack = document.createElement('div');
            newBtnBack.classList.add('filter-back-box');

            newBtnBack.innerHTML = `
                <div class="filter-back-box-cover"></div>
                <div class="filter-back-box-content">
                    <p>По данному запросу товаров не найдено</p>
                    <div class="filter-back-box-buttons">
                        <button type="button" class="filter-back-btn">Назад</button>
                        <a style="text-align: center; width: 100%; display: block; margin: 10px 0 0 0;" href="<?php echo esc_url($shop_url); ?>" class="filter-reset-btn">В общий каталог</a>
                    </div>
                </div>
            `;

            siteMainBlock.appendChild(newBtnBack);

            const backBtn = newBtnBack.querySelector('.filter-back-btn');

            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    const referrer = document.referrer ? new URL(document.referrer) : null;
                    const current   = new URL(window.location.href);
                    const shopUrl   = '<?php echo esc_js($shop_url); ?>';

                    // Use referrer only if same origin and different from current URL
                    if (
                        referrer &&
                        referrer.origin === current.origin &&
                        referrer.href !== current.href
                    ) {
                        window.location.href = referrer.href;
                        return;
                    }

                    // Fallback: remove all filters and go to shop
                    window.location.href = shopUrl;
                });
            }
        });
    </script>
    <?php
}