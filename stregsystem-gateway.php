<?php
/*
Plugin Name: Stregsystem Gateway
Plugin URI: https://github.com/krestenlaust/WooCommerce-Stregkort-Gateway
Description: Pay using F-Club Stregkort for WooCommerce
Version: 1.0
Author: Kresten Laust
Author URI: https://fklub.dk
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

function stregsystem_gateway_activate() {
    if ( !class_exists( 'WooCommerce' ) || version_compare( get_option('woocommerce_version'), '7.0', '<' ) ) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce 7.0 or higher', 'my-plugin-textdomain'), 'Plugin dependency check', array('back_link' => true));
    }
}
register_activation_hook(__FILE__, 'stregsystem_gateway_activate');



add_action( 'plugins_loaded', 'initialize_my_payment_gateway' );

function initialize_my_payment_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;


    class WC_Stregsystem_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'stregsystem_gateway'; // Your gateway identifier
            $this->icon               = ''; // URL of the icon that will be displayed on checkout page
            $this->has_fields         = false; // True if you need custom credit card form
            $this->method_title       = 'Stregsystem Payment Gateway';
            $this->method_description = 'Pay with Stregkonto online';

            $this->supports = array( 'products', 'refunds' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Stregsystem Payment Gateway',
                    'type'        => 'checkbox',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'default'     => 'Stregsystem Payment Gateway',
                ),
                'api_endpoint' => array(
                    'title'       => 'API Endpoint',
                    'type'        => 'text',
                ),
                'api_key' => array(
                    'title'       => 'API Key',
                    'type'        => 'text'
                ),
                'api_secret' => array(
                    'title'       => 'API Secret',
                    'type'        => 'password'
                )
                // Additional fields here if necessary
            );
        }

        public function process_payment( $order_id ) {
            global $woocommerce;
            $order = wc_get_order( $order_id );
            $woocommerce->cart->empty_cart();

            // Get the API credentials.
            $api_key    = $this->get_option( 'api_key' );
            $api_secret = $this->get_option( 'api_secret' );

            // Simulate an API request.
            $response = $this->simulate_api_request($api_key, $api_secret, $order);

            if ($response['success']) {
                // Payment was successful.
                $order->payment_complete();

                // Save the transaction ID as order meta
                $order->set_transaction_id($response['transaction_id']);
                $order->save(); // Make sure to save the order to store meta data

                // Add order note
                $order->add_order_note( 'Payment completed using Stregkonto.' );

                // Return thankyou redirect
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            } else {
                // Payment failed.
                wc_add_notice( 'Payment error: ' . $response['message'], 'error' );
                return;
            }
        }

        private function simulate_api_request($api_key, $api_secret, $order) {
            $order_total = $order->get_total();

            // Here you'd normally make an HTTP request to your payment provider.
            // Include the order total in the request.
            // Below is a mock response to simulate a transaction.

            // Simulating a check for the correct amount.
            if ($this->is_correct_amount($order_total)) {
                // Mock response for successful transaction.
                return array(
                    'success' => true,
                    'transaction_id' => '123456', // Transaction ID from the payment provider.
                    'message' => 'Transaction successful'
                );
            } else {
                // Mock response for failed transaction due to incorrect amount.
                return array(
                    'success' => false,
                    'message' => 'Incorrect payment amount'
                );
            }
        }

        private function is_correct_amount($order_total) {
            // Here, you would compare the order total with the amount processed by the payment provider.
            // This is a mockup and always returns true for simplicity.
            return true; // In a real scenario, this should be the result of the amount check.
        }


        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );
            $transaction_id = $order->get_transaction_id();

            if ( ! $transaction_id ) {
                return new WP_Error( 'error', __( 'Refund failed: No transaction ID', 'woocommerce' ) );
            }

            // Check if the refund amount is valid
            if ( ! is_numeric( $amount ) || $amount <= 0 ) {
                return new WP_Error( 'error', __( 'Refund failed: Invalid amount', 'woocommerce' ) );
            }

            // Prepare your API request here. This is an example.
            $api_response = $this->api_refund_request( $transaction_id, $amount );

            if ( $api_response['success'] ) {
                // Refund was successful
                return true;
            } else {
                // Refund failed
                return new WP_Error( 'error', __( 'Refund failed: ' . $api_response['message'], 'woocommerce' ) );
            }
        }

        private function api_refund_request( $transaction_id, $amount ) {
            // This function would contain the logic to communicate with your payment API for refunds.
            // This is a mockup response.
            return array(
                'success' => true,  // or false in case of an error
                'message' => 'Refund processed successfully' // or the error message
            );
        }
    }
}



add_filter( 'woocommerce_payment_gateways', 'add_stregsystem_gateway' );
function add_stregsystem_gateway( $gateways ) {
    $gateways[] = 'WC_Stregsystem_Gateway';
    return $gateways;
}
