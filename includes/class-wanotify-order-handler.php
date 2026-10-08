<?php
/**
 * WaNotify Order Handler.
 *
 * Hooks into WooCommerce order lifecycle to send webhooks to WaNotify,
 * and exposes REST endpoints for two-way WhatsApp COD verification and order updates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WaNotify_Order_Handler {

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
        // Order Creation
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_created' ), 20, 3 );

        // Order Status Changed
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 20, 4 );

        // REST API routes for two-way sync
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * When a new order is placed at checkout.
     *
     * @param int      $order_id
     * @param array    $posted_data
     * @param WC_Order $order
     */
    public function on_order_created( $order_id, $posted_data = null, $order = null ) {
        $settings = get_option( 'wanotify_settings', array() );
        $enabled  = ! empty( $settings['order_notifications_enabled'] ) ? $settings['order_notifications_enabled'] : 'yes';

        if ( 'yes' !== $enabled && ! ! empty( $settings['cod_enabled'] ) ) {
            return;
        }

        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }

        $payload = $this->build_order_payload( $order );
        $this->api_client->dispatch_event( 'order.created', $payload, true );
    }

    /**
     * When order status changes.
     *
     * @param int      $order_id
     * @param string   $old_status
     * @param string   $new_status
     * @param WC_Order $order
     */
    public function on_order_status_changed( $order_id, $old_status, $new_status, $order = null ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }

        // Avoid infinite loop if triggered by our own REST write-back
        if ( defined( 'WANOTIFY_INTERNAL_UPDATE' ) && WANOTIFY_INTERNAL_UPDATE ) {
            return;
        }

        $payload = $this->build_order_payload( $order );
        $payload['status_transition'] = array(
            'from' => $old_status,
            'to'   => $new_status,
        );

        $this->api_client->dispatch_event( 'order.updated', $payload, true );
    }

    /**
     * Format WC_Order into a standardized payload.
     *
     * @param WC_Order $order
     * @return array
     */
    public function build_order_payload( WC_Order $order ) {
        $items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $image_url = '';
            if ( $product && $product->get_image_id() ) {
                $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
            }

            $items[] = array(
                'id'         => $item_id,
                'name'       => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'quantity'   => $item->get_quantity(),
                'subtotal'   => (float) $item->get_subtotal(),
                'total'      => (float) $item->get_total(),
                'sku'        => $product ? $product->get_sku() : '',
                'image'      => $image_url,
            );
        }

        $payment_method = $order->get_payment_method();
        $is_cod = ( 'cod' === strtolower( $payment_method ) || stripos( $order->get_payment_method_title(), 'cash' ) !== false );

        return array(
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'status'         => $order->get_status(),
            'currency'       => $order->get_currency(),
            'total'          => (float) $order->get_total(),
            'subtotal'       => (float) $order->get_subtotal(),
            'shipping_total' => (float) $order->get_shipping_total(),
            'payment_method' => $payment_method,
            'payment_method_title' => $order->get_payment_method_title(),
            'is_cod'         => $is_cod,
            'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : gmdate( 'c' ),
            'customer'       => array(
                'id'         => $order->get_customer_id(),
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'name'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'phone'      => $order->get_billing_phone(),
                'email'      => $order->get_billing_email(),
            ),
            'billing'        => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'company'    => $order->get_billing_company(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
            ),
            'shipping'       => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name'  => $order->get_shipping_last_name(),
                'company'    => $order->get_shipping_company(),
                'address_1'  => $order->get_shipping_address_1(),
                'address_2'  => $order->get_shipping_address_2(),
                'city'       => $order->get_shipping_city(),
                'state'      => $order->get_shipping_state(),
                'postcode'   => $order->get_shipping_postcode(),
                'country'    => $order->get_shipping_country(),
            ),
            'line_items'     => $items,
        );
    }

    /**
     * Register REST API routes for two-way synchronization.
     */
    public function register_rest_routes() {
        register_rest_route( 'wanotify/v1', '/order-action', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_order_action' ),
            'permission_callback' => array( $this, 'check_rest_permission' ),
        ) );
    }

    /**
     * Verify incoming REST permission via API key or signature.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_rest_permission( WP_REST_Request $request ) {
        $secret  = $this->api_client->get_webhook_secret();
        $api_key = $this->api_client->get_api_key();

        // 1. Check API Key header
        $incoming_key = $request->get_header( 'x_wanotify_api_key' );
        if ( ! empty( $api_key ) && ! empty( $incoming_key ) && hash_equals( $api_key, $incoming_key ) ) {
            return true;
        }

        // 2. Check HMAC signature
        $incoming_sig = $request->get_header( 'x_wc_webhook_signature' );
        if ( ! empty( $secret ) && ! empty( $incoming_sig ) ) {
            $body = $request->get_body();
            $expected = base64_encode( hash_hmac( 'sha256', $body, $secret, true ) );
            if ( hash_equals( $expected, $incoming_sig ) ) {
                return true;
            }
        }

        // 3. Fallback check for basic authorization header
        $auth_header = $request->get_header( 'authorization' );
        if ( ! empty( $api_key ) && ! empty( $auth_header ) ) {
            if ( 'Bearer ' . $api_key === $auth_header ) {
                return true;
            }
        }

        return new WP_Error( 'rest_forbidden', __( 'Invalid WaNotify authorization signature or API key.', 'wanotify' ), array( 'status' => 401 ) );
    }

    /**
     * Handle incoming action from WaNotify (e.g., WhatsApp COD button clicked).
     *
     * Expected parameters:
     * - order_id: WC Order ID
     * - action: 'CONFIRMED' | 'CANCELLED' | 'NOTE'
     * - note: Optional custom note
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_order_action( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        $action   = strtoupper( sanitize_text_field( $request->get_param( 'action' ) ) );
        $custom_note = sanitize_text_field( $request->get_param( 'note' ) );

        if ( ! $order_id ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Missing order_id',
            ), 400 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Order not found',
            ), 404 );
        }

        if ( ! defined( 'WANOTIFY_INTERNAL_UPDATE' ) ) {
            define( 'WANOTIFY_INTERNAL_UPDATE', true );
        }

        $timestamp = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

        if ( in_array( $action, array( 'CONFIRMED', 'CONFIRM_COD' ), true ) ) {
            // Confirm COD: Set to processing
            $order->update_status( 'processing', __( 'WaNotify: COD confirmed by customer via WhatsApp.', 'wanotify' ) );
            $note = ! empty( $custom_note ) ? $custom_note : sprintf( __( '[WaNotify] Cash on Delivery verified via WhatsApp at %s', 'wanotify' ), $timestamp );
            $order->add_order_note( $note, false );
            $order->update_meta_data( '_wanotify_cod_verified', 'yes' );
            $order->update_meta_data( '_wanotify_cod_verified_at', time() );
            $order->save();

            return new WP_REST_Response( array(
                'success'  => true,
                'order_id' => $order_id,
                'status'   => 'processing',
                'action'   => 'CONFIRMED',
                'note'     => $note,
            ), 200 );
        }

        if ( in_array( $action, array( 'CANCELLED', 'CANCEL_ORDER' ), true ) ) {
            // Cancel COD: Set to cancelled and restore inventory
            $order->update_status( 'cancelled', __( 'WaNotify: Order cancelled by customer via WhatsApp prompt.', 'wanotify' ) );
            $note = ! empty( $custom_note ) ? $custom_note : sprintf( __( '[WaNotify] Order cancelled via WhatsApp by customer at %s', 'wanotify' ), $timestamp );
            $order->add_order_note( $note, false );
            $order->update_meta_data( '_wanotify_cod_cancelled', 'yes' );
            $order->update_meta_data( '_wanotify_cod_cancelled_at', time() );
            $order->save();

            // WooCommerce inventory restocking
            if ( function_exists( 'wc_maybe_increase_stock_levels' ) ) {
                wc_maybe_increase_stock_levels( $order );
            }

            return new WP_REST_Response( array(
                'success'  => true,
                'order_id' => $order_id,
                'status'   => 'cancelled',
                'action'   => 'CANCELLED',
                'note'     => $note,
            ), 200 );
        }

        // Generic Note update
        if ( ! empty( $custom_note ) ) {
            $order->add_order_note( $custom_note, false );
            $order->save();
            return new WP_REST_Response( array(
                'success'  => true,
                'order_id' => $order_id,
                'note'     => $custom_note,
            ), 200 );
        }

        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'Unknown action: ' . $action,
        ), 400 );
    }
}
