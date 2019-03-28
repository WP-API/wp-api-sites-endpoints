<?php
/**
 * Unit tests covering WP_REST_Sites_Controller functionality.
 *
 * @package    WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Sites_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $superadmin_id;
	protected static $admin_id;
	protected static $editor_id;
	protected static $subscriber_id;
	protected static $author_id;

	protected static $network_id;
	protected static $password_id;
	protected static $private_id;
	protected static $draft_id;
	protected static $trash_id;
	protected static $approved_id;
	protected static $hold_id;

	protected $endpoint;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor_id     = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$author_id     = $factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Sea Captain',
				'first_name'   => 'Horatio',
				'last_name'    => 'McCallister',
				'user_email'   => 'captain@thefryingdutchman.com',
				'user_url'     => 'http://thefryingdutchman.com',
			)
		);

		self::$network_id = $factory->network->create();

		self::$approved_id = $factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
			)
		);
		self::$hold_id     = $factory->blog->create(
			array(
				'public'  => 0,
				'site_id' => self::$network_id,
			)
		);
	}

	public static function wpTearDownAfterClass() {
		global $wpdb;

		self::delete_user( self::$superadmin_id );
		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$subscriber_id );
		self::delete_user( self::$author_id );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", self::$network_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id= %d", self::$network_id ) );
	}

	public function setUp() {
		parent::setUp();
		$this->endpoint = new WP_REST_Sites_Controller;

		update_site_option( 'site_admins', array( 'superadmin' ) );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/sites', $routes );
		$this->assertCount( 2, $routes['/wp/v2/sites'] );
		$this->assertArrayHasKey( '/wp/v2/sites/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/sites/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites/' . self::$approved_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertEquals(
			array(
				'after',
				'author',
				'author_email',
				'author_exclude',
				'before',
				'context',
				'exclude',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'parent',
				'parent_exclude',
				'password',
				'per_page',
				'network',
				'search',
				'status',
				'type',
			),
			$keys
		);
	}

	public function test_get_items() {
		wp_set_current_user( self::$superadmin_id );

		$this->factory->blog->create_many( 6, array( 'site_id' => self::$network_id ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$sites = $response->get_data();
		// We created 6 sites in this method, plus self::$approved_id.
		$this->assertCount( 7, $sites );
	}


	public function test_get_items_without_private_network_permission() {
		wp_set_current_user( self::$superadmin_id );

		$args            = array(
			'public'  => 1,
			'site_id' => self::$private_id,
		);
		$private_site = $this->factory->blog->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertFalse( in_array( $private_site, wp_list_pluck( $collection_data, 'id' ), true ) );
	}

	public function test_get_items_with_private_network_permission() {
		wp_set_current_user( self::$superadmin_id );

		$args            = array(
			'public'  => 1,
			'site_id' => self::$private_id,
		);
		$private_site = $this->factory->blog->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertTrue( in_array( $private_site, wp_list_pluck( $collection_data, 'id' ), true ) );
	}

	public function test_get_items_with_invalid_network() {
		wp_set_current_user( self::$superadmin_id );

		$site_id = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertFalse( in_array( $site_id, wp_list_pluck( $collection_data, 'id' ), true ) );

		wp_delete_site( $site_id );
	}

	public function test_get_items_with_invalid_network_permission() {
		wp_set_current_user( self::$superadmin_id );

		$site_id = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertTrue( in_array( $site_id, wp_list_pluck( $collection_data, 'id' ), true ) );

		wp_delete_site( $site_id );
	}

	public function test_get_items_no_permission_for_context() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_items_no_network() {
		$this->factory->blog->create_many( 2, array( 'site_id' => 0 ) );
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'network', 0 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 2, $sites );
	}

	public function test_get_items_no_permission_for_no_network() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'network', 0 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	public function test_get_items_edit_context() {
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_items_for_network() {
		$second_network_id = $this->factory->network->create();
		$this->factory->blog->create_many( $second_network_id, 2 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_query_params(
			array(
				'network' => $second_network_id,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$sites = $response->get_data();
		$this->assertCount( 2, $sites );
	}

	public function test_get_items_include_query() {
		wp_set_current_user( self::$superadmin_id );
		$args = array(
			'public'  => 1,
			'site_id' => self::$network_id,
		);
		$id1  = $this->factory->blog->create( $args );
		$this->factory->blog->create( $args );
		$id3     = $this->factory->blog->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		// Order=>asc
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'include', array( $id3, $id1 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( $id1, $data[0]['id'] );
		// Orderby=>include
		$request->set_param( 'orderby', 'include' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( $id3, $data[0]['id'] );
		// Orderby=>invalid should fail.
		$request->set_param( 'orderby', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		// fails on invalid id.
		$request->set_param( 'orderby', array( 'include' ) );
		$request->set_param( 'include', array( 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_exclude_query() {
		wp_set_current_user( self::$superadmin_id );
		$args     = array(
			'public'  => 1,
			'site_id' => self::$network_id,
		);
		$id1      = $this->factory->blog->create( $args );
		$id2      = $this->factory->blog->create( $args );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertTrue( in_array( $id1, wp_list_pluck( $data, 'id' ), true ) );
		$this->assertTrue( in_array( $id2, wp_list_pluck( $data, 'id' ), true ) );
		$request->set_param( 'exclude', array( $id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertTrue( in_array( $id1, wp_list_pluck( $data, 'id' ), true ) );
		$this->assertFalse( in_array( $id2, wp_list_pluck( $data, 'id' ), true ) );

		// fails on invalid id.
		$request->set_param( 'exclude', array( 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_offset_query() {
		wp_set_current_user( self::$superadmin_id );
		$args = array(
			'public'  => 1,
			'site_id' => self::$network_id,
		);
		$this->factory->blog->create( $args );
		$this->factory->blog->create( $args );
		$this->factory->blog->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'offset', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 3, $response->get_data() );
		// 'offset' works with 'per_page'
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
		// 'offset' takes priority over 'page'
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
		// 'offset' with invalid value errors.
		$request->set_param( 'offset', 'moreplease' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_order_query() {
		wp_set_current_user( self::$superadmin_id );
		$args = array(
			'public'  => 1,
			'site_id' => self::$network_id,
		);
		$this->factory->blog->create( $args );
		$this->factory->blog->create( $args );
		$id3     = $this->factory->blog->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		// order defaults to 'desc'
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( $id3, $data[0]['id'] );
		// order=>asc
		$request->set_param( 'order', 'asc' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( self::$approved_id, $data[0]['id'] );
		// order=>asc,id should fail
		$request->set_param( 'order', 'asc,id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_private_network_no_permissions() {
		wp_set_current_user( 0 );
		$network_id = $this->factory->network->create( array( 'network_status' => 'private' ) );
		$request    = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'network', $network_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_network', $response, 401 );
	}

	public function test_get_items_author_arg() {
		// Authorized
		wp_set_current_user( self::$superadmin_id );
		$args = array(
			'public'  => 1,
			'site_id' => self::$network_id,
			'user_id' => self::$author_id,
		);
		$this->factory->blog->create( $args );
		$args['user_id'] = self::$subscriber_id;
		$this->factory->blog->create( $args );
		unset( $args['user_id'] );
		$this->factory->blog->create( $args );

		// 'author' limits result to 1 of 3
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'author', self::$author_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 1, $sites );
		// Multiple authors are supported
		$request->set_param( 'author', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 2, $sites );
		// Invalid author param errors
		$request->set_param( 'author', 'skippy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		// Unavailable to unauthenticated; defaults to error
		wp_set_current_user( 0 );
		$request->set_param( 'author', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_param', $response, 401 );
	}

	public function test_get_items_author_exclude_arg() {
		// Authorized
		wp_set_current_user( self::$superadmin_id );
		$args = array(
			'public'  => 1,
			'site_id' => self::$network_id,
			'user_id' => self::$author_id,
		);
		$this->factory->blog->create( $args );
		$args['user_id'] = self::$subscriber_id;
		$this->factory->blog->create( $args );
		unset( $args['user_id'] );
		$this->factory->blog->create( $args );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$sites    = $response->get_data();
		$this->assertCount( 4, $sites );

		// 'author_exclude' limits result to 3 of 4
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'author_exclude', self::$author_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 3, $sites );
		// 'author_exclude' for both site authors (2 of 4)
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'author_exclude', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 2, $sites );
		// 'author_exclude' for both invalid author
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'author_exclude', 'skippy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		// Unavailable to unauthenticated; defaults to error
		wp_set_current_user( 0 );
		$request->set_param( 'author_exclude', array( self::$author_id, self::$subscriber_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_param', $response, 401 );
	}

	public function test_get_items_parent_arg() {
		$args                   = array(
			'public'  => 1,
			'site_id' => self::$network_id,
		);
		$parent_id              = $this->factory->blog->create( $args );
		$parent_id2             = $this->factory->blog->create( $args );
		$args['site_parent'] = $parent_id;
		$this->factory->blog->create( $args );
		$args['site_parent'] = $parent_id2;
		$this->factory->blog->create( $args );
		// All sites in the database
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 5, $response->get_data() );
		// Limit to the parent
		$request->set_param( 'parent', $parent_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 1, $response->get_data() );
		// Limit to two parents
		$request->set_param( 'parent', array( $parent_id, $parent_id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
		// Invalid parent should error
		$request->set_param( 'parent', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_parent_exclude_arg() {
		$args                   = array(
			'public'  => 1,
			'site_id' => self::$network_id,
		);
		$parent_id              = $this->factory->blog->create( $args );
		$parent_id2             = $this->factory->blog->create( $args );
		$args['site_parent'] = $parent_id;
		$this->factory->blog->create( $args );
		$args['site_parent'] = $parent_id2;
		$this->factory->blog->create( $args );
		// All sites in the database
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 5, $response->get_data() );
		// Exclude this particular parent
		$request->set_param( 'parent_exclude', $parent_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 4, $response->get_data() );
		// Exclude both site parents
		$request->set_param( 'parent_exclude', array( $parent_id, $parent_id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 3, $response->get_data() );
		// Invalid parent id should error
		$request->set_param( 'parent_exclude', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_search_query() {
		wp_set_current_user( self::$superadmin_id );
		$args                    = array(
			'public'          => 1,
			'site_id'         => self::$network_id,
			'site_content' => 'foo',
			'site_author'  => 'Homer J Simpson',
		);
		$id1                     = $this->factory->blog->create( $args );
		$args['site_content'] = 'bar';
		$this->factory->blog->create( $args );
		$args['site_content'] = 'burrito';
		$this->factory->blog->create( $args );
		// 3 sites, plus 1 created in construct
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 4, $response->get_data() );
		// One matching sites
		$request->set_param( 'search', 'foo' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( $id1, $data[0]['id'] );
	}

	public function test_get_sites_pagination_headers() {
		wp_set_current_user( self::$superadmin_id );
		// Start of the index
		for ( $i = 0; $i < 49; $i ++ ) {
			$this->factory->blog->create(
				array(
					'site_content' => "Site {$i}",
					'site_id'         => self::$network_id,
				)
			);
		}
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertEquals( 50, $headers['X-WP-Total'] );
		$this->assertEquals( 5, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( '/wp/v2/sites' )
		);
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// 3rd page
		$this->factory->blog->create(
			array(
				'site_content' => 'Site 51',
				'site_id'         => self::$network_id,
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( '/wp/v2/sites' )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'page' => 4,
			),
			rest_url( '/wp/v2/sites' )
		);
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// Last page
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'page', 6 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 5,
			),
			rest_url( '/wp/v2/sites' )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
		// Out of bounds
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'page', 8 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 6,
			),
			rest_url( '/wp/v2/sites' )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
	}

	public function test_get_sites_invalid_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'after', rand_str() );
		$request->set_param( 'before', rand_str() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_sites_valid_date() {
		$site1 = $this->factory->blog->create(
			array(
				'site_date' => '2016-01-15T00:00:00Z',
				'site_id'      => self::$network_id,
			)
		);
		$site2 = $this->factory->blog->create(
			array(
				'site_date' => '2016-01-16T00:00:00Z',
				'site_id'      => self::$network_id,
			)
		);
		$site3 = $this->factory->blog->create(
			array(
				'site_date' => '2016-01-17T00:00:00Z',
				'site_id'      => self::$network_id,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( $site2, $data[0]['id'] );
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_site_data( $data, 'view', $response->get_links() );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_site_data( $data, 'edit', $response->get_links() );
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$superadmin_id );
		$endpoint = new WP_REST_Sites_Controller;
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,status' );
		$obj      = get_site( self::$approved_id );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertEquals(
			array(
				'id',
				'status',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_get_site_author_avatar_urls() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 24, $data['author_avatar_urls'] );
		$this->assertArrayHasKey( 48, $data['author_avatar_urls'] );
		$this->assertArrayHasKey( 96, $data['author_avatar_urls'] );

		$site = get_site( self::$approved_id );
		/**
		 * Ignore the subdomain, since 'get_avatar_url randomly sets the Gravatar
		 * server when building the url string.
		 */
		$this->assertEquals( substr( get_avatar_url( $blog->site_author_email ), 9 ), substr( $data['author_avatar_urls'][96], 9 ) );
	}

	public function test_get_site_invalid_id() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_id', $response, 404 );
	}

	public function test_get_site_invalid_context() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', self::$approved_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_site_invalid_network_id() {
		wp_set_current_user( 0 );
		$site_id = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $site_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_network_invalid_id', $response, 404 );
	}

	public function test_get_site_invalid_network_id_as_admin() {
		wp_set_current_user( self::$superadmin_id );
		$site_id = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $site_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_network_invalid_id', $response, 404 );
	}

	public function test_get_site_not_approved() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$hold_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	public function test_get_site_not_approved_same_user() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%d', self::$hold_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_site_with_children_link() {
		$site_id_1 = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);

		$child_site = $this->factory->blog->create(
			array(
				'public'         => 1,
				'site_parent' => $site_id_1,
				'site_id'        => self::$network_id,
				'user_id'        => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $site_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'children', $response->get_links() );
	}

	public function test_get_site_without_children_link() {
		$site_id_1 = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $site_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}

	public function test_get_site_with_password_without_edit_network_permission() {
		wp_set_current_user( self::$subscriber_id );
		$args             = array(
			'public'  => 1,
			'site_id' => self::$password_id,
		);
		$password_site = $this->factory->blog->create( $args );
		$request          = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $password_site ) );
		$response         = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * @ticket 38692
	 */
	public function test_get_site_with_password_with_valid_password() {
		wp_set_current_user( self::$subscriber_id );

		$args             = array(
			'public'  => 1,
			'site_id' => self::$password_id,
		);
		$password_site = $this->factory->blog->create( $args );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $password_site ) );
		$request->set_param( 'password', 'toomanysecrets' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_create_item() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->check_site_data( $data, 'edit', $response->get_links() );
		$this->assertEquals( 'hold', $data['status'] );
		$this->assertEquals( '2014-11-07T10:14:25', $data['date'] );
		$this->assertEquals( self::$network_id, $data['network'] );
	}

	public function site_dates_provider() {
		return array(
			'set date without timezone'     => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date'            => '2016-12-12T14:00:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date_gmt without timezone' => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date_gmt'        => '2016-12-12T19:00:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date with timezone'        => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date'            => '2016-12-12T18:00:00-01:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date_gmt with timezone'    => array(
				'params'  => array(
					'timezone_string' => 'America/New_York',
					'date_gmt'        => '2016-12-12T18:00:00-01:00',
				),
				'results' => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
		);
	}

	/**
	 * @dataProvider site_dates_provider
	 */
	public function test_create_site_date( $params, $results ) {
		wp_set_current_user( self::$superadmin_id );
		update_option( 'timezone_string', $params['timezone_string'] );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'content', 'not empty' );
		$request->set_param( 'network', self::$network_id );
		if ( isset( $params['date'] ) ) {
			$request->set_param( 'date', $params['date'] );
		}
		if ( isset( $params['date_gmt'] ) ) {
			$request->set_param( 'date_gmt', $params['date_gmt'] );
		}
		$response = rest_get_server()->dispatch( $request );

		update_option( 'timezone_string', '' );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$site = get_site( $data['id'] );

		$this->assertEquals( $results['date'], $data['date'] );
		$site_date = str_replace( 'T', ' ', $results['date'] );
		$this->assertEquals( $site_date, $blog->site_date );

		$this->assertEquals( $results['date_gmt'], $data['date_gmt'] );
		$site_date_gmt = str_replace( 'T', ' ', $results['date_gmt'] );
		$this->assertEquals( $site_date_gmt, $blog->site_date_gmt );
	}

	public function test_create_item_using_accepted_content_raw_value() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => array(
				'raw' => 'Once something has been approved by the government, it\'s no longer immoral.',
			),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data        = $response->get_data();
		$new_site = get_site( $data['id'] );
		$this->assertEquals( $params['content']['raw'], $new_blog->site_content );
	}

	public function test_create_item_error_from_filter() {
		add_filter( 'rest_pre_insert_site', array( $this, 'return_premade_error' ) );
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'homer@example.org',
			'content'      => array(
				'raw' => 'Aw, he loves beer. Here, little fella.',
			),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'test_rest_premade_error', $response, 418 );
	}

	public function return_premade_error() {
		return new WP_Error( 'test_rest_premade_error', "I'm sorry, I thought he was a party robot.", array( 'status' => 418 ) );
	}

	public function test_create_site_missing_required_author_name() {
		add_filter( 'rest_allow_anonymous_sites', '__return_true' );
		update_option( 'require_name_email', 1 );

		$params = array(
			'network'      => self::$network_id,
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_author_data_required', $response, 400 );
	}

	public function test_create_site_empty_required_author_name() {
		add_filter( 'rest_allow_anonymous_sites', '__return_true' );
		update_option( 'require_name_email', 1 );

		$params = array(
			'author_name'  => '',
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'network'      => self::$network_id,
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_author_data_required', $response, 400 );
	}

	public function test_create_site_missing_required_author_email() {
		wp_set_current_user( self::$superadmin_id );
		update_option( 'require_name_email', 1 );

		$params = array(
			'network'     => self::$network_id,
			'author_name' => 'Edna Krabappel',
			'content'     => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_author_data_required', $response, 400 );
	}

	public function test_create_site_empty_required_author_email() {
		wp_set_current_user( self::$superadmin_id );
		update_option( 'require_name_email', 1 );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Edna Krabappel',
			'author_email' => '',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_author_data_required', $response, 400 );
	}

	public function test_create_site_author_email_too_short() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer J. Simpson',
			'author_email' => 'a@b',
			'content'      => 'in this house, we obey the laws of thermodynamics!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'author_email', $data['data']['params'] );
	}

	public function test_create_item_invalid_no_content() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_content_invalid', $response, 400 );

		$params['content'] = '';
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_content_invalid', $response, 400 );
	}

	public function test_create_item_invalid_date() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => 'It\'s all over\, people! We don\'t have a prayer!',
			'date'         => rand_str(),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}


	public function test_create_item_assign_different_user() {
		$subscriber_id = $this->factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'cbg@androidsdungeon.com',
			)
		);

		wp_set_current_user( self::$superadmin_id );
		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author'       => $subscriber_id,
			'content'      => 'Worst Site Ever!',
			'date'         => '2014-11-07T10:14:25',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $subscriber_id, $data['author'] );
		$this->assertEquals( '127.0.0.1', $data['author_ip'] );
	}

	public function test_create_site_without_type() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => $network_id,
			'author'       => self::$admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'site', $data['type'] );

		$site_id = $data['id'];

		// Make sure the new site is present in the collection.
		$collection = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$collection->set_param( 'network', $network_id );
		$collection_response = rest_get_server()->dispatch( $collection );
		$collection_data     = $collection_response->get_data();
		$this->assertEquals( $site_id, $collection_data[0]['id'] );
	}

	/**
	 * @ticket 38820
	 */
	public function test_create_site_with_invalid_type() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => $network_id,
			'author'       => self::$admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
			'date'         => '2014-11-07T10:14:25',
			'type'         => 'foo',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_site_type', $response, 400 );
	}

	public function test_create_site_invalid_email() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => $network_id,
			'author'       => self::$admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'hello:)',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
			'date'         => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_item_current_user() {
		$user_id = $this->factory->user->create(
			array(
				'role'         => 'subscriber',
				'user_email'   => 'lylelanley@example.com',
				'first_name'   => 'Lyle',
				'last_name'    => 'Lanley',
				'display_name' => 'Lyle Lanley',
				'user_url'     => 'http://simpsons.wikia.com/wiki/Lyle_Lanley',
			)
		);

		wp_set_current_user( $user_id );

		$params = array(
			'network' => self::$network_id,
			'content' => "Well sir, there's nothing on earth like a genuine, bona fide, electrified, six-car Monorail!",
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $user_id, $data['author'] );

		// Check author data matches
		$author = get_user_by( 'id', $user_id );
		$site   = get_site( $data['id'] );
		$this->assertEquals( $author->display_name, $blog->site_author );
		$this->assertEquals( $author->user_email, $blog->site_author_email );
		$this->assertEquals( $author->user_url, $blog->site_author_url );
	}

	public function test_create_site_other_user() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( self::$subscriber_id, $data['author'] );
		$this->assertEquals( 'Homer Jay Simpson', $data['author_name'] );
		$this->assertEquals( 'chunkylover53@aol.com', $data['author_email'] );
		$this->assertEquals( 'http://compuglobalhypermeganet.com', $data['author_url'] );
	}

	public function test_create_site_other_user_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$admin_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_invalid_author', $response, 403 );
	}

	public function test_create_site_invalid_network() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'network'      => 'some-slug',
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_site_status_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
			'status'       => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_invalid_status', $response, 403 );
	}

	public function test_create_site_with_status_IP_and_user_agent() {
		$network_id = $this->factory->network->create();
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'           => $network_id,
			'author_name'       => 'Comic Book Guy',
			'author_email'      => 'cbg@androidsdungeon.com',
			'author_ip'         => '139.130.4.5',
			'author_url'        => 'http://androidsdungeon.com',
			'author_user_agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
			'content'           => 'Worst Site Ever!',
			'status'            => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'approved', $data['status'] );
		$this->assertEquals( '139.130.4.5', $data['author_ip'] );
		$this->assertEquals( 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36', $data['author_user_agent'] );
	}

	public function test_create_site_user_agent_header() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->add_header( 'user_agent', 'Mozilla/4.0 (compatible; MSIE 5.5; AOL 4.0; Windows 95)' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$new_site = get_site( $data['id'] );
		$this->assertEquals( 'Mozilla/4.0 (compatible; MSIE 5.5; AOL 4.0; Windows 95)', $new_blog->site_agent );
	}

	public function test_create_site_author_ip() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '127.0.0.3',
			'content'      => 'Worst Site Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response    = rest_get_server()->dispatch( $request );
		$data        = $response->get_data();
		$new_site = get_site( $data['id'] );
		$this->assertEquals( '127.0.0.3', $new_blog->site_author_IP );
	}

	public function test_create_site_invalid_author_IP() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '867.5309',
			'content'      => 'Worst Site Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_site_author_ip_no_permission() {
		wp_set_current_user( self::$subscriber_id );
		$params  = array(
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '10.0.10.1',
			'content'      => 'Worst Site Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_author_ip', $response, 403 );
	}

	public function test_create_site_author_ip_defaults_to_remote_addr() {
		wp_set_current_user( self::$superadmin_id );
		$_SERVER['REMOTE_ADDR'] = '127.0.0.2';
		$params                 = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
		);
		$request                = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response    = rest_get_server()->dispatch( $request );
		$data        = $response->get_data();
		$new_site = get_site( $data['id'] );
		$this->assertEquals( '127.0.0.2', $new_blog->site_author_IP );
	}

	public function test_create_site_no_network_id() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_invalid_network_id', $response, 403 );
	}

	public function test_create_site_no_network_id_no_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => self::$subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_network_id', $response, 403 );
	}

	public function test_create_site_invalid_network_id() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'status'       => 'approved',
			'network'      => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_network_id', $response, 403 );
	}

	public function test_create_site_draft_network() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$draft_id,
			'author_name'  => 'Ishmael',
			'author_email' => 'herman-melville@earthlink.net',
			'author_url'   => 'https://en.wikipedia.org/wiki/Herman_Melville',
			'content'      => 'Call me Ishmael.',
			'author'       => self::$subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_draft_network', $response, 403 );
	}

	public function test_create_site_trash_network() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$trash_id,
			'author_name'  => 'Ishmael',
			'author_email' => 'herman-melville@earthlink.net',
			'author_url'   => 'https://en.wikipedia.org/wiki/Herman_Melville',
			'content'      => 'Call me Ishmael.',
			'author'       => self::$subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_trash_network', $response, 403 );
	}

	public function test_create_site_private_network_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$private_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'I\’d be a vegetarian if bacon grew on trees.',
			'author'       => self::$subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read_network', $response, 403 );
	}

	public function test_create_site_password_network_invalid_permission() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$password_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'I\’d be a vegetarian if bacon grew on trees.',
			'author'       => self::$subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_network', $response, 403 );
	}

	public function test_create_item_duplicate() {
		wp_set_current_user( self::$subscriber_id );
		$this->factory->blog->create(
			array(
				'site_id'              => self::$network_id,
				'site_author'       => 'Guy N. Cognito',
				'site_author_email' => 'chunkylover53@aol.co.uk',
				'site_content'      => 'Homer? Who is Homer? My name is Guy N. Cognito.',
			)
		);

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Guy N. Cognito',
			'author_email' => 'chunkylover53@aol.co.uk',
			'content'      => 'Homer? Who is Homer? My name is Guy N. Cognito.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 409, $response->get_status() );
	}

	public function test_create_site_closed() {
		$network_id = $this->factory->network->create(
			array(
				'site_status' => 'closed',
			)
		);
		wp_set_current_user( self::$subscriber_id );

		$params = array(
			'network' => $network_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_create_site_require_login() {
		wp_set_current_user( 0 );
		update_option( 'site_registration', 1 );
		add_filter( 'rest_allow_anonymous_sites', '__return_true' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'network', self::$network_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_site_login_required', $data['code'] );
	}

	public function test_create_item_invalid_author() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network' => self::$network_id,
			'author'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			'content' => 'It\'s all over\, people! We don\'t have a prayer!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_author_invalid', $response, 400 );
	}

	public function test_create_item_pull_author_info() {
		wp_set_current_user( self::$superadmin_id );

		$author = new WP_User( self::$author_id );
		$params = array(
			'network' => self::$network_id,
			'author'  => self::$author_id,
			'content' => 'It\'s all over\, people! We don\'t have a prayer!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$result = $response->get_data();
		$this->assertSame( self::$author_id, $result['author'] );
		$this->assertSame( 'Sea Captain', $result['author_name'] );
		$this->assertSame( 'captain@thefryingdutchman.com', $result['author_email'] );
		$this->assertSame( 'http://thefryingdutchman.com', $result['author_url'] );
	}

	public function test_create_site_two_times() {
		add_filter( 'rest_allow_anonymous_sites', '__return_true' );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Shakes fist at sky',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function anonymous_sites_callback_null() {
		// I'm a plugin developer who forgot to include a return value for some
		// code path in my 'rest_allow_anonymous_sites' filter.
	}

	public function test_allow_anonymous_sites_null() {
		add_filter( 'rest_allow_anonymous_sites', array( $this, 'anonymous_sites_callback_null' ), 10, 2 );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Site Ever!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'rest_allow_anonymous_sites', array( $this, 'anonymous_sites_callback_null' ), 10, 2 );

		$this->assertErrorResponse( 'rest_site_login_required', $response, 401 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_site_author_name_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => rand_long_str( 246 ),
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'date'         => '1995-04-30T10:22:00',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_author_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_site_author_email_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@' . rand_long_str( 190 ) . '.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'date'         => '1995-04-30T10:22:00',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_author_email_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_site_author_url_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.' . rand_long_str( 185 ) . '.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'date'         => '1995-04-30T10:22:00',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_author_url_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_create_site_content_too_long() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$network_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => rand_long_str( 66525 ),
			'date'         => '1995-04-30T10:22:00',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_content_column_length', $response, 400 );
	}

	public function test_create_site_without_password() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'network'      => self::$password_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read_network', $response, 403 );
	}

	public function test_create_site_with_password() {
		add_filter( 'rest_allow_anonymous_sites', '__return_true' );

		$params  = array(
			'network'      => self::$password_id,
			'author_name'  => 'Bleeding Gums Murphy',
			'author_email' => 'murphy@gingivitis.com',
			'author_url'   => 'http://jazz.gingivitis.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
			'password'     => 'toomanysecrets',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_update_item() {
		$network_id = $this->factory->network->create();

		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'author'       => self::$subscriber_id,
			'author_name'  => 'Disco Stu',
			'author_url'   => 'http://stusdisco.com',
			'author_email' => 'stu@stusdisco.com',
			'author_ip'    => '4.4.4.4',
			'content'      => 'Testing.',
			'date'         => '2014-11-07T10:14:25',
			'network'      => $network_id,
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$site    = $response->get_data();
		$updated = get_site( self::$approved_id );
		$this->assertEquals( $params['content'], $site['content']['raw'] );
		$this->assertEquals( $params['author'], $site['author'] );
		$this->assertEquals( $params['author_name'], $site['author_name'] );
		$this->assertEquals( $params['author_url'], $site['author_url'] );
		$this->assertEquals( $params['author_email'], $site['author_email'] );
		$this->assertEquals( $params['author_ip'], $site['author_ip'] );
		$this->assertEquals( $params['network'], $site['network'] );

		$this->assertEquals( mysql_to_rfc3339( $updated->site_date ), $site['date'] );
		$this->assertEquals( '2014-11-07T10:14:25', $site['date'] );
	}

	/**
	 * @dataProvider site_dates_provider
	 */
	public function test_update_site_date( $params, $results ) {
		wp_set_current_user( self::$editor_id );
		update_option( 'timezone_string', $params['timezone_string'] );

		$site_id = $this->factory->blog->create();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', $site_id ) );
		if ( isset( $params['date'] ) ) {
			$request->set_param( 'date', $params['date'] );
		}
		if ( isset( $params['date_gmt'] ) ) {
			$request->set_param( 'date_gmt', $params['date_gmt'] );
		}
		$response = rest_get_server()->dispatch( $request );

		update_option( 'timezone_string', '' );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$site = get_site( $data['id'] );

		$this->assertEquals( $results['date'], $data['date'] );
		$site_date = str_replace( 'T', ' ', $results['date'] );
		$this->assertEquals( $site_date, $blog->site_date );

		$this->assertEquals( $results['date_gmt'], $data['date_gmt'] );
		$site_date_gmt = str_replace( 'T', ' ', $results['date_gmt'] );
		$this->assertEquals( $site_date_gmt, $blog->site_date_gmt );
	}

	public function test_update_item_no_content() {
		$network_id = $this->factory->network->create();

		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->set_param( 'author_email', 'another@email.com' );

		// Sending a request without content is fine.
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Sending a request with empty site is not fine.
		$request->set_param( 'author_email', 'yetanother@email.com' );
		$request->set_param( 'content', '' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_content_invalid', $response, 400 );
	}

	public function test_update_item_no_change() {
		$site = get_site( self::$approved_id );

		wp_set_current_user( self::$superadmin_id );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->set_param( 'network', $blog->site_id );

		// Run twice to make sure that the update still succeeds even if no DB
		// rows are updated.
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_site_status() {
		wp_set_current_user( self::$superadmin_id );

		$site_id = $this->factory->blog->create(
			array(
				'public'  => 0,
				'site_id' => self::$network_id,
			)
		);

		$params  = array(
			'status' => 'approve',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', $site_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$site    = $response->get_data();
		$updated = get_site( $site_id );
		$this->assertEquals( 'approved', $site['status'] );
		$this->assertEquals( 1, $updated->public );
	}

	public function test_update_site_field_does_not_use_default_values() {
		wp_set_current_user( self::$superadmin_id );

		$site_id = $this->factory->blog->create(
			array(
				'public'          => 0,
				'site_id'         => self::$network_id,
				'site_content' => 'some content',
			)
		);

		$params  = array(
			'status' => 'approve',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', $site_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$site    = $response->get_data();
		$updated = get_site( $site_id );
		$this->assertEquals( 'approved', $site['status'] );
		$this->assertEquals( 1, $updated->public );
		$this->assertEquals( 'some content', $updated->site_content );
	}

	public function test_update_site_date_gmt() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'date_gmt' => '2015-05-07T10:14:25',
			'content'  => 'I\'ll be deep in the cold, cold ground before I recognize Missouri.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$site    = $response->get_data();
		$updated = get_site( self::$approved_id );
		$this->assertEquals( $params['date_gmt'], $site['date_gmt'] );
		$this->assertEquals( $params['date_gmt'], mysql_to_rfc3339( $updated->site_date_gmt ) );
	}

	public function test_update_site_author_email_only() {
		wp_set_current_user( self::$editor_id );
		update_option( 'require_name_email', 1 );

		$params = array(
			'network'      => self::$network_id,
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_site_empty_author_name() {
		wp_set_current_user( self::$editor_id );
		update_option( 'require_name_email', 1 );

		$params = array(
			'author_name'  => '',
			'author_email' => 'ekrabappel@springfield-elementary.edu',
			'network'      => self::$network_id,
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_site_author_name_only() {
		wp_set_current_user( self::$superadmin_id );
		update_option( 'require_name_email', 1 );

		$params = array(
			'network'     => self::$network_id,
			'author_name' => 'Edna Krabappel',
			'content'     => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_site_empty_author_email() {
		wp_set_current_user( self::$superadmin_id );
		update_option( 'require_name_email', 1 );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Edna Krabappel',
			'author_email' => '',
			'content'      => 'Now, I don\'t want you to worry class. These tests will have no affect on your grades. They merely determine your future social status and financial success. If any.',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_site_author_email_too_short() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'network'      => self::$network_id,
			'author_name'  => 'Homer J. Simpson',
			'author_email' => 'a@b',
			'content'      => 'in this house, we obey the laws of thermodynamics!',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'author_email', $data['data']['params'] );
	}

	public function test_update_site_invalid_type() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'type' => 'trackback',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_type', $response, 404 );
	}

	public function test_update_site_with_raw_property() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'content' => array(
				'raw' => 'What the heck kind of name is Persephone?',
			),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$site    = $response->get_data();
		$updated = get_site( self::$approved_id );
		$this->assertEquals( $params['content']['raw'], $updated->site_content );
	}

	public function test_update_item_invalid_date() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'content' => rand_str(),
			'date'    => rand_str(),
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_invalid_date_gmt() {
		wp_set_current_user( self::$superadmin_id );

		$params = array(
			'content'  => rand_str(),
			'date_gmt' => rand_str(),
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_site_invalid_id() {
		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'content' => 'Oh, they have the internet on computers now!',
		);
		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_id', $response, 404 );
	}

	public function test_update_site_invalid_network_id() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->set_param( 'network', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_network_id', $response, 403 );
	}

	public function test_update_site_invalid_permission() {
		add_filter( 'rest_allow_anonymous_sites', '__return_true' );

		$params  = array(
			'content' => 'Disco Stu likes disco music.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$hold_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
	}

	public function test_update_site_private_network_invalid_permission() {
		$private_site_id = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$private_id,
				'user_id' => 0,
			)
		);

		wp_set_current_user( self::$subscriber_id );

		$params  = array(
			'content' => 'Disco Stu likes disco music.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', $private_site_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_site_with_children_link() {
		wp_set_current_user( self::$superadmin_id );
		$site_id_1 = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);

		$child_site = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);

		// Check if site 1 does not have the child link.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $site_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );

		// Change the site parent.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%s', $child_site ) );
		$request->set_param( 'parent', $site_id_1 );
		$request->set_param( 'content', rand_str() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Check if site 1 now has the child link.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $site_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'children', $response->get_links() );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_site_author_name_too_long() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'author_name' => rand_long_str( 246 ),
			'content'     => 'This isn\'t a saxophone. It\'s an umbrella.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_author_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_site_author_email_too_long() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'author_email' => 'murphy@' . rand_long_str( 190 ) . '.com',
			'content'      => 'This isn\'t a saxophone. It\'s an umbrella.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_author_email_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_site_author_url_too_long() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'author_url' => 'http://jazz.' . rand_long_str( 185 ) . '.com',
			'content'    => 'This isn\'t a saxophone. It\'s an umbrella.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_author_url_column_length', $response, 400 );
	}

	/**
	 * @ticket 38477
	 */
	public function test_update_site_content_too_long() {
		wp_set_current_user( self::$superadmin_id );

		$params  = array(
			'content' => rand_long_str( 66525 ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'site_content_column_length', $response, 400 );
	}

	public function verify_site_roundtrip( $input = array(), $expected_output = array() ) {
		// Create the site
		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'author_email', 'cbg@androidsdungeon.com' );
		$request->set_param( 'network', self::$network_id );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output
		$this->assertInternalType( 'array', $actual_output['content'] );
		$this->assertArrayHasKey( 'raw', $actual_output['content'] );
		$this->assertEquals( $expected_output['content']['raw'], $actual_output['content']['raw'] );
		$this->assertEquals( $expected_output['content']['rendered'], trim( $actual_output['content']['rendered'] ) );
		$this->assertEquals( $expected_output['author_name'], $actual_output['author_name'] );
		$this->assertEquals( $expected_output['author_user_agent'], $actual_output['author_user_agent'] );

		// Compare expected API output to WP internal values
		$site = get_site( $actual_output['id'] );
		$this->assertEquals( $expected_output['content']['raw'], $blog->site_content );
		$this->assertEquals( $expected_output['author_name'], $blog->site_author );
		$this->assertEquals( $expected_output['author_user_agent'], $blog->site_agent );

		// Update the site
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/sites/%d', $actual_output['id'] ) );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		// FIXME at least one value must change, or update fails
		// See https://core.trac.wordpress.org/ticket/38700
		$request->set_param( 'author_ip', '127.0.0.2' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output
		$this->assertEquals( $expected_output['content']['raw'], $actual_output['content']['raw'] );
		$this->assertEquals( $expected_output['content']['rendered'], trim( $actual_output['content']['rendered'] ) );
		$this->assertEquals( $expected_output['author_name'], $actual_output['author_name'] );
		$this->assertEquals( $expected_output['author_user_agent'], $actual_output['author_user_agent'] );

		// Compare expected API output to WP internal values
		$site = get_site( $actual_output['id'] );
		$this->assertEquals( $expected_output['content']['raw'], $blog->site_content );
		$this->assertEquals( $expected_output['author_name'], $blog->site_author );
		$this->assertEquals( $expected_output['author_user_agent'], $blog->site_agent );
	}

	public function test_site_roundtrip_as_editor() {
		wp_set_current_user( self::$editor_id );
		$this->assertEquals( ! is_multisite(), current_user_can( 'unfiltered_html' ) );
		$this->verify_site_roundtrip(
			array(
				'content'           => '\o/ ¯\_(ツ)_/¯',
				'author_name'       => '\o/ ¯\_(ツ)_/¯',
				'author_user_agent' => '\o/ ¯\_(ツ)_/¯',
			),
			array(
				'content'           => array(
					'raw'      => '\o/ ¯\_(ツ)_/¯',
					'rendered' => '<p>\o/ ¯\_(ツ)_/¯</p>',
				),
				'author_name'       => '\o/ ¯\_(ツ)_/¯',
				'author_user_agent' => '\o/ ¯\_(ツ)_/¯',
			)
		);
	}

	public function test_site_roundtrip_as_editor_unfiltered_html() {
		wp_set_current_user( self::$editor_id );
		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'unfiltered_html' ) );
			$this->verify_site_roundtrip(
				array(
					'content'           => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_name'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_user_agent' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'content'           => array(
						'raw'      => 'div <strong>strong</strong> oh noes',
						'rendered' => '<p>div <strong>strong</strong> oh noes</p>',
					),
					'author_name'       => 'div strong',
					'author_user_agent' => 'div strong',
				)
			);
		} else {
			$this->assertTrue( current_user_can( 'unfiltered_html' ) );
			$this->verify_site_roundtrip(
				array(
					'content'           => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_name'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'author_user_agent' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'content'           => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
					),
					'author_name'       => 'div strong',
					'author_user_agent' => 'div strong',
				)
			);
		}
	}

	public function test_site_roundtrip_as_superadmin() {
		wp_set_current_user( self::$superadmin_id );
		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_site_roundtrip(
			array(
				'content'           => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'author_name'       => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'author_user_agent' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
			),
			array(
				'content'           => array(
					'raw'      => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
					'rendered' => '<p>\\\&#038;\\\ &amp; &invalid; < &lt; &amp;lt;' . "\n</p>",
				),
				'author_name'       => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'author_user_agent' => '\\\&\\\ &amp; &invalid; &lt; &lt; &amp;lt;',
			)
		);
	}

	public function test_site_roundtrip_as_superadmin_unfiltered_html() {
		wp_set_current_user( self::$superadmin_id );
		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_site_roundtrip(
			array(
				'content'           => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'author_name'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'author_user_agent' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			),
			array(
				'content'           => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
				),
				'author_name'       => 'div strong',
				'author_user_agent' => 'div strong',
			)
		);
	}

	public function test_delete_item() {
		wp_set_current_user( self::$superadmin_id );

		$site_id = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/sites/%d', $site_id ) );
		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'trash', $data['status'] );
	}

	public function test_delete_item_skip_trash() {
		wp_set_current_user( self::$superadmin_id );

		$site_id          = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);
		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/sites/%d', $site_id ) );
		$request['force'] = true;

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous']['network'] );
	}

	public function test_delete_item_already_trashed() {
		wp_set_current_user( self::$superadmin_id );

		$site_id  = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);
		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/sites/%d', $site_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data     = $response->get_data();
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_already_trashed', $response, 410 );
	}

	public function test_delete_site_invalid_id() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/sites/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_site_invalid_id', $response, 404 );
	}

	public function test_delete_site_without_permission() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_delete_child_site_link() {
		wp_set_current_user( self::$superadmin_id );
		$site_id_1 = $this->factory->blog->create(
			array(
				'public'  => 1,
				'site_id' => self::$network_id,
				'user_id' => self::$subscriber_id,
			)
		);

		$child_site = $this->factory->blog->create(
			array(
				'public'         => 1,
				'site_parent' => $site_id_1,
				'site_id'        => self::$network_id,
				'user_id'        => self::$subscriber_id,
			)
		);

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/sites/%s', $child_site ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Verify children link is gone.
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/sites/%s', $site_id_1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 17, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'author_avatar_urls', $properties );
		$this->assertArrayHasKey( 'author_email', $properties );
		$this->assertArrayHasKey( 'author_ip', $properties );
		$this->assertArrayHasKey( 'author_name', $properties );
		$this->assertArrayHasKey( 'author_url', $properties );
		$this->assertArrayHasKey( 'author_user_agent', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'network', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'type', $properties );

		$this->assertEquals( 0, $properties['parent']['default'] );
		$this->assertEquals( 0, $properties['network']['default'] );

		$this->assertEquals( true, $properties['link']['readonly'] );
		$this->assertEquals( true, $properties['type']['readonly'] );
	}

	public function test_get_item_schema_show_avatar() {
		update_option( 'show_avatars', false );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayNotHasKey( 'author_avatar_urls', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'site',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertEquals( $schema, $data['schema']['properties']['my_custom_int'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . self::$approved_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites/' . self::$approved_id );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
				'content'       => 'abc',
			)
		);

		wp_set_current_user( 1 );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 123, get_site_meta( self::$approved_id, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
				'title'         => 'hello',
				'content'       => 'goodbye',
				'network'       => self::$network_id,
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 123, $response->data['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function test_additional_field_update_errors() {
		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'site',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		wp_set_current_user( self::$superadmin_id );

		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/sites/%d', self::$approved_id ) );
		$request->set_body_params(
			array(
				'my_custom_int' => 'returnError',
				'content'       => 'abc',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_site_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $site ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
		update_site_meta( $blog->site_ID, 'my_custom_int', $value );
	}

	protected function check_site_data( $data, $context, $links ) {
		$site = get_site( $data['id'] );

		$this->assertEquals( $blog->site_ID, $data['id'] );
		$this->assertEquals( $blog->site_id, $data['network'] );
		$this->assertEquals( $blog->site_parent, $data['parent'] );
		$this->assertEquals( $blog->user_id, $data['author'] );
		$this->assertEquals( $blog->site_author, $data['author_name'] );
		$this->assertEquals( $blog->site_author_url, $data['author_url'] );
		$this->assertEquals( wpautop( $blog->site_content ), $data['content']['rendered'] );
		$this->assertEquals( mysql_to_rfc3339( $blog->site_date ), $data['date'] );
		$this->assertEquals( mysql_to_rfc3339( $blog->site_date_gmt ), $data['date_gmt'] );
		$this->assertEquals( get_site_link( $site ), $data['link'] );
		$this->assertContains( 'author_avatar_urls', $data );
		$this->assertEqualSets(
			array(
				'self',
				'collection',
				'up',
			),
			array_keys( $links )
		);

		if ( 'edit' === $context ) {
			$this->assertEquals( $blog->site_author_email, $data['author_email'] );
			$this->assertEquals( $blog->site_author_IP, $data['author_ip'] );
			$this->assertEquals( $blog->site_agent, $data['author_user_agent'] );
			$this->assertEquals( $blog->site_content, $data['content']['raw'] );
		}

		if ( 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'author_email', $data );
			$this->assertArrayNotHasKey( 'author_ip', $data );
			$this->assertArrayNotHasKey( 'author_user_agent', $data );
			$this->assertArrayNotHasKey( 'raw', $data['content'] );
		}
	}
}
