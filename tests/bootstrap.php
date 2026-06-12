<?php

/**
 * PHPUnit bootstrap for wp-proud-location.
 *
 * Load order is critical:
 *   1. Patchwork — must be active before any file that defines functions you
 *      want to mock per-test. Requiring it before stubs.php ensures its stream
 *      wrapper is in place when the WP function stubs are defined.
 *   2. Composer autoload — loads Brain\Monkey (and Mockery).
 *   3. stubs.php — minimal WP function + class stubs for load-time calls.
 *   4. Plugin file — loaded once so all class definitions are available.
 *
 * Run from the plugin root:
 *   composer install
 *   vendor/bin/phpunit
 */

require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs.php';

// Load the plugin. is_admin() returns false from stubs, so new LocationAddress
// and new LocationLayer are not instantiated at load time — the class
// definitions are available but constructors are not called. That is correct
// for unit testing apply_geocode() directly.
require_once __DIR__ . '/../wp-proud-location.php';
