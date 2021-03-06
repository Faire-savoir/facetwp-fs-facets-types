<?php

defined( 'ABSPATH' ) or exit;

/**
 * DateRange_Flatpickr facet class
 */
class FacetWP_FS_DateRange_Flatpickr {

    function __construct() {
        $this->label = __( 'FS - Date Range (Flatpickr)', 'fwp-front' );
        add_filter( 'facetwp_index_row', array( $this, 'date_format' ), 1, 2 );
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {

        $output = '';
        $value = $params['selected_values'];
        $value = empty( $value ) ? [ '', '' ] : $value;
        $placeholder = empty( $params['facet']['placeholder'] ) ? '' : __( $params['facet']['placeholder'], 'fwp-front' );
        
        $output .= '<input type="hidden" readonly="readonly" class="facetwp-date facetwp-date-min" value="' . esc_attr( $value[0] ) . '" placeholder="' . $placeholder . '" />';
        $output .= '<input type="hidden" readonly="readonly" class="facetwp-date facetwp-date-max" value="' . esc_attr( $value[1] ) . '"/>';
        

        return $output;
    }


    /**
     * Filter the query based on selected values
     */
    function filter_posts( $params ) {
        global $wpdb;

        $facet = $params['facet'];
        $values = $params['selected_values'];
        $where = '';

        $min = empty( $values[0] ) ? false : $values[0];
        $max = empty( $values[1] ) ? false : $values[1];

        $fields       = isset( $facet['fields'] ) ? $facet['fields'] : 'both';
        $compare_type = empty( $facet['compare_type'] ) ? 'basic' : $facet['compare_type'];
        $is_dual      = ! empty( $facet['source_other'] );

        if ( $is_dual && 'basic' != $compare_type ) {
            if ( 'exact' == $fields ) {
                $max = $min;
            }

            $min = ( false !== $min ) ? $min : '0000-00-00';
            $max = ( false !== $max ) ? $max : '3000-12-31';

            /**
             * Enclose compare
             * The post's range must surround the user-defined range
             */
            if ( 'enclose' == $compare_type ) {
                $where .= " AND LEFT(facet_value, 10) <= '$min'";
                $where .= " AND LEFT(facet_display_value, 10) >= '$max'";
            }

            /**
             * Intersect compare
             * @link http://stackoverflow.com/a/325964
             */
            if ( 'intersect' == $compare_type ) {
                $where .= " AND LEFT(facet_value, 10) <= '$max'";
                $where .= " AND LEFT(facet_display_value, 10) >= '$min'";
            }
        }

        /**
         * Basic compare
         * The user-defined range must surround the post's range
         */
        else {
            if ( 'exact' == $fields ) {
                $max = $min;
            }
            if ( false !== $min ) {
                $where .= " AND LEFT(facet_value, 10) >= '$min'";
            }
            if ( false !== $max ) {
                $where .= " AND LEFT(facet_display_value, 10) <= '$max'";
            }
        }

        $sql = "
        SELECT DISTINCT post_id FROM {$wpdb->prefix}facetwp_index
        WHERE facet_name = '{$facet['name']}' $where";
        return facetwp_sql( $sql, $facet );
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts() {
        $locale = get_locale();
        $locale = empty( $locale ) ? 'en' : substr( $locale, 0, 2 );
        $locale = ( 'ca' == $locale ) ? 'cat' : $locale;

        FWP()->display->json['date_range_flatpickr'] = [
            'locale'    => $locale,
            'clearText' => __( 'Clear', 'fwp-front' ),
        ];
        FWP()->display->assets['flatpickr.css'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
        FWP()->display->assets['flatpickr.js'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js', FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
        if ( 'on' == FWP()->helper->get_setting( 'debug_mode', 'off' ) ) {
            $front_script = FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/src/js/date_range_flatpickr.js';
        }else{
            $front_script = FACETWP_FS_FACETS_TYPES_PLUGIN_URL . 'assets/js/date_range_flatpickr.min.js';
        }
        FWP()->display->assets['date_range_flatpickr.js'] = [ $front_script, FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];

        if ( 'en' != $locale ) {
            FWP()->display->assets['flatpickr-l10n.js'] = [ FACETWP_FS_FACETS_TYPES_PLUGIN_URL . "assets/vendor/flatpickr/l10n/$locale.js", FACETWP_FS_FACETS_TYPES_PLUGIN_VERSION ];
        }
    }


    /**
     * Output admin settings HTML
     */
    function settings_html() {
?>
        <div class="facetwp-row">
            <div>
                <div class="facetwp-tooltip">
                    <?php _e('Other data source', 'fwp-front'); ?>:
                    <div class="facetwp-tooltip-content"><?php _e( 'Use a separate value for the upper limit?', 'fwp-front' ); ?></div>
                </div>
            </div>
            <div>
                <data-sources
                    :facet="facet"
                    setting-name="source_other">
                </data-sources>
            </div>
        </div>
        <div class="facetwp-row" v-show="facet.source_other">
            <div><?php _e('Compare type', 'fwp-front'); ?>:</div>
            <div>
                <select class="facet-compare-type">
                    <option value=""><?php _e( 'Basic', 'fwp-front' ); ?></option>
                    <option value="enclose"><?php _e( 'Enclose', 'fwp-front' ); ?></option>
                    <option value="intersect"><?php _e( 'Intersect', 'fwp-front' ); ?></option>
                </select>
            </div>
        </div>
        <div class="facetwp-row">
            <div>
                <div class="facetwp-tooltip">
                    <?php _e('Display format', 'fwp-front'); ?>:
                    <div class="facetwp-tooltip-content">See available <a href="https://flatpickr.js.org/formatting/" target="_blank">formatting tokens</a></div>
                </div>
            </div>
            <div><input type="text" class="facet-format" placeholder="Y-m-d" /></div>
        </div>
        <div class="facetwp-row">
            <div>
                <div><?php _e('Placeholder', 'fwp-front'); ?>:</div>
            </div>
            <div>
                <input type="text" class="facet-placeholder" placeholder="Dates" />
            </div>
        </div>
    
<?php
    }


    /**
     * (Front-end) Attach settings to the AJAX response
     */
    function settings_js( $params ) {

        global $wpdb;

        $facet = $params['facet'];
        $selected_values = $params['selected_values'];
        $fields = empty( $facet['fields'] ) ? 'both' : $facet['fields'];
        $format = empty( $facet['format'] ) ? 'Y-m-d' : $facet['format'];

        // Use "OR" mode by excluding the facet's own selection
        $where_clause = $this->get_where_clause( $facet );

        $sql = "
        SELECT MIN(facet_value) AS `minDate`, MAX(facet_display_value) AS `maxDate` FROM {$wpdb->prefix}facetwp_index
        WHERE facet_name = '{$facet['name']}' AND facet_display_value != '' $where_clause";
        $row = $wpdb->get_row( $sql );

        $min = substr( $row->minDate, 0, 10 );
        $max = substr( $row->maxDate, 0, 10 );

        if ( 'both' == $fields ) {
            $min_upper = ! empty( $selected_values[1] ) ? $selected_values[1] : $max;
            $max_lower = ! empty( $selected_values[0] ) ? $selected_values[0] : $min;

            $range = [
                'min' => [
                    'minDate' => $min,
                    'maxDate' => $min_upper
                ],
                'max' => [
                    'minDate' => $max_lower,
                    'maxDate' => $max
                ]
            ];
        }
        else {
            $range = [
                'minDate' => $min,
                'maxDate' => $max
            ];
        }

        return [
            'format' => $format,
            'fields' => $fields,
            'range' => $range
        ];
    }

    /**
     * Adjust the $where_clause for facets in "OR" mode
     *
     * FWP()->or_values contains EVERY facet and their matching post IDs
     * FWP()->unfiltered_post_ids contains original post IDs
     *
     * @since 3.2.0
     */
    function get_where_clause( $facet ) {

        // If no results, empty the facet
        if ( 0 === FWP()->facet->query->found_posts ) {
            $post_ids = [];
        }

        // Ignore the current facet's selections
        elseif ( isset( FWP()->or_values ) && ( 1 < count( FWP()->or_values ) || ! isset( FWP()->or_values[ $facet['name'] ] ) ) ) {
            $post_ids = [];
            $or_values = FWP()->or_values; // Preserve original
            unset( $or_values[ $facet['name'] ] );

            $counter = 0;
            foreach ( $or_values as $name => $vals ) {
                $post_ids = ( 0 == $counter ) ? $vals : array_intersect( $post_ids, $vals );
                $counter++;
            }

            $post_ids = array_intersect( $post_ids, FWP()->unfiltered_post_ids );
        }

        // Default
        else {
            $post_ids = FWP()->unfiltered_post_ids;
        }

        $post_ids = empty( $post_ids ) ? [ 0 ] : $post_ids;
        return ' AND post_id IN (' . implode( ',', $post_ids ) . ')';
    }

    function date_format( $params, $class ) {

		$facet = FWP()->helper->get_facet_by_name( $params['facet_name'] );

		if ( false !== $facet && 'date_range_flatpickr' == $facet['type'] ) {
            if( strlen( $params['facet_value'] ) == 8 && strpos( $params['facet_value'], '-' ) === FALSE) {

                $debut = substr( $params['facet_value'], 0, 4 ) . '-' . substr( $params['facet_value'], 4, 2 ) . '-' . substr( $params['facet_value'], 6, 2);
                $params['facet_value'] = $debut;
            }

            if( strlen( $params['facet_display_value'] ) == 8 && strpos( $params['facet_display_value'], '-' ) === FALSE) {
                
                $fin   = substr( $params['facet_display_value'], 0, 4 ) . '-' . substr( $params['facet_display_value'], 4, 2 ) . '-' . substr( $params['facet_display_value'], 6, 2);
                $params['facet_display_value'] = $fin;
            }
		}

        return $params;
	}

}
