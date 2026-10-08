<?php
/**
 * WaNotify API Client.
 *
 * Handles outgoing HTTP webhook dispatching and authentication with the WaNotify SaaS backend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WaNotify_API_Client {

    const DEFAULT_API_URL = 'https://wanotify.io';

    /**
     * Get option helper with defaults.
     */
    public function get_setting( $key, $default = '' ) {
        $options = get_option( 'wanotify_settings', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Get the configured SaaS API Base URL.
     */
    public function get_api_url() {
        $url = trim( $this->get_setting( 'api_url', self::DEFAULT_API_URL ) );
        return untrailingslashit( ! empty( $url ) ? $url : self::DEFAULT_API_URL );
    }

    /**
     * Get Store ID registered in WaNotify.
     */
    public function get_store_id() {
        return trim( $this->get_setting( 'store_id', '' ) );
    }

    /**
     * Get Webhook Secret used for HMAC-SHA256 signatures.
     */
    public function get_webhook_secret() {
        return trim( $this->get_setting( 'webhook_secret', '' ) );
    }

    /**
     * Get API Key used for REST endpoints.
     */
    public function get_api_key() {
        return trim( $this->get_setting( 'api_key', '' ) );
    }

    /**
     * Check if plugin is connected/configured.
     */
    public function is_configured() {
        return ! empty( $this->get_store_id() ) && ! empty( $this->get_webhook_secret() );
    }

    /**
     * Generate HMAC-SHA256 Base64 signature matching WaNotify SaaS backend middleware.
     *
     * In backend:
     * crypto.createHmac('sha256', store.webhookSecret).update(rawBodyBuffer).digest('base64');
     *
     * @param string $payload JSON encoded body
     * @return string Base64 encoded HMAC-SHA256 string
     */
    public function generate_signature( $payload ) {
        $secret = $this->get_webhook_secret();
        if ( empty( $secret ) ) {
            return '';
        }
        $hash = hash_hmac( 'sha256', $payload, $secret, true );
        return base64_encode( $hash );
    }

    /**
     * Send webhook payload to WaNotify SaaS backend.
     *
     * @param string $topic   Event topic (e.g., 'order.created', 'order.updated', 'cart.abandoned')
     * @param array  $payload Event data
     * @param bool   $async   Whether to run non-blocking (default: true for instant merchant checkouts)
     * @return array|WP_Error
     */
    public function dispatch_event( $topic, array $payload, $async = true ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'WaNotify is not fully configured.', 'wanotify' ) );
        }

        $endpoint = $this->get_api_url() . '/api/webhooks/woocommerce?storeId=' . urlencode( $this->get_store_id() );
        $json_payload = wp_json_encode( $payload );
        $signature    = $this->generate_signature( $json_payload );

        $headers = array(
            'Content-Type'             => 'application/json; charset=utf-8',
            'X-WC-Webhook-Source'      => home_url( '/' ),
            'X-WC-Webhook-Topic'       => $topic,
            'X-WC-Webhook-Signature'   => $signature,
            'X-Store-Id'               => $this->get_store_id(),
            'User-Agent'               => 'WaNotify-WooCommerce-Plugin/' . WANOTIFY_VERSION,
        );

        if ( ! empty( $this->get_api_key() ) ) {
            $headers['X-WaNotify-API-Key'] = $this->get_api_key();
        }

        $args = array(
            'method'      => 'POST',
            'timeout'     => $async ? 2 : 12,
            'redirection' => 2,
            'httpversion' => '1.1',
            'blocking'    => ! $async,
            'headers'     => $headers,
            'body'        => $json_payload,
            'cookies'     => array(),
            'sslverify'   => apply_filters( 'wanotify_ssl_verify', true ),
        );

        $response = wp_remote_post( $endpoint, $args );

        // If asynchronous, we return immediate success
        if ( $async ) {
            return array( 'success' => true, 'async' => true );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        return array(
            'success' => ( $code >= 200 && $code < 300 ),
            'status'  => $code,
            'body'    => json_decode( $body, true ),
        );
    }

    /**
     * Test connection to WaNotify SaaS backend.
     *
     * @return array
     */
    public function test_connection() {
        if ( ! $this->is_configured() ) {
            return array(
                'success' => false,
                'message' => __( 'Please fill in both Store ID and Webhook Secret before testing.', 'wanotify' ),
            );
        }

        $test_payload = array(
            'event'       => 'ping',
            'test'        => true,
            'timestamp'   => time(),
            'site_url'    => home_url(),
            'site_name'   => get_bloginfo( 'name' ),
            'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
            'plugin_ver'  => WANOTIFY_VERSION,
        );

        $res = $this->dispatch_event( 'system.ping', $test_payload, false );

        if ( is_wp_error( $res ) ) {
            return array(
                'success' => false,
                'message' => $res->get_error_message(),
            );
        }

        if ( ! empty( $res['success'] ) ) {
            return array(
                'success' => true,
                'message' => __( 'Connection successful! Your WooCommerce store is securely linked with WaNotify.', 'wanotify' ),
                'data'    => $res['body'],
            );
        }

        $status = isset( $res['status'] ) ? $res['status'] : 400;
        $detail = isset( $res['body']['message'] ) ? $res['body']['message'] : __( 'Verification failed. Please check your Secret and Store ID.', 'wanotify' );

        return array(
            'success' => false,
            'status'  => $status,
            'message' => sprintf( __( 'Server returned status %1$d: %2$s', 'wanotify' ), $status, $detail ),
        );
    }
}
