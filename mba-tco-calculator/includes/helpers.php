<?php
/**
 * Helpers for MBA TCO Calculator plugin.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

use function __;
use function apply_filters;
use function get_option;
use function number_format_i18n;
use function wp_parse_args;

defined( 'ABSPATH' ) || exit;

/**
 * Return default plugin options.
 *
 * @return array
 */
function get_default_options(): array {
    $defaults = [
        'vehicles'  => [
            [
                'id'               => 'veh_thermique_1',
                'label'            => __( 'Berline Thermique', 'mba-tco-calculator' ),
                'type'             => 'thermique',
                'acquisition_mode' => 'achat',
                'price_ttc'        => 32000,
                'loyer_mensuel'    => 0,
                'valeur_residuelle'=> 8000,
                'conso_urbain'     => 7.5,
                'conso_route'      => 5.5,
                'conso_autoroute'  => 6.8,
                'entretien_an'     => 650,
                'pneus_an'         => 220,
                'assurance_an'     => 850,
            ],
            [
                'id'               => 'veh_bev_1',
                'label'            => __( 'Citadine Électrique', 'mba-tco-calculator' ),
                'type'             => 'bev',
                'acquisition_mode' => 'achat',
                'price_ttc'        => 36000,
                'loyer_mensuel'    => 0,
                'valeur_residuelle'=> 12000,
                'conso_urbain'     => 14,
                'conso_route'      => 15,
                'conso_autoroute'  => 18,
                'entretien_an'     => 420,
                'pneus_an'         => 260,
                'assurance_an'     => 780,
            ],
        ],
        'energy'    => [
            'carburant_eur_l' => 1.95,
            'elec_site'       => 0.18,
            'elec_home'       => 0.16,
            'elec_public'     => 0.32,
            'mix_site'        => 40,
            'mix_home'        => 40,
            'mix_public'      => 20,
            'loss_factor'     => 1.07,
        ],
        'charging'  => [
            'prix_unitaire_ht' => 1800,
            'maintenance_an'   => 180,
            'subvention_pct'   => 20,
            'ratio_vehicule_borne' => 4,
            'duree_amortissement'  => 6,
            'borne_puissance'      => 7,
        ],
        'fiscalite' => [
            'tva_recup'            => 0,
            'bonus_malus'          => 0,
            'amort_non_deductible' => 0,
            'inclure_aen'          => false,
            'aen_montant_annuel'   => 0,
        ],
        'interface' => [
            'card_primary_color'   => '#003366',
            'card_secondary_color' => '#F5F7FA',
            'enable_fleet'         => true,
            'default_fleet_count'  => 1,
        ],
    ];

    /**
     * Allow defaults override.
     */
    return apply_filters( 'mba_tco_default_options', $defaults );
}

/**
 * Retrieve plugin options merged with defaults.
 *
 * @return array
 */
function get_options(): array {
    $saved = get_option( 'mba_tco_options', [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }

    return wp_parse_args( $saved, get_default_options() );
}

/**
 * Sanitize percentage values to 0-100.
 */
function clamp_percent( $value ): float {
    $value = floatval( $value );
    if ( $value < 0 ) {
        $value = 0;
    }
    if ( $value > 100 ) {
        $value = 100;
    }

    return $value;
}

/**
 * Round values using French formatting (comma for decimal).
 */
function format_currency( float $value ): string {
    return number_format_i18n( $value, 2 );
}

/**
 * Normalize mix percentages to ensure the total equals 100.
 *
 * @param array $mix [ 'site' => 40, 'home' => 40, 'public' => 20 ]
 *
 * @return array
 */
function normalize_mix( array $mix ): array {
    $total = array_sum( $mix );
    if ( $total <= 0 ) {
        return $mix;
    }

    foreach ( $mix as $key => $value ) {
        $mix[ $key ] = round( ( $value / $total ) * 100, 2 );
    }

    return $mix;
}
