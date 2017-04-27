<?php
/*
Plugin Name: Proud Location
Plugin URI: http://proudcity.com/
Description: Declares an Location custom post type.
Version: 1.0
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

    $this->post_type = 'proud_location';
    $this->taxonomy = 'location-taxonomy';

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

      register_post_type( $this->post_type, $args );
  }

  function create_taxonomy() {
    register_taxonomy(
        $this->taxonomy,
        $this->post_type,
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
    $return['terms'] = wp_get_post_terms( $object['id'], $this->taxonomy, array( "fields" => "all" ) );
    // Try to get primary term from SEO
    if( class_exists( '\\WPSEO_Primary_Term' ) ) {
      $primary = new \WPSEO_Primary_Term($this->taxonomy, $object[ 'id' ]);
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
    wp_enqueue_script( 'google-places-api', '//maps.googleapis.com/maps/api/js?key='.get_option('google_api_key', true) .'&libraries=places' );
    // Autocomplete
    wp_register_script( 'google-places-field', $path . 'google-places.js' );
    // Get field ids
    $options = $this->get_field_ids();
    // Set global lat / lng
    $options['lat'] = get_option('lat', true);
    $options['lng'] = get_option('lng', true);
    wp_localize_script( 'google-places-field', 'proud_location', $options );
    wp_enqueue_script( 'google-places-field' );

  }

  /**
   * Returns a (string) $address from an (object|array) $location.
   */
  public function address_string($location) {
    $location = (array) $location;
    return $location['address'] .
      (!empty($location['address2']) ? ', ' . $location['address'] : '') .
      $location['city'] . ', ' . $location['state'] . ' ' . $location['zip'];
  }

  /** 
   * Saves form values
   */
  public function save_meta( $post_id, $post, $update ) {
    // Grab form values from Request
    $values = $this->validate_values( $post );
    if( !empty( $values ) ) {
      if( empty( $values['lat'] ) || empty( $values['lat'] ) ) {
        // @todo: use google_api_key here?
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $this->address_string( $values ) );
        $response = wp_remote_get( $url );
        if( is_array($response) ) {
          $body = json_decode($response['body']);
          if ( !empty($body->results[0]) ) {
            $geo = $body->results[0]->geometry->location; // use the content
            print_r($geo);
            $values['lat'] = $geo->lat;
            $values['lng'] = $geo->lng;
          }
        }
      }
      $this->save_all( $values, $post_id );
    }
  }
}
if( is_admin() )
  new LocationAddress;

// Location desc meta box (empty for body)
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

