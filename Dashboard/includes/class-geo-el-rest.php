<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Geo_El_Rest {
	private static $items = null;

	private static function bootstrap_items(): void {
		if ( self::$items !== null ) {
			return;
		}
		$now = time();
		self::$items = [
			101 => [
				'id' => 101, 'title' => 'Summer Sale Landing', 'type' => 'Page',
				'countries' => ['GB','US'], 'status' => 'publish', 'modified' => date( 'Y-m-d H:i', strtotime( '-2 days', $now ) )
			],
			102 => [
				'id' => 102, 'title' => '10% Popup (UK)', 'type' => 'Popup',
				'countries' => ['GB'], 'status' => 'publish', 'modified' => date( 'Y-m-d H:i', strtotime( '-1 day', $now ) )
			],
			103 => [
				'id' => 103, 'title' => 'Hero Section – DE', 'type' => 'Section',
				'countries' => ['DE'], 'status' => 'draft', 'modified' => date( 'Y-m-d H:i', strtotime( '-4 days', $now ) )
			],
			104 => [
				'id' => 104, 'title' => 'Lead Gen Form – CA', 'type' => 'Form',
				'countries' => ['CA'], 'status' => 'publish', 'modified' => date( 'Y-m-d H:i', strtotime( '-6 hours', $now ) )
			],
		];
	}

	public static function register_routes(): void {
		register_rest_route(
			'geo-elementor/v1',
			'/dashboard',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => [ __CLASS__, 'get_dashboard_data' ],
				'args'                => [
					'type'    => [ 'type' => 'string', 'required' => false ],
					'country' => [ 'type' => 'string', 'required' => false ],
				],
			]
		);

		register_rest_route(
			'geo-elementor/v1',
			'/countries',
			[
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => [ __CLASS__, 'get_countries' ],
			]
		);

		register_rest_route(
			'geo-elementor/v1',
			'/items/(?P<id>\d+)/status',
			[
				'methods'             => WP_REST_Server::EDITABLE, // PATCH/POST
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => [ __CLASS__, 'patch_item_status' ],
				'args'                => [
					'status' => [ 'type' => 'string', 'required' => true, 'enum' => [ 'publish', 'draft' ] ],
				],
			]
		);

		register_rest_route(
			'geo-elementor/v1',
			'/rules',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => [ __CLASS__, 'create_rule' ],
				'args'                => [
					'title'     => [ 'type' => 'string', 'required' => true ],
					'type'      => [ 'type' => 'string', 'required' => true, 'enum' => [ 'Page', 'Popup', 'Section', 'Form' ] ],
					'countries' => [ 'type' => 'array', 'required' => true, 'items' => [ 'type' => 'string' ] ],
				],
			]
		);
	}

	public static function get_dashboard_data( WP_REST_Request $req ): WP_REST_Response {
		self::bootstrap_items();

		$now = time();
		$days = [];
		for ( $i = 6; $i >= 0; $i-- ) {
			$days[] = date( 'Y-m-d', strtotime( "-$i days", $now ) );
		}

		$items = array_values( self::$items );

		$data = [
			'topLocations' => [
				[ 'name' => 'United States', 'code' => 'US', 'percent' => 42 ],
				[ 'name' => 'United Kingdom', 'code' => 'GB', 'percent' => 27 ],
				[ 'name' => 'Germany', 'code' => 'DE', 'percent' => 14 ],
				[ 'name' => 'Canada', 'code' => 'CA', 'percent' => 9 ],
				[ 'name' => 'Other', 'code' => 'OTHER', 'percent' => 8 ],
			],
			'rulesUsage' => [
				[ 'type' => 'Pages',   'count' => count( array_filter( $items, fn( $i ) => $i['type'] === 'Page' ) ) ],
				[ 'type' => 'Popups',  'count' => count( array_filter( $items, fn( $i ) => $i['type'] === 'Popup' ) ) ],
				[ 'type' => 'Sections','count' => count( array_filter( $items, fn( $i ) => $i['type'] === 'Section' ) ) ],
				[ 'type' => 'Forms',   'count' => count( array_filter( $items, fn( $i ) => $i['type'] === 'Form' ) ) ],
			],
			'engagement' => [
				'labels' => $days,
				'byCountry' => [
					'GB' => [
						'views' => [120, 90, 80, 140, 160, 155, 171],
						'conversions' => [12, 10, 7, 15, 18, 17, 19]
					],
					'US' => [
						'views' => [200, 180, 210, 220, 240, 260, 275],
						'conversions' => [20, 18, 19, 21, 24, 26, 28]
					]
				]
			],
			'items' => $items,
			'filters' => [
				'types' => ['All','Page','Popup','Section','Form'],
				'countries' => ['All','US','GB','DE','CA']
			]
		];

		return new WP_REST_Response( $data, 200 );
	}

	public static function get_countries( WP_REST_Request $req ): WP_REST_Response {
		$countries = [
			['code' => 'US', 'name' => 'United States'],
			['code' => 'GB', 'name' => 'United Kingdom'],
			['code' => 'DE', 'name' => 'Germany'],
			['code' => 'CA', 'name' => 'Canada'],
			['code' => 'FR', 'name' => 'France'],
			['code' => 'IT', 'name' => 'Italy'],
			['code' => 'ES', 'name' => 'Spain'],
			['code' => 'AU', 'name' => 'Australia'],
			['code' => 'OTHER', 'name' => 'Other'],
		];
		return new WP_REST_Response( [ 'countries' => $countries ], 200 );
	}

	public static function patch_item_status( WP_REST_Request $req ): WP_REST_Response {
		self::bootstrap_items();
		$id = (int) $req->get_param( 'id' );
		$status = $req->get_param( 'status' );

		if ( ! isset( self::$items[ $id ] ) ) {
			return new WP_REST_Response( [ 'message' => 'Item not found' ], 404 );
		}
		self::$items[ $id ]['status'] = $status;
		self::$items[ $id ]['modified'] = current_time( 'Y-m-d H:i' );

		return new WP_REST_Response( self::$items[ $id ], 200 );
	}

	public static function create_rule( WP_REST_Request $req ): WP_REST_Response {
		self::bootstrap_items();

		$title     = trim( (string) $req->get_param( 'title' ) );
		$type      = (string) $req->get_param( 'type' );
		$countries = $req->get_param( 'countries' );

		if ( $title === '' || empty( $countries ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid payload' ], 400 );
		}

		$id = max( array_keys( self::$items ) ) + 1;
		$item = [
			'id' => $id,
			'title' => $title,
			'type' => $type,
			'countries' => array_values( array_unique( array_map( 'strval', $countries ) ) ),
			'status' => 'draft',
			'modified' => current_time( 'Y-m-d H:i' ),
		];
		self::$items[ $id ] = $item;

		return new WP_REST_Response( $item, 201 );
	}
}
