<?php
/**
 * Plugin Name: Checkout Finland for WooCommerce
 * Plugin URI: https://github.com/CheckoutFinland/checkout-finland-for-woocommerce
 * Description: Notice: This plugin is no longer maintained. Use Paytrail for WooCommerce instead. Checkout Finland is a payment gateway that offers 20+ payment methods for Finnish customers.
 * Version: 1.7.1
 * Requires at least: 4.9
 * Tested up to: 5.8
 * Requires PHP: 7.3
 * WC requires at least: 3.0
 * WC tested up to: 5.7
 * Author: Checkout Finland
 * Author URI: https://www.checkout.fi/
 * Text Domain: op-payment-service-woocommerce
 * Domain Path: /languages
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Copyright: Checkout Finland
 */

namespace OpMerchantServices\WooCommercePaymentGateway;

// Ensure that the file is being run within the WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * The main plugin class
 */
final class Plugin {

    /**
     * WooCommerce payment gateway ID.
     */
    public const GATEWAY_ID = 'checkout_finland';

    /**
     * Merchant ID for the test mode.
     */
    public const TEST_MERCHANT_ID = 375917;

    /**
     * Secret key for the test mode.
     */
    public const TEST_SECRET_KEY = 'SAIPPUAKAUPPIAS';

    /**
     * The URL from which the method description should be fetched.
     */
    public const METHOD_INFO_URL = 'https://cdn2.hubspot.net/hubfs/2610868/ext-media/op-psp-service-info.json';

    /**
     * The URL of the payment method icon
     */
    public const ICON_URL = 'https://cdn2.hubspot.net/hubfs/2610868/ext-media/op-psp-master-logo.svg';

    public const PAYMENT_METHOD_IMG_URL = 'https://payment.checkout.fi/static/img/payment-methods';

    public const BASE_URL = 'op-payment-service/';

    public const ADD_CARD_REDIRECT_SUCCESS_URL = 'card-success';

    public const ADD_CARD_REDIRECT_CANCEL_URL = 'card-cancel';

    public const ADD_CARD_CONTEXT_MY_ACCOUNT = 'my_account';

    public const ADD_CARD_CONTEXT_CHECKOUT= 'checkout';

    public const ADD_CARD_CONTEXT_CHANGE_PAYMENT_METHOD = 'change_payment_method';

    public const CARD_ENDPOINT = 'card';

    public const CALLBACK_URL = 'callback';

    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    private static $instance;

    /**
     * Plugin version.
     *
     * @var string
     */
    public static $version;

    /**
     * Plugin directory.
     *
     * @var string
     */
    protected $plugin_dir;

    /**
     * Plugin directory URL.
     *
     * @var string
     */
    protected $plugin_dir_url;

    /**
     * Container array for possible initialization errors.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Plugin info
     *
     * @var array
     */
    protected $plugin_info = [
        'Plugin Name',
        'Plugin URI',
        'Description',
        'Version',
        'Author',
        'Author URI',
        'Text Domain',
        'Domain Path',
    ];

    /**
     * Constructor function
     */
    protected function __construct() {
        $this->plugin_dir     = __DIR__;
        $this->plugin_dir_url = plugin_dir_url( __FILE__ );
        $this->plugin_info    = array_combine( $this->plugin_info, get_file_data( __FILE__, $this->plugin_info ) );

        self::$version = $this->plugin_info['Version'];

        // Load the plugin textdomain.
        load_plugin_textdomain( 'op-payment-service-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        // Register admin notice about plugin status
        add_action( 'admin_notices', [$this, 'admin_notice_maintain'] );
        // Register customizations
        add_action( 'customize_register', [ $this, 'checkout_customizations' ] );
        // Add custom styles
        add_action( 'wp_head', [ $this, 'op_checkout_customize_css' ] );
        // Enable WP Dashicons on frontend
        add_action( 'wp_enqueue_scripts', function() {
            wp_enqueue_style( 'dashicons' );
        } );
    }

    /**
     * Print custom styles
     */
    public function op_checkout_customize_css() {
        ?>
            <style type="text/css">
                .provider-group {
                    background-color: <?php echo get_theme_mod('op_group_background', '#ebebeb'); ?> !important;
                    color: <?php echo get_theme_mod('op_group_text', '#515151'); ?> !important;
                }
                .provider-group.selected {
                    background-color: <?php echo get_theme_mod('op_group_highlighted_background', '#33798d'); ?> !important;
                    color: <?php echo get_theme_mod('op_group_highlighted_text', '#ffffff'); ?> !important;
                }
                .provider-group.selected div {
                    color: <?php echo get_theme_mod('op_group_highlighted_text', '#ffffff'); ?> !important;
                }
                .provider-group:hover {
                    background-color: <?php echo get_theme_mod('op_group_hover_background', '#d0d0d0'); ?> !important;
                    color: <?php echo get_theme_mod('op_group_hover_text', '#515151'); ?> !important;
                }
                .provider-group.selected:hover {
                    background-color: <?php echo get_theme_mod('op_group_highlighted_background', '#33798d'); ?> !important;
                    color: <?php echo get_theme_mod('op_group_highlighted_text', '#ffffff'); ?> !important;
                }
                .woocommerce-checkout #payment .op-payment-service-woocommerce-payment-fields--list-item--input:checked+.op-payment-service-woocommerce-payment-fields--list-item--wrapper, .woocommerce-checkout #payment .op-payment-service-woocommerce-payment-fields--list-item:hover .op-payment-service-woocommerce-payment-fields--list-item--wrapper {
                    border: 2px solid <?php echo get_theme_mod('op_method_highlighted', '#33798d'); ?> !important;
                }
                .woocommerce-checkout #payment ul.payment_methods li.op-payment-service-woocommerce-payment-fields--list-item .op-payment-service-woocommerce-payment-fields--list-item--wrapper:hover {
                    border: 2px solid <?php echo get_theme_mod('op_method_hover', '#5399ad'); ?> !important;
                }
            </style>
        <?php
    }

    public function admin_notice_maintain() {
        $allowed_html = array(
            'a'      => array(
                'href'  => array(),
                'title' => array(),
                'target' => array(),
            )
        );
        $class = 'notice notice-error is-dismissible';
        $message = __( 'Notice: Checkout Finland for Woocommerce is deprecated. To continue using the payment service, install this plugin: <a href="https://wordpress.org/plugins/paytrail-for-woocommerce/" target="_blank">Paytrail for Woocommerce</a>.', 'op-payment-service-woocommerce' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses( $message,$allowed_html ) ); 
    }
    

    /**
     * Customizer options
     */
    public function checkout_customizations( $wp_customize ) {
        // Settings
        $wp_customize->add_setting( 'op_group_background' , array(
            'default'   => '#ebebeb',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_group_text' , array(
            'default'   => '#515151',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_group_highlighted_background' , array(
            'default'   => '#33798d',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_group_highlighted_text' , array(
            'default'   => '#ffffff',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_group_hover_background' , array(
            'default'   => '#d0d0d0',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_group_hover_text' , array(
            'default'   => '#313131',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_method_highlighted' , array(
            'default'   => '#33798d',
            'transport' => 'refresh',
        ) );
        $wp_customize->add_setting( 'op_method_hover' , array(
            'default'   => '#5399ad',
            'transport' => 'refresh',
        ) );
        // Section
        $wp_customize->add_section( 'op_checkout_customize_section' , array(
            'title'      => __( 'Payment page personalization', 'op-payment-service-woocommerce' ),
            'priority'   => 30,
        ) );
        // Controls
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_bgcolor', array(
            'label'      => __( 'Payment method group background', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_group_background',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_fgcolor', array(
            'label'      => __( 'Payment method group text', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_group_text',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_bgcolor_selected', array(
            'label'      => __( 'Selected payment method group background', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_group_highlighted_background',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_fgcolor_selected', array(
            'label'      => __( 'Selected payment method group text', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_group_highlighted_text',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_bgcolor_hover', array(
            'label'      => __( 'Payment method group background hover', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_group_hover_background',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_fgcolor_hover', array(
            'label'      => __( 'Payment method group text hover', 'op hover-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_group_hover_text',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_bordercolor_selected', array(
            'label'      => __( 'Selected payment method', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_method_highlighted',
        ) ) );
        $wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'op_bordercolor_hover', array(
            'label'      => __( 'Payment method hover', 'op-payment-service-woocommerce' ),
            'section'    => 'op_checkout_customize_section',
            'settings'   => 'op_method_hover',
        ) ) );
    }

    /**
     * Singleton instance getter function
     *
     * @return Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            // Construct the object.
            self::$instance = new self();

            // Run initialization checks. If any of the checks
            // fails, interrupt the execution.
            if ( ! self::$instance->initialization_checks() ) {
                return;
            }

            // Check if Composer has been initialized in this directory.
            // Otherwise we just use global composer autoloading.
            if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
                require_once __DIR__ . '/vendor/autoload.php';
            }

            // Create new instance of Router class
            new Router();

            // Add the gateway class to WooCommerce.
            add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
                $gateways[] = Gateway::CLASS;

                return $gateways;
            });
        }

        return self::$instance;
    }

    /**
     * Run checks for plugin requirements.
     *
     * Returns false if checks failed.
     *
     * @return bool
     */
    protected function initialization_checks() {
        $errors = [];

        $errors[] = self::check_php_version();
        $errors[] = self::check_woocommerce_active_status();
        $errors[] = self::check_woocommerce_version();

        $errors = array_filter( $errors );

        if ( ! empty( $errors ) ) {
            add_action( 'admin_notices', function() use ( $errors ) {
                echo '<div class="notice notice-error">';
                array_walk( $errors, 'esc_html_e' );
                echo '</div>';
            });

            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Checks to run on plugin activation
     *
     * @return void
     */
    public static function activation_check() {
        $checks = [
            'check_php_version',
            'check_woocommerce_active_status',
            'check_woocommerce_version',
        ];

        array_walk( $checks, function( $check ) {
            $error = call_user_func( __CLASS__ . '::' . $check );

            if ( $error ) {
                wp_die( esc_html( $error ) );
            }
        });
    }

    /**
     * Ensure that the PHP version is at least 7.0.0.
     *
     * @return string|null
     */
    public static function check_php_version() : ?string {
        if ( ! version_compare( PHP_VERSION, '7.1.0', '>=' ) ) {
            return sprintf(
                // translators: The placeholder contains the current PHP version.
                esc_html__( 'Checkout Finland payment gateway plugin requires a PHP version of at least 7.1. You are currently running version %1$s.', 'op-payment-service-woocommerce' ),
                esc_html( PHP_VERSION )
            );
        }

        return null;
    }

    /**
     * Ensure that the WooCommerce plugin is active.
     *
     * @return string|null
     */
    public static function check_woocommerce_active_status() : ?string {
        if ( ! class_exists( '\WC_Payment_Gateway' ) ) {
            return esc_html__( 'Checkout Finland payment gateway plugin requires WooCommerce to be activated.', 'op-payment-service-woocommerce' );
        }

        return null;
    }

    /**
     * Ensure that we have at least version 3.5 of the WooCommerce plugin.
     *
     * @return string|null
     */
    public static function check_woocommerce_version() : ?string {
        if (
            defined( 'WOO_COMMERCE_VERSION' ) &&
            version_compare( WOO_COMMERCE_VERSION, '3.5' ) === -1
        ) {
            return esc_html__( 'Checkout Finland gateway plugin requires WooCommerce version of 3.5 or greater.', 'op-payment-service-woocommerce' );
        }

        return null;
    }

    /**
     * Get plugin directory.
     *
     * @return string
     */
    public function get_plugin_dir() : string {
        return $this->plugin_dir;
    }

    /**
     * Get plugin directory URL.
     *
     * @return string
     */
    public function get_plugin_dir_url() : string {
        return $this->plugin_dir_url;
    }

    /**
     * Get plugin info.
     *
     * @return array
     */
    public function get_plugin_info() : array {
        return $this->plugin_info;
    }
}

add_action( 'plugins_loaded', function() {
    Plugin::instance();
});


register_activation_hook( __FILE__, __NAMESPACE__ . '\\Plugin::activation_check' );
