<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Google_Analytics_Integration as Plugin;

/**
 * WC_Google_Gtag_JS class
 *
 * JS for recording Google Gtag info
 */
class WC_Google_Gtag_JS extends WC_Abstract_Google_Analytics_JS {

	/** @var string $script_handle Handle for the front end JavaScript file */
	public $script_handle = 'woocommerce-google-analytics-integration';

	/** @var string $script_data Data required for frontend event tracking */
	private $script_data = array();

	/** @var array $mappings A map of the GA4 events and the classic WooCommerce hooks that trigger them */
	private $mappings = array(
		'begin_checkout'         => 'woocommerce_before_checkout_form',
		'add_shipping_info'      => 'woocommerce_thankyou',
		'view_item_list'         => 'woocommerce_shop_loop',
		'add_to_cart'            => 'woocommerce_add_to_cart',
		// 'cart-set-item-quantity' => 'woocommerce_after_cart_item_quantity_update',
		'remove_from_cart'       => 'woocommerce_cart_item_removed',
		'view_item'              => 'woocommerce_after_single_product',
		'select_content'         => 'woocommerce_after_single_product',
	);

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $options Options
	 */
	public function __construct( $options = array() ) {
		parent::__construct();
		self::$options = $options;

		$this->load_analytics_config();
		$this->map_actions();

		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_footer', array( $this, 'inline_script_data' ) );
	}

	/**
	 * Register front end scripts and inline script data
	 *
	 * @return void
	 */
	public function register_scripts(): void {
		wp_enqueue_script(
			'google-tag-manager',
			'https://www.googletagmanager.com/gtag/js?id=' . self::get( 'ga_id' ),
			array(),
			null,
			false
		);

		wp_enqueue_script(
			$this->script_handle,
			Plugin::get_instance()->get_js_asset_url( 'main.js' ),
			array(
				...Plugin::get_instance()->get_js_asset_dependencies( 'main' ),
				'google-tag-manager',
			),
			Plugin::get_instance()->get_js_asset_version( 'main' ),
			true
		);
	}

	/**
	 * Add inline script data to the front end
	 *
	 * @return void
	 */
	public function inline_script_data(): void {
		wp_add_inline_script(
			$this->script_handle,
			sprintf(
				'const wcgaiData = %s;',
				$this->get_script_data()
			),
			'before'
		);
	}

	/**
	 * Hook into WooCommerce and add corresponding Blocks Actions to our event data
	 *
	 * @return void
	 */
	public function map_actions(): void {
		array_walk(
			$this->mappings,
			function( $hook, $gtag_event ) {
				add_action(
					$hook,
					function() use ( $gtag_event ) {
						$this->set_script_data( 'events', $gtag_event, true );
					}
				);
			}
		);
	}

	/**
	 * Add an event to the script data
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 * @param string       $key  If not null then the $data will be added as a new array item with this key.
	 *
	 * @return void
	 */
	public function set_script_data( string $type, string|array $data, ?string $key = null ): void {
		if ( ! isset( $this->script_data[ $type ] ) ) {
			$this->script_data[ $type ] = array();
		}

		if ( ! is_null( $key ) ) {
			$this->script_data[ $type ][ $key ] = $data;
		} else {
			$this->script_data[ $type ][] = $data;
		}

	}

	/**
	 * Return a JSON encoded string of all script data for the current page load
	 *
	 * @return string
	 */
	public function get_script_data(): string {
		return wp_json_encode( $this->script_data );
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	public static function tracker_var(): string {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Add Google Analytics configuration data to the script data
	 *
	 * @return void
	 */
	public function load_analytics_config(): void {
		$this->script_data['config'] = array(
			'developer_id'         => self::DEVELOPER_ID,
			'gtag_id'              => self::get( 'ga_id' ),
			'tracker_var'          => self::tracker_var(),
			'track_404'            => 'yes' === self::get( 'ga_404_tracking_enabled' ),
			'allow_google_signals' => 'yes' === self::get( 'ga_support_display_advertising' ),
			'link_attribution'     => 'yes' === self::get( 'ga_support_enhanced_link_attribution' ),
			'anonymize_ip'         => 'yes' === self::get( 'ga_anonymize_enabled' ),
			'logged_in'            => is_user_logged_in(),
			'linker'               => array(
				'domains'        => ! empty( self::get( 'ga_linker_cross_domains' ) ) ? array_map( 'esc_js', explode( ',', self::get( 'ga_linker_cross_domains' ) ) ) : array(),
				'allow_incoming' => 'yes' === self::get( 'ga_linker_allow_incoming_enabled' ),
			),
			'custom_map'           => array(
				'dimension1' => 'logged_in',
			),
		);
	}

	/**
	 * Get the class instance
	 *
	 * @param array $options Options
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	public static function get_instance( $options = array() ): WC_Abstract_Google_Analytics_JS {
		if ( null === self::$instance ) {
			self::$instance = new self( $options );
		}

		return self::$instance;
	}
}
