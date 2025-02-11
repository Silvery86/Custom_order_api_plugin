<?php
/*
Plugin Name: Plugin Manager API
Description: Create custom API
Version: 1.1
Author: Ablue-Dev
GitHub Plugin URI: https://github.com/Silvery86/Custom_order_api_plugin
GitHub Branch: main
*/


function get_orders_custom_by_date($data) {
    // Get custom day from GET request
    $custom_day = isset($_GET['custom_day']) ? intval($_GET['custom_day']) : 0;

    if ($custom_day <= 0) {
        echo 'Please provide a valid custom day value.';
        return;
    }

    $custom_days_ago = new \DateTime("-$custom_day days");
    $today = new \DateTime();

    $args = array(
        'date_created' => $custom_days_ago->format('Y-m-d') . '...' . $today->format('Y-m-d'),
        'limit' => -1,
    );
    $orders = wc_get_orders($args);

    if ($orders) {
        $order_data = array();

        foreach ($orders as $order) {
            try {
                if ($order->get_total() > 0) {
                    // Extracting product details
                    $order_products = array();
                    foreach ($order->get_items() as $item_id => $item) {
                        // Get product attributes
                        $meta_datas = $item->get_meta_data();

                        $pa_size = array();
                        foreach ($meta_datas as $meta_data) {
                            $pa_size[] = array(
                                $meta_data->key => $meta_data->value,
                            );
                        }

                        // Get product id
                        $product_id = $item->get_product_id();

                        // Get product sku
                        $product_sku = get_post_meta($product_id, '_sku', true);

                        // Get product image url
                        $image_url = get_the_post_thumbnail_url($product_id, 'full');

                        $order_products[] = array(
                            'product_sku' => $product_sku,
                            'item_name' => $item->get_name(),
                            'attribute' => $pa_size,
                            'meta_datas' => $meta_datas,
                            'quantity' => $item->get_quantity(),
                            'subtotal' => $item->get_subtotal(),
                            'variation_id' => $item->get_variation_id(),
                            'product_type' => $image_url,
                        );
                    }

                    // Combine first name and last name to full name
                    $first_name = $order->get_shipping_first_name();
                    $last_name = $order->get_shipping_last_name();
                    $full_name = $first_name . ' ' . $last_name;

                    // Get shipping address
                    $address_line_1 = $order->get_shipping_address_1();
                    $address_line_2 = $order->get_shipping_address_2();
                    $city = $order->get_shipping_city();
                    $state = $order->get_shipping_state();
                    $country = $order->get_shipping_country();
                    // Construct the complete address
                    $address_parts = array_filter([$address_line_1, $address_line_2]); // Remove empty parts
                    $complete_address = implode(', ', $address_parts);

                    // get domain name
                    $full_url = home_url();
                    $parsed_url = parse_url($full_url);
                    $domain = '';
                    if (isset($parsed_url['host'])) {
                        $domain = $parsed_url['host']; // Retrieve the domain name
                    }

                    // Get the order date and set to store's timezone
                    $store_timezone = new \DateTimeZone(wc_timezone_string());
                    $order_date = $order->get_date_created()->setTimezone($store_timezone);

                    // Get the customer ID
                    $customer_id = $order->get_customer_id();

                    // Get the first order date
                    $first_order_date = '';
                    $last_order_date = '';

                    $first_order = wc_get_orders(array(
                        'customer' => $customer_id,
                        'limit' => 1, // Retrieve the first order
                        'orderby' => 'date',
                        'order' => 'ASC', // Get the earliest order first
                    ));

                    if (!empty($first_order)) {
                        $first_order_date = $first_order[0]->get_date_created()->setTimezone($store_timezone);
                    }

                    // Get the last order date
                    $last_order = wc_get_orders(array(
                        'customer' => $customer_id,
                        'limit' => 1, // Retrieve the last order
                        'orderby' => 'date',
                        'order' => 'DESC', // Get the latest order first
                    ));

                    if (!empty($last_order)) {
                        $last_order_date = $last_order[0]->get_date_created()->setTimezone($store_timezone);
                    }

                    // If no previous orders, set first and last order dates to current order date
                    if (!$first_order_date) {
                        $first_order_date = $order_date;
                    }
                    if (!$last_order_date) {
                        $last_order_date = $order_date;
                    }

                    $order_meta_data = $order->get_meta_data();
                    $cs_paypal_payout = 0;
                    $cs_paypal_fee = 0;
                    $cs_stripe_fee = 0;
                    $cs_stripe_payout = 0;
                    $shield_pp = '';
                    foreach ($order_meta_data as $meta) {
                        if ($meta->key === '_cs_paypal_payout') {
                            $cs_paypal_payout = $meta->value;
                        }
                        if ($meta->key === '_cs_paypal_fee') {
                            $cs_paypal_fee = $meta->value;
                        }
                        if ($meta->key === '_mecom_paypal_proxy_url') {
                            $shield_pp = $meta->value;
                        }
                        if ($meta->key === '_cs_stripe_fee') {
                            $cs_stripe_fee = $meta->value;
                        }
                        if ($meta->key === '_cs_stripe_payout') {
                            $cs_stripe_payout = $meta->value;
                        }
                    }

                    $state_code = $order->get_shipping_state();
                    $country_code = $order->get_shipping_country();
                    $country_name = WC()->countries->get_countries()[$country_code];
                    if ($country_code) {
                        $state_name = WC()->countries->get_states($country_code)[$state_code];
                    }

                    $order_data[] = array(
                        'domain_name' => $domain,
                        'order_number' => $order->get_order_number(),
                        'revenue' => ($order->get_total() !== null) ? $order->get_total() : 0,
                        'paypal_fee' => $cs_paypal_fee,
                        'rev_paypal' => $cs_paypal_payout,
                        'stripe_fee' => $cs_stripe_fee,
                        'rev_stripe' => $cs_stripe_payout,
                        'quantity' => count($order->get_items()),
                        'base_cost' => '',
                        'tn' => '',
                        'carrier' => '',
                        'tn_status' => '',
                        'design' => '',
                        'order_id' => $order->get_id(),
                        'order_status' => $order->get_status(),
                        'order_date' => $order_date->format('Y-m-d H:i:s'),
                        'first_order_date' => $first_order_date->format('Y-m-d H:i:s'),
                        'last_order_date' => $last_order_date->format('Y-m-d H:i:s'),
                        'shipping_full_name' => $full_name,
                        'shipping_address' => $complete_address,
                        'company' => $order->get_shipping_company(),
                        'shipping_city' => $order->get_shipping_city(),
                        'shipping_state' => $state_name,
                        'shipping_postcode' => $order->get_shipping_postcode(),
                        'shipping_country' => $country_name,
                        'country_code' => $country_code,
                        'customer_note' => $order->get_customer_note(),
                        'billing_phone' => $order->get_billing_phone(),
                        'billing_email' => $order->get_billing_email(),
                        'shipping_amount' => $order->get_shipping_total(),
                        'shield_pp' => $shield_pp,
                        'payment_method' => $order->get_payment_method(),
                        'transaction_id' => $order->get_transaction_id(),
                        'products' => $order_products,
                        'meta_data' => $order_meta_data,
                    );
                }
            } catch (Exception $e) {
                echo "Error";
            }
        }
        header('Content-Type: application/json');
        echo json_encode($order_data);
        exit;
    } else {
        echo 'No orders found.';
    }
}

add_action('rest_api_init', function () {
    register_rest_route(
        'wc/v3',
        'get-orders',
        array(
            'methods' => 'GET',
            'callback' => 'get_orders_custom_by_date',
            'permission_callback' => function ($request) {
                return current_user_can('manage_options');
            },
        )
    );
});

