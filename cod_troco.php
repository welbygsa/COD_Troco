<?php
/*
Plugin Name: WooCommerce cod_troco Gateway
Plugin URI: http://vistapub.com.br/cod_troco
Description: Extends WooCommerce with an cod_troco gateway.
Version: 1.0
Author: WooThemes
Author URI: http://vistapub.com.br/
	Copyright: © 2009-2011 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}


add_action('plugins_loaded', 'woocommerce_cod_troco_init', 0);
function woocommerce_cod_troco_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	/**
 	 * Localisation
	 */
	load_plugin_textdomain('wc-cod_troco', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
	/**
 	 * Gateway class
 	 */
	class WC_Cod_Troco extends WC_Payment_Gateway {
	
		// Go wild in here
		
		
		    /**
		     * Constructor for the gateway.
		     */
			public function __construct() {
				$this->id                 = 'cod_troco';
				$this->icon               = apply_filters( 'woocommerce_cod_troco_icon', '' );
				$this->method_title       = __( 'Pagamento na Entrega com Troco', 'woocommerce' );
				$this->method_description = __( 'Have your customers pay with cash (or by other means) upon delivery.', 'woocommerce' );
				$this->has_fields         = true;

				// Load the settings
				$this->init_form_fields();
				$this->init_settings();

				// Get settings
				$this->title              = $this->get_option( 'title' );
				$this->description        = $this->get_option( 'description' );
				$this->instructions       = $this->get_option( 'instructions', $this->description );
				$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
				$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou_cod_troco', array( $this, 'thankyou_page' ) );

		    	// Customer Emails
		    	add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
			}

		    /**
		     * Initialise Gateway Settings Form Fields
		     */
		    public function init_form_fields() {
		    	$shipping_methods = array();

		    	if ( is_admin() )
			    	foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
				    	$shipping_methods[ $method->id ] = $method->get_title();
			    	}

		    	$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable COD_troco', 'woocommerce' ),
						'label'       => __( 'Enable Cash on Delivery', 'woocommerce' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no'
					),
					'title' => array(
						'title'       => __( 'Title', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
						'default'     => __( 'Cash on Delivery', 'woocommerce' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
						'default'     => __( 'Pay with cash upon delivery.', 'woocommerce' ),
						'desc_tip'    => true,
					),
					'instructions' => array(
						'title'       => __( 'Instructions', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
						'default'     => __( 'Pay with cash upon delivery.', 'woocommerce' ),
						'desc_tip'    => true,
					),
					'enable_for_methods' => array(
						'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
						'type'              => 'multiselect',
						'class'             => 'wc-enhanced-select',
						'css'               => 'width: 450px;',
						'default'           => '',
						'description'       => __( 'If COD is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
						'options'           => $shipping_methods,
						'desc_tip'          => true,
						'custom_attributes' => array(
							'data-placeholder' => __( 'Select shipping methods', 'woocommerce' )
						)
					),
					'enable_for_virtual' => array(
						'title'             => __( 'Accept for virtual orders', 'woocommerce' ),
						'label'             => __( 'Accept COD if the order is virtual', 'woocommerce' ),
						'type'              => 'checkbox',
						'default'           => 'yes'
					)
		 	   );
		    }



			/**
			 * Payment form on checkout page
			 */
			public function payment_fields() {
				$description = $this->get_description();

				if ( $description ) {
					echo wpautop( wptexturize( trim( $description ) ) );
				}

				/* Colocar o Campo Troco aqui */

				?>
				<form class="form-horizontal" role="form" id="bv-form" style="margin-top:10px;">
				<fieldset>
					<div>
						<label for="troco">Informe a necessidade de Máquina de Cartão ou Troco para: </label>
						<input id="troco" class="input-md" type="text" maxlength="40" name="troco" />
					</div>
					<div class="clear"></div>
				</fieldset>
				</form>
				<?

			}



			/**
			 * Check If The Gateway Is Available For Use
			 *
			 * @return bool
			 */
			public function is_available() {
				$order          = null;
				$needs_shipping = false;

				// Test if shipping is needed first
				if ( WC()->cart && WC()->cart->needs_shipping() ) {
					$needs_shipping = true;
				} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
					$order_id = absint( get_query_var( 'order-pay' ) );
					$order    = wc_get_order( $order_id );

					// Test if order needs shipping.
					if ( 0 < sizeof( $order->get_items() ) ) {
						foreach ( $order->get_items() as $item ) {
							$_product = $order->get_product_from_item( $item );
							if ( $_product->needs_shipping() ) {
								$needs_shipping = true;
								break;
							}
						}
					}
				}

				$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

				// Virtual order, with virtual disabled
				if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
					return false;
				}

				// Check methods
				if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {

					// Only apply if all packages are being shipped via chosen methods, or order is virtual
					$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

					if ( isset( $chosen_shipping_methods_session ) ) {
						$chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
					} else {
						$chosen_shipping_methods = array();
					}

					$check_method = false;

					if ( is_object( $order ) ) {
						if ( $order->shipping_method ) {
							$check_method = $order->shipping_method;
						}

					} elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
						$check_method = false;
					} elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
						$check_method = $chosen_shipping_methods[0];
					}

					if ( ! $check_method ) {
						return false;
					}

					$found = false;

					foreach ( $this->enable_for_methods as $method_id ) {
						if ( strpos( $check_method, $method_id ) === 0 ) {
							$found = true;
							break;
						}
					}

					if ( ! $found ) {
						return false;
					}
				}

				return parent::is_available();
			}


		    /**
		     * Process the payment and return the result
		     *
		     * @param int $order_id
		     * @return array
		     */
			public function process_payment( $order_id ) {

				$order = wc_get_order( $order_id );

				// Mark as processing (payment won't be taken until delivery)
				$order->update_status( 'processing', __( 'Payment to be made upon delivery.', 'woocommerce' ) );

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				WC()->cart->empty_cart();

				// Return thankyou redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);
			}

		    /**
		     * Output for the order received page.
		     */
			public function thankyou_page() {
				if ( $this->instructions ) {
		        	echo wpautop( wptexturize( $this->instructions ) );
				}
			}

		    /**
		     * Add content to the WC emails.
		     *
		     * @access public
		     * @param WC_Order $order
		     * @param bool $sent_to_admin
		     * @param bool $plain_text
		     */
			public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
				if ( $this->instructions && ! $sent_to_admin && 'cod_troco' === $order->payment_method ) {
					echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
				}
			}
		
		
		
		
		
		
	}
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_cod_troco_gateway($methods) {
		$methods[] = 'WC_Cod_Troco';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_cod_troco_gateway' );
} 
