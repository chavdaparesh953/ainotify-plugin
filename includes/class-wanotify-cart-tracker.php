<?php
/**
 * WaNotify Cart Tracker.
 *
 * Tracks checkout activity and phone numbers in real-time to trigger
 * automated 30-minute abandoned cart recovery WhatsApp messages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WaNotify_Cart_Tracker {

    /**
     * API Client instance.
     * @var WaNotify_API_Client
     */
    protected $api_client;

    /**
     * Constructor.
     */
    public function __construct( WaNotify_API_Client $api_client ) {
        $this->api_client = $api_client;
        $this->init_hooks();
    }

    /**
     * Register hooks.
     */
    protected function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // AJAX handlers for guest and logged-in users
        add_action( 'wp_ajax_wanotify_track_cart', array( $this, 'handle_cart_track_ajax' ) );
        add_action( 'wp_ajax_nopriv_wanotify_track_cart', array( $this, 'handle_cart_track_ajax' ) );

        // Clear cart session when an order is completed
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_completed_clear_cart' ) );
    }

    /**
     * Enqueue tracking script on checkout and cart pages.
     */
    public function enqueue_scripts() {
        $settings = get_option( 'wanotify_settings', array() );
        $enabled  = ! empty( $settings['abandoned_cart_enabled'] ) ? $settings['abandoned_cart_enabled'] : 'yes';

        if ( 'yes' !== $enabled || ! $this->api_client->is_configured() ) {
            return;
        }

        if ( is_checkout() || is_cart() ) {
            wp_enqueue_script(
                'wanotify-cart-tracker',
                WANOTIFY_PLUGIN_URL . 'assets/js/cart-tracker.js',
                array( 'jquery' ),
                WANOTIFY_VERSION,
                true
            );

            wp_localize_script(
                'wanotify-cart-tracker',
                'wanotifyCartConfig',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'wanotify_cart_track_nonce' ),
                )
            );
        }
    }

    /**
     * Handle incoming AJAX cart tracking beacon.
     */
    public function handle_cart_track_ajax() {
        check_ajax_referer( 'wanotify_cart_track_nonce', 'nonce' );

        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

        // Strip non-digits from phone (allow leading +)
        $clean_phone = preg_replace( '/[^\d+]/', '', $phone );

        if ( empty( $clean_phone ) && empty( $email ) ) {
            wp_send_json_error( array( 'message' => 'No contact info provided' ) );
        }

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            wp_send_json_error( array( 'message' => 'Cart is empty' ) );
        }

        $cart = WC()->cart;
        $items = array();

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product   = $cart_item['data'];
            $image_url = '';
            if ( $product && $product->get_image_id() ) {
                $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
            }

            $items[] = array(
                'product_id' => $cart_item['product_id'],
                'name'       => $product ? $product->get_name() : '',
                'quantity'   => $cart_item['quantity'],
                'price'      => (float) ( $product ? $product->get_price() : 0 ),
                'subtotal'   => (float) $cart_item['line_subtotal'],
                'image'      => $image_url,
            );
        }

        // Get checkout URL with pre-filled session if supported
        $checkout_url = wc_get_checkout_url();

        // Unique cart token based on session or cookie
        $token = WC()->session ? WC()->session->get_customer_id() : md5( $clean_phone . $email . time() );

        $payload = array(
            'cart_token'    => $token,
            'customer'      => array(
                'phone'      => $clean_phone,
                'email'      => $email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'name'       => trim( $first_name . ' ' . $last_name ),
            ),
            'currency'      => get_woocommerce_currency(),
            'total'         => (float) $cart->get_total( 'edit' ),
            'subtotal'      => (float) $cart->get_subtotal(),
            'item_count'    => $cart->get_cart_contents_count(),
            'items'         => $items,
            'checkout_url'  => $checkout_url,
            'timestamp'     => time(),
        );

        // Send non-blocking cart event to SaaS backend
        $this->api_client->dispatch_event( 'cart.abandoned', $payload, true );

        wp_send_json_success( array( 'tracked' => true, 'cart_token' => $token ) );
    }

    /**
     * When order is placed, mark cart as recovered.
     */
    public function on_order_completed_clear_cart( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();

        if ( empty( $phone ) && empty( $email ) ) {
            return;
        }

        $payload = array(
            'order_id' => $order_id,
            'phone'    => $phone,
            'email'    => $email,
            'status'   => 'recovered',
        );

        $this->api_client->dispatch_event( 'cart.recovered', $payload, true );
    }
}
