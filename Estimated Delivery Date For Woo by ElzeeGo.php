<?php
/**
 * Plugin Name: Estimated Delivery Date for WooCommerce
 * Description: Display estimated delivery date on product, cart, and checkout pages.
 * Version: 1.0
 * Author: https://elzeego.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Add settings to WooCommerce
function eddw_add_settings( $settings ) {
    $custom_settings = array(
        array(
            'title' => __( 'Estimated Delivery Date', 'woocommerce' ),
            'type'  => 'title',
            'desc'  => 'Customize the delivery date settings.',
            'id'    => 'eddw_options'
        ),
        array(
            'title'    => __( 'Minimum Estimated Delivery Days', 'woocommerce' ),
            'desc'     => __( 'Enter the minimum number of days for delivery', 'woocommerce' ),
            'id'       => 'woocommerce_min_estimated_delivery_days',
            'default'  => '2',
            'type'     => 'number',
        ),
        array(
            'title'    => __( 'Maximum Estimated Delivery Days', 'woocommerce' ),
            'desc'     => __( 'Enter the maximum number of days for delivery', 'woocommerce' ),
            'id'       => 'woocommerce_max_estimated_delivery_days',
            'default'  => '5',
            'type'     => 'number',
        ),
        array(
            'title'    => __( 'Exclude Weekends', 'woocommerce' ),
            'desc'     => __( 'Check to exclude weekends from the estimated delivery days', 'woocommerce' ),
            'id'       => 'woocommerce_exclude_weekends',
            'default'  => 'no',
            'type'     => 'checkbox',
        ),
        array(
            'title'    => __( 'Product Categories', 'woocommerce' ),
            'desc'     => __( 'Select the categories where the estimated delivery date should be displayed.', 'woocommerce' ),
            'id'       => 'woocommerce_eddw_categories',
            'class'    => 'wc-enhanced-select',
            'css'      => 'min-width:300px;',
            'default'  => '',
            'type'     => 'multiselect',
            'options'  => array_reduce( get_terms( 'product_cat', array('hide_empty' => false) ), function($carry, $item) {
                $carry[$item->term_id] = $item->name;
                return $carry;
            }, array() ),
            'desc_tip' => true,
        ),
        array(
            'title'    => __( 'Display Style', 'woocommerce' ),
            'desc'     => __( 'Select the style for displaying the estimated delivery date on the product page.', 'woocommerce' ),
            'id'       => 'woocommerce_eddw_display_style',
            'default'  => 'style_1',
            'type'     => 'select',
            'options'  => array(
                'style_1' => __( 'Style 1', 'woocommerce' ),
                'style_2' => __( 'Style 2', 'woocommerce' ),
                'style_3' => __( 'Style 3', 'woocommerce' ),
            ),
            'desc_tip' => true,
        ),
        array(
            'type' => 'sectionend',
            'id'   => 'eddw_options',
        ),
    );

    return array_merge( $settings, $custom_settings );
}

add_filter( 'woocommerce_general_settings', 'eddw_add_settings' );

// Calculate the estimated delivery date based on settings
function calculate_estimated_delivery_date() {
    $min_days = get_option( 'woocommerce_min_estimated_delivery_days', '2' );
    $max_days = get_option( 'woocommerce_max_estimated_delivery_days', '5' );
    $exclude_weekends = get_option( 'woocommerce_exclude_weekends', 'no' );

    $min_date = new DateTime();
    $max_date = new DateTime();

    if ($exclude_weekends === 'yes') {
        for ($i = 0; $i < $min_days; $i++) {
            $min_date->modify('+1 day');
            while ($min_date->format('N') >= 6) {
                $min_date->modify('+1 day');
            }
        }

        for ($i = 0; $i < $max_days; $i++) {
            $max_date->modify('+1 day');
            while ($max_date->format('N') >= 6) {
                $max_date->modify('+1 day');
            }
        }

        // Add additional 2 days for weekends exclusion
        $min_date->modify('+2 days');
        $max_date->modify('+2 days');
    } else {
        $min_date->modify("+$min_days days");
        $max_date->modify("+$max_days days");
    }

    // If delivery falls on a weekend, adjust to the next Monday
    while ($min_date->format('N') >= 6) {
        $min_date->modify('+1 day');
    }

    while ($max_date->format('N') >= 6) {
        $max_date->modify('+1 day');
    }

    return "Numatomas pristatymas tarp " . date_i18n('m-d-Y', $min_date->getTimestamp()) . " ir " . date_i18n('m-d-Y', $max_date->getTimestamp());
}


// Enqueue styles for the frontend
function eddw_enqueue_styles() {
    echo '
    <style>
        .eddw_style_1 {
            border: 1px solid #000;
            padding: 5px 10px;
            display: inline-block;
            margin: 10px 0;
        }
        
        .eddw_style_2 {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .eddw_style_3 {
            border: 2px dashed #000;
            padding: 5px 10px;
            font-style: italic;
            margin: 10px 0;
        }
    </style>';
}

add_action( 'wp_head', 'eddw_enqueue_styles' );

// Display on Product Page
function eddw_display_on_product_page() {
    global $product;
    $selected_categories = get_option('woocommerce_eddw_categories', array());
    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array("fields" => "ids"));

    if (array_intersect($selected_categories, $product_categories) || empty($selected_categories)) {
        $style = get_option('woocommerce_eddw_display_style', 'style_1');
        $estimated_dates = calculate_estimated_delivery_date();
        echo "<p class='eddw_$style'>$estimated_dates.</p>";
    }
}

add_action( 'woocommerce_after_add_to_cart_button', 'eddw_display_on_product_page' );

// Display on Cart and Checkout Pages
function eddw_display_on_cart_and_checkout() {
    $selected_categories = get_option('woocommerce_eddw_categories', array());

    if (empty($selected_categories)) {
        return;  // If no categories are selected, don't display the date.
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_categories = wp_get_post_terms($cart_item['product_id'], 'product_cat', array("fields" => "ids"));

        if (array_intersect($selected_categories, $product_categories)) {
            $estimated_dates = calculate_estimated_delivery_date();
            echo "<p>$estimated_dates.</p>";
            return;  // Display the date if at least one product matches the selected categories and then exit.
        }
    }
}

add_action( 'woocommerce_before_cart_totals', 'eddw_display_on_cart_and_checkout' );
add_action( 'woocommerce_review_order_before_payment', 'eddw_display_on_cart_and_checkout' );

?>