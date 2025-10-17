<?php
/**
 * Shortcode renderer.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

use function array_filter;
use function array_map;
use function explode;
use function esc_attr;
use function sanitize_text_field;
use function shortcode_atts;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

/**
 * Handle frontend shortcode output.
 */
class Shortcode {
    /**
     * Register shortcode hook.
     */
    public static function register(): void {
        add_shortcode( 'tco_calculator', [ __CLASS__, 'render' ] );
    }

    /**
     * Render shortcode.
     */
    public static function render( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'compare' => 2,
                'mode'    => 'simple',
                'presets' => '',
            ],
            $atts,
            'tco_calculator'
        );

        $props = wp_json_encode(
            [
                'compare' => max( 2, min( 4, intval( $atts['compare'] ) ) ),
                'mode'    => sanitize_text_field( $atts['mode'] ),
                'presets' => array_filter( array_map( 'sanitize_text_field', explode( ',', $atts['presets'] ) ) ),
            ]
        );

        wp_enqueue_style( 'mba-tco' );
        wp_enqueue_script( 'mba-tco' );

        return '<div class="mba-tco" data-props=\'' . esc_attr( $props ) . '\'></div>';
    }
}
