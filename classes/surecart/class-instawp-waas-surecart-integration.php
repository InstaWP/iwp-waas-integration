<?php

use SureCart\Models\Product;
use SureCart\Integrations\IntegrationService;
use SureCart\Integrations\Contracts\IntegrationInterface;
use SureCart\Integrations\Contracts\PurchaseSyncInterface;

/**
 * Controls the LearnDash integration.
 */
if ( ! class_exists( 'InstaWP_WaaS_SureCart_Integration' ) ) {
	
	class InstaWP_WaaS_SureCart_Integration extends IntegrationService implements IntegrationInterface, PurchaseSyncInterface {
		
		/**
		 * Get the slug for the integration.
		 *
		 * @return string
		 */
		public function getName() {
			return 'instawp/waas';
		}

		/**
		 * Get the SureCart model used for the integration.
		 * Only 'product' is supported at this time.
		 *
		 * @return string
		 */
		public function getModel() {
			return 'product';
		}

		/**
		 * Get the integration logo url.
		 * This can be to a png, jpg, or svg for example.
		 *
		 * @return string
		 */
		public function getLogo() {
			return esc_url_raw( INSTAWP_WAAS_WC_URL . 'images/icon-256x256.png' );
		}

		/**
		 * The display name for the integration in the dropdown.
		 *
		 * @return string
		 */
		public function getLabel() {
			return __( 'InstaWP WaaS', 'iwp-waas-integration' );
		}

		/**
		 * The label for the integration item that will be chosen.
		 *
		 * @return string
		 */
		public function getItemLabel() {
			return __( 'Connected WaaS', 'iwp-waas-integration' );
		}

		/**
		 * Help text for the integration item chooser.
		 *
		 * @return string
		 */
		public function getItemHelp() {
			return __( 'Copy paste the WaaS slug from WaaS preview URL', 'iwp-waas-integration' );
		}

		/**
		 * Is this enabled?
		 *
		 * @return boolean
		 */
		public function enabled() {
			return true;
		}

		public function getItems( $items = [], $search = '' ) {
			$options = instawp_waas()->fetch_waas_list( $this->get_api_key() );

			foreach ( $options as $item ) {
				$items[] = [
					'id'    => $item['id'],
					'label' => $item['name']
				];
			}

			if ( ! empty( $search ) ) {
				$items = array_filter( $items, function( $item ) use( $search ) {
					return strpos( strtolower( $item['label'] ), strtolower( $search ) ) !== false;
				} );
			}

			return $items;
		}

		public function getItem( $waas_id ) {
			$options = instawp_waas()->fetch_waas_list( $this->get_api_key() );
			$item    = [
				'id'    => $waas_id,
				'label' => 'Not found'
			];

			if ( ! empty( $options[ $waas_id ] ) ) {
				$item = [
					'id'    => $options[ $waas_id ]['id'],
					'label' => $options[ $waas_id ]['name']
				];
			}

			return $item;
		}

		public function get_api_key() {
			return wpsf_get_setting( 'iwp_waas_integration', 'settings_tab_settings', 'api_key' );
		}

		public function get_email_option( $key ) {
			return wpsf_get_setting( 'iwp_waas_integration', 'email_tab_email', $key );
		}

		/**
		 * Add the role when the purchase is created.
		 *
		 * @param \SureCart\Models\Integration $integration The integrations.
		 * @param \WP_User                     $wp_user The user.
		 *
		 * @return boolean|void Returns true if the user course access updation was successful otherwise false.
		 */
		public function onPurchaseCreated( $integration, $wp_user ) {
			$api_key = $this->get_api_key();
			if ( empty( $api_key ) || empty( $integration->integration_id ) || wpsf_get_setting( 'iwp_waas_integration', 'settings_tab_settings', 'app_email' ) ) {
				return;
			}

			$api_url = instawp_waas()->get_field_value( $integration->integration_id, 'webhookUrl', $api_key );
			if ( ! $api_url ) {
				return;
			}

			$response = wp_remote_post( $api_url, [
				'sslverify' => false,
				'headers'   => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json'
				], 
				'body'           => wp_json_encode( [
					'name'       => $wp_user->display_name,
					'email'      => $wp_user->user_email,
					'send_email' => wpsf_get_setting( 'iwp_waas_integration', 'settings_tab_settings', 'app_email' ),
				] ),
			] );
	
			if ( is_wp_error( $response ) ) {
				error_log( 'error is: '. $response->get_error_message() );
			} else {
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body );

				if ( $data->status && ! empty( $data->data->unique_link ) ) {
					$link = $data->data->unique_link;
				}
			}

			if ( isset( $link ) ) {
				$email_subject = $this->get_email_option( 'subject' ) ?? __( 'Your WaaS Link', 'iwp_waas' );
				$email_body    = $this->get_email_option( 'body' ) ?? __( 'Link to build your website is {{link}}: ', 'iwp-waas-integration' ) . $link;
				$email_body    = str_replace( '{{link}}', $link, $email_body );

				wp_mail( $wp_user->user_email , $email_subject, $email_body, [ 'Content-Type: text/html; charset=UTF-8' ] );
			}
		}

		/**
		 * Add the role when the purchase is invoked
		 *
		 * @param \SureCart\Models\Integration $integration The integrations.
		 * @param \WP_User                     $wp_user The user.
		 *
		 * @return boolean|void Returns true if the user course access updation was successful otherwise false.
		 */
		public function onPurchaseInvoked( $integration, $wp_user ) {
			$this->onPurchaseCreated( $integration, $wp_user );
		}
	}
}

(new InstaWP_WaaS_SureCart_Integration())->bootstrap();