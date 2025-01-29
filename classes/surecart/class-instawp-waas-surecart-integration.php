<?php

use SureCart\Models\Purchase;
use SureCart\Integrations\IntegrationService;
use SureCart\Integrations\Contracts\IntegrationInterface;
use SureCart\Integrations\Contracts\PurchaseSyncInterface;

/**
 * Controls the InstaWP WaaS integration.
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

		/**
		 * Gets a filtered list of WaaS items.
		 *
		 * @param array $items Initial items array.
		 * @param string $search Search term to filter by.
		 * @return array Filtered array of WaaS items.
		 */
		public function getItems( $items = [], $search = '' ) {
			$options = $this->getWaaSList();

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

		/**
		 * Gets a specific WaaS item by ID.
		 *
		 * @param string $waas_id The WaaS ID.
		 * @return array The WaaS item details.
		 */
		public function getItem( $waas_id ) {
			$options = $this->getWaaSList();
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

		/**
		 * Gets the API key from plugin settings.
		 *
		 * @return string The API key.
		 */
		private function getApiKey() {
			return wpsf_get_setting( 'iwp_waas_integration', 'settings_tab_settings', 'api_key' );
		}

		/**
		 * Gets a specific email option from settings.
		 *
		 * @param string $key The option key to retrieve.
		 * @return string|null The email option value.
		 */
		private function getEmailOption( $key ) {
			return wpsf_get_setting( 'iwp_waas_integration', 'email_tab_email', $key );
		}

		/**
		 * Checks if the app email is enabled.
		 *
		 * @return boolean
		 */
		private function canSendAppEmail() {
			return (bool) wpsf_get_setting( 'iwp_waas_integration', 'settings_tab_settings', 'app_email' );
		}

		/**
		 * Retrieves the list of WaaS instances.
		 *
		 * @return array Array of WaaS instances.
		 */
		private function getWaaSList() {
			$cached = get_transient( 'iwp_waas_api_data' );
            if ( ! empty( $cached ) && is_array( $cached ) ) {
                return $cached;
            }

			$options = instawp_waas()->fetch_waas_list( $this->getApiKey() );
			set_transient( 'iwp_waas_api_data', $options, 300 );

			return $options;
		}

		/**
		 * Create InstaWP WaaS Link when the purchase is created.
		 *
		 * @param \SureCart\Models\Integration $integration The integrations.
		 * @param \WP_User                     $wp_user The user.
		 *
		 * @return void
		 */
		public function onPurchaseCreated( $integration, $wp_user ) {
			if ( $this->isEmailSent( $wp_user->ID, $integration->integration_id ) || ! $this->hasValidCredentials( $integration ) || empty( $this->getPurchaseId() ) ) {
				return;
			}

			$api_url = $this->getApiUrl( $integration );
			if ( ! $api_url ) {
				return;
			}

			$purchase = Purchase::with( [ 'order', 'invoice' ] )->find( $this->getPurchaseId() );
			$response = $this->makeApiRequest( $api_url, $wp_user );

			if ( is_wp_error( $response ) ) {
				error_log( 'error is: ' . $response->get_error_message() );
				return;
			}

			$this->handleCustomEmail( $response, $purchase, $wp_user );
		}

		/**
		 * Validates if the integration has valid API key and integration ID.
		 *
		 * @param \SureCart\Models\Integration $integration The integration instance.
		 * @return boolean True if credentials are valid, false otherwise.
		 */
		private function hasValidCredentials( $integration ) {
			$api_key = $this->getApiKey();
			return ! empty( $api_key ) && ! empty( $integration->integration_id );
		}

		/**
		 * Gets the API URL for the integration.
		 *
		 * @param \SureCart\Models\Integration $integration The integration instance.
		 * @return string|false The API URL if found, false otherwise.
		 */
		private function getApiUrl( $integration ) {
			return instawp_waas()->get_field_value( $integration->integration_id, 'webhookUrl', $this->getApiKey() );
		}

		/**
		 * Makes an API request to create a WaaS instance.
		 *
		 * @param string $api_url The API endpoint URL.
		 * @param \WP_User $wp_user The WordPress user.
		 * @return array|\WP_Error The API response or WP_Error on failure.
		 */
		private function makeApiRequest( $api_url, $wp_user ) {
			$args = [
				'name'  => $wp_user->display_name,
				'email' => $wp_user->user_email,
			];

			if ( $this->canSendAppEmail() ) {
				$args['send_email'] = true;
			}

			return wp_remote_post( $api_url, [
				'sslverify' => false,
				'headers'   => [
					'Authorization' => 'Bearer ' . $this->getApiKey(),
					'Content-Type'  => 'application/json'
				],
				'body'      => wp_json_encode( $args ),
			] );
		}

		/**
		 * Handles the custom email process after successful WaaS creation.
		 *
		 * @param array $response The API response.
		 * @param \SureCart\Models\Purchase $purchase The purchase instance.
		 * @param \WP_User $wp_user The WordPress user.
		 * @return void
		 */
		private function handleCustomEmail( $response, $purchase, $wp_user ) {
			if ( $this->canSendAppEmail() ) {
				return;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body );

			if ( ! $data->status ) {
				return;
			}

			$this->storeCancelLink( $purchase, $data, $wp_user );
			
			$link = $data->data->unique_link;
			if ( ! empty( $link ) ) {
				$this->sendCustomEmail( $wp_user, $link );
			}
		}

		/**
		 * Stores the cancellation link in user meta.
		 *
		 * @param \SureCart\Models\Purchase $purchase The purchase instance.
		 * @param object $data The API response data.
		 * @param \WP_User $wp_user The WordPress user.
		 * @return void
		 */
		private function storeCancelLink( $purchase, $data, $wp_user ) {
			if ( empty( $data->data->cancel_link ) ) {
				return;
			}

			$waas_list = $this->getUserWaaSList( $wp_user->ID );
			$waas_list[ $purchase->id ] = $data->data->cancel_link;

			$this->updateUserWaaSList( $wp_user->ID, $waas_list );
		}

		/**
		 * Sends a custom email to the user with their WaaS link.
		 *
		 * @param \WP_User $wp_user The WordPress user.
		 * @param string $link The WaaS instance link.
		 * @return void
		 */
		private function sendCustomEmail( $wp_user, $link ) {
			$email_subject = $this->getEmailOption( 'subject' ) ?? __( 'Your WaaS Link', 'iwp-waas-integration' );
			$email_body    = $this->getEmailOption( 'body' ) ?? __( 'Link to build your website is {{link}}: ', 'iwp-waas-integration' ) . $link;

			$email_body    = str_replace('{{link}}', $link, $email_body);

			$result = wp_mail(
				$wp_user->user_email,
				$email_subject,
				$email_body,
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);

			if ( ! $result ) {
				error_log( 'error sending email to: ' . $wp_user->user_email );
			}
		}

		/**
		 * Create InstaWP WaaS Link when the purchase is invoked.
		 *
		 * @param \SureCart\Models\Integration $integration The integrations.
		 * @param \WP_User                     $wp_user The user.
		 *
		 * @return void
		 */
		public function onPurchaseInvoked( $integration, $wp_user ) {
			$this->onPurchaseCreated( $integration, $wp_user );
		}

		/**
		 * Delete InstaWP WaaS Link when the purchase is revoked.
		 *
		 * @param \SureCart\Models\Integration $integration The integrations.
		 * @param \WP_User                     $wp_user The user.
		 *
		 * @return boolean|void Returns true if the user course access updation was successful otherwise false.
		 */
		public function onPurchaseRevoked( $integration, $wp_user ) {
			if ( ! $this->hasValidCredentials( $integration ) || empty( $this->getPurchaseId() ) ) {
				return;
			}

			$purchase = Purchase::with( [ 'order', 'invoice' ] )->find( $this->getPurchaseId() );
			$waas_list = $this->getUserWaaSList( $wp_user->ID );

			if ( ! isset( $waas_list[ $purchase->id ] ) ) {
				return;
			}

			$this->deleteCancelLink( $waas_list[ $purchase->id ] );
			$this->updateWaaSList( $wp_user->ID, $waas_list, $purchase->id );
		}

		/**
		 * Deletes the WaaS instance using the cancel link.
		 *
		 * @param string $cancel_link The cancellation link URL.
		 * @return array|\WP_Error The API response or WP_Error on failure.
		 */
		private function deleteCancelLink( $cancel_link ) {
			return wp_remote_request( $cancel_link, [
				'method'    => 'DELETE',
				'sslverify' => false,
				'headers'   => [
					'Authorization' => 'Bearer ' . $this->getApiKey(),
					'Content-Type'  => 'application/json'
				],
			] );
		}

		/**
		 * Updates or removes the user's stored waas list.
		 *
		 * @param int $user_id The user ID.
		 * @param array $waas_list Array of existing waas list.
		 * @param string $purchase_id The purchase ID to remove.
		 * @return void
		 */
		private function updateWaaSList( $user_id, $waas_list, $purchase_id ) {
			unset( $waas_list[ $purchase_id ] );

			if ( ! empty( $waas_list ) ) {
				$this->updateUserWaaSList( $user_id, $waas_list );
			} else {
				$this->deleteUserWaaSList( $user_id );
			}
		}

		/**
		 * Checks if an email has been sent to the user recently.
		 *
		 * @param int $user_id The user ID.
		 * @param string $integration_id The integration ID.
		 * @return boolean True if email was sent within the last 5 seconds, false otherwise.
		 */
		private function isEmailSent( $user_id, $integration_id ) {
			$key     = 'instawp_waas_surecart_send_email_' . $user_id . '_' . $integration_id;
			$is_sent = (bool) get_transient( $key );
			if ( ! $is_sent ) {
				set_transient( $key, true, 5 );
			}

			return $is_sent;
		}

		/**
		 * Gets the user meta for the given key.
		 *
		 * @param int $user_id The user ID.
		 * @return array|null The user meta array or null if not found.
		 */
		private function getUserWaaSList( $user_id ) {
			$waas_list = get_user_meta( $user_id, 'instawp_waas_surecart_link_ids', true );
			return empty( $waas_list ) ? [] : $waas_list;
		}

		/**
		 * Updates the user meta for the given key.
		 *
		 * @param int $user_id The user ID.
		 * @param string $key The meta key.
		 * @param mixed $value The value to update.
		 * @return void
		 */
		private function updateUserWaaSList( $user_id, $value ) {
			update_user_meta( $user_id, 'instawp_waas_surecart_link_ids', $value );
		}

		/**
		 * Deletes the user's WaaS list.
		 *
		 * @param int $user_id The user ID.
		 * @return void
		 */
		private function deleteUserWaaSList( $user_id ) {
			delete_user_meta( $user_id, 'instawp_waas_surecart_link_ids' );
		}
	}
}

( new InstaWP_WaaS_SureCart_Integration() )->bootstrap();