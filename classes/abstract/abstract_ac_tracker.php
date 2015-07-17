<?php
/**
 * Animals Code Tracker - Abstract
 *
 * The tracker abstract contains the default functions for tracking the plugin data.
 *
 * @class    Abstract_AC_Tracker
 * @version  1.0.0
 * @package  Animals Code/Classes/Abstract
 * @category Abstract
 * @author   Sebastien Dumont
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Abstract_AC_Tracker {

	/**
	 * URL to the Animals Code Tracker API endpoint.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private static $api_url = 'http://tracking.animalscode.com/v1/';

	/**
	 * Animals Code Tracker API key.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private static $api_key = '';

	/**
	 * Animals Code Tracker API secret key.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private static $api_secret_key = '';

	/**
	 * Animals Code Tracker plugin key.
	 *
	 * The plugin key must match the key given 
	 * from the tracker service.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private static $plugin_key = '';

	/**
	 * Get all options starting with the plugin version.
	 *
	 * @since  1.0.0
	 * @filter animals_code_tracker_get_all_options
	 * @return array
	 */
	private static function get_all_options_values() {
		$options = array();

		// Version of plugin.
		$options['plugin_version'] = '';

		// Plugin Key.
		$options['plugin_key'] = self::$plugin_key;

		return apply_filters( 'animals_code_tracker_get_all_options', $options );
	}

	/**
	 * Animals Code Supported Themes
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 * @return  array
	 */
	private static function get_supported_themes() {
		return array( 'twentyfifteen', 'twentyfourteen', 'twentythirteen', 'twentyeleven', 'twentytwelve', 'twentyten' );
	}

}