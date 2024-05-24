<?php
/**
 * InstaWP WooCommerce Integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * InstaWP_WaaS_EDD_Integration
 */
if ( ! class_exists( 'InstaWP_WaaS_EDD_Integration' ) ) {

    class InstaWP_WaaS_EDD_Integration {

        protected $id = 'instawp_waas';

        /**
         * Initialize the integration.
         */
        public function __construct() {
            add_action( 'init', [ $this, 'register_email' ], 3 );
            add_action( 'edd_complete_purchase', [ $this, 'send_order_email' ], 9999 );
            add_action( 'edd_order_updated', [ $this, 'order_updated' ], 10, 2 );
            add_filter( 'edd_settings_sections', [ $this, 'add_section' ] );
            add_filter( 'edd_settings_misc', [ $this, 'add_misc_settings' ] );
            add_filter( 'edd_settings_emails', [ $this, 'add_email_settings' ] );
            add_filter( 'edd_email_tags', [ $this, 'add_email_tag' ] );
            add_filter( 'edd_metabox_fields_save', [ $this, 'add_field' ], 99 );
            add_action( 'edd_meta_box_settings_fields', [ $this, 'show_waas_list' ], 99 );
        }

        public function register_email() {
            require_once INSTAWP_WAAS_WC_PATH . 'classes/easy-digital-downloads/class-instawp-edd-email.php';
            \EDD\Emails\Registry::register( $this->id, 'InstaWP_WaaS_EDD_Email' );
        }

        public function order_updated( $item_id, $data ) {
            if ( $data['status'] !== 'revoked' ) {
                return;
            }

            $payment   = edd_get_payment( $item_id );
            $links     = $payment->get_meta( 'instawp_waas_links', true );
            $links     = ! empty( $links ) ? $links : [];
            $link_data = [];

            foreach ( $links as $link_id => $link ) {
                $response = wp_remote_request( $link['cancel'], [
                    'method'    => 'DELETE',
                    'sslverify' => false,
                    'headers'   => [
                        'Authorization' => 'Bearer ' . edd_get_option( 'instawp_api_key' ),
                        'Content-Type'  => 'application/json'
                    ],
                ] );
        
                unset( $links[ $link_id ] );

                if ( ! empty( $links ) ) {
                    $payment->update_meta( 'instawp_waas_links', $links );
                } else {
                    $payment->delete_meta( 'instawp_waas_links' );
                }
            }
        }

        public function send_order_email( $order_id ) {
            if ( edd_get_option( 'instawp_app_email' ) ) {
                $payment = edd_get_payment( $order_id );

                $this->email_tag_waas_details( $payment->ID );
                return;
            }

            if ( ! empty( $order_id ) ) {
                $order = edd_get_order( $order_id );
            }
    
            if ( false === $order ) {
                return;
            }
    
            if ( 'refund' === $order->type ) {
                return;
            }
    
            $waas_email = \EDD\Emails\Registry::get( $this->id, array( $order ) );
            $waas_email->send();
        }

        public function add_section( $sections ) {
            $sections['misc']['instawp_api'] = __( 'InstaWP API', 'iwp-waas-integration' );
            $sections['emails'][ $this->id ] = __( 'InstaWP WaaS', 'iwp-waas-integration' );

            return $sections;
        }

        public function add_misc_settings( $settings ) {
            $settings['instawp_api'] = array(
				'api_key' => array(
					'id'            => 'instawp_api_key',
					'name'          => __( 'InstaWP API Key', 'iwp-waas-integration' ),
					'type'          => 'text',
				),
				'app_email' => [
                    'id'   => 'instawp_app_email',
                    'name' => __( 'Send email through App', 'iwp-waas-integration' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Enabling this will disable WordPress email which can be configured from Emails > InstaWP WaaS.', 'iwp-waas-integration' ),
                ],
			);

            return $settings;
        }

        public function add_email_settings( $settings ) {
            $settings[ $this->id ] = array(
				'notification_subject' => array(
					'id'   => 'instawp_notification_subject',
					'name' => __( 'Notification Subject', 'iwp-waas-integration' ),
					'desc' => __( 'Enter the subject line for the sale notification email.', 'iwp-waas-integration' ),
					'type' => 'text',
					'std'  => 'New download purchase - Order #{payment_id}',
				),
				'notification_heading' => array(
					'id'   => 'instawp_notification_heading',
					'name' => __( 'Notification Heading', 'iwp-waas-integration' ),
					'desc' => __( 'Enter the heading for the sale notification email.', 'iwp-waas-integration' ),
					'type' => 'text',
					'std'  => __( 'New Sale!', 'iwp-waas-integration' ),
				),
				'notification'         => array(
					'id'   => 'instawp_notification',
					'name' => __( 'Notification', 'iwp-waas-integration' ),
					'desc' => __( 'Text to email as a notification for every completed purchase. Personalize with HTML and <code>{tag}</code> markers.', 'iwp-waas-integration' ) . '<br/><br/>' . edd_get_emails_tags_list(),
					'type' => 'rich_editor',
				),
			);

            return $settings;
        }

        public function add_email_tag( $email_tags ) {
            $email_tags[] = array(
                'tag'         => 'waas_list',
                'label'       => __( 'InstaWP WaaS', 'iwp-waas-integration' ),
                'description' => __( 'InstaWP WaaS List Links.', 'iwp-waas-integration' ),
                'function'    => [ $this, 'email_tag_waas_details' ],
            );
            
            return $email_tags;
        }

        public function email_tag_waas_details( $payment_id ) {
            $downloads = edd_get_payment_meta_cart_details( $payment_id );
            $links     = $this->generate_links( $payment_id );
            $content   = 'None';
            $link_data = [];
            $items     = [];

            foreach ( $downloads as $download ) {
                $items[] = $download['name'];
            }

            foreach ( array_values( $links ) as $link_id => $link ) {
                $link_data[] = sprintf( 'InstaWP WaaS Unique Link for %s: %s', $items[ $link_id ], $link['create'] );
            }

            if ( ! empty( $link_data ) ) {
                $content = '<p>' . join( '</p><p>', $link_data ) . '</p>';
            }

            return $content;
        }

        public function add_field( $fields ) {
            $fields[] = '_edd_instawp_waas';
            
            return $fields;
        }

        public function show_waas_list( $post_id ) {
            $api_key = edd_get_option( 'instawp_api_key' );

            if ( ! current_user_can( 'manage_shop_settings' ) || ! $api_key ) {
                return;
            } ?>
            <div class="edd-form-group edd-product-options-wrapper" id="edd_instawp_waas_wrap">
                <div class="edd-form-group__control">
                    <label class="edd-form-group__label edd-product-options__title" for="edd_instawp_waas">
                        <?php esc_html_e( 'InstaWP WaaS', 'iwp-waas-integration' ); ?>
                    </label>
                    <?php
                    $args = array(
                        'name'             => '_edd_instawp_waas',
                        'id'               => 'edd_instawp_waas',
                        'selected'         => get_post_meta( $post_id, '_edd_instawp_waas', true ),
                        'options'          => $this->get_waas_options(),
                        'show_option_all'  => null,
                        'show_option_none' => 'None',
                        'class'            => 'edd-form-group__input',
                    );
                    echo EDD()->html->select( $args );
                    ?>
                </div>
            </div>
            <?php
        }

        public function generate_links( $payment_id ) {
            $payment      = edd_get_payment( $payment_id );
            $payment_meta = edd_get_payment_meta( $payment->ID );
            $downloads    = edd_get_payment_meta_cart_details( $payment->ID );
            $content      = 'None';
            $link_data    = [];

            foreach ( $downloads as $download ) {
                $waas_id = get_post_meta( $download['id'], '_edd_instawp_waas', true );
                $api_url = instawp_waas()->get_field_value( $waas_id, 'webhookUrl', edd_get_option( 'instawp_api_key' ) );
                $args    = [
                    'name'  => $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'],
                    'email' => $payment_meta['user_info']['email'],
                ];
                
                if ( edd_get_option( 'instawp_app_email' ) ) {
                    $args['send_email'] = true;
                }

                if ( $waas_id && $api_url ) {
                    $response = wp_remote_post( $api_url, [
                        'sslverify' => false,
                        'headers'   => [
                            'Authorization' => 'Bearer ' . edd_get_option( 'instawp_api_key' ),
                            'Content-Type'  => 'application/json'
                        ], 
                        'body'      => wp_json_encode( $args ),
                    ] );
            
                    if ( is_wp_error( $response ) ) {
                        error_log( 'error is: '. $response->get_error_message() );
                    } else {
                        $body = wp_remote_retrieve_body( $response );
                        $data = json_decode( $body );

                        if ( $data->status ) {
                            $links[ $data->data->link_id ] = [
                                'create' => $data->data->unique_link,
                                'cancel' => $data->data->cancel_link,
                            ];
                        }
                    }
                }
            }

            if ( ! empty( $links ) ) {
                $payment->update_meta( 'instawp_waas_links', $links );
            }

            return $links;
        }

        public function get_waas_options() {
            $options = [];
            $api_key = edd_get_option( 'instawp_api_key' );

            if ( ! $api_key ) {
                return $options;
            }

            $items = instawp_waas()->fetch_waas_list( $api_key );
            foreach ( $items as $item ) {
                $options[ $item['id'] ] = $item['name'];
            }

            return $options;
        }
    }
}