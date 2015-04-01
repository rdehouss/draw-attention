<?php

// uncomment this line for testing
//set_site_transient( 'update_plugins', null );

class DrawAttention_Updater {
	public $parent;
	
	const edd_store_url = 'http://tylerdigital.com';

	function __construct( $parent ) {
		$this->parent = $parent;

		add_action( 'admin_init', array( $this, 'plugin_updater') );
		add_action( 'admin_init', array( $this, 'activate_license' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'plugin_action_links_' . 'draw-attention-pro/'.$this->parent->plugin_slug.'.php', array( $this, 'add_action_links' ) );
	}

	public function admin_menu() {
		global $submenu;

		add_submenu_page( 'edit.php?post_type=da_image', __( 'License & Support', 'drawattention' ), __( 'License & Support', 'drawattention' ), 'manage_options', 'da_license', array( $this, 'output_license_page' ) );
	}

	public function add_action_links( $links ) {
		$license_key_status = get_option( 'da_license_key_status' );
		if ( $license_key_status == 'valid' ) { return $links; }

		return array_merge(
			array(
				'license' => '<a href="'.admin_url( 'edit.php?post_type=da_image&page=da_license' ).'">' . __( 'Enter License Key', 'drawattention' ) . '</a>'
			),
			$links
		);

	}

	function output_license_page() {
		echo '<h2>'.__('Draw Attention Pro - License & Updates', 'drawattention' ).'.</h2>';
		echo $this->license_key_html();
	}

	function license_key_html() {
		$license_key_status = get_option( 'da_license_key_status' );
		if ( empty( $license_key_status ) ) {
			$license_key_status_html = '<p class="notice-yellow">'.__( 'Please enter your license key to receive support & updates', 'drawattention' ).'. <a href="http://tylerdigital.com/products/draw-attention/" target="_blank">'.__( 'Click here to purchase or renew a license', 'drawattention' ).'</a></p>';
		} elseif ( $license_key_status == 'valid' ) {
			$license_key_status_html = '<p class="notice-green">'.__( 'Valid license', 'drawattention' ).'</p>';
		} elseif ( $license_key_status == 'invalid' ) {
			$license_key_status_html = '<p class="notice-red">'.__( 'Invalid license. Please verify the license key, you may need to <a href="http://tylerdigital.com/products/draw-attention/" target="_blank">renew your license</a> or <a href="mailto:support@tylerdigital.com">contact support</a></p>', 'drawattention');
		}

		$html  = '<form>';
		$html .= '<label for="da_license_key">'.__( 'License Key', 'drawattention' ).'</label><br />';
		$html .= '<input type="hidden" name="post_type" value="da_image" />';
		$html .= '<input type="hidden" name="page" value="da_license" />';
		if ( $license_key = get_option( 'da_license_key' ) ) {
			$html .= '<input type="password" name="da_license_key" id="da_license_key" size="32" value="'.$license_key.'" />';
		} else {
			$html .= '<input type="text" name="da_license_key" id="da_license_key" size="32" />';
		}
		$html .= $license_key_status_html;
		$html .= '<input type="submit" value="'.__( 'Update', 'drawattention' ).'" />';
		$html .= '</form>';

		return $html;
	}

	function plugin_updater() {
		$license_key = trim( get_option( 'da_license_key' ) );
		$edd_updater = new TD_DA_EDD_SL_Plugin_Updater( self::edd_store_url, dirname ( dirname( DrawAttention::file ) ).'/draw-attention.php', array( 
				'version' 	=> DrawAttention::VERSION, 				// current version number
				'license' 	=> $license_key, 		// license key (used get_option above to retrieve from DB)
				'item_name' => DrawAttention::name, 	// name of this plugin
				'author' 	=> 'Tyler Digital'  // author of this plugin
			)
		);
	}

	function activate_license() {
		if ( !isset( $_REQUEST['da_license_key'] ) ) return;

		$license_key = trim( $_REQUEST['da_license_key'] );
		if ( empty( $license_key ) ) {
			delete_option( 'da_license_key_status' );
			delete_option( 'da_license_key' );
			return;
		}


		// data to send in our API request
		$api_params = array( 
			'edd_action'=> 'activate_license', 
			'license' 	=> $license_key, 
			'item_name' => urlencode( DrawAttention::name ) // the name of our product in EDD
		);
		
		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, DrawAttention_Updater::edd_store_url ), array( 'timeout' => 15, 'sslverify' => false ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		
		// $license_data->license will be either "active" or "inactive"

		update_option( 'da_license_key_status', $license_data->license );
		
		if ( $license_data->license == 'valid' ) {
			update_option( 'da_license_key', $license_key );
		}
	}

}
if ( class_exists( 'TD_DA_EDD_SL_Plugin_Updater' ) ) return;
class TD_DA_EDD_SL_Plugin_Updater {
	private $api_url  = '';
	private $api_data = array();
	private $name     = '';
	private $slug     = '';

	/**
	 * Class constructor.
	 *
	 * @uses plugin_basename()
	 * @uses hook()
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */
	function __construct( $_api_url, $_plugin_file, $_api_data = null ) {
		$this->api_url  = trailingslashit( $_api_url );
		$this->api_data = urlencode_deep( $_api_data );
		$this->name     = plugin_basename( $_plugin_file );
		$this->slug     = basename( $_plugin_file, '.php');
		$this->version  = $_api_data['version'];

		// Set up hooks.
		$this->hook();
	}

	/**
	 * Set up Wordpress filters to hook into WP's update process.
	 *
	 * @uses add_filter()
	 *
	 * @return void
	 */
	private function hook() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_site_transient_update_plugins_filter' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );
	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update api just when Wordpress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native Wordpress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param array $_transient_data Update array build by Wordpress.
	 * @return array Modified update array with custom plugin data.
	 */
	function pre_set_site_transient_update_plugins_filter( $_transient_data ) {


		if( empty( $_transient_data ) ) return $_transient_data;

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_latest_version', $to_send );

		if( false !== $api_response && is_object( $api_response ) && isset( $api_response->new_version ) ) {
			if( version_compare( $this->version, $api_response->new_version, '<' ) )
				$_transient_data->response[$this->name] = $api_response;
		}
		return $_transient_data;
	}


	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed $_data
	 * @param string $_action
	 * @param object $_args
	 * @return object $_data
	 */
	function plugins_api_filter( $_data, $_action = '', $_args = null ) {
		if ( ( $_action != 'plugin_information' ) || !isset( $_args->slug ) || ( $_args->slug != $this->slug ) ) return $_data;

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_information', $to_send );
		if ( false !== $api_response ) $_data = $api_response;

		return $_data;
	}


	/**
	 * Disable SSL verification in order to prevent download update failures
	 *
	 * @param array $args
	 * @param string $url
	 * @return object $array
	 */
	function http_request_args( $args, $url ) {
		// If it is an https request and we are performing a package download, disable ssl verification
		if( strpos( $url, 'https://' ) !== false && strpos( $url, 'edd_action=package_download' ) ) {
			$args['sslverify'] = false;
		}
		return $args;
	}

	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 *
	 * @uses get_bloginfo()
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param string $_action The requested action.
	 * @param array $_data Parameters for the API action.
	 * @return false||object
	 */
	private function api_request( $_action, $_data ) {

		global $wp_version;

		$data = array_merge( $this->api_data, $_data );

		if( $data['slug'] != $this->slug )
			return;

		if( empty( $data['license'] ) )
			return;

		$api_params = array(
			'edd_action' 	=> 'get_version',
			'license' 		=> $data['license'],
			'name' 			=> $data['item_name'],
			'slug' 			=> $this->slug,
			'author'		=> $data['author'],
			'url'           => home_url()
		);
		$request = wp_remote_post( $this->api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( ! is_wp_error( $request ) ):
			$request = json_decode( wp_remote_retrieve_body( $request ) );
			if( $request && isset( $request->sections ) )
				$request->sections = maybe_unserialize( $request->sections );
			return $request;
		else:
			return false;
		endif;
	}
}