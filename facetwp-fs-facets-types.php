<?php
/**
 * Plugin Name: FacetWP - FS Facets Types
 * Description: Add Facets Types created by FS
 * Version:     1.1.3
 * Author:      Faire Savoir
 * Author URI:  https://www.faire-savoir.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class FacetWP_FS_Facets_Types {

	var $version = '1.1.3';
	var $plugin_name = 'FacetWP - FS Facets Types';
	var $plugin_id = 'facetwp-fs-facets-types';

	var $required_plugins = [
		'facetwp/index.php',
	];
	
	public function __construct() {

		$path = plugin_dir_path( __FILE__ );
		$url = plugins_url( '/', __FILE__ );

		// CONSTS
		define( 'FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION',$this->version );
		define( 'FACETWP_FS_FACETS_TYPES_PLUGIN_ID',$this->plugin_id );
		define( 'FACETWP_FS_FACETS_TYPES_PLUGIN_NAME',$this->plugin_name );
		define( 'FACETWP_FS_FACETS_TYPES_PLUGIN_URL',$url );
		define( 'FACETWP_FS_FACETS_TYPES_PLUGIN_PATH',$path );

		// Check Updates for plugin
		add_action( 'admin_init', [ $this, 'check_updates' ] );
		// Check Dependancies
		add_action( 'admin_init', [ $this,'check_plugins_dependancies' ] );

		// Init Plugin
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	/**
	 * Check plugin updates thanks to github repository link.
	 */
	public function check_updates(){
		$plugins_dir = plugin_dir_path( __DIR__ );
		if ( file_exists($plugins_dir.'plugin-update-checker/plugin-update-checker.php') ) {
			require $plugins_dir.'plugin-update-checker/plugin-update-checker.php';
			$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
				'https://github.com/Faire-savoir/'.$this->plugin_id,
				__FILE__,
				$this->plugin_id
			);
			$myUpdateChecker->getVcsApi()->enableReleaseAssets();

			add_filter('puc_request_info_result-'.$this->plugin_id,[ $this,'puc_modify_plugin_render' ]);
			add_filter('puc_view_details_link_position-'.$this->plugin_id,[ $this,'puc_modify_link_position' ]);
		}
	}

	/**
	 * Modifies the appearance of the plugin as in the detail page or during updates.
	 */
	public function puc_modify_plugin_render( $result ){
		$result->banners = ['high'=>'http://faire-savoir.com/sites/default/files/fs-banniere.jpg'];
		$result->icons = ['2x'=>'http://faire-savoir.com/sites/default/files/fs-icon.jpg'];
		return $result;
	}
	/**
	 * Changes the position of the link in the plugin list page.
	 */
  	public function puc_modify_link_position( $position ){
		$position = 'append';
		return $position;
	}

	/**
	 * This function checks the dependancies with other plugins
	 */
	function check_plugins_dependancies(){
		if ( is_admin() && current_user_can('activate_plugins') ){
			if ( !is_plugin_active('facetwp/index.php') ) {
				
				add_action( 'admin_notices', function(){
					echo '<div class="error"><p>';
					printf(__('The required plugin %s is missing to install %s.'), '<b>"FacetWP"</b>', '<b>"'.$this->plugin_name.'"</b>');
					echo'</p></div>';
				});

				deactivate_plugins( plugin_basename( __FILE__ ) );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}
	}

	/**
	 * Fired by `plugins_loaded` action hook.
	 */
	public function init() {
		include( 'includes/date_range_flatpickr.php' );
		include( 'includes/leaflet_map.php' );
		
		add_filter( 'facetwp_facet_types', function( $facet_types ) {
			$facet_types['date_range_flatpickr'] = new FacetWP_FS_DateRange_Flatpickr();
			$facet_types['leaflet_map'] = new FacetWP_FS_Leaflet_Map();
			return $facet_types;
		});
	}

}

// Instantiate FacetWP_FS_DateRange.
new FacetWP_FS_Facets_Types();
