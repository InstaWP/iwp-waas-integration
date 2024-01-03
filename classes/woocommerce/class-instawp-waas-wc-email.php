<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'InstaWP_WaaS_WC_Email' ) ) {

	class InstaWP_WaaS_WC_Email extends \WC_Email {
		/**
		 * Class constructor.
		 */
		public function __construct() {
			$this->id             = 'instawp_waas';
			$this->customer_email = true;
			$this->title          = __( 'InstaWP WaaS', 'iwp-waas-integration' );
			$this->description    = __( 'This Email will be sent when Send email through App option is enabled from Settings.', 'iwp-waas-integration' );
			$this->template_html  = 'emails/waas.php';
			$this->template_plain = 'emails/plain/waas.php';
			$this->template_base  = INSTAWP_WAAS_WC_PATH . 'templates/';
			$this->placeholders   = array(
				'{site_title}' => $this->get_blogname(),
			);
			
			// Triggers for this email.
			add_action( 'woocommerce_order_status_completed_notification', [ $this, 'trigger' ], 10, 2 );

			// Call parent constructor.
			parent::__construct();
		}

		public function get_default_subject() {
			return 'InstaWP WaaS Created for #{order_number}';
		}
	
		public function get_default_heading() {
			return 'InstaWP WaaS Details';
		}

		public function get_default_additional_content() {
			return 'Here are the details of InstaWP WaaS: {waas_list}';
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int            $order_id The order ID.
		 * @param WC_Order|false $order Order object.
		 */
		public function trigger( $order_id, $order = false ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                         = $order;
				$this->recipient                      = $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get content html.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				[
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				],
				'',
				$this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				[
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				],
				'',
				$this->template_base
			);
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = [
				'subject'            => [
					'title'       => __( 'Subject', 'iwp-waas-integration' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				],
				'heading'            => [
					'title'       => __( 'Email heading', 'iwp-waas-integration' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				],
				'additional_content' => [
					'title'       => __( 'Additional content', 'iwp-waas-integration' ),
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'iwp-waas-integration' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				],
				'email_type'         => [
					'title'       => __( 'Email type', 'iwp-waas-integration' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'iwp-waas-integration' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				],
			];
		}
	}

}

return new InstaWP_WaaS_WC_Email();
