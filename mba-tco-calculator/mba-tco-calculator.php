<?php
/**
 * Plugin Name: MBA TCO Calculator
 * Description: Calculateur de coût total de possession (TCO) pour comparer plusieurs véhicules.
 * Version: 1.0.0
 * Author: MBA
 * Text Domain: mba-tco-calculator
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

use MBA\TCO\Assets;
use MBA\TCO\REST;
use MBA\TCO\Admin;
use MBA\TCO\Shortcode;
use MBA\TCO\Elementor_Widget;

if ( ! defined( 'MBA_TCO_PATH' ) ) {
    define( 'MBA_TCO_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MBA_TCO_URL' ) ) {
    define( 'MBA_TCO_URL', plugin_dir_url( __FILE__ ) );
}

spl_autoload_register(
    static function ( $class ) {
        if ( strpos( $class, 'MBA\\TCO\\' ) !== 0 ) {
            return;
        }

        $relative = strtolower( str_replace( 'MBA\\TCO\\', '', $class ) );
        $relative = str_replace( '_', '-', $relative );
        $path     = MBA_TCO_PATH . 'includes/class-' . $relative . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
);

require_once MBA_TCO_PATH . 'includes/helpers.php';

add_action(
    'plugins_loaded',
    static function () {
        load_plugin_textdomain( 'mba-tco-calculator', false, basename( dirname( __FILE__ ) ) . '/languages' );
    }
);

add_action(
    'init',
    static function () {
        Assets::register();
        Shortcode::register();

        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            Elementor_Widget::register();
        }
    }
);

add_action(
    'rest_api_init',
    static function () {
        REST::register_routes();
    }
);

add_action(
    'admin_init',
    static function () {
        Admin::register_settings();
    }
);

add_action(
    'admin_menu',
    static function () {
        Admin::register_menu();
    }
);

add_action(
    'wp_enqueue_scripts',
    static function () {
        Assets::enqueue_frontend();
    }
);

add_action(
    'admin_enqueue_scripts',
    static function ( $hook ) {
        Admin::enqueue_assets( $hook );
    }
);
