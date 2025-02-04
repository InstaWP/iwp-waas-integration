<?php // @codingStandardsIgnoreLine
/**
 * Plugin Name: InstaWP WaaS Integration
 * Description: Integration with WooCommerce & SureCart for InstaWP WaaS Feature
 * Version:     1.0.5
 * Author:      InstaWP
 * Author URI:  https://instawp.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iwp-waas-integration    
 * WC requires at least: 3.1
 * WC tested up to: 9.6
 *
 * @package iwp-waas-integration
 * @author  Sayan Datta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abort if the class is already exists.
 */
if ( ! class_exists( 'InstaWP_WaaS_Integration' ) ) {

	/**
	 * InstaWP WaaS class.
	 *
	 * @class Main class of the plugin.
	 */
	class InstaWP_WaaS_Integration {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		public $version = '1.0.5';

        /**
		 * Settings Group.
		 *
		 * @var string
		 */
		public $settings_group = 'iwp_waas_integration';

        /**
         * @var WordPressSettingsFramework
         */
        private $wpsf;

		/**
		 * Instance of this class.
		 *
		 * @since 1.0.0
		 * @var object
		 */
		protected static $instance;

		/**
		 * Get the singleton instance of this class.
		 *
		 * @since 1.0.0
		 * @return InstaWP_WaaS_Integration
		 */
		public static function get() {
			if ( ! ( self::$instance instanceof self ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
            $this->define_constants();

            require_once INSTAWP_WAAS_WC_PATH . 'plugin-update-checker/plugin-update-checker.php';
            require_once INSTAWP_WAAS_WC_PATH . 'settings/wp-settings-framework.php';

            add_filter( 'wpsf_register_settings_' . $this->settings_group, [ $this, 'settings' ] );
            $this->wpsf = new WordPressSettingsFramework( null, $this->settings_group );

            $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( 'https://github.com/InstaWP/iwp-waas-integration', INSTAWP_WAAS_WC_FILE, 'iwp-waas-integration' );
            $updateChecker->setBranch( 'main' );

            add_action( 'admin_menu', [ $this, 'register_menu' ], 20 ); 
            add_action( 'plugins_loaded', [ $this, 'register_classes' ], 11 );
            add_action( 'before_woocommerce_init', [ $this, 'declare_compatibility' ] );
            add_action( 'woocommerce_integrations_init', [ $this, 'wc_integration_class' ] );
            add_filter( 'woocommerce_integrations', [ $this, 'wc_integrations' ], 999 );
            add_filter( 'woocommerce_email_classes', [ $this, 'wc_email_classes' ], 999 );
		}

        /**
         * Define the plugin constants.
         */
        private function define_constants() {
            define( 'INSTAWP_WAAS_WC_VERSION', $this->version );
            define( 'INSTAWP_WAAS_WC_FILE', __FILE__ );
            define( 'INSTAWP_WAAS_WC_PATH', dirname( INSTAWP_WAAS_WC_FILE ) . '/' );
            define( 'INSTAWP_WAAS_WC_URL', plugins_url( '', INSTAWP_WAAS_WC_FILE ) . '/' );

            if ( ! defined( 'INSTAWP_WAAS_API_DOMAIN' ) ) {
                define( 'INSTAWP_WAAS_API_DOMAIN', 'https://app.instawp.io' );
            }
        }

        /**
         * Register the classes.
         */
        public function register_classes() {
            if ( class_exists( 'SureCart' ) ) {
                require_once INSTAWP_WAAS_WC_PATH . 'classes/surecart/class-instawp-waas-surecart-integration.php';
            }

            if ( function_exists( 'EDD' ) ) {
                require_once INSTAWP_WAAS_WC_PATH . 'classes/easy-digital-downloads/class-instawp-edd-integration.php';
                new InstaWP_WaaS_EDD_Integration();
            }
        }

        /**
         * WooCommerce email loader
         *
         * @param array $emails emails.
         * @return array
         */
        public function wc_integrations( $integrations ) {
            $integrations[] = 'InstaWP_WaaS_WC_Integration';
            
            return $integrations;
        }

        public function wc_integration_class() {
            require_once INSTAWP_WAAS_WC_PATH . 'classes/woocommerce/class-instawp-waas-wc-integration.php';
        }

        /**
         * WooCommerce email loader
         *
         * @param array $emails emails.
         * @return array
         */
        public function wc_email_classes( $emails ) {
            $emails['InstaWP_WaaS_WC_Email'] = include_once INSTAWP_WAAS_WC_PATH . 'classes/woocommerce/class-instawp-waas-wc-email.php';
            
            return $emails;
        }

        /**
         * Declaring HPOS compatibility
         */
        public function declare_compatibility() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', INSTAWP_WAAS_WC_FILE, true );
            }
        }

        /**
         * Register the menu.
         */
        public function register_menu() {
            if ( class_exists( 'SureCart' ) ) {
                $this->wpsf->add_settings_page( [
                    'parent_slug' => 'sc-dashboard',
                    'page_title'  => __( 'InstaWP WaaS SureCart Integration', 'iwp-waas-integration' ),
                    'menu_title'  => __( 'InstaWP WaaS', 'iwp-waas-integration' ),
                    'capability'  => 'manage_sc_shop_settings',
                ] );
            }
        }

        /**
         * Register the settings.
         * 
         * @param array $settings Settings.
         * @return array
         */
        public function settings( $settings ) {
            $settings['tabs'] = [
                [
                    'id'    => 'settings_tab',
                    'title' => __( 'Settings', 'iwp-waas-integration' ),
                ],
                [
                    'id'    => 'email_tab',
                    'title' => __( 'Email', 'iwp-waas-integration' ),
                ],
            ];

            $settings['sections'] = [
                [
                    'tab_id'        => 'settings_tab',
                    'section_id'    => 'settings',
                    'section_title' => 'Configure',
                    'section_order' => 10,
                    'fields'        => [
                        [
                            'id'      => 'api_key',
                            'title'   => __( 'API Key', 'iwp-waas-integration' ),
                            'desc'    => __( 'Enter InstaWP API Key here.', 'iwp-waas-integration' ),
                            'type'    => 'text',
                        ],
                        [
                            'id'      => 'app_email',
                            'title'   => __( 'Send email through App', 'iwp-waas-integration' ),
                            'type'    => 'toggle',
                            'default' => false,
                        ],
                    ]
                ],
            ];

            if ( ! wpsf_get_setting( $this->settings_group, 'settings_tab_settings', 'app_email' ) ) {
                $settings['sections'][] = [
                    'tab_id'        => 'email_tab',
                    'section_id'    => 'email',
                    'section_title' => 'Email Settings',
                    'section_order' => 10,
                    'fields'        => [
                        [
                            'id'      => 'subject',
                            'title'   => __( 'Email Subject', 'iwp-waas-integration' ),
                            'type'    => 'text',
                            'default' => 'Your InstaWP WaaS link',
                        ],
                        [
                            'id'      => 'body',
                            'title'   => __( 'Email Body', 'iwp-waas-integration' ),
                            'type'    => 'editor',
                            'default' => 'Link to build your website is {{link}}',
                        ],
                    ],
                ];
            }
        
            return $settings;
        }

        /**
         * Get the field value.
         * 
         * @param string $id ID.
         * @param string $field Field.
         * @param string $api_key API Key.
         * @return string
         */
        public function get_field_value( $id, $field, $api_key ) {
            $items = $this->fetch_waas_list( $api_key );

            return $items[ $id ][ $field ] ?? '';
        }

        /**
         * Fetch the waas list.
         * 
         * @param string $api_key API Key.
         * @return array
         */
        public function fetch_waas_list( $api_key ) {
            $response = wp_remote_get( INSTAWP_WAAS_API_DOMAIN . '/api/v2/waas', [
                'sslverify' => false,
                'headers'   => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key
                ]
            ] );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                error_log("error is: ". $error_message);
                return [];
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( ! $data['status'] ) {
                return [];
            }
            
            $options = [];
            foreach ( $data['data'] as $item ) {
                $options[ $item['id'] ] = $item;
            }

            return $options;
        }
    }
}

/**
 * Check the existance of the function.
 */
if ( ! function_exists( 'instawp_waas' ) ) {
	/**
	 * Returns the main instance of InstaWP_WaaS_Integration to prevent the need to use globals.
	 *
	 * @return InstaWP_WaaS_Integration
	 */
	function instawp_waas() {
		return InstaWP_WaaS_Integration::get();
	}

	// Start it.
	instawp_waas();
}