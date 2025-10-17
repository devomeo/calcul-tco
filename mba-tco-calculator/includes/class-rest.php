<?php
/**
 * REST API endpoints.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function __;
use function do_action;
use function register_rest_route;
use function wp_verify_nonce;

/**
 * REST controller.
 */
class REST {
    /**
     * Register routes.
     */
    public static function register_routes(): void {
        register_rest_route(
            'mba-tco/v1',
            '/calc',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'permission_callback' => [ __CLASS__, 'check_permissions' ],
                    'callback'            => [ __CLASS__, 'handle_calc' ],
                ],
            ],
            true
        );

        register_rest_route(
            'mba-tco/v1',
            '/presets',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ __CLASS__, 'handle_presets' ],
                    'permission_callback' => '__return_true',
                ],
            ],
            true
        );
    }

    /**
     * Check permissions for calculation request.
     */
    public static function check_permissions( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Nonce invalide', 'mba-tco-calculator' ), [ 'status' => 403 ] );
        }

        return true;
    }

    /**
     * Handle calculation request.
     */
    public static function handle_calc( WP_REST_Request $request ) {
        $payload = json_decode( $request->get_body(), true );
        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'invalid_payload', __( 'Payload invalide', 'mba-tco-calculator' ), [ 'status' => 400 ] );
        }

        $results = Calculator::compute( $payload );
        do_action( 'mba_tco_after_calculation', $payload, $results );

        return new WP_REST_Response( $results );
    }

    /**
     * Handle presets request.
     */
    public static function handle_presets(): WP_REST_Response {
        $options = get_options();
        $data    = [
            'vehicles'  => array_values( $options['vehicles'] ),
            'energy'    => $options['energy'],
            'charging'  => $options['charging'],
            'fiscalite' => $options['fiscalite'],
        ];

        return new WP_REST_Response( $data );
    }
}
