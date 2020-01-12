<?php
/**
 * Class WC_Gateway_DATAPONE file.
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + dataphone gateway
 */
function wc_dataphone_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_DATAPONE';
	return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'wc_dataphone_add_to_gateways' );


/**
 * Dataphone Payment Gateway.
 *
 * Provides a Dataphone Payment Gateway. Based on code by Mike Pepper.
 *
 * @class       WC_Gateway_DATAPONE
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce/Classes/Payment
 */

add_action( 'plugins_loaded', 'wc_dataphone_gateway_init', 11 );

function wc_dataphone_gateway_init() {

	class WC_Gateway_DATAPONE extends WC_Payment_Gateway {

		/**
		 * Array of locales
		 *
		 * @var array
		 */
		public $locale;

		/**
		 * Array of CCV fields
		 *
		 * @var array
		 */
		public $custom_fields;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'dataphone';
			$this->icon               = apply_filters( 'woocommerce_dataphone_icon', '' );
			$this->has_fields         = false;
			$this->method_title       = __( 'Dataphone payment transaction', 'woocommerce' );
			$this->method_description = __( 'Take payments in person via DATAPONE. Using an open dataphone to input credit cards and perform payments', 'woocommerce' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			// Actions.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_dataphone', array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_checkout_process', array( $this, 'open_dataphone_field_validation_process') );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_open_dataphone_fields' ) );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'open_dataphone_display_admin_order_meta' ), 10, 1 );
			// Customer Emails.
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'enabled'         => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Open Dataphone transfer', 'woocommerce' ),
					'default' => 'no',
				),
				'title'           => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Open Dataphone Payment', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'You will provide a valid credit card for us to charge you using an open Dataphone payment process. Your order will not be shipped until the funds have cleared in our account. We will notify you accordingly.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions'    => array(
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			);

		}

		public function init_settings() {
			$this->custom_fields = array(
				'card-number' => array(
			    	'field' => esc_attr( $this->id ) . '-card-number',
			    	'label' =>  __( 'Credit Card Number', 'woocommerce' ),
				),
				'card-expiry' => array(
			    	'field' => esc_attr( $this->id ) . 'card-expiry',
			    	'label' =>  __( 'Credit Expiration date', 'woocommerce' ),
			    ),
			    'card-cvc' => array(
			    	'field' => esc_attr( $this->id ) . 'card-cvc',
			    	'label' =>  __( 'Credit Card CVC', 'woocommerce' ),
				)
			);
		}

		/**
		 * Builds our payment fields area - including tokenization fields for logged
		 * in users, and the actual payment fields.
		 *
		 */
		public function payment_fields() {
			$description = $this->get_description();
			if ( $description ) {
				echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
			}
			$this->form();
		}

		/**
		 * Output field name HTML
		 *
		 * Gateways which support tokenization do not require names - we don't want the data to post to the server.
		 *
		 * @since  2.6.0
		 * @param  string $name Field name.
		 * @return string
		 */
		public function field_name( $name ) {
			return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
		}

		/**
		 * Outputs fields for entering credit card information.
		 *
		 * @since 2.6.0
		 */
		public function form() {

			$fields = array();

			$cvc_field = '<p class="form-row form-row-last">
				<label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" pattern="\d*" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
			</p>';

			$card_type = '<p class="form-row wc-credit-card-type"><div class="card-type"></div></p>';

			$default_fields = array(
				'card-number-field' => '
 				<div id="my-card-2" class="card-js" data-capture-name="true"></div>
				<p class="form-row form-row-wide">
					<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>

					<input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number"
	                    type="text" name="number"
	                    pattern="\d*"
	                    inputmode="numeric" autocomplete="cc-number" autocompletetype="cc-number" x-autocompletetype="cc-number" 
	                    autocorrect="no" autocapitalize="no" spellcheck="no" type="tel"
	                    placeholder="&#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149;"
	                    ' . $this->field_name( 'card-number' ) . '>
				</p>',
				'card-expiry-field' => '<p class="form-row form-row-first">
					<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YY)', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
				</p>',
			);

			$default_fields['card-cvc-field'] = $cvc_field;

			$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
			?>

			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
				<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
				<?php
				foreach ( $fields as $field ) {
					echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				}
				echo $card_type;
				?>
				<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
				<div class="clear"></div>
			</fieldset>
			<?php
		}

		public function open_dataphone_field_validation_process() {
			print_r($_POST);
			foreach ( $this->custom_fields as $field_name ) {
				if ( ! $_POST[$field_name['field']] ) {
				    wc_add_notice( __( $field_name['label'] . ' is mandatory. Please enter a value' ), 'error' );
				}
			}
		}

		/**
		 * Saving output of the credit card form for open dataphone payment.
		 *
		 * @param WC_Order $order Order instance.
		 * @param  array $data Posted data.
		 */
		public function save_open_dataphone_fields ( $order_id ) {
		    foreach ( $this->custom_fields as $field_name ) {
		        if ( ! empty ( $_POST[ $field_name['field'] ] ) ) {
		            $meta_key = '_' . $field_name['field'];
		            $field_value = sanitize_text_field ( $_POST[ $field_name['field'] ] ); // WC will handle sanitation
		            update_post_meta( $order_id, $meta_key, $field_value );
		        }
		    }

		}

		/**
		 * Displaying output of the credit card form for open dataphone payment in the admin page
		 *
		 * @param WC_Order $order Order instance.
		 * @param  array $data Posted data.
		 */

		public function open_dataphone_display_admin_order_meta ( $order ) {
			echo "<fieldset id='wc-" . esc_attr( $this->id ) . "-cc-info' class='postbox'>";
			foreach ( $this->custom_fields as $field_name ) {
	            $meta_key = '_' . $field_name['label'];
	            echo '<p><strong>'.__($field_name['label'], 'woocommerce').':</strong> <br/>' . get_post_meta( $order->get_id(), $field_name['field'], true ) . '</p>';
		    }
		    echo "</fieldset>";
		}


		/**
		 * Output for the order received page.
		 *
		 * @param int $order_id Order ID.
		 */
		public function thankyou_page ( $order_id ) {

			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
			}
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @param WC_Order $order Order object.
		 * @param bool     $sent_to_admin Sent to admin.
		 * @param bool     $plain_text Email format: plain text or HTML.
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( ! $sent_to_admin && 'dataphone' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				if ( $this->instructions ) {
					echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
				}
			}

		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			if ( $order->get_total() > 0 ) {
				// Mark as on-hold (we're awaiting the payment).
				$order->update_status( apply_filters( 'woocommerce_dataphone_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting DATAPONE payment', 'woocommerce' ) );
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thankyou redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}

	}
}