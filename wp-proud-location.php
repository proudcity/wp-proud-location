<?php
/*
Plugin Name: Poud Location
Plugin URI: http://proudcity.com/
Description: Declares an Location custom post type.
Version: 1.0
Author: ProudCity
Author URI: http://proudcity.com/
License: GPLv2
*/

namespace Proud\Location;

// Load Extendible
// -----------------------
if ( ! class_exists( 'ProudPlugin' ) ) {
  require_once( plugin_dir_path(__FILE__) . '../wp-proud-core/proud-plugin.class.php' );
}

class ProudLocation extends \ProudPlugin {

  /*public function __construct() {
    add_action( 'init', array($this, 'initialize') );
    add_action( 'admin_init', array($this, 'location_admin') );
    
    //add_filter( 'template_include', 'location_template' );
    add_action( 'rest_api_init', array($this, 'location_rest_support') );
  }*/

  public function __construct() {
    /*parent::__construct( array(
      'textdomain'     => 'wp-proud-location',
      'plugin_path'    => __FILE__,
    ) );*/

    $this->post_type = 'proud_location';
    $this->taxonomy = 'location-taxonomy';

    $this->hook( 'init', 'create_location' );
    $this->hook( 'admin_init', 'location_admin' );
    //$this->hook( 'plugins_loaded', 'agency_init_widgets' );
    $this->hook( 'save_post', 'add_location_fields', 10, 2 );
    $this->hook( 'rest_api_init', 'location_rest_support' );
    $this->hook( 'init', 'create_taxonomy' );
    //add_filter( 'template_include', array($this, 'agency_template') );
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

  public function location_admin() {
    add_meta_box( 'location_address_meta_box',
      'Address',
      array($this, 'display_address_meta_box'),
      $this->post_type, 'normal', 'high'
    );
    add_meta_box( 'location_contact_meta_box',
      'Contact information',
      array($this, 'display_contact_meta_box'),
      $this->post_type, 'normal', 'high'
    );
    add_meta_box( 'location_description_meta_box',
      'Description',
      array($this, 'display_description_meta_box'),
      $this->post_type, 'normal', 'high'
    );
  }

  public function location_rest_support() {
    register_api_field( 'proud_location',
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
      $return = array();
      foreach ( $this->build_fields($object['id']) as $key => $field) {
        if ($value = get_post_meta( $object['id'], $key, true )) {
          $return[$key] = $value;
        }
      }
      $return['terms'] = wp_get_post_terms($object['id'], $this->taxonomy, array("fields" => "all"));
      foreach ($return['terms'] as $term) {
        if (empty($return['icon']) && $term->slug != 'featured' && $term->slug != 'all') {
          $return['icon'] = $term->slug;
        }
      }

 
      return $return;
  }

  public function build_fields_address($id) {
    return [  
        'address' => [
          '#type' => 'text',
          '#title' => __pcHelp('Address'),
          '#name' => 'address',
          '#value' => get_post_meta( $id, 'address', true )
        ],
        'address2' => [
          '#type' => 'text',
          '#title' => __pcHelp('Address 2'),
          '#name' => 'address2',
          '#value' => get_post_meta( $id, 'address2', true )
        ],
        'city' => [
          '#type' => 'text',
          '#title' => __pcHelp('City'),
          '#name' => 'city',
          '#value' => get_post_meta( $id, 'city', true )
        ],
        'state' => [
          '#type' => 'text',
          '#title' => __pcHelp('State'),
          '#name' => 'state',
          '#value' => get_post_meta( $id, 'state', true )
        ],
        'zip' => [
          '#type' => 'text',
          '#title' => __pcHelp('Zip'),
          '#name' => 'zip',
          '#value' => get_post_meta( $id, 'zip', true )
        ],
        'custom_latlng' => [
          '#type' => 'checkbox',
          '#title' => __pcHelp('Customize lat/lng'),
          '#name' => 'custom_latlng',
          '#return_value' => '1',
          '#label_above' => false,
          '#replace_title' => __pcHelp( 'Enter custom Latitude/Longitude' ),
        ],
        'lat' => [
          '#type' => 'text',
          '#title' => __pcHelp('Latitude'),
          '#name' => 'lat',
          '#value' => get_post_meta( $id, 'lat', true ),
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
          '#name' => 'lng',
          '#value' => get_post_meta( $id, 'lng', true),
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
    ];
    return $return;
  }

  public function build_fields_contact($id) {
    return [  
        'email' => [
          '#type' => 'text',
          '#title' => __pcHelp('Email'),
          '#name' => 'email',
          '#value' => get_post_meta( $id, 'email', true )
        ],
        'phone' => [
          '#type' => 'text',
          '#title' => __pcHelp('Phone'),
          '#name' => 'phone',
          '#value' => get_post_meta( $id, 'phone', true )
        ],
        'website' => [
          '#type' => 'text',
          '#title' => __pcHelp('Website'),
          '#name' => 'website',
          '#value' => get_post_meta( $id, 'website', true )
        ],
        'hours' => [
          '#type' => 'textarea',
          '#title' => __pcHelp('Hours'),
          '#name' => 'hours',
          '#value' => get_post_meta( $id, 'hours', true )
        ],
    ];
    return $return;
  }

  public function build_fields($id) {
    $this->fields = array_merge( $this->build_fields_address($id), $this->build_fields_contact($id) );
    return $this->fields;
  }



  public function display_address_meta_box( $location ) {

    $path = plugins_url('assets/',__FILE__);
    wp_enqueue_script( 'google-places-api', '//maps.googleapis.com/maps/api/js?key='.get_option('google_places_key', true) .'&libraries=places' );
    wp_enqueue_script( 'google-places-field', $path . 'google-places.js' );
    // @todo: Proud settings aren't set on backend
    ?>
      <script>
      var location_coords = {
        lat: <?php echo get_option('lat', true); ?>,
        lng: <?php echo get_option('lng', true); ?>
      };
      </script>
    <?php

    $this->fields = $this->build_fields_address($location->ID);
    $form = new \Proud\Core\FormHelper( $this->key, $this->fields );
    $form->printFields();
  }

  public function display_contact_meta_box( $location ) {
    $this->fields = $this->build_fields_contact($location->ID);
    $form = new \Proud\Core\FormHelper( $this->key, $this->fields );
    $form->printFields();
  }

  public function display_description_meta_box( $location ) {
  }


  /**
   * Saves contact metadata fields 
   */
  public function add_location_fields( $id, $location ) {
    if ( $location->post_type == $this->post_type ) {
      if (empty($_POST['lat']) || empty($_POST['lng'])) {
        // @todo: use google_places_key here?
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($this->address_string($_POST));
        $response = wp_remote_get( $url );
        if( is_array($response) ) {
          $body = json_decode($response['body']);
          if ( !empty($body->results[0]) ) {
            $geo = $body->results[0]->geometry->location; // use the content
            print_r($geo);
            $_POST['lat'] = $geo->lat;
            $_POST['lng'] = $geo->lng;
          }
        }
      }

      foreach ($this->build_fields() as $key => $field) {
        if ( !empty( $_POST[$key] ) ) {  // @todo: check if it has been set already to allow clearing of value
          update_post_meta( $id, $key, $_POST[$key] );
        }
      }
    }
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

} // class


new ProudLocation;
