<?php
/**
 * Plugin Name: WP REST API - Sites Endpoints
 * Description: Sites Endpoints for the WP REST API
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 0.1.0
 * Plugin URI: https://github.com/WP-API/wp-api-sites-endpoint
 * License: GPL2+
 * Network: true
 */

/**
 *
 */
function sites_rest_api_init() {
	if ( class_exists( 'WP_REST_Controller' ) && ! class_exists( 'WP_REST_Sites_Controller' ) ) {
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-site-meta-fields.php';
		require_once dirname( __FILE__ ) . '/lib/class-wp-rest-sites-controller.php';
	}
	$plugins_controller = new WP_REST_Sites_Controller();
	$plugins_controller->register_routes();
}

add_action( 'rest_api_init', 'sites_rest_api_init' );
