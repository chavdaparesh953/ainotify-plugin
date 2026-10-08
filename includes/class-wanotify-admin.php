<?php
/**
 * WaNotify Admin Settings Interface.
 *
 * Adds settings page under WooCommerce > WaNotify with connection status,
 * configuration fields, live connection tester, and automated webhook credentials.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WaNotify_Admin {

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
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 50 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_wanotify_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    /**
     * Add admin menu under WooCommerce.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'WaNotify WhatsApp Automation', 'wanotify' ),
            __( 'WaNotify', 'wanotify' ),
            'manage_woocommerce',
            'wanotify-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_wanotify-settings' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wanotify-admin-css',
            WANOTIFY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WANOTIFY_VERSION
        );

        wp_enqueue_script(
            'wanotify-admin-js',
            WANOTIFY_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WANOTIFY_VERSION,
            true
        );

        wp_localize_script(
            'wanotify-admin-js',
            'wanotifyAdmin',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wanotify_admin_nonce' ),
                'testing'  => __( 'Testing connection to WaNotify...', 'wanotify' ),
            )
        );
    }

    /**
     * Register plugin settings with sanitize callbacks.
     */
    public function register_settings() {
        register_setting(
            'wanotify_settings_group',
            'wanotify_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize settings on save.
     */
    public function sanitize_settings( $input ) {
        $clean = array();
        $clean['api_url']                     = ! empty( $input['api_url'] ) ? esc_url_raw( trim( $input['api_url'] ) ) : 'https://wanotify.io';
        $clean['store_id']                    = isset( $input['store_id'] ) ? sanitize_text_field( trim( $input['store_id'] ) ) : '';
        $clean['webhook_secret']              = isset( $input['webhook_secret'] ) ? sanitize_text_field( trim( $input['webhook_secret'] ) ) : '';
        $clean['api_key']                     = isset( $input['api_key'] ) ? sanitize_text_field( trim( $input['api_key'] ) ) : '';
        $clean['cod_enabled']                 = isset( $input['cod_enabled'] ) && 'yes' === $input['cod_enabled'] ? 'yes' : 'no';
        $clean['order_notifications_enabled'] = isset( $input['order_notifications_enabled'] ) && 'yes' === $input['order_notifications_enabled'] ? 'yes' : 'no';
        $clean['abandoned_cart_enabled']      = isset( $input['abandoned_cart_enabled'] ) && 'yes' === $input['abandoned_cart_enabled'] ? 'yes' : 'no';
        return $clean;
    }

    /**
     * Handle AJAX connection testing.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'wanotify_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized user.', 'wanotify' ) ) );
        }

        $result = $this->api_client->test_connection();
        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Render the admin settings screen.
     */
    public function render_settings_page() {
        $settings     = get_option( 'wanotify_settings', array() );
        $is_connected = $this->api_client->is_configured();
        $rest_url     = rest_url( 'wanotify/v1/order-action' );

        $api_url      = ! empty( $settings['api_url'] ) ? $settings['api_url'] : 'https://wanotify.io';
        $store_id     = ! empty( $settings['store_id'] ) ? $settings['store_id'] : '';
        $webhook_sec  = ! empty( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';
        $api_key      = ! empty( $settings['api_key'] ) ? $settings['api_key'] : '';
        $cod_on       = ! isset( $settings['cod_enabled'] ) || 'yes' === $settings['cod_enabled'];
        $orders_on    = ! isset( $settings['order_notifications_enabled'] ) || 'yes' === $settings['order_notifications_enabled'];
        $cart_on      = ! isset( $settings['abandoned_cart_enabled'] ) || 'yes' === $settings['abandoned_cart_enabled'];
        ?>
        <div class="wrap wanotify-admin-wrap">
            <div class="wanotify-header">
                <div class="wanotify-brand">
                    <div class="wanotify-logo-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1>WaNotify</h1>
                        <p class="wanotify-subtitle"><?php esc_html_e( 'Official WhatsApp & SMS Automation for WooCommerce', 'wanotify' ); ?></p>
                    </div>
                </div>

                <div class="wanotify-status-pill <?php echo $is_connected ? 'is-connected' : 'is-disconnected'; ?>">
                    <span class="wanotify-status-dot"></span>
                    <span class="wanotify-status-text">
                        <?php echo $is_connected ? esc_html__( 'Connected to WaNotify', 'wanotify' ) : esc_html__( 'Disconnected / Incomplete Credentials', 'wanotify' ); ?>
                    </span>
                </div>
            </div>

            <?php settings_errors(); ?>

            <div class="wanotify-grid">
                <!-- Main Form Column -->
                <div class="wanotify-main-col">
                    <form method="post" action="options.php" class="wanotify-card">
                        <?php settings_fields( 'wanotify_settings_group' ); ?>

                        <div class="wanotify-card-header">
                            <h2><?php esc_html_e( 'Connection Credentials', 'wanotify' ); ?></h2>
                            <p><?php esc_html_e( 'Find these details inside your WaNotify Dashboard under Store Settings.', 'wanotify' ); ?></p>
                        </div>

                        <div class="wanotify-field-group">
                            <label for="wanotify_api_url"><?php esc_html_e( 'WaNotify Platform URL', 'wanotify' ); ?></label>
                            <input type="url" id="wanotify_api_url" name="wanotify_settings[api_url]" value="<?php echo esc_attr( $api_url ); ?>" class="regular-text" placeholder="https://wanotify.io" required />
                            <p class="description"><?php esc_html_e( 'Default is https://wanotify.io. If you run a custom instance, enter your base domain.', 'wanotify' ); ?></p>
                        </div>

                        <div class="wanotify-field-row">
                            <div class="wanotify-field-group">
                                <label for="wanotify_store_id"><?php esc_html_e( 'Store ID', 'wanotify' ); ?> <span class="required">*</span></label>
                                <input type="text" id="wanotify_store_id" name="wanotify_settings[store_id]" value="<?php echo esc_attr( $store_id ); ?>" class="regular-text" placeholder="store_abc123" required />
                                <p class="description"><?php esc_html_e( 'Unique identifier for this store from WaNotify.', 'wanotify' ); ?></p>
                            </div>

                            <div class="wanotify-field-group">
                                <label for="wanotify_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'wanotify' ); ?> <span class="required">*</span></label>
                                <input type="password" id="wanotify_webhook_secret" name="wanotify_settings[webhook_secret]" value="<?php echo esc_attr( $webhook_sec ); ?>" class="regular-text" placeholder="whsec_..." required />
                                <p class="description"><?php esc_html_e( 'HMAC-SHA256 secret key for signing outgoing order webhooks.', 'wanotify' ); ?></p>
                            </div>
                        </div>

                        <div class="wanotify-field-group">
                            <label for="wanotify_api_key"><?php esc_html_e( 'WaNotify Inbound API Key (Two-Way COD Actions)', 'wanotify' ); ?></label>
                            <input type="password" id="wanotify_api_key" name="wanotify_settings[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="wn_live_..." />
                            <p class="description"><?php esc_html_e( 'Authorizes WaNotify to confirm COD orders or cancel fake orders directly in your WooCommerce store.', 'wanotify' ); ?></p>
                        </div>

                        <hr class="wanotify-divider" />

                        <div class="wanotify-card-header">
                            <h2><?php esc_html_e( 'Automation Modules', 'wanotify' ); ?></h2>
                            <p><?php esc_html_e( 'Enable or disable features managed by this plugin.', 'wanotify' ); ?></p>
                        </div>

                        <div class="wanotify-toggles">
                            <label class="wanotify-toggle-item">
                                <input type="checkbox" name="wanotify_settings[cod_enabled]" value="yes" <?php checked( $cod_on ); ?> />
                                <div>
                                    <strong><?php esc_html_e( 'Two-Way Cash on Delivery (COD) WhatsApp Verification', 'wanotify' ); ?></strong>
                                    <p><?php esc_html_e( 'Dispatches interactive WhatsApp buttons to customer. Clicking "Confirm" changes status to Processing; "Cancel" cancels order and restores inventory.', 'wanotify' ); ?></p>
                                </div>
                            </label>

                            <label class="wanotify-toggle-item">
                                <input type="checkbox" name="wanotify_settings[order_notifications_enabled]" value="yes" <?php checked( $orders_on ); ?> />
                                <div>
                                    <strong><?php esc_html_e( 'Real-Time Order & Tracking Updates', 'wanotify' ); ?></strong>
                                    <p><?php esc_html_e( 'Sends instant order confirmation, packing, and fulfillment tracking messages via official WhatsApp Cloud API.', 'wanotify' ); ?></p>
                                </div>
                            </label>

                            <label class="wanotify-toggle-item">
                                <input type="checkbox" name="wanotify_settings[abandoned_cart_enabled]" value="yes" <?php checked( $cart_on ); ?> />
                                <div>
                                    <strong><?php esc_html_e( '30-Minute Abandoned Cart Recovery Beacon', 'wanotify' ); ?></strong>
                                    <p><?php esc_html_e( 'Captures customer phone on checkout blur and schedules high-converting WhatsApp recovery sequence with direct checkout link.', 'wanotify' ); ?></p>
                                </div>
                            </label>
                        </div>

                        <div class="wanotify-actions">
                            <?php submit_button( __( 'Save Changes', 'wanotify' ), 'primary', 'submit', false ); ?>
                            <button type="button" id="wanotify-test-btn" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e( 'Test Live Connection', 'wanotify' ); ?>
                            </button>
                        </div>

                        <div id="wanotify-test-result" style="display:none;" class="wanotify-test-box"></div>
                    </form>
                </div>

                <!-- Sidebar Column -->
                <div class="wanotify-side-col">
                    <div class="wanotify-card wanotify-info-box">
                        <h3><?php esc_html_e( 'Two-Way Write-Back Endpoint', 'wanotify' ); ?></h3>
                        <p><?php esc_html_e( 'WaNotify automatically calls this REST endpoint when customers click WhatsApp buttons:', 'wanotify' ); ?></p>
                        <div class="wanotify-copy-box">
                            <code><?php echo esc_html( $rest_url ); ?></code>
                        </div>
                    </div>

                    <div class="wanotify-card wanotify-hpos-box">
                        <h3><?php esc_html_e( 'HPOS Compatibility', 'wanotify' ); ?></h3>
                        <div class="wanotify-hpos-badge">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span><?php esc_html_e( 'High-Performance Order Storage 100% Supported', 'wanotify' ); ?></span>
                        </div>
                        <p><?php esc_html_e( 'Works seamlessly with standard post meta as well as WooCommerce custom order tables.', 'wanotify' ); ?></p>
                    </div>

                    <div class="wanotify-card">
                        <h3><?php esc_html_e( 'Need Assistance?', 'wanotify' ); ?></h3>
                        <p><?php esc_html_e( 'Access our step-by-step guides, Meta WhatsApp template library, and customer support.', 'wanotify' ); ?></p>
                        <a href="https://wanotify.io/docs" target="_blank" class="button button-secondary" style="width:100%; text-align:center;">
                            <?php esc_html_e( 'View Documentation', 'wanotify' ); ?> &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
