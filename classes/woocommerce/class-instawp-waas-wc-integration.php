<?php
/**
 * InstaWP WooCommerce Integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * InstaWP_WaaS_WC_Integration
 */
if ( ! class_exists( 'InstaWP_WaaS_WC_Integration' ) ) {

    class InstaWP_WaaS_WC_Integration extends \WC_Integration {

        /**
         * Initialize the integration.
         */
        public function __construct() {
            $this->id                 = 'instawp_api';
            $this->method_title       = __( 'InstaWP API', 'iwp-waas-integration' );
            $this->method_description = __( 'Integration with WooCommerce for InstaWP WaaS Feature.', 'iwp-waas-integration' );
        
            $this->init_form_fields();
            $this->init_settings();

            add_action( 'woocommerce_update_options_integration_' . $this->id, [ $this, 'process_admin_options' ] );
            add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_field' ] );
            add_action( 'woocommerce_process_product_meta', [ $this, 'save_field' ] );
            add_filter( 'woocommerce_email_enabled_instawp_waas', [ $this, 'is_enabled' ], 999 );
            add_filter( 'woocommerce_email_additional_content_instawp_waas', [ $this, 'email_additional_content' ], 99, 3 );
        }

        /**
         * Initializes the settings fields.
         */
        public function init_form_fields() {
            $this->form_fields = [
                'api_key' => [
                    'title' => __( 'InstaWP API Key', 'iwp-waas-integration' ),
                    'type' => 'text',
                    'desc' => __( 'Enter your InstaWP API key here.', 'iwp-waas-integration' ),
                ],
                'app_email' => [
                    'title' => __( 'Send email through App', 'iwp-waas-integration' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Enabling this will disable WordPress email which can be configured from Emails > InstaWP WaaS.', 'iwp-waas-integration' ),
                ],
            ];
        }

        public function add_field() {
            echo '<div class="options_group show_if_virtual1">';
            woocommerce_wp_select( [
                'id'      => 'instawp-wc-waas',
                'options' => $this->get_waas_options(),
                'value'   => get_post_meta( get_the_ID(), 'instawp_wc_waas', true ),
                'label'   => 'InstaWP WaaS List',
            ] );
            echo '</div>';
        }

        public function save_field( $id ) {
            $waas_id = ! empty( $_POST[ 'instawp-wc-waas' ] ) ? intval( $_POST[ 'instawp-wc-waas' ] ) : '';
            
            if ( $waas_id ) {
                update_post_meta( $id, 'instawp_wc_waas', $waas_id );
            } else {
                delete_post_meta( $id, 'instawp_wc_waas' );
            }
        }

        public function email_additional_content( $formatted_additional_content, $order, $object ) {
            if ( strpos( $formatted_additional_content, '{waas_list}' ) === false ) {
                return $formatted_additional_content;
            }

            $content   = 'None';
            $link_data = [];

            foreach ( $order->get_items() as $item_id => $item ) {
                $waas_id = get_post_meta( $item->get_product_id(), 'instawp_wc_waas', true );
                $api_url = instawp_waas()->get_field_value( $waas_id, 'webhookUrl', $this->get_option( 'api_key' ) );
                $args    = [
                    'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                ];

                if ( 'yes' === $this->get_option( 'app_email', 'no' ) ) {
                    $args['send_email'] = true;
                }

                if ( $waas_id && $api_url ) {
                    $response = wp_remote_post( $api_url, [
                        'sslverify' => false,
                        'headers'   => [
                            'Authorization' => 'Bearer ' . $this->get_option( 'api_key' ),
                            'Content-Type'  => 'application/json'
                        ], 
                        'body'      => wp_json_encode( $args ),
                    ] );
            
                    if ( is_wp_error( $response ) ) {
                        error_log( 'error is: '. $response->get_error_message() );
                    } else {
                        $body = wp_remote_retrieve_body( $response );
                        $data = json_decode( $body );

                        if ( $data->status && ! empty( $data->data->unique_link ) ) {
                            $link_data[] = sprintf( 'WaaS InstaWP Unique Link for %s: %s', $item->get_name(), $data->data->unique_link );
                        }
                    }
                }
            }

            if ( ! empty( $link_data ) ) {
                $content = '<p>' . join( '</p><p>', $link_data ) . '</p>';
            }

            return str_replace( '{waas_list}', $content, $formatted_additional_content );
        }

        public function is_enabled() {
            $api_key = $this->get_option( 'api_key' );
            if ( ! $api_key ) {
                return false;
            }
            
            return 'no' === $this->get_option( 'app_email', 'no' );
        }

        public function get_waas_options() {
            $items = instawp_waas()->fetch_waas_list( $this->get_option( 'api_key' ) );

            $options = [
                '' => '-- Select --'
            ];
            foreach ( $items as $item ) {
                $options[ $item['id'] ] = $item['name'];
            }

            return $options;
        }
    }
}