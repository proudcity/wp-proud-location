<?php
/*
Plugin Name: Proud Location
Plugin URI: http://proudcity.com/
Description: Declares an Location custom post type.
Version: 2026.06.12.1050
Author: ProudCity
Author URI: http://proudcity.com/
License: Affero GPL v3
*/

namespace Proud\Location;

// Load Extendible
// -----------------------
if ( ! class_exists( 'ProudPlugin' ) ) {
  require_once( plugin_dir_path(__FILE__) . '../wp-proud-core/proud-plugin.class.php' );
}

class ProudLocation extends \ProudPlugin {

  public function __construct() {
    parent::__construct( array(
      'textdomain'     => 'wp-proud-location',
      'plugin_path'    => __FILE__,
    ) );

    $this->hook( 'init', 'create_location' );
    $this->hook( 'rest_api_init', 'location_rest_support' );
    $this->hook( 'init', 'create_taxonomy' );
    add_filter( 'proud_search_exclude', array( $this, 'searchfilter' ) );

  }

  /**
   * Adds locations to search blacklist
   */
  public function searchfilter($posts) {
    array_push($posts, 'proud_location');
    return $posts;
  }


    public function create_location() {
        $labels = array(
            'name'               => _x( 'Locations', 'post name', 'wp-location' ),
            'singular_name'      => _x( 'Location', 'post type singular name', 'wp-location' ),
            'menu_name'          => _x( 'Locations', 'admin menu', 'wp-location' ),
            'name_admin_bar'     => _x( 'Location', 'add new on admin bar', 'wp-location' ),
            'add_new'            => _x( 'Add New', 'location', 'wp-location' ),
            'add_new_item'       => __( 'Add New Location', 'wp-location' ),
            'new_item'           => __( 'New Location', 'wp-location' ),
            'edit_item'          => __( 'Edit Location', 'wp-location' ),
            'view_item'          => __( 'View Location', 'wp-location' ),
            'all_items'          => __( 'All Locations', 'wp-location' ),
            'search_items'       => __( 'Search location', 'wp-location' ),
            'parent_item_colon'  => __( 'Parent location:', 'wp-location' ),
            'not_found'          => __( 'No locations found.', 'wp-location' ),
            'not_found_in_trash' => __( 'No locations found in Trash.', 'wp-location' )
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __( 'Description.', 'wp-location' ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'         => 'dashicons-location-alt',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'locations' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'show_in_rest'       => true,
            'rest_base'          => 'locations',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports'           => array( 'title', 'editor', 'thumbnail',)
        );

        register_post_type( 'proud_location', $args );
    }

  function create_taxonomy() {
    register_taxonomy(
        'location-taxonomy',
        'proud_location',
        array(
            'labels' => array(
                'name' => 'Location Layers',
                'add_new_item' => 'Add New Location Layer',
                'new_item_name' => "New Layer"
            ),
            'show_ui' => true,
            'show_tagcloud' => false,
            'hierarchical' => true
        )
    );
  }

  public function location_rest_support() {
    register_rest_field( 'proud_location',
          'meta',
          array(
              'get_callback'    => array( $this, 'location_rest_metadata' ),
              'update_callback' => null,
              'schema'          => null,
          )
    );
  }

  /**
   * Alter the REST endpoint.
   * Add metadata to the post response
   */
  public function location_rest_metadata( $object, $field_name, $request ) {
    $Address = new LocationAddress;
    $return = $Address->get_options( $object[ 'id' ] );
    // Get our terms
    $return['terms'] = wp_get_post_terms( $object['id'], 'location-taxonomy', array( "fields" => "all" ) );
    // Try to get primary term from SEO
    if( class_exists( '\\WPSEO_Primary_Term' ) ) {
      $primary = new \WPSEO_Primary_Term('location-taxonomy', $object[ 'id' ]);
      $primary_term = $primary->get_primary_term();
    }
    $term_layer = null;
    foreach ( $return['terms'] as $term ) {
      // We have a primary term, so use that
      if ( $primary_term && $term->term_id === $primary_term ) {
        $term_layer = $term;
        break;
      }
      if ( empty( $return['icon'] ) && $term->slug != 'featured' && $term->slug != 'all' ) {
        $term_layer = $term;
      }
    }
    // Try to attach taxonomy icon, color
    if( isset( $term_layer->term_id ) ) {
      $meta = get_term_meta( $term_layer->term_id );
      $return['icon'] = !empty( $meta['icon'] ) ? $meta['icon'][0] : '';
      $return['color'] = !empty( $meta['color'] ) ? $meta['color'][0] : '';
      $return['active_term'] = $term_layer->slug;
    }
    return $return;

  }
} // class
new ProudLocation;

// LocationAddress meta box
if (class_exists('ProudMetaBox')) {
    class LocationAddress extends \ProudMetaBox {

    public $options = [  // Meta options, key => default
        'address' => '',
        'address2' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'custom_latlng' => '',
        'lat' => '',
        'lng' => '',
        'email' => '',
        'phone' => '',
        'website' => '',
        'hours' => '',
    ];

    public function __construct() {
        parent::__construct(
        'location_address', // key
        'Address', // title
        'proud_location', // screen
        'normal',  // position
        'high' // priority
        );
    }

    /**
    * Called on form creation
    * @param $displaying : false if just building form, true if about to display
    * Use displaying:true to do any difficult loading that should only occur when
    * the form actually will display
    */
    public function set_fields( $displaying ) {
        // Already set, no loading necessary
        if( $displaying ) {
        return;
        }

        $this->fields = [
        'address' => [
            '#type' => 'text',
            '#title' => __pcHelp('Address'),
            '#args' => array('autocomplete' => 'false')
        ],
        'address2' => [
            '#type' => 'text',
            '#title' => __pcHelp('Address 2'),
        ],
        'city' => [
            '#type' => 'text',
            '#title' => __pcHelp('City'),
        ],
        'state' => [
            '#type' => 'text',
            '#title' => __pcHelp('State'),
        ],
        'zip' => [
            '#type' => 'text',
            '#title' => __pcHelp('Zip'),
        ],
        'custom_latlng' => [
            '#type' => 'checkbox',
            '#title' => __pcHelp('Customize lat/lng'),
            '#return_value' => '1',
            '#label_above' => false,
            '#replace_title' => __pcHelp( 'Enter custom Latitude/Longitude' ),
        ],
        'lat' => [
            '#type' => 'text',
            '#title' => __pcHelp('Latitude'),
            '#states' => [
            'visible' => [
                'custom_latlng' => [
                'operator' => '==',
                'value' => ['1'],
                'glue' => '||'
                ],
            ],
            ],
        ],
        'lng' => [
            '#type' => 'text',
            '#title' => __pcHelp('Longitude'),
            '#description' => __pcHelp('To automatically geocode the lat/lng from your address fields, leave both the Latitude and Longitude fields blank.'),
            '#states' => [
            'visible' => [
                'custom_latlng' => [
                'operator' => '==',
                'value' => ['1'],
                'glue' => '||'
                ],
            ],
            ],
        ],
        'location_html' => [
            '#type' => 'html',
            '#html' => '<hr><p><strong>Contact Information</strong></p>',
        ],
        'email' => [
            '#type' => 'text',
            '#title' => __pcHelp('Email'),
        ],
        'phone' => [
            '#type' => 'text',
            '#title' => __pcHelp('Phone'),
        ],
        'website' => [
            '#type' => 'text',
            '#title' => __pcHelp('Website'),
        ],
        'hours' => [
            '#type' => 'textarea',
            '#title' => __pcHelp('Hours'),
        ],
        ];
    }

    /**
    * Prints form
    */
    public function settings_content( $post ) {
        parent::settings_content( $post );
        // Enqueue JS
        $path = plugins_url('assets/',__FILE__);
        wp_enqueue_script( 'google-places-api', '//maps.googleapis.com/maps/api/js?key=' . get_option( 'google_api_key', '' ) . '&libraries=places' );
        // Autocomplete
        wp_register_script( 'google-places-field', $path . 'google-places.js' );
        // Get field ids
        $options = $this->get_field_ids();
        // Set global lat / lng
        $options['lat'] = get_option( 'lat', '' );
        $options['lng'] = get_option( 'lng', '' );
        wp_localize_script( 'google-places-field', 'proud_location', $options );
        wp_enqueue_script( 'google-places-field' );
    }

    /**
    * Returns a (string) $address from an (object|array) $location.
    */
    public function address_string( $location ) {
        $location = (array) $location;
        return $location['address'] .
            ( !empty( $location['address2'] ) ? ', ' . $location['address2'] : '' ) .
            $location['city'] . ', ' . $location['state'] . ' ' . $location['zip'];
    }

    /**
     * Returns true when any address field in $values differs from stored meta.
     *
     * Trims and lowercases before comparing so whitespace-only edits do not
     * trigger a needless geocode. An empty prior meta value (new post) also
     * counts as changed.
     */
    private function address_changed( int $post_id, array $values ): bool {
        foreach ( [ 'address', 'address2', 'city', 'state', 'zip' ] as $field ) {
            $prior   = strtolower( trim( (string) get_post_meta( $post_id, $field, true ) ) );
            $current = strtolower( trim( (string) ( $values[ $field ] ?? '' ) ) );
            if ( $prior !== $current ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calls the Google Geocoding API and returns ['lat' => ..., 'lng' => ...]
     * on success, or null on any failure.
     *
     * Failure cases (all preserve previous coords via null return):
     *   - google_api_key option is empty
     *   - address string is empty
     *   - wp_remote_get returns WP_Error
     *   - HTTP response code is not 200
     *   - JSON decode fails or status is not 'OK'
     *   - geometry.location.lat / .lng is missing
     */
    private function geocode_address( array $values ): ?array {
        $key = get_option( 'google_api_key', '' );
        if ( empty( $key ) ) {
            return null;
        }

        // Skip if all substantive address fields are empty.
        $has_address = ! empty( trim( (string) ( $values['address'] ?? '' ) ) )
            || ! empty( trim( (string) ( $values['city'] ?? '' ) ) )
            || ! empty( trim( (string) ( $values['zip'] ?? '' ) ) );
        if ( ! $has_address ) {
            return null;
        }

        $address_str = $this->address_string( $values );

        $url      = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . rawurlencode( $address_str ) . '&key=' . rawurlencode( $key );
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            error_log( 'wp-proud-location: geocode request failed for post.' );
            return null;
        }

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $body ) || ! isset( $body->status ) || $body->status !== 'OK' ) {
            error_log( 'wp-proud-location: geocode returned status ' . sanitize_text_field( (string) ( $body->status ?? 'unknown' ) ) );
            return null;
        }

        $location = $body->results[0]->geometry->location ?? null;
        if ( ! isset( $location->lat, $location->lng ) ) {
            return null;
        }

        $lat = filter_var( $location->lat, FILTER_VALIDATE_FLOAT );
        $lng = filter_var( $location->lng, FILTER_VALIDATE_FLOAT );
        if ( $lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * Applies geocoding logic to $values and returns the final $values array
     * with lat/lng resolved.
     *
     * Geocode runs when:
     *   - custom_latlng is not '1', AND one of:
     *     - The address fields changed since the last save (or this is a new post), OR
     *     - custom_latlng was previously '1' and is now being unset (transition), OR
     *     - lat or lng is empty.
     *
     * On geocode failure (API key missing, network error, non-OK response) the
     * previously stored lat/lng are preserved — coordinates are never blanked.
     *
     * @param int   $post_id Post being saved.
     * @param array $values  Validated form values from validate_values().
     * @return array $values with lat/lng set to the correct final values.
     */
    public function apply_geocode( int $post_id, array $values ): array {
        // custom_latlng='1': user-supplied coords — never geocode.
        if ( ! empty( $values['custom_latlng'] ) && $values['custom_latlng'] === '1' ) {
            return $values;
        }

        $prior_custom = get_post_meta( $post_id, 'custom_latlng', true );
        $custom_just_unchecked = ( $prior_custom === '1' && empty( $values['custom_latlng'] ) );

        $needs_geocode = $custom_just_unchecked
            || empty( $values['lat'] )
            || empty( $values['lng'] )
            || $this->address_changed( $post_id, $values );

        if ( ! $needs_geocode ) {
            return $values;
        }

        $coords = $this->geocode_address( $values );

        if ( $coords === null ) {
            // Failure: fall back to whatever was previously stored.
            $values['lat'] = get_post_meta( $post_id, 'lat', true );
            $values['lng'] = get_post_meta( $post_id, 'lng', true );
        } else {
            $values['lat'] = $coords['lat'];
            $values['lng'] = $coords['lng'];
        }

        return $values;
    }

    /**
    * Saves form values
    */
    public function save_meta( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $values = $this->validate_values( $post );
        if ( ! empty( $values ) ) {
            $values = $this->apply_geocode( $post_id, $values );
            $this->save_all( $values, $post_id );
        }
    }
    }
    if( is_admin() )
    new LocationAddress;
}

// Location desc meta box (empty for body)
if (class_exists('ProudMetaBox')) {
    class LocationDescription extends \ProudMetaBox {

    public $options = [  // Meta options, key => default
    ];

    public function __construct() {
        parent::__construct(
        'location_description', // key
        'Description', // title
        'proud_location', // screen
        'normal',  // position
        'high' // priority
        );
    }

    /**
    * Called on form creation
    * @param $displaying : false if just building form, true if about to display
    * Use displaying:true to do any difficult loading that should only occur when
    * the form actually will display
    */
    public function set_fields( $displaying ) {
        $this->fields = [];
    }
    }
    if( is_admin() )
    new LocationDescription;


    // Location desc meta box (empty for body)
    class LocationLayer extends \ProudTermMetaBox {

    public $options = [  // Meta options, key => default
        'icon' => '',
        'color' => '',
    ];

    public function __construct() {
        parent::__construct(
        'location-taxonomy', // key
        'Settings' // title
        );
    }

    private function colors() {
        return [
        '' => ' - Select - ',
        '#ED9356' => 'Orange',
        '#456D9C' => 'Blue',
        '#E76C6D' => 'Red',
        '#5A97C4' => 'Dark blue',
        '#4DC3FF' => 'Baby blue',
        '#9BBF6A' => 'Green',
        ];
    }

    /**
    * Called on form creation
    * @param $displaying : false if just building form, true if about to display
    * Use displaying:true to do any difficult loading that should only occur when
    * the form actually will display
    */
    public function set_fields( $displaying ) {
        // Already set, no loading necessary
        if( $displaying ) {
        return;
        }
        global $proudcore;

        $this->fields = [
        'icon' => [
            '#title' => 'Icon',
            '#type' => 'fa-icon',
            '#default_value' => '',
            '#to_js_settings' => false
        ],
        'color' => [
            '#title' => 'Color',
            '#type' => 'select',
            '#options' => $this->colors(),
            '#default_value' => '',
            '#to_js_settings' => false
        ],
        'markup' => [
            '#type' => 'html',
            '#html' => '<style type="text/css">.term-description-wrap { display: none; }</style>',
        ],
        ];
    }

    }
    if( is_admin() )
    new LocationLayer;
}
