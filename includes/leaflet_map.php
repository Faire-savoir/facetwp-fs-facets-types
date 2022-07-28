<?php

defined( 'ABSPATH' ) or exit;

/**
 * Leaflet Map facet class
 */
class FacetWP_FS_Leaflet_Map {
	
	public const LEAFLET_VERSION = '1.6.0';

	public $map_facet;
	public $proximity_facet;
	public $proximity_coords;

	function __construct() {

		$this->label = __( 'FS - Leaflet Map', 'fwp-front' );

		add_filter( 'facetwp_index_row', array( $this, 'index_latlng' ), 1, 2 );
		add_filter( 'facetwp_render_output', array( $this, 'add_marker_data' ), 10, 2 );
		// ajax load of marker content
		add_action( 'facetwp_init', function() {
			if ( isset( $_POST['action'] ) && 'facetwp_leaflet_map_marker_content' == $_POST['action'] ) {
				$post_id = (int) $_POST['post_id'];
				$facet_name = $_POST['facet_name'];
	
				echo $this->get_marker_content( $post_id, $facet_name );
				wp_die();
			}
		});
	}

	function get_map_design( $slug = null ) {
		$designs = [
			'osm'              => __( 'OpenStreetMap', 'fwp-front' ),
			'mapbox-street'    => __( 'Mapbox Street', 'fwp-front' ),
			'mapbox-satellite' => __( 'Mapbox Satellite', 'fwp-front' ),
			'google-roadmap'   => __( 'Google Roadmap*', 'fwp-front' ),
			'google-satellite' => __( 'Google Satellite*', 'fwp-front' ),
			'google-terrain'   => __( 'Google Terrain*', 'fwp-front' ),
			'google-hybrid'    => __( 'Google Hybrid*', 'fwp-front' ),
		];

		return $designs[ $slug ] ?? $designs ;
	}

	function get_gmaps_url() {
		// hard-coded
		$api_key = defined( 'GMAPS_API_KEY' ) ? GMAPS_API_KEY : '';

		// admin ui
		$tmp_key = FWP()->helper->get_setting( 'gmaps_api_key' );
		$api_key = empty( $tmp_key ) ? $api_key : $tmp_key;

		// hook
		$api_key = apply_filters( 'facetwp_gmaps_api_key', $api_key );

		return '//maps.googleapis.com/maps/api/js?libraries=places&key=' . $api_key;
	}


	/**
	 * Generate the facet HTML
	 */
	function render( $params ) {
		$width = $params['facet']['map_width'];
		$width = empty( $width ) ? 600 : $width;
		$width = is_numeric( $width ) ? $width . 'px' : $width;

		$height = $params['facet']['map_height'];
		$height = empty( $height ) ? 300 : $height;
		$height = is_numeric( $height ) ? $height . 'px' : $height;

		$class = '';
		$btn_label = __( 'Map filtering', 'fwp-front' );

		if ( $this->is_map_filtering_enabled() ) {
			$class = ' enabled';
			$btn_label = __( 'Reset', 'fwp-front' );
		}

		$output = '<div id="facetwp-leaflet-map" style="width:' . $width . '; height:' . $height . '"></div>';

		if ( isset($params['facet']['filtering']) && $params['facet']['filtering'] == 'yes' ){
			$output .= '<div class="filtering-btn"><button class="facetwp-leaflet-map-filtering' . $class . '">' . esc_html( $btn_label ) . '</button></div>';
		}
		return $output;
	}


	/**
	 * Is filtering turned on for the map?
	 * @return bool
	 */
	function is_map_filtering_enabled() {
		foreach ( FWP()->facet->facets as $facet ) {
			if ( 'leaflet_map' == $facet['type'] && !empty( $facet['selected_values'] ) ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Is a proximity facet in use? If so, return a lat/lng array
	 * @return mixed array of coordinates, or FALSE
	 */
	function is_proximity_in_use() {
		foreach ( FWP()->facet->facets as $facet ) {
			if ( 'proximity' == $facet['type'] && !empty( $facet['selected_values'] ) ) {
				$this->proximity_facet = $facet;
				return [
					'lat'    => (float) $facet['selected_values'][0],
					'lng'    => (float) $facet['selected_values'][1],
					'radius' => (int) $facet['selected_values'][2]
				];
			}
		}
		return false;
	}


	function add_marker_data( $output, $params ) {
		if ( ! $this->is_map_active() ) {
			return $output;
		}

		// Exit if paging and limit = "all"
		if ( 0 < (bool) FWP()->facet->ajax_params['soft_refresh'] ) {
			if ( 'all' == FWP()->helper->facet_is( $this->map_facet, 'limit', 'all' ) ) {
				$output['settings']['leaflet_map'] = '';
				return $output;
			}
		}

		$settings = [
			'locations' => [],
		];

		$settings['config'] = [
			'cluster'       => $this->map_facet['cluster'],
			'default_lat'   => (float) $this->map_facet['default_lat'],
			'default_lng'   => (float) $this->map_facet['default_lng'],
			'default_zoom'  => (int) $this->map_facet['default_zoom'],
		];

		$settings['init'] = [
			'scrollWheelZoom' => false,
			'gestureHandling' => true,
			'style' => $this->map_facet['map_design'],
			'zoom' => (int) $this->map_facet['default_zoom'] ?: 13,
			'minZoom' => (int) $this->map_facet['min_zoom'] ?: 1,
			'maxZoom' => (int) $this->map_facet['max_zoom'] ?: 20,
			'center' => [
				'lat' => (float) $this->map_facet['default_lat'],
				'lng' => (float) $this->map_facet['default_lng'],
			],
		];

		$settings = apply_filters( 'facetwp_map_init_args', $settings );

		// Get the proximity facet's coordinates (if available)
		$this->proximity_coords = $this->is_proximity_in_use();

		if ( false !== $this->proximity_coords ) {
			$marker_args = [
				'content' => __( 'Your location', 'fwp-map' ),
				'position' => $this->proximity_coords,
				'icon' => [
					'path' => 'M8,0C3.582,0,0,3.582,0,8s8,24,8,24s8-19.582,8-24S12.418,0,8,0z M8,12c-2.209,0-4-1.791-4-4 s1.791-4,4-4s4,1.791,4,4S10.209,12,8,12z',
					'fillColor' => 'gold',
					'fillOpacity' => 0.8,
					'scale' => 0.8,
					'anchor' => [ 'x' => 8.5, 'y' => 32 ]
				]
			];

			$marker_args = apply_filters( 'facetwp_map_proximity_marker_args', $marker_args );

			if ( !empty( $marker_args ) ) {
				$settings['locations'][] = $marker_args;
			}
		}

		// get all post IDs
		if ( isset( $this->map_facet['limit'] ) && 'all' == $this->map_facet['limit'] ) {
			$post_ids = isset( FWP()->filtered_post_ids ) ? FWP()->filtered_post_ids : FWP()->facet->query_args['post__in'];
		}
		// get paginated post IDs
		else {
			$posts =  FWP()->facet->query->posts;
			if( !empty( $posts[0]) && is_object( $posts[0] ) ) {
				$post_ids = (array) wp_list_pluck( $posts, 'ID' );
			}
			else {
				// WP_QUery with filter fields = 'ids'
				$post_ids = $posts;
			}
		}

		// remove duplicates
		$post_ids = array_unique( $post_ids );

		$all_coords = $this->get_coordinates( $post_ids, $this->map_facet );

		foreach ( $post_ids as $post_id ) {
			if ( isset( $all_coords[ $post_id ] ) ) {
				foreach ( $all_coords[ $post_id ] as $coords ) {
					$args = [
						'type' => get_post_type( $post_id ),
						'category' => $this->get_category_of_post_id( $post_id ),
						'position' => $coords,
						'post_id' => $post_id,
					];

					if ( 'yes' !== $this->map_facet['ajax_markers'] ) {
						$args['content'] = $this->get_marker_content( $post_id );
					}

					$args = apply_filters( 'facetwp_leaflet_map_markers_filter', $args, $post_id );

					if ( false !== $args ) {
						$settings['locations'][$post_id] = $args;
					}
				}
			}
		}

		$output['settings']['leaflet_map'] = $settings;

		return $output;
	}


	/**
	 * Grab all coordinates from the index table
	 */
	function get_coordinates( $post_ids, $facet ) {
		global $wpdb;

		$output = [];
		if ( !empty( $post_ids ) ) {
			$post_ids = implode( ',', $post_ids );

			$sql = "
			SELECT post_id, facet_value AS lat, facet_display_value AS lng
			FROM {$wpdb->prefix}facetwp_index
			WHERE facet_name = '{$facet['name']}' AND post_id IN ($post_ids)";

			$result = $wpdb->get_results( $sql );

			foreach ( $result as $row ) {
				$output[ $row->post_id ][] = array(
					'lat' => (float) $row->lat,
					'lng' => (float) $row->lng,
				);
			}

			// Support ACF repeaters
			if ( false !== $this->proximity_coords ) {
				foreach ( $output as $post_id => $coords ) {
					if ( 1 < count( $coords ) ) {
						$output[ $post_id ] = [];

						foreach ( $coords as $latlng ) {
							if ( $this->is_within_bounds( $latlng ) ) {
								$output[ $post_id ][] = $latlng;
							}
						}
					}
				}
			}

		}
		return $output;
	}


	/**
	 * Is the current point within the proximity bounds?
	 */
	function is_within_bounds( $latlng ) {
		$lat1 = $latlng['lat'];
		$lng1 = $latlng['lng'];
		$lat2 = $this->proximity_coords['lat'];
		$lng2 = $this->proximity_coords['lng'];

		$radius = $this->proximity_coords['radius'];
		$unit = $this->proximity_facet['unit'];

		if ( $lat1 == $lat2 && $lng1 == $lng2 ) {
			return true;
		}

		$dist = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $lng1 - $lng2 ) );
		$dist = min( max( $dist, -1 ), 1 ); // force value between -1 and 1
		$dist = rad2deg( acos( $dist ) );

		$miles = $dist * 60 * 1.1515;
		$needle = ( 'km' == $unit ) ? $miles * 1.609344 : $miles;
		return $needle <= $radius;
	}


	/**
	 * Is this page using a map facet?
	 */
	function is_map_active() {
		foreach ( FWP()->facet->facets as $name => $facet ) {
			if ( 'leaflet_map' == $facet['type'] ) {
				$this->map_facet = $facet; // save the facet
				return true;
			}
		}
		return false;
	}


	/**
	 * Get marker content (pulled via ajax)
	 */
	function get_marker_content( $post_id, $facet_name = false ) {
		if ( false !== $facet_name ) {
			$facet = FWP()->helper->get_facet_by_name( $facet_name );
			$content = $facet['marker_content'];
		}
		else {
			$content = $this->map_facet['marker_content'];
		}

		if ( empty( $content ) ) {
			return '';
		}

		global $post;

		ob_start();

		// Set the main $post object
		$post = get_post( $post_id );

		setup_postdata( $post );

		// Remove UTF-8 non-breaking spaces
		$html = preg_replace( "/\xC2\xA0/", ' ', $content );

		eval( '?>' . $html );

		// Reset globals
		wp_reset_postdata();

		// Store buffered output
		return ob_get_clean();
	}


	function get_category_of_post_id( $post_id ) {
		$category_name = null;
		$category = get_the_category( $post_id );
		if ( !empty( $category ) ){
			$category = reset($category);
			if ( !empty( $category->category_nicename ) ){
				$category_name = $category->category_nicename;
			}
		}
		return $category_name;
	}

	/**
	 * Filter the query based on the map bounds
	 */
	function filter_posts( $params ) {
		global $wpdb;

		$facet = $params['facet'];
		$selected_values = (array) $params['selected_values'];

		$swlng = (float) $selected_values[0];
		$swlat = (float) $selected_values[1];
		$nelng = (float) $selected_values[2];
		$nelat = (float) $selected_values[3];

		// @url https://stackoverflow.com/a/35944747
		if ( $swlng < $nelng ) {
			$compare_lng = "facet_display_value BETWEEN $swlng AND $nelng";
		}
		else {
			$compare_lng = "facet_display_value BETWEEN $swlng AND 180 OR ";
			$compare_lng .= "facet_display_value BETWEEN -180 AND $nelng";
		}

		$sql = "
		SELECT DISTINCT post_id FROM {$wpdb->prefix}facetwp_index
		WHERE facet_name = '{$facet['name']}' AND
		facet_value BETWEEN $swlat AND $nelat AND ($compare_lng)";

		$return = $wpdb->get_col( $sql );

		return $return;
	}


	/**
	 * Output any front-end scripts
	 */
	function front_scripts() {

		FWP()->display->assets['facetwp-leaflet_map-css'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/src/css/leaflet_map.css', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
		// LEAFLET
		FWP()->display->assets['leafletcss'] = '//unpkg.com/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.css';
		FWP()->display->assets['leafletjs'] = '//unpkg.com/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.js';
		// Google Roadmap for LEAFLET
		FWP()->display->assets['gmaps'] = $this->get_gmaps_url();
		FWP()->display->assets['googlemap'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/src/js/leaflet-google-correct-v1.js', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
		// Leaflet Gesture Handling
		FWP()->display->assets['leaflet-gesture-handling-css'] = '//unpkg.com/leaflet-gesture-handling/dist/leaflet-gesture-handling.min.css';
		FWP()->display->assets['leaflet-gesture-handling-js'] = '//unpkg.com/leaflet-gesture-handling';

		if ( $this->map_facet['cluster'] == 'yes' ){
			FWP()->display->assets['leaflet-markercluster-css'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/dist/MarkerCluster.Default.css', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
			FWP()->display->assets['leaflet-markercluster-js'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/vendor/leaflet-markercluster/dist/leaflet.markercluster.js', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
		}
		FWP()->display->assets['facetwp-leaflet_map-script'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/src/js/leaflet_map.js', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];

		if ( $this->map_facet['filtering'] == 'yes' ){
			FWP()->display->json['leaflet_map']['filterText'] = __( 'Enable map filtering', 'fwp-front' );
			FWP()->display->json['leaflet_map']['resetText'] = __( 'Reset', 'fwp-front' );
		}

		if ( $this->map_facet['ajax_markers'] == 'yes' ) {
			FWP()->display->json['leaflet_map']['facet_name'] = $this->map_facet['name'];
			FWP()->display->json['leaflet_map']['ajaxurl'] = admin_url( 'admin-ajax.php' );
		}
	}


	/**
	* Output admin settings HTML
	*/
	function settings_html() {
		$sources = FWP()->helper->get_data_sources();
?>
		<div class="facetwp-row">
			<div>
				<?php _e('Other data source', 'fwp-front'); ?>:
				<div class="facetwp-tooltip">
					<span class="icon-question">?</span>
					<div class="facetwp-tooltip-content"><?php _e( 'Use a separate value for the longitude?', 'fwp-front' ); ?></div>
				</div>
			</div>
			<div>
				<data-sources
					:facet="facet"
					setting-name="source_other">
				</data-sources>
			</div>
		</div>
		<div class="facetwp-row">
			<div>
				<?php _e('Map design', 'fwp-front'); ?>:
				<div class="facetwp-tooltip">
					<span class="icon-question">?</span>
					<div class="facetwp-tooltip-content"><?php _e( 'All "Google Maps" need a "Google Maps API Key" in "FacetWP Settings" tab', 'fwp-front' ); ?></div>
					*
				</div>
			</div>
			<div>
				<select class="facet-map-design">
					<option value="default"><?php _e( 'Default', 'fwp-front' ); ?></option>
					<?php 
						$map_styles = $this->get_map_design();
					 	if ( is_array( $map_styles ) ) : ?>	
							<?php foreach ($map_styles as $key => $value) : ?>
								<option value="<?php echo $key; ?>"><?php echo $value; ?></option>
							<?php endforeach; ?>
						<?php endif; 
					?>
				</select>
			</div>
		</div>
		<div class="facetwp-row">
			<div>
				<?php _e('Enable filtering', 'fwp-front'); ?>:
			</div>
			<div>
				<select class="facet-filtering">
					<option value="no"><?php _e( 'No', 'fwp-front' ); ?></option>
					<option value="yes"><?php _e( 'Yes', 'fwp-front' ); ?></option>
				</select>
			</div>
		</div>
		<div class="facetwp-row">
			<div>
				<div class="facetwp-tooltip">
					<?php _e( 'Marker clustering', 'fwp-front' ); ?>:
					<div class="facetwp-tooltip-content"><?php _e( 'Group markers into clusters?', 'fwp-front' ); ?></div>
				</div>
			</div>
			<div>
				<label class="facetwp-switch">
					<input type="checkbox" class="facet-cluster" true-value="yes" false-value="no" />
					<span class="facetwp-slider"></span>
				</label>
			</div>
		</div>
		<div class="facetwp-row">
			<div>
				<div class="facetwp-tooltip">
					<?php _e( 'Ajax marker content', 'fwp-front' ); ?>:
					<div class="facetwp-tooltip-content"><?php _e( 'Dynamically load marker content, which could improve load times for pages containing many map markers', 'fwp-front' ); ?></div>
				</div>
			</div>
			<div>
				<label class="facetwp-switch">
					<input type="checkbox" class="facet-ajax-markers" true-value="yes" false-value="no" />
					<span class="facetwp-slider"></span>
				</label>
			</div>
		</div>
		<div class="facetwp-row">
			<div><?php _e('Marker limit', 'fwp-front'); ?>:</div>
			<div>
				<select class="facet-limit">
					<option value="all"><?php _e( 'Show all results', 'fwp-front' ); ?></option>
					<option value="paged"><?php _e( 'Show current page results', 'fwp-front' ); ?></option>
				</select>
			</div>
		</div>
		<div class="facetwp-row">
			<div><?php _e( 'Map width / height', 'fwp-front' ); ?>:</div>
			<div>
				<input type="text" class="facet-map-width" placeholder="<?php _e( 'Width', 'fwp-front' ); ?>" style="width:96px" />
				<input type="text" class="facet-map-height" placeholder="<?php _e( 'Height', 'fwp-front' ); ?>" style="width:96px" />
			</div>
		</div>
		<div class="facetwp-row">
			<div>
				<div class="facetwp-tooltip">
					<?php _e( 'Zoom min / max', 'fwp-front' ); ?>:
					<div class="facetwp-tooltip-content"><?php _e( 'Set zoom bounds (between 1 and 20)?', 'fwp-front' ); ?></div>
				</div>
			</div>
			<div>
				<input type="text" class="facet-min-zoom" value="1" placeholder="<?php _e( 'Min', 'fwp-front' ); ?>" style="width:96px" />
				<input type="text" class="facet-max-zoom" value="20" placeholder="<?php _e( 'Max', 'fwp-front' ); ?>" style="width:96px" />
			</div>
		</div>
		<div class="facetwp-row">
			<div>
				<div class="facetwp-tooltip">
					<?php _e( 'Default lat / lng', 'fwp-front' ); ?>:
					<div class="facetwp-tooltip-content"><?php _e( 'Center the map here if there are no results', 'fwp-front' ); ?></div>
				</div>
			</div>
			<div>
				<input type="text" class="facet-default-lat" placeholder="<?php _e( 'Latitude', 'fwp-front' ); ?>" style="width:96px" />
				<input type="text" class="facet-default-lng" placeholder="<?php _e( 'Longitude', 'fwp-front' ); ?>" style="width:96px" />
				<input type="text" class="facet-default-zoom" placeholder="<?php _e( 'Zoom (1-20)', 'fwp-front' ); ?>" style="width:96px" />
			</div>
		</div>
		<div class="facetwp-row">
			<div><?php _e('Marker content', 'fwp-front'); ?>:</div>
			<div><textarea class="facet-marker-content"></textarea></div>
		</div>
<?php
	}


	/**
	 * Index the coordinates
	 * We expect a comma-separated "latitude, longitude"
	 */
	function index_latlng( $params, $class ) {

		$facet = FWP()->helper->get_facet_by_name( $params['facet_name'] );

		if ( false !== $facet && 'leaflet_map' == $facet['type'] ) {
			$latlng = $params['facet_value'];

			// Only handle "lat, lng" strings
			if ( is_string( $latlng ) ) {
				$latlng = preg_replace( '/[^0-9.,-]/', '', $latlng );

				if ( !empty( $facet['source_other'] ) ) {
					$other_params = $params;
					$other_params['facet_source'] = $facet['source_other'];
					$rows = $class->get_row_data( $other_params );

					if ( false === strpos( $latlng, ',' ) && isset($rows[0]['facet_display_value']) ) {
						$lng = $rows[0]['facet_display_value'];
						$lng = preg_replace( '/[^0-9.,-]/', '', $lng );
						$latlng .= ',' . $lng;
					}
				}

				if ( preg_match( "/^([\d.-]+),([\d.-]+)$/", $latlng ) ) {
					$latlng = explode( ',', $latlng );
					$params['facet_value'] = $latlng[0];
					$params['facet_display_value'] = $latlng[1];
				}
			}
		}

		return $params;
	}

}
