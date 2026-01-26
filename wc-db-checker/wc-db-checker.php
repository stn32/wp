<?php
/**
 * Plugin Name: WooCommerce Product Checker
 * Plugin URI: https://example.com/woocommerce-product-checker
 * Description: A plugin to check and repair WooCommerce variable products for missing meta without modifying unless repaired. Includes cron for auto-repair.
 * Version: 1.2.0
 * Author: stn32
 * Author URI: https://stn32
 * License: GPL-2.0+
 * Text Domain: wc-product-checker
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

// Initialize with low priority (after other plugins)
add_action( 'plugins_loaded', 'wc_product_checker_init', 999 );

function wc_product_checker_init() {

    /**
     * Class WC_Product_Checker
     */
    class WC_Product_Checker {

        /**
         * Constructor
         */
        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            // Cron hook
            add_action( 'wc_product_checker_repair_cron', array( $this, 'run_auto_repair' ) );
        }

        /**
         * Add admin menu page
         */
        public function add_admin_menu() {
            add_submenu_page(
                'woocommerce',
                __( 'Product Checker', 'wc-product-checker' ),
                __( 'Product Checker', 'wc-product-checker' ),
                'manage_woocommerce',
                'wc-product-checker',
                array( $this, 'render_page' )
            );
        }

        /**
         * Enqueue scripts and styles
         */
        public function enqueue_scripts( $hook ) {
            if ( 'woocommerce_page_wc-product-checker' !== $hook ) {
                return;
            }
            wp_enqueue_script( 'wc-product-checker-js', plugin_dir_url( __FILE__ ) . 'js/wc-db-checker.js', array( 'jquery' ), '1.2.0', true );
            wp_localize_script( 'wc-product-checker-js', 'wc_product_checker', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wc-product-checker-nonce' ),
            ) );
        }

        /**
         * Render the admin page
         */
        public function render_page() {
            $auto_repair_enabled = get_option( 'wc_product_checker_auto_repair_enabled', false );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'WooCommerce Product Checker', 'wc-product-checker' ); ?></h1>
                <p><?php esc_html_e( 'This tool checks and repairs variable products. Checks do not modify data.', 'wc-product-checker' ); ?></p>

                <div id="wc-product-checker-container">
                    <!-- Products Check -->
                    <h2><?php esc_html_e( 'Check Products', 'wc-product-checker' ); ?></h2>
                    <button id="check-products" class="button button-primary"><?php esc_html_e( 'Check Products', 'wc-product-checker' ); ?></button>
                    <div id="products-result" style="margin-top: 10px; border: 1px solid #ddd; padding: 10px; background: #fff; min-height: 100px;"></div>

                    <!-- Repair Products -->
                    <h2><?php esc_html_e( 'Repair Products', 'wc-product-checker' ); ?></h2>
                    <button id="repair-products" class="button button-secondary"><?php esc_html_e( 'Repair All Products (Sync & Save)', 'wc-product-checker' ); ?></button>
                    <div id="repair-result" style="margin-top: 10px; border: 1px solid #ddd; padding: 10px; background: #fff; min-height: 100px;"></div>

                    <!-- Auto Repair -->
                    <h2><?php esc_html_e( 'Auto Repair (Cron)', 'wc-product-checker' ); ?></h2>
                    <p><?php esc_html_e( 'Enable automatic repair every hour.', 'wc-product-checker' ); ?></p>
                    <button id="enable-auto-repair" class="button <?php echo $auto_repair_enabled ? 'button-disabled' : 'button-primary'; ?>" <?php echo $auto_repair_enabled ? 'disabled' : ''; ?>><?php esc_html_e( 'Enable Regular Repair', 'wc-product-checker' ); ?></button>
                    <button id="disable-auto-repair" class="button <?php echo !$auto_repair_enabled ? 'button-disabled' : 'button-primary'; ?>" <?php echo !$auto_repair_enabled ? 'disabled' : ''; ?>><?php esc_html_e( 'Disable Regular Repair', 'wc-product-checker' ); ?></button>
                    <div id="auto-repair-result" style="margin-top: 10px; border: 1px solid #ddd; padding: 10px; background: #fff; min-height: 50px;"></div>
                </div>
            </div>
            <?php
        }

        /**
         * Run auto repair if enabled
         */
        public function run_auto_repair() {
            if ( get_option( 'wc_product_checker_auto_repair_enabled', false ) ) {
                wc_db_repair_products( true ); // Pass true for cron mode (no JSON response)
            }
        }
    }

    // Instantiate the class
    new WC_Product_Checker();

    // AJAX handlers
    add_action( 'wp_ajax_check_products', 'wc_db_check_products' );
    add_action( 'wp_ajax_repair_products', 'wc_db_repair_products' );
    add_action( 'wp_ajax_toggle_auto_repair', 'wc_db_toggle_auto_repair' );

    /**
     * AJAX handler for checking products
     */
    function wc_db_check_products() {
        check_ajax_referer( 'wc-product-checker-nonce', 'nonce' );

        $errors = array();

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'variable',
                ),
            ),
        );
        $products = new WP_Query( $args );

        if ( $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product( $product_id );

                if ( ! $product->is_type( 'variable' ) ) {
                    continue;
                }

                $min_variation_price = get_post_meta( $product_id, '_min_variation_price', true );

                if ( ! metadata_exists( 'post', $product_id, '_stock_status' ) ) {
                    $errors[] = sprintf( 'Product ID %d: Missing _stock_status meta.', $product_id );
                }

                $variations = $product->get_children();
                if ( empty( $variations ) ) {
                    $errors[] = sprintf( 'Product ID %d: No variations found.', $product_id );
                    continue;
                }

                $actual_min_price = PHP_FLOAT_MAX;
                $has_valid_variation = false;

                foreach ( $variations as $variation_id ) {
                    if ( get_post_status( $variation_id ) !== 'publish' ) {
                        continue;
                    }

                    $var_regular_price = get_post_meta( $variation_id, '_regular_price', true );
                    $var_sale_price = get_post_meta( $variation_id, '_sale_price', true );
                    $var_price = ! empty( $var_sale_price ) ? $var_sale_price : $var_regular_price;

                    if ( empty( $var_regular_price ) ) {
                        $errors[] = sprintf( 'Variation ID %d (parent %d): Missing or empty _regular_price meta.', $variation_id, $product_id );
                    } else {
                        $has_valid_variation = true;
                    }
                    if ( ! metadata_exists( 'post', $variation_id, '_price' ) || get_post_meta( $variation_id, '_price', true ) === '' ) {
                        $errors[] = sprintf( 'Variation ID %d (parent %d): Missing or empty _price meta.', $variation_id, $product_id );
                    }
                    if ( ! metadata_exists( 'post', $variation_id, '_stock' ) ) {
                        $errors[] = sprintf( 'Variation ID %d (parent %d): Missing _stock meta.', $variation_id, $product_id );
                    }
                    if ( ! metadata_exists( 'post', $variation_id, '_stock_status' ) ) {
                        $errors[] = sprintf( 'Variation ID %d (parent %d): Missing _stock_status meta.', $variation_id, $product_id );
                    }
                    if ( ! metadata_exists( 'post', $variation_id, '_manage_stock' ) ) {
                        $errors[] = sprintf( 'Variation ID %d (parent %d): Missing _manage_stock meta.', $variation_id, $product_id );
                    }

                    if ( ! empty( $var_price ) && is_numeric( $var_price ) ) {
                        $actual_min_price = min( $actual_min_price, (float) $var_price );
                    }
                }

                if ( $has_valid_variation ) {
                    if ( ! metadata_exists( 'post', $product_id, '_min_variation_price' ) || $min_variation_price === '' ) {
                        $errors[] = sprintf( 'Product ID %d: Missing or empty _min_variation_price meta (should be %s based on variations).', $product_id, $actual_min_price );
                    } elseif ( (float) $min_variation_price !== $actual_min_price ) {
                        $errors[] = sprintf( 'Product ID %d: Mismatch in _min_variation_price (%s) vs actual min from variations (%s).', $product_id, $min_variation_price, $actual_min_price );
                    }
                } else {
                    $errors[] = sprintf( 'Product ID %d: No valid variations with prices found.', $product_id );
                }
            }
            wp_reset_postdata();
        }

        if ( empty( $errors ) ) {
            $result = __( 'No errors found in products.', 'wc-product-checker' );
        } else {
            $result = implode( '<br>', $errors );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for repairing products (also used in cron)
     * @param bool $cron_mode If true, no JSON response
     */
    function wc_db_repair_products( $cron_mode = false ) {
        if ( ! $cron_mode ) {
            check_ajax_referer( 'wc-product-checker-nonce', 'nonce' );
        }

        $repaired = array();
        $failed = array();

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'variable',
                ),
            ),
        );
        $products = new WP_Query( $args );

        if ( $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product( $product_id );

                if ( ! $product->is_type( 'variable' ) ) {
                    continue;
                }

                try {
                    // First, fix variations stock status if inconsistent
                    $variations = $product->get_children();
                    foreach ( $variations as $variation_id ) {
                        $variation = wc_get_product( $variation_id );
                        if ( ! $variation ) continue;

                        $manage_stock = $variation->managing_stock(); // Checks _manage_stock
                        $stock_qty = (int) $variation->get_stock_quantity();
                        $current_status = $variation->get_stock_status();

                        // If stock > 0 and manage_stock, force 'instock' if not set
                        if ( $manage_stock && $stock_qty > 0 && $current_status !== 'instock' ) {
                            $variation->set_stock_status( 'instock' );
                            $variation->save();
                        }
                    }

                    // Now sync parent from variations
                    WC_Product_Variable::sync( $product_id );
                    WC_Product_Variable::sync_stock_status( $product_id );

                    // Clear transients to force refresh
                    wc_delete_product_transients( $product_id );

                    // Final save
                    $product->save();

                    $repaired[] = sprintf( 'Product ID %d: Repaired (synced variations, status, and saved).', $product_id );
                } catch ( Exception $e ) {
                    $failed[] = sprintf( 'Product ID %d: Failed to repair - %s', $product_id, $e->getMessage() );
                }
            }
            wp_reset_postdata();
        }

        if ( $cron_mode ) {
            if ( ! empty( $failed ) ) {
                error_log( 'WC Product Checker Cron: Failed repairs: ' . implode( ', ', $failed ) );
            }
            return;
        }

        $result = '';
        if ( ! empty( $repaired ) ) {
            $result .= 'Repaired:<br>' . implode( '<br>', $repaired ) . '<br>';
        }
        if ( ! empty( $failed ) ) {
            $result .= 'Failed:<br>' . implode( '<br>', $failed );
        }
        if ( empty( $repaired ) && empty( $failed ) ) {
            $result = __( 'No products to repair.', 'wc-product-checker' );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for toggling auto repair
     */
    function wc_db_toggle_auto_repair() {
        check_ajax_referer( 'wc-product-checker-nonce', 'nonce' );

        $enable = sanitize_text_field( $_POST['enable'] ) === 'true';
        update_option( 'wc_product_checker_auto_repair_enabled', $enable );

        if ( $enable ) {
            if ( ! wp_next_scheduled( 'wc_product_checker_repair_cron' ) ) {
                wp_schedule_event( time(), 'hourly', 'wc_product_checker_repair_cron' );
            }
            $result = __( 'Auto repair enabled (every hour).', 'wc-product-checker' );
        } else {
            wp_clear_scheduled_hook( 'wc_product_checker_repair_cron' );
            $result = __( 'Auto repair disabled.', 'wc-product-checker' );
        }

        wp_send_json_success( $result );
    }
}
