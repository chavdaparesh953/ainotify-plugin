<?php
/**
 * Plugin Name:       WaNotify - WhatsApp & SMS Automation for WooCommerce
 * Plugin URI:        https://wanotify.io
 * Description:       Official WooCommerce integration for WaNotify SaaS. Instant WhatsApp order notifications, automated Two-Way COD verification, and 30-minute abandoned cart recovery.
 * Version:           1.0.0
 * Author:            WaNotify Team
 * Author URI:        https://wanotify.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wanotify
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   9.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WANOTIFY_VERSION', '1.0.0' );
define( 'WANOTIFY_PLUGIN_FILE', __FILE__ );
define( 'WANOTIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WANOTIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WANOTIFY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare High-Performance Order Storage (HPOS) Compatibility.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Main WaNotify Plugin Class.
 */
final class WaNotify_Plugin {

    /**
     * Single instance of the plugin.
     * @var WaNotify_Plugin
     */
    private static $instance = null;

    /**
     * API Client instance.
     * @var WaNotify_API_Client
     */
    public $api_client;

    /**
     * Order Handler instance.
     * @var WaNotify_Order_Handler
     */
    public $order_handler;

    /**
     * Cart Tracker instance.
     * @var WaNotify_Cart_Tracker
     */
    public $cart_tracker;

    /**
     * Admin instance.
     * @var WaNotify_Admin
     */
    public $admin;

    /**
     * Main plugin instance getter.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize plugin hooks.
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_filter( 'plugin_action_links_' . WANOTIFY_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
    }

    /**
     * Check WooCommerce dependency and bootstrap components.
     */
    public function on_plugins_loaded() {
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        $this->includes();
        $this->init_components();
    }

    /**
     * Verify if WooCommerce is active.
     */
    public function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Admin notice if WooCommerce is inactive.
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e( 'WaNotify requires WooCommerce to be installed and active.', 'wanotify' ); ?></strong>
                <?php esc_html_e( 'Please activate WooCommerce to enable WhatsApp & SMS automation.', 'wanotify' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Include required core classes.
     */
    private function includes() {
        require_once WANOTIFY_PLUGIN_DIR . 'includes/class-wanotify-api-client.php';
        require_once WANOTIFY_PLUGIN_DIR . 'includes/class-wanotify-order-handler.php';
        require_once WANOTIFY_PLUGIN_DIR . 'includes/class-wanotify-cart-tracker.php';

        if ( is_admin() ) {
            require_once WANOTIFY_PLUGIN_DIR . 'includes/class-wanotify-admin.php';
        }
    }

    /**
     * Instantiate plugin modules.
     */
    private function init_components() {
        $this->api_client    = new WaNotify_API_Client();
        $this->order_handler = new WaNotify_Order_Handler( $this->api_client );
        $this->cart_tracker  = new WaNotify_Cart_Tracker( $this->api_client );

        if ( is_admin() ) {
            $this->admin = new WaNotify_Admin( $this->api_client );
        }
    }

    /**
     * Add Settings shortcut link on Plugins page.
     */
    public function add_action_links( $links ) {
        $settings_url = admin_url( 'admin.php?page=wanotify-settings' );
        $custom_links = array(
            '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wanotify' ) . '</a>',
            '<a href="https://wanotify.io" target="_blank" style="color: #10b981; font-weight: 600;">' . esc_html__( 'Dashboard', 'wanotify' ) . '</a>',
        );
        return array_merge( $custom_links, $links );
    }
}

/**
 * Global helper function to access the plugin instance.
 */
function wanotify() {
    return WaNotify_Plugin::instance();
}

// Fire up the plugin.
wanotify();
