<?php

/**
 * Minimal WordPress function stubs and class stubs for wp-proud-location tests.
 *
 * Covers calls made at file-include time (before Brain\Monkey per-test mocking
 * takes over). Uses if(!function_exists) guards so they can be overridden
 * per-test via Brain\Monkey\Functions.
 */

namespace {
    if (!function_exists('add_action')) {
        function add_action() { return true; }
    }
    if (!function_exists('add_filter')) {
        function add_filter() { return true; }
    }
    if (!function_exists('add_meta_box')) {
        function add_meta_box() {}
    }
    if (!function_exists('register_post_type')) {
        function register_post_type() {}
    }
    if (!function_exists('register_taxonomy')) {
        function register_taxonomy() {}
    }
    if (!function_exists('register_rest_field')) {
        function register_rest_field() {}
    }
    if (!function_exists('is_admin')) {
        function is_admin() { return false; }
    }
    if (!function_exists('plugins_url')) {
        function plugins_url() { return ''; }
    }
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path() { return ''; }
    }
    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script() {}
    }
    if (!function_exists('wp_register_script')) {
        function wp_register_script() {}
    }
    if (!function_exists('wp_localize_script')) {
        function wp_localize_script() {}
    }
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) { return $default; }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false) { return ''; }
    }
    if (!function_exists('update_post_meta')) {
        function update_post_meta() { return true; }
    }
    if (!function_exists('wp_remote_get')) {
        function wp_remote_get() { return new WP_Error('stub', 'Not mocked'); }
    }
    if (!function_exists('wp_remote_retrieve_response_code')) {
        function wp_remote_retrieve_response_code() { return 0; }
    }
    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body() { return ''; }
    }
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) { return $thing instanceof WP_Error; }
    }
    if (!function_exists('wp_is_post_revision')) {
        function wp_is_post_revision() { return false; }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value) { return $value; }
    }
    if (!function_exists('__')) {
        function __($text, $domain = '') { return $text; }
    }
    if (!function_exists('_x')) {
        function _x($text, $context, $domain = '') { return $text; }
    }
    if (!function_exists('__pcHelp')) {
        function __pcHelp($str) { return $str; }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return $str; }
    }
    if (!function_exists('absint')) {
        function absint($n) { return abs((int) $n); }
    }
    if (!function_exists('error_log')) {
        function error_log($message, $message_type = 0) {}
    }

    if (!class_exists('WP_Error')) {
        class WP_Error {
            public function __construct(
                public string $code = '',
                public string $message = ''
            ) {}
        }
    }

    // Minimal ProudPlugin stub — the plugin extends this at load time.
    if (!class_exists('ProudPlugin')) {
        abstract class ProudPlugin {
            public function __construct(array $args = []) {}
            protected function hook($hook, $method, $priority = 10, $args = 1) {
                add_action($hook, [$this, $method], $priority, $args);
            }
        }
    }

    // Minimal FormHelper stub — ProudMetaBox::register_box() instantiates it.
    // We never actually call getFormValues() in these tests (apply_geocode is
    // called directly), so this stub just needs to exist.
    if (!class_exists('Proud\Core\FormHelper')) {
        // Declare inside the namespace block below.
    }
}

namespace Proud\Core {
    if (!class_exists('Proud\Core\FormHelper')) {
        class FormHelper {
            public function __construct($key, $fields, $depth, $type) {}
            public function getFormValues($post) { return []; }
            public function get_field_id($name) { return $name . '_id'; }
            public function get_field_name($name) { return $name . '_name'; }
            public function printFields($options, $fields, $depth, $type) {}
        }
    }
}

namespace {
    // ProudTermMetaBox abstract class — LocationLayer extends it.
    if (!class_exists('ProudTermMetaBox')) {
        abstract class ProudTermMetaBox {
            public $key;
            public $title;
            public $fields;
            public $form;
            public $options = [];

            public function __construct($key, $title) {
                $this->key   = $key;
                $this->title = $title;
            }

            abstract protected function set_fields($displaying);

            public function build_options($id = null) {}
            public function get_options($id = null) { return $this->options; }
            public function save_all($values, $id) {}
            public function settings_content($post) {}
            public function print_form($post) {}
        }
    }

    // ProudMetaBox abstract class — LocationAddress extends it.
    if (!class_exists('ProudMetaBox')) {
        abstract class ProudMetaBox {
            public $options = [];
            public $key;
            public $title;
            public $screen;
            public $position;
            public $priority;
            public $post;
            public $fields;
            public $form;

            public function __construct($key, $title, $screen = null, $position = 'advanced', $priority = 'default') {
                $this->key      = $key;
                $this->title    = $title;
                $this->screen   = $screen;
                $this->position = $position;
                $this->priority = $priority;
                add_action('save_post', [$this, 'save_meta'], 10, 3);
                add_action('admin_init', [$this, 'register_box']);
            }

            public function register_box() {
                $this->set_fields(false);
                $this->form = new \Proud\Core\FormHelper($this->key, $this->fields, 1, 'form');
            }

            abstract protected function set_fields($displaying);

            public function build_options($id = null) {
                if (!$id) return;
                $meta = get_post_meta($id);
                foreach ($this->options as $option => $default) {
                    if (isset($meta[$option][0])) {
                        $this->options[$option] = $meta[$option][0];
                    }
                }
            }

            public function get_options($id = null) {
                $this->build_options($id);
                return $this->options;
            }

            public function get_field_ids($fields = []) {
                if (empty($fields)) {
                    $fields = array_keys($this->fields);
                }
                $ids = [];
                foreach ($fields as $fieldname) {
                    $ids[$fieldname . '_id'] = $this->form->get_field_id($fieldname);
                }
                return $ids;
            }

            public function validate_values($post) {
                if (empty($this->form)) return false;
                return $this->form->getFormValues($_POST);
            }

            public function save_all($values, $post_id) {
                foreach (array_keys($this->options) as $key) {
                    if (isset($values[$key])) {
                        update_post_meta($post_id, $key, $values[$key]);
                    }
                }
            }

            public function save_meta($post_id, $post, $update) {
                $values = $this->validate_values($post);
                if (!empty($values)) {
                    $this->save_all($values, $post_id);
                }
            }
        }
    }
}
