<?php

/**
 * Plugin Name: WooCommerce PostaPont
 * Plugin URI: http://visztpeter.me
 * Description: PostaPont integráció WooCommerce-hez.
 * Version: 1.0.2
 * Author: Peter Viszt
 * Author URI: http://visztpeter.me
 */



if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_PostaPont {

	public static $plugin_prefix;
	public static $plugin_url;
	public static $plugin_path;
	public static $plugin_basename;
	public static $version;

    //Construct
    public function __construct() {

		//Default variables
		self::$plugin_prefix = 'wc_postapont_';
		self::$plugin_basename = plugin_basename(__FILE__);
		self::$plugin_url = plugin_dir_url(self::$plugin_basename);
		self::$plugin_path = trailingslashit(dirname(__FILE__));
		self::$version = '1.0.2'; 

		//Add settings to Settings / Shipping
		add_filter( 'woocommerce_shipping_settings', array( $this, 'settings' ) );

		//Load frontend scripts
 		add_action('wp_enqueue_scripts', array( $this, 'frontend_css_js') );

 		//Add html for the map selector
 		add_action('woocommerce_review_order_after_order_total', array( $this, 'wc_postapont_map_html') );

 		//Validate PostaPont selection
 		add_action('woocommerce_checkout_process', array( $this, 'wc_postapont_map_validation') );

 		//Save the selected postapont
 		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'wc_postapont_map_save_selected_location') );

 		//Show selected value on the thankyou page
 		add_action( 'woocommerce_thankyou', array( $this, 'wc_postapont_thankyou_page') );

 		//Show selected value in user / admin emails
 		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'wc_postapont_show_selected_location_admin') , 10, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'wc_postapont_show_selected_location_admin') ,10,1);

    }

    //Add CSS & JS
	public function frontend_css_js() {
		global $post;

		//Only if we're on the checkout page
		if(get_the_ID () == get_option ( 'woocommerce_checkout_page_id' , 0 )) {
			$google_maps_url = 'http://maps.googleapis.com/maps/api/js?sensor=false&language=hu&region=HU';
			if(get_option( 'wc_postapont_maps_api' ) != '') {
				$google_maps_url = $google_maps_url.'&key='.get_option( 'wc_postapont_maps_api' );
			}
			wp_enqueue_script('wc-postapont-google-maps', $google_maps_url,array( 'jquery' ));
			wp_enqueue_style('wc-postapont-css',wc_postapont::$plugin_url.'postapont.css');
			wp_register_script( 'wc-postapont-js', wc_postapont::$plugin_url.'postapont.js',array( 'jquery' ));

			$categories = array('10_posta','20_molkut','30_csomagautomata');
			$posta_pont_options = array();
			foreach($categories as $category) {
				if(get_option('woocommerce_postapont_'.$category,'yes') == 'no') {
					$posta_pont_options['disabled_categories'][] = $category;
				}
			}
			wp_localize_script( 'wc-postapont-js', 'posta_pont_options', $posta_pont_options );
			wp_enqueue_script('wc-postapont-js');

		}
    }

	//Settings
	public function settings( $settings ) {
		$updated_settings = array();
		foreach ( $settings as $section ) {
			if ( isset( $section['id'] ) && 'shipping_options' == $section['id'] && isset( $section['type'] ) && 'sectionend' == $section['type'] ) {
				$shipping_methods = array();
				global $woocommerce;

				$updated_settings[] = array(
					'title'         => __( 'Posta Pont Jelölőkategóriák', 'wc_postapont' ),
					'desc'          => __( 'PostaPontok', 'wc_postapont' ),
					'id'            => 'woocommerce_postapont_10_posta',
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => 'start'
				);

				$updated_settings[] = array(
					'desc'          => __( 'MOL PostaPontok', 'wc_postapont' ),
					'id'            => 'woocommerce_postapont_20_molkut',
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => '',
					'autoload'      => false

				);

				$updated_settings[] = array(
					'desc'          => __( 'Csomagautomaták', 'wc_postapont' ),
					'id'            => 'woocommerce_postapont_30_csomagautomata',
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => 'end',
					'autoload'      => false,
					'desc_tip'      => __( 'Alapértelmezésben minden jelölőkategória megjelenik a térképen. Egy vagy több kategória is kikapcsolható egyszerre.', 'wc_postapont' ),
				);

				$shipping_methods[''] = __( 'Válassz egy szállítási módot', 'wc_postapont' );
				foreach ( WC()->shipping->load_shipping_methods() as $method ) {
					if($method->enabled == 'yes') $shipping_methods[$method->id] = $method->get_title();
				}

				$updated_settings[] = array(
					'name'     => __( 'Posta Pont Szállítási Mód', 'wc_postapont' ),
					'id'       => 'wc_postapont_shipping_method',
					'type' 	   => 'select',
					'css' 	   => 'min-width:150px;',
					'options'  => $shipping_methods,
					'class'	   => 'chosen_select',
					'desc'     => '<br>'.__( 'Válaszd ki azt a szállítási módot, amikor szeretnéd, hogy a Posta Pont térkép megjelenjen.', 'wc_postapont' ),
				);

				$updated_settings[] = array(
					'name'     => __( 'Posta Pont Google Api Key', 'wc_postapont' ),
					'id'       => 'wc_postapont_maps_api',
					'type' 	   => 'text',
					'desc'     => __( 'Opcionális', 'wc_postapont' ),
					'desc_tip' =>  __( 'A térkép használata ingyenes, azonban nagyobb forgalom esetén üzleti licence szükséges. Készíthet úgynevezett API kulcsot is a térkép alkalmazásához, amellyel nyomon követheti annak használatát, statisztikákat kérhet, és más hasznos tevékenységet végezhet. Opcionális.', 'wc_postapont' ),
				);

			}
			$updated_settings[] = $section;
		}
		return $updated_settings;
	}

	//Display extra HTML on checkout
	public function wc_postapont_map_html() {
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		if(get_option('wc_postapont_shipping_method') == $chosen_methods[0]):
		?>
		<tr class="wc_postapont_select_row">
			<td colspan="2">
				<div id="postapontvalasztoapi"></div>
				<input type="hidden" name="wc_selected_postapont" id="wc_selected_postapont" />
				<div id="valasztott_postapont"><strong><?php _e('A kiválasztott Posta Pont átvevőhely:','wc_postapont'); ?></strong><p><?php _e('Kérjük, válasszon <strong>átvevőhelyet</strong> a legördülő listából, vagy a térképen egy PostaPontra kattintva!','wc_postapont'); ?></p></div>
			</td>
		</tr>
		<?php
		endif;
	}

	//Checkout Validation
	public function wc_postapont_map_validation() {
		//Only required if its a posta pont shipping method
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		if(get_option('wc_postapont_shipping_method') == $chosen_methods[0]) {

		    if ( ! $_POST['wc_selected_postapont'] )
	        wc_add_notice( __( 'Kérjük, válasszon <strong>átvevőhelyet</strong> a legördülő listából, vagy a térképen egy PostaPontra kattintva!', 'wc_postapont' ), 'error' );

	    }
	}

	//Save Posta Pont meta
	public function wc_postapont_map_save_selected_location($order_id) {
		if ( ! empty( $_POST['wc_selected_postapont'] ) ) {
			update_post_meta( $order_id, 'wc_selected_postapont', sanitize_text_field( $_POST['wc_selected_postapont'] ) );
	    }
	}

	//Display on the Thank You page
	public function wc_postapont_thankyou_page($order_id) {
		//Check if PostaPont was selected
		$posta_pont_data = get_post_meta($order_id,'wc_selected_postapont',true);
		if($posta_pont_data) {
			$posta_pont_data = explode('|', $posta_pont_data);
			$posta_pont_data = implode('<br>', $posta_pont_data);
			?>
			<h2><?php _e('A kiválasztott Posta Pont átvevőhely:','wc_postapont'); ?></h2>
			<address>
			<?php echo $posta_pont_data; ?>
			</address>
			<?php
		}
	}

	//Display on admin order page
	public function wc_postapont_show_selected_location_admin($order){

		//Check if PostaPont was selected
		$posta_pont_data = get_post_meta($order->id,'wc_selected_postapont',true);
		if($posta_pont_data) {
			$posta_pont_data = explode('|', $posta_pont_data);
			$posta_pont_data = implode('<br>', $posta_pont_data);
		}

		?>
		<h4><?php _e('A kiválasztott Posta Pont átvevőhely:','wc_postapont'); ?></h4>
		<p>
		<?php echo $posta_pont_data; ?>
		</p>
		<?php

	}

}

$GLOBALS['wc_postapont'] = new wc_postapont();

//PostaPont szállítási mód súly alapján
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function wc_postapont_shipping_method_init() {
		if ( ! class_exists( 'WC_PostaPont_Shipping_Method' ) ) {
			class WC_PostaPont_Shipping_Method extends WC_Shipping_Method {
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'wc_postapont_shipping_method'; // Id for your shipping method. Should be uunique.
					$this->method_title       = __( 'PostaPont Szállítási Mód', 'wc_postapont' );  // Title shown in admin
					$this->method_description = __( 'Ez egy egyszerű súly alapú szállítási mód. A Posta Pont intergrációhoz nem kötelező használni, mert a térképes választót bármely, már meglévő szállítási módhoz hozzá lehet csatolni a Beállítások / Szállítás menüpontban.', 'wc_postapont' ); // Description shown in admin
					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

					// Define user set variables
					$this->title 		  = $this->get_option( 'title' );
					$this->tax_status	  = $this->get_option( 'tax_status' );
					$this->options 		  = (array) explode( "\n", $this->get_option( 'options' ) );

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}


				function init_form_fields() {

					$this->form_fields = array(
						'enabled' => array(
							'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
							'type' 			=> 'checkbox',
							'label' 		=> __( 'Enable this shipping method', 'woocommerce' ),
							'default' 		=> 'no',
						),
						'title' => array(
							'title' 		=> __( 'Method Title', 'woocommerce' ),
							'type' 			=> 'text',
							'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default'		=> __( 'PostaPont', 'woocommerce' ),
							'desc_tip'		=> true
						),
						'tax_status' => array(
							'title' 		=> __( 'Tax Status', 'woocommerce' ),
							'type' 			=> 'select',
							'default' 		=> 'taxable',
							'options'		=> array(
								'taxable' 	=> __( 'Taxable', 'woocommerce' ),
								'none' 		=> _x( 'None', 'Tax status', 'woocommerce' )
							),
						),
						'options' => array(
							'title' 		=> __( 'Additional Rates', 'woocommerce' ),
							'type' 			=> 'textarea',
							'description'	=> __( 'Soronként egy szállítási értéket lehet megadni súly alapján: Minimum súly(kg) | Maximum súly(kg) | Ár<br> Például: <code>0 | 1 | 1250</code>.', 'wc_postapont' ),
							'default'		=> '',
							'desc_tip'		=> true,
							'placeholder'	=> __( 'Minimum súly | Maximum súly | Ár', 'wc_postapont' )
						)
					);

				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package ) {

					if ( sizeof( $this->options ) > 0) {

						//Get items weight
						global $woocommerce;
						$total_weight = $woocommerce->cart->cart_contents_weight;

						//Shipping costs
						$costs = array();

						// Loop options
						foreach ( $this->options as $option ) {

							$this_option = array_map( 'trim', explode( WC_DELIMITER, $option ) );

							if ( sizeof( $this_option ) !== 3 ) continue;

							if(($total_weight >= $this_option[0]) && ($total_weight <= $this_option[1])) {
								$costs[] = $this_option[2];
							}
						}

						if(!empty($costs)) {

							//Add shipping rate
							$rate = array(
								'id' => $this->id,
								'label' => $this->title,
								'cost' => min($costs),
							);

							// Register the rate
							$this->add_rate( $rate );

						}

					}

				}
			}
		}
	}

	add_action( 'woocommerce_shipping_init', 'wc_postapont_shipping_method_init' );

	function wc_postapont_add_shipping_method( $methods ) {
		$methods[] = 'WC_PostaPont_Shipping_Method';
		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'wc_postapont_add_shipping_method' );
}


?>
