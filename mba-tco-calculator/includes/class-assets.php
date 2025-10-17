<?php
/**
 * Manage plugin assets.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

use function esc_url_raw;
use function function_exists;
use function has_block;
use function has_shortcode;
use function is_admin;
use function rest_url;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;

defined( 'ABSPATH' ) || exit;

/**
 * Assets handler.
 */
class Assets {
    /**
     * Register plugin assets.
     */
    public static function register(): void {
        $version = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : filemtime( MBA_TCO_PATH . 'assets/tco.js' );

        wp_register_style(
            'mba-tco',
            MBA_TCO_URL . 'assets/tco.css',
            [],
            filemtime( MBA_TCO_PATH . 'assets/tco.css' )
        );

        wp_register_script(
            'mba-tco',
            MBA_TCO_URL . 'assets/tco.js',
            [ 'wp-i18n' ],
            $version,
            true
        );

        $options = get_options();

        wp_localize_script(
            'mba-tco',
            'MBA_TCO_OPTS',
            [
                'options' => $options,
                'rest'    => [
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                    'url'   => esc_url_raw( rest_url( 'mba-tco/v1/calc' ) ),
                ],
            ]
        );
    }

    /**
     * Enqueue assets on front-end when shortcode/widget is present.
     */
    public static function enqueue_frontend(): void {
        $has_block = function_exists( 'has_block' ) && has_block( 'mba/tco-calculator' );
        if ( ! $has_block && ! self::is_shortcode_present() ) {
            return;
        }

        wp_enqueue_style( 'mba-tco' );
        wp_enqueue_script( 'mba-tco' );
    }

    /**
     * Enqueue assets for admin pages.
     */
    public static function enqueue_admin(): void {
        wp_enqueue_style( 'mba-tco-admin', MBA_TCO_URL . 'assets/tco.css', [], filemtime( MBA_TCO_PATH . 'assets/tco.css' ) );
    }

    /**
     * Determine if shortcode exists in current post content.
     */
    protected static function is_shortcode_present(): bool {
        if ( is_admin() ) {
            return false;
        }

        global $post;
        if ( ! $post ) {
            return false;
        }

        return has_shortcode( $post->post_content, 'tco_calculator' );
    }
}
