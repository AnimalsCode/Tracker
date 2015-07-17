<?php
/**
 * Animals Code Tracker
 *
 * The tracker class adds functionality to track plugin usage based on 
 * if the customer opted in.
 * No personal information is tracked, only general site and plugin settings.
 *
 * @class    AC_Tracker
 * @version  1.0.0
 * @package  Animals Code/Classes
 * @category Class
 * @author   Sebastien Dumont
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AC_Tracker extends Abstract_AC_Tracker {

	/**
	 * URL to the Animals Code Tracker API endpoint.
	 *
	 * @var string
	 */
	private static $api_url;

	/**
	 * Animals Code Tracker API key.
	 *
	 * @var string
	 */
	private static $api_key;

	/**
	 * Animals Code Tracker API secret key.
	 *
	 * @var string
	 */
	private static $api_secret_key;

	/**
	 * Animals Code Tracker plugin key.
	 *
	 * @var string
	 */
	private static $plugin_key;

	/**
	 * Constructor.
	 */
	private static function __construct() {
		self::init();
	}

	/**
	 * Hook into cron event.
	 */
	public static function init() {
		add_action( 'animals_code_tracker_send_event', array( __CLASS__, 'send_tracking_data' ) );
	}

	/**
	 * Decide whether to send tracking data or not.
	 *
	 * @param  boolean $override
	 * @filter animals_code_tracker_last_send_interval
	 * @return void
	 */
	public static function send_tracking_data( $override = false ) {
		// Dont trigger this on AJAX Requests
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! apply_filters( 'animals_code_tracker_send_override', $override ) ) {
			// Send a maximum of once per week by default.
			$last_send = self::get_last_send_time();
			if ( $last_send && $last_send > apply_filters( 'animals_code_tracker_last_send_interval', strtotime( '-1 week' ) ) ) {
				return;
			}
		} else {
			// Make sure there is at least a 1 hour delay between override sends, we dont want duplicate calls due to double clicking links.
			$last_send = self::get_last_send_time();
			if ( $last_send && $last_send > strtotime( '-1 hours' ) ) {
				return;
			}
		}

		// Update time first before sending to ensure it is set
		update_option( 'animals_code_tracker_last_send', time() );

		$params   = self::get_tracking_data();

		$response = wp_remote_post( self::$api_url, array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false,
				'headers'     => array( 
									'user-agent' => 'AnimalsCodeTracker/' . md5( esc_url( home_url( '/' ) ) ) . ';',
									'api_key' => self::$api_key,
									'api_secret_key' => self::$api_secret_key,
								),
				'body'        => json_encode( $params ),
				'cookies'     => array()
			)
		);
	}

	/**
	 * Get the last time tracking data was sent.
	 *
	 * @filter animals_code_tracker_last_send_time
	 * @return int|bool
	 */
	private static function get_last_send_time() {
		return apply_filters( 'animals_code_tracker_last_send_time', get_option( 'animals_code_tracker_last_send', false ) );
	}

	/**
	 * Get all the tracking data.
	 *
	 * @filter animals_code_tracker_admin_email
	 * @filter animals_code_tracker_data
	 * @return array
	 */
	private static function get_tracking_data() {
		$data                       = array();

		// General site info
		$data['url']                = home_url();
		$data['email']              = apply_filters( 'animals_code_tracker_admin_email', get_option( 'admin_email' ) );
		$data['theme']              = self::get_theme_info();

		// WordPress Info
		$data['wp']                 = self::get_wordpress_info();

		// Server Info
		$data['server']             = self::get_server_info();

		// Plugin info
		$all_plugins                = self::get_all_plugins();
		$data['active_plugins']     = $all_plugins['active_plugins'];
		$data['inactive_plugins']   = $all_plugins['inactive_plugins'];

		// Get all plugin options info
		$data['settings']           = self::get_all_options_values();

		// User info
		$data['users']              = self::get_user_counts();

		return apply_filters( 'animals_code_tracker_data', $data );
	}

	/**
	 * Get the current theme info, theme name and version.
	 *
	 * @return array
	 */
	public static function get_theme_info() {
		$wp_version = get_bloginfo( 'version' );

		if ( version_compare( $wp_version, '3.4', '<' ) ) {
			$theme_data    = get_theme_data( get_stylesheet_directory() . '/style.css' );
			$theme_name    = $theme_data['Name'];
			$theme_version = $theme_data['Version'];
		} else {
			$theme_data    = wp_get_theme();
			$theme_name    = $theme_data->Name;
			$theme_version = $theme_data->Version;
		}

		$theme_child_theme = is_child_theme() ? 'Yes' : 'No';
		$theme_ac_supported = ( ! current_theme_supports( 'animals_code' ) && ! in_array( $theme_data->template, self::get_supported_themes() ) ) ? 'No' : 'Yes';

		return array( 'name' => $theme_name, 'version' => $theme_version, 'child_theme' => $theme_child_theme, 'ac_supported' => $theme_ac_supported );
	}

	/**
	 * Get WordPress related data.
	 *
	 * @return array
	 */
	private static function get_wordpress_info() {
		$wp_data = array();

		$memory                  = self::ac_track_let_to_num( WP_MEMORY_LIMIT );
		$wp_data['memory_limit'] = size_format( $memory );
		$wp_data['debug_mode']   = ( defined('WP_DEBUG') && WP_DEBUG ) ? 'Yes' : 'No';
		$wp_data['locale']       = get_locale();
		$wp_data['version']      = get_bloginfo( 'version' );
		$wp_data['multisite']    = is_multisite() ? 'Yes' : 'No';

		return $wp_data;
	}

	/**
	 * Get server related info.
	 *
	 * @return array
	 */
	private static function get_server_info() {
		$server_data = array();

		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$server_data['software'] = $_SERVER['SERVER_SOFTWARE'];
		}

		if ( function_exists( 'phpversion' ) ) {
			$server_data['php_version'] = phpversion();
		}

		if ( function_exists( 'ini_get' ) ) {
			$server_data['php_post_max_size'] = size_format( self::ac_track_let_to_num( ini_get( 'post_max_size' ) ) );
			$server_data['php_time_limt']      = ini_get( 'max_execution_time' );
			$server_data['php_max_input_vars'] = ini_get( 'max_input_vars' );
			$server_data['php_suhosin']        = extension_loaded( 'suhosin' ) ? 'Yes' : 'No';
		}

		global $wpdb;
		$server_data['mysql_version']        = $wpdb->db_version();
		$server_data['php_max_upload_size']  = size_format( wp_max_upload_size() );
		$server_data['php_default_timezone'] = date_default_timezone_get();
		$server_data['php_soap']             = class_exists( 'SoapClient' ) ? 'Yes' : 'No';
		$server_data['php_fsockopen']        = function_exists( 'fsockopen' ) ? 'Yes' : 'No';
		$server_data['php_curl']             = function_exists( 'curl_init' ) ? 'Yes' : 'No';

		return $server_data;
	}

	/**
	 * Get all plugins grouped into activated or not.
	 *
	 * @return array
	 */
	private static function get_all_plugins() {
		// Ensure get_plugins function is loaded.
		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$plugins             = get_plugins();
		$active_plugins_keys = get_option( 'active_plugins', array() );
		$active_plugins      = array();

		foreach ( $plugins as $k => $v ) {
			// Take care of formatting the data how we want it.
			$formatted = array();

			$formatted['name'] = strip_tags( $v['Name'] );

			if ( isset( $v['Version'] ) ) {
				$formatted['version'] = strip_tags( $v['Version'] );
			}
			if ( isset( $v['Author'] ) ) {
				$formatted['author'] = strip_tags( $v['Author'] );
			}
			if ( isset( $v['Network'] ) ) {
				$formatted['network'] = strip_tags( $v['Network'] );
			}
			if ( isset( $v['PluginURI'] ) ) {
				$formatted['plugin_uri'] = strip_tags( $v['PluginURI'] );
			}

			if ( in_array( $k, $active_plugins_keys ) ) {
				// Remove active plugins from list so we can show active and inactive separately.
				unset( $plugins[$k] );
				$active_plugins[$k] = $formatted;
			} else {
				$plugins[$k] = $formatted;
			}
		}

		return array( 'active_plugins' => $active_plugins, 'inactive_plugins' => $plugins );
	}

	/**
	 * Get user totals based on user role.
	 *
	 * @return array
	 */
	private static function get_user_counts() {
		$user_count          = array();
		$user_count_data     = count_users();
		$user_count['total'] = $user_count_data['total_users'];

		// Get user count based on user role
		foreach ( $user_count_data['avail_roles'] as $role => $count ) {
			$user_count[ $role ] = $count;
		}

		return $user_count;
	}

	/**
	 * This function transforms the php.ini notation 
	 * for numbers (like '2M') to an integer.
	 *
	 * @param  $size
	 * @return int
	 */
	private static function ac_track_let_to_num() {
		$l   = substr( $size, -1 );
		$ret = substr( $size, 0, -1 );

		switch ( strtoupper( $l ) ) {
			case 'P':
				$ret *= 1024;
			case 'T':
				$ret *= 1024;
			case 'G':
				$ret *= 1024;
			case 'M':
				$ret *= 1024;
			case 'K':
				$ret *= 1024;
		}

		return $ret;
	}

}

new AC_Tracker();
