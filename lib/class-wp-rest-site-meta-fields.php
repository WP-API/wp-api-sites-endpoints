<?php
/**
 * REST API: WP_REST_Site_Meta_Fields class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since x.x.x
 */

/**
 * Core class to manage site meta via the REST API.
 *
 * @since x.x.x
 *
 * @see WP_REST_Meta_Fields
 */
class WP_REST_Site_Meta_Fields extends WP_REST_Meta_Fields {

	/**
	 * Retrieves the object type for site meta.
	 *
	 * @since x.x.x
	 *
	 * @return string The meta type.
	 */
	protected function get_meta_type() {
		return 'site';
	}

	/**
	 * Retrieves the object meta subtype.
	 *
	 * @since x.x.x
	 *
	 * @return string 'site' There are no subtypes.
	 */
	protected function get_meta_subtype() {
		return 'site';
	}

	/**
	 * Retrieves the type for register_rest_field() in the context of sites.
	 *
	 * @since x.x.x
	 *
	 * @return string The REST field type.
	 */
	public function get_rest_field_type() {
		return 'site';
	}
}
