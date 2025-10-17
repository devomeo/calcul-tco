<?php
/**
 * Core TCO calculator logic.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

defined( 'ABSPATH' ) || exit;

use function apply_filters;
use function array_sum;
use function floatval;
use function intval;
use function max;
use function sanitize_text_field;

/**
 * Pure PHP calculator used by REST endpoints and UI.
 */
class Calculator {
    /**
     * Compute the TCO output from a payload.
     *
     * Expected payload sample:
     *
     * $payload = [
     *   'vehicles' => [
     *      [
     *          'id' => 'veh_1',
     *          'label' => 'Berline',
     *          'type' => 'thermique',
     *          'acquisition' => [
     *              'mode' => 'achat',
     *              'prix_ttc' => 32000,
     *              'valeur_residuelle' => 8000,
     *              'loyers_mensuels' => 420,
     *              'frais_entree_sortie' => 500,
     *          ],
     *          'usage' => [
     *              'km_annuel' => 25000,
     *              'duree' => 4,
     *              'repartition' => [ 'urbain' => 40, 'route' => 40, 'autoroute' => 20 ],
     *          ],
     *          'energie' => [
     *              'consommation' => [ 'urbain' => 7.5, 'route' => 5.5, 'autoroute' => 6.8 ],
     *              'prix_carburant' => 1.9,
     *              'prix_electricite' => [ 'site' => 0.18, 'home' => 0.16, 'public' => 0.32 ],
     *              'mix_elec' => [ 'site' => 40, 'home' => 40, 'public' => 20 ],
     *              'coefficient_pertes' => 1.07,
     *          ],
     *          'couts' => [
     *              'entretien_an' => 620,
     *              'pneus_an' => 220,
     *              'assurance_an' => 850,
     *          ],
     *          'fiscalite' => [
     *              'tva_recup' => 0,
     *              'bonus_malus' => 0,
     *              'amort_non_deductible' => 0,
     *              'divers' => 0,
     *              'aen_inclure' => false,
     *              'aen_annuel' => 0,
     *          ],
     *          'recharge' => [
     *              'borne_nb' => 2,
     *              'prix_unitaire' => 1800,
     *              'maintenance_annuelle' => 180,
     *              'subvention_pct' => 20,
     *              'ratio_vehicule_borne' => 4,
     *              'duree_amortissement' => 6,
     *          ],
     *      ],
     *   ],
     *   'fleet_count' => 1,
     * ];
     *
     * @param array $payload User selections.
     *
     * @return array Detailed calculation results.
     */
    public static function compute( array $payload ): array {
        $vehicles    = $payload['vehicles'] ?? [];
        $fleet_count = max( 1, intval( $payload['fleet_count'] ?? 1 ) );
        $results     = [];
        $best_total  = null;

        foreach ( $vehicles as $vehicle ) {
            $calc = self::compute_vehicle( $vehicle, $fleet_count );
            if ( null === $best_total || $calc['totals']['tco_total'] < $best_total ) {
                $best_total = $calc['totals']['tco_total'];
            }
            $results[] = $calc;
        }

        foreach ( $results as &$result ) {
            $result['meta']['is_best'] = ( $result['totals']['tco_total'] === $best_total );
        }

        usort(
            $results,
            static function ( $a, $b ) {
                return $a['totals']['tco_total'] <=> $b['totals']['tco_total'];
            }
        );

        return [
            'vehicles' => $results,
        ];
    }

    /**
     * Compute TCO for one vehicle.
     */
    protected static function compute_vehicle( array $vehicle, int $fleet_count ): array {
        $usage     = $vehicle['usage'] ?? [];
        $energie   = $vehicle['energie'] ?? [];
        $couts     = $vehicle['couts'] ?? [];
        $fiscalite = $vehicle['fiscalite'] ?? [];
        $recharge  = $vehicle['recharge'] ?? [];
        $acq       = $vehicle['acquisition'] ?? [];

        $km_annuel = max( 0, floatval( $usage['km_annuel'] ?? 0 ) );
        $duree     = max( 1, floatval( $usage['duree'] ?? 1 ) );
        $total_km  = $km_annuel * $duree;

        $mix = $usage['repartition'] ?? [ 'urbain' => 0, 'route' => 0, 'autoroute' => 0 ];
        $mix_total = max( 1, array_sum( $mix ) );
        foreach ( $mix as $key => $value ) {
            $mix[ $key ] = ( $value / $mix_total );
        }

        $consommation = $energie['consommation'] ?? [ 'urbain' => 0, 'route' => 0, 'autoroute' => 0 ];
        $conso_mixte  = ( $mix['urbain'] ?? 0 ) * ( $consommation['urbain'] ?? 0 )
            + ( $mix['route'] ?? 0 ) * ( $consommation['route'] ?? 0 )
            + ( $mix['autoroute'] ?? 0 ) * ( $consommation['autoroute'] ?? 0 );

        $type = sanitize_text_field( $vehicle['type'] ?? '' );
        if ( in_array( $type, [ 'bev', 'phev', 'hybride' ], true ) ) {
            $conso_mixte *= floatval( $energie['coefficient_pertes'] ?? 1.0 );
        }

        $mix_elec       = $energie['mix_elec'] ?? [ 'site' => 0, 'home' => 0, 'public' => 0 ];
        $mix_elec_total = max( 1, array_sum( $mix_elec ) );
        foreach ( $mix_elec as $key => $value ) {
            $mix_elec[ $key ] = ( $value / $mix_elec_total );
        }

        $prix_elecs = $energie['prix_electricite'] ?? [ 'site' => 0, 'home' => 0, 'public' => 0 ];
        $prix_mix_elec = ( $mix_elec['site'] ?? 0 ) * ( $prix_elecs['site'] ?? 0 )
            + ( $mix_elec['home'] ?? 0 ) * ( $prix_elecs['home'] ?? 0 )
            + ( $mix_elec['public'] ?? 0 ) * ( $prix_elecs['public'] ?? 0 );

        $prix_carburant = floatval( $energie['prix_carburant'] ?? 0 );
        $prix_mixte     = 'thermique' === $type || 'hybride' === $type ? $prix_carburant : $prix_mix_elec;

        $energie_total = 0;
        if ( $conso_mixte > 0 ) {
            $energie_total = ( $total_km * $conso_mixte / 100 ) * $prix_mixte;
        }

        $energie_total = apply_filters( 'mba_tco_adjust_energy_cost', $energie_total, $vehicle );

        $entretien_total = $duree * floatval( $couts['entretien_an'] ?? 0 );
        $pneus_total     = $duree * floatval( $couts['pneus_an'] ?? 0 );
        $assurance_total = $duree * floatval( $couts['assurance_an'] ?? 0 );

        $frais_divers = floatval( $fiscalite['divers'] ?? 0 );
        $bonus_malus  = floatval( $fiscalite['bonus_malus'] ?? 0 );
        $amort_nd     = floatval( $fiscalite['amort_non_deductible'] ?? 0 );
        $fiscalite_total = $bonus_malus + $amort_nd + $frais_divers;
        $tva_recup      = clamp_percent( $fiscalite['tva_recup'] ?? 0 );

        if ( ! empty( $fiscalite['inclure_aen'] ) && ! empty( $fiscalite['aen_inclure'] ) ) {
            $fiscalite_total += $duree * floatval( $fiscalite['aen_annuel'] ?? 0 );
        }

        $borne_total = self::compute_charging_costs( $recharge, $duree, $vehicle );

        $mode = sanitize_text_field( $acq['mode'] ?? 'achat' );
        $tco_total = 0;
        $detail    = [
            'acquisition' => 0,
            'loyers'      => 0,
            'energie'     => $energie_total,
            'entretien'   => $entretien_total,
            'pneus'       => $pneus_total,
            'assurance'   => $assurance_total,
            'fiscalite'   => $fiscalite_total,
            'bornes'      => $borne_total,
        ];

        if ( 'achat' === $mode ) {
            $prix_ttc   = floatval( $acq['prix_ttc'] ?? 0 );
            $vr         = floatval( $acq['valeur_residuelle'] ?? 0 );
            $detail['acquisition'] = max( 0, $prix_ttc - $vr );
            if ( $tva_recup > 0 && $detail['acquisition'] > 0 ) {
                $fiscalite_total   -= ( $detail['acquisition'] * $tva_recup ) / 100;
                $detail['fiscalite'] = $fiscalite_total;
            }
            $tco_total            = array_sum( $detail );
        } else {
            $loyers_mensuels   = floatval( $acq['loyers_mensuels'] ?? 0 );
            $loyers_total      = $loyers_mensuels * 12 * $duree;
            $frais_entree_sortie = floatval( $acq['frais_entree_sortie'] ?? 0 );
            $detail['loyers']     = $loyers_total + $frais_entree_sortie;
            if ( $tva_recup > 0 && $detail['loyers'] > 0 ) {
                $fiscalite_total   -= ( $detail['loyers'] * $tva_recup ) / 100;
                $detail['fiscalite'] = $fiscalite_total;
            }

            if ( empty( $acq['entretien_inclus'] ) ) {
                // Already counted in detail.
            } else {
                $detail['entretien'] = 0;
                $detail['pneus']     = 0;
                $detail['assurance'] = 0;
            }

            $tco_total = array_sum( $detail );
        }

        $tco_total *= $fleet_count;
        foreach ( $detail as $key => $value ) {
            $detail[ $key ] = $value * $fleet_count;
        }

        $tco_mensuel = $tco_total / ( $duree * 12 );
        $tco_km      = $total_km > 0 ? $tco_total / ( $total_km * $fleet_count ) : 0;

        return [
            'meta'   => [
                'id'    => sanitize_text_field( $vehicle['id'] ?? '' ),
                'label' => sanitize_text_field( $vehicle['label'] ?? '' ),
                'type'  => $type,
            ],
            'totals' => [
                'tco_total'   => $tco_total,
                'tco_monthly' => $tco_mensuel,
                'tco_km'      => $tco_km,
            ],
            'detail' => $detail,
        ];
    }

    /**
     * Compute costs related to charging infrastructure.
     */
    protected static function compute_charging_costs( array $recharge, float $duree, array $vehicle ): float {
        $nb_bornes   = max( 0, floatval( $recharge['borne_nb'] ?? 0 ) );
        $prix_unite  = floatval( $recharge['prix_unitaire'] ?? 0 );
        $maintenance = floatval( $recharge['maintenance_annuelle'] ?? 0 );
        $subvention  = clamp_percent( $recharge['subvention_pct'] ?? 0 );
        $ratio       = max( 1, floatval( $recharge['ratio_vehicule_borne'] ?? 1 ) );
        $amort       = max( 1, floatval( $recharge['duree_amortissement'] ?? 1 ) );

        $capex      = $prix_unite * $nb_bornes;
        $subvention_amount = ( $capex * $subvention ) / 100;
        $capex_net  = max( 0, $capex - $subvention_amount );
        $amort_ann  = $capex_net / $amort;
        $borne_total = ( $amort_ann + $maintenance ) * $duree * ( 1 / $ratio );

        $borne_total = apply_filters( 'mba_tco_adjust_charger_cost', $borne_total, $vehicle );

        return $borne_total;
    }
}
