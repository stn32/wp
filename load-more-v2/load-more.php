<?php
/**
 * Plugin Name: load-more-v2
 * Description: Adds a reliable "Load More" button to WooCommerce product archives.
 * Author:      stn32
 * Version:     2.1.2
 */

if (!defined('ABSPATH')) exit;

/**
 * Add Load More button + localize data AFTER WooCommerce loop
 */
add_action('woocommerce_after_shop_loop', 's32_load_more_add_load_more_btn', 800);
function s32_load_more_add_load_more_btn() {
    global $wp_query;

    if ($wp_query->max_num_pages <= 1) {
        return;
    }

    $plugin_url = plugin_dir_url(__FILE__);
    $version    = '2.2';

    // Стили
    wp_enqueue_style(
        's32-load-more',
        $plugin_url . 'src-3/style-3.css',
        [],
        $version
    );

    // Скрипт
    wp_enqueue_script(
        's32-load-more',
        $plugin_url . 'src-3/loadmore-3.js',
        [],
        $version,
        true
    );

    wp_localize_script('s32-load-more', 's32_loadmore', [
        'ajaxurl'      => admin_url('admin-ajax.php'),
        'query_vars'   => $wp_query->query_vars,
        'current_page' => max(1, get_query_var('paged')),
        'max_page'     => $wp_query->max_num_pages,
    ]);

    echo '<div class="s32-load-more-wrap">';
    echo '<button id="s32-load-more" class="s32-load-more-btn" data-page="1">Показать ещё</button>';
    echo '</div>';
}

/**
 * AJAX Handler
 */
add_action('wp_ajax_s32_load_more', 's32_load_more_handler', 800);
add_action('wp_ajax_nopriv_s32_load_more', 's32_load_more_handler', 800);

function s32_load_more_handler() {
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $query_vars = isset($_POST['query_vars']) ? json_decode(stripslashes($_POST['query_vars']), true) : [];

    $query_vars['paged'] = $paged;
    $query_vars['post_status'] = 'publish';

    $query = new WP_Query($query_vars);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }
    }
    wp_reset_postdata();
    wp_die();
}
