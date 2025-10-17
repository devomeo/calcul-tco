<?php
/**
 * Admin settings for TCO calculator.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

use function __;
use function add_menu_page;
use function add_settings_field;
use function add_settings_section;
use function add_settings_error;
use function add_submenu_page;
use function admin_url;
use function checked;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function register_setting;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function settings_errors;
use function settings_fields;
use function submit_button;
use function update_option;
use function wp_nonce_field;

defined( 'ABSPATH' ) || exit;

/**
 * Admin handler.
 */
class Admin {
    /**
     * Option name used for storing settings.
     */
    public const OPTION_NAME = 'mba_tco_options';

    /**
     * Register settings using Settings API.
     */
    public static function register_settings(): void {
        if ( isset( $_POST['mba_tco_restore_defaults'] ) ) {
            check_admin_referer( 'mba_tco_restore', 'mba_tco_restore_nonce' );
            update_option( self::OPTION_NAME, get_default_options() );
            add_settings_error( self::OPTION_NAME, 'restored', __( 'Valeurs par défaut restaurées.', 'mba-tco-calculator' ), 'updated' );
        }

        register_setting(
            'mba_tco_settings',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_options' ],
                'default'           => get_default_options(),
            ]
        );

        add_settings_section( 'mba_tco_section_presets', __( 'Presets véhicules', 'mba-tco-calculator' ), '__return_null', 'mba_tco_presets' );
        add_settings_section( 'mba_tco_section_energy', __( 'Énergies & recharge', 'mba-tco-calculator' ), '__return_null', 'mba_tco_energy' );
        add_settings_section( 'mba_tco_section_fiscalite', __( 'Fiscalité', 'mba-tco-calculator' ), '__return_null', 'mba_tco_fiscalite' );
        add_settings_section( 'mba_tco_section_interface', __( 'Interface', 'mba-tco-calculator' ), '__return_null', 'mba_tco_interface' );

        add_settings_field( 'mba_tco_presets_table', __( 'Liste des véhicules', 'mba-tco-calculator' ), [ __CLASS__, 'render_presets_field' ], 'mba_tco_presets', 'mba_tco_section_presets' );
        add_settings_field( 'mba_tco_energy_defaults', __( 'Tarifs par défaut', 'mba-tco-calculator' ), [ __CLASS__, 'render_energy_field' ], 'mba_tco_energy', 'mba_tco_section_energy' );
        add_settings_field( 'mba_tco_fiscalite_defaults', __( 'Valeurs par défaut', 'mba-tco-calculator' ), [ __CLASS__, 'render_fiscalite_field' ], 'mba_tco_fiscalite', 'mba_tco_section_fiscalite' );
        add_settings_field( 'mba_tco_interface_defaults', __( 'Personnalisation', 'mba-tco-calculator' ), [ __CLASS__, 'render_interface_field' ], 'mba_tco_interface', 'mba_tco_section_interface' );
    }

    /**
     * Sanitize options before saving.
     */
    public static function sanitize_options( $input ): array {
        $defaults = get_default_options();

        if ( ! is_array( $input ) ) {
            return $defaults;
        }

        $sanitized              = [];
        $sanitized['vehicles']  = [];
        $sanitized['energy']    = $defaults['energy'];
        $sanitized['charging']  = $defaults['charging'];
        $sanitized['fiscalite'] = $defaults['fiscalite'];
        $sanitized['interface'] = $defaults['interface'];

        if ( ! empty( $input['vehicles'] ) && is_array( $input['vehicles'] ) ) {
            foreach ( $input['vehicles'] as $vehicle ) {
                $sanitized['vehicles'][] = [
                    'id'               => sanitize_key( $vehicle['id'] ?? uniqid( 'veh_', true ) ),
                    'label'            => sanitize_text_field( $vehicle['label'] ?? '' ),
                    'type'             => sanitize_text_field( $vehicle['type'] ?? 'thermique' ),
                    'acquisition_mode' => sanitize_text_field( $vehicle['acquisition_mode'] ?? 'achat' ),
                    'price_ttc'        => floatval( $vehicle['price_ttc'] ?? 0 ),
                    'loyer_mensuel'    => floatval( $vehicle['loyer_mensuel'] ?? 0 ),
                    'valeur_residuelle'=> floatval( $vehicle['valeur_residuelle'] ?? 0 ),
                    'conso_urbain'     => floatval( $vehicle['conso_urbain'] ?? 0 ),
                    'conso_route'      => floatval( $vehicle['conso_route'] ?? 0 ),
                    'conso_autoroute'  => floatval( $vehicle['conso_autoroute'] ?? 0 ),
                    'entretien_an'     => floatval( $vehicle['entretien_an'] ?? 0 ),
                    'pneus_an'         => floatval( $vehicle['pneus_an'] ?? 0 ),
                    'assurance_an'     => floatval( $vehicle['assurance_an'] ?? 0 ),
                ];
            }
        } else {
            $sanitized['vehicles'] = $defaults['vehicles'];
        }

        if ( isset( $input['energy'] ) && is_array( $input['energy'] ) ) {
            $sanitized['energy'] = [
                'carburant_eur_l' => floatval( $input['energy']['carburant_eur_l'] ?? $defaults['energy']['carburant_eur_l'] ),
                'elec_site'       => floatval( $input['energy']['elec_site'] ?? $defaults['energy']['elec_site'] ),
                'elec_home'       => floatval( $input['energy']['elec_home'] ?? $defaults['energy']['elec_home'] ),
                'elec_public'     => floatval( $input['energy']['elec_public'] ?? $defaults['energy']['elec_public'] ),
                'mix_site'        => clamp_percent( $input['energy']['mix_site'] ?? $defaults['energy']['mix_site'] ),
                'mix_home'        => clamp_percent( $input['energy']['mix_home'] ?? $defaults['energy']['mix_home'] ),
                'mix_public'      => clamp_percent( $input['energy']['mix_public'] ?? $defaults['energy']['mix_public'] ),
                'loss_factor'     => floatval( $input['energy']['loss_factor'] ?? $defaults['energy']['loss_factor'] ),
            ];
        }

        if ( isset( $input['charging'] ) && is_array( $input['charging'] ) ) {
            $sanitized['charging'] = [
                'prix_unitaire_ht'     => floatval( $input['charging']['prix_unitaire_ht'] ?? $defaults['charging']['prix_unitaire_ht'] ),
                'maintenance_an'       => floatval( $input['charging']['maintenance_an'] ?? $defaults['charging']['maintenance_an'] ),
                'subvention_pct'       => clamp_percent( $input['charging']['subvention_pct'] ?? $defaults['charging']['subvention_pct'] ),
                'ratio_vehicule_borne' => max( 1, floatval( $input['charging']['ratio_vehicule_borne'] ?? $defaults['charging']['ratio_vehicule_borne'] ) ),
                'duree_amortissement'  => max( 1, floatval( $input['charging']['duree_amortissement'] ?? $defaults['charging']['duree_amortissement'] ) ),
                'borne_puissance'      => sanitize_text_field( $input['charging']['borne_puissance'] ?? $defaults['charging']['borne_puissance'] ),
            ];
        }

        if ( isset( $input['fiscalite'] ) && is_array( $input['fiscalite'] ) ) {
            $sanitized['fiscalite'] = [
                'tva_recup'            => clamp_percent( $input['fiscalite']['tva_recup'] ?? $defaults['fiscalite']['tva_recup'] ),
                'bonus_malus'          => floatval( $input['fiscalite']['bonus_malus'] ?? $defaults['fiscalite']['bonus_malus'] ),
                'amort_non_deductible' => floatval( $input['fiscalite']['amort_non_deductible'] ?? $defaults['fiscalite']['amort_non_deductible'] ),
                'inclure_aen'          => ! empty( $input['fiscalite']['inclure_aen'] ),
                'aen_montant_annuel'   => floatval( $input['fiscalite']['aen_montant_annuel'] ?? $defaults['fiscalite']['aen_montant_annuel'] ),
            ];
        }

        if ( isset( $input['interface'] ) && is_array( $input['interface'] ) ) {
            $primary   = sanitize_hex_color( $input['interface']['card_primary_color'] ?? $defaults['interface']['card_primary_color'] );
            $secondary = sanitize_hex_color( $input['interface']['card_secondary_color'] ?? $defaults['interface']['card_secondary_color'] );
            $sanitized['interface'] = [
                'card_primary_color'   => $primary ? $primary : $defaults['interface']['card_primary_color'],
                'card_secondary_color' => $secondary ? $secondary : $defaults['interface']['card_secondary_color'],
                'enable_fleet'         => ! empty( $input['interface']['enable_fleet'] ),
                'default_fleet_count'  => max( 1, intval( $input['interface']['default_fleet_count'] ?? $defaults['interface']['default_fleet_count'] ) ),
            ];
        }

        return $sanitized;
    }

    /**
     * Register admin menu.
     */
    public static function register_menu(): void {
        add_menu_page(
            __( 'MBA', 'mba-tco-calculator' ),
            __( 'MBA', 'mba-tco-calculator' ),
            'manage_options',
            'mba_tco_root',
            [ __CLASS__, 'render_placeholder_page' ],
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'mba_tco_root',
            __( 'TCO Calculator', 'mba-tco-calculator' ),
            __( 'TCO Calculator', 'mba-tco-calculator' ),
            'manage_options',
            'mba_tco_calculator',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Placeholder page for the root menu.
     */
    public static function render_placeholder_page(): void {
        echo '<div class="wrap"><h1>' . esc_html__( 'MBA', 'mba-tco-calculator' ) . '</h1></div>';
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page(): void {
        $active_tab = sanitize_text_field( $_GET['tab'] ?? 'presets' );
        $tabs       = [
            'presets'   => __( 'Presets véhicules', 'mba-tco-calculator' ),
            'energy'    => __( 'Énergies & recharge', 'mba-tco-calculator' ),
            'fiscalite' => __( 'Fiscalité', 'mba-tco-calculator' ),
            'interface' => __( 'Interface', 'mba-tco-calculator' ),
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'MBA – TCO Calculator', 'mba-tco-calculator' ) . '</h1>';
        settings_errors( self::OPTION_NAME );
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $tab => $label ) {
            $class = 'nav-tab';
            if ( $tab === $active_tab ) {
                $class .= ' nav-tab-active';
            }
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=mba_tco_calculator&tab=' . $tab ) ),
                esc_attr( $class ),
                esc_html( $label )
            );
        }
        echo '</h2>';

        echo '<form method="post" action="options.php">';
        settings_fields( 'mba_tco_settings' );
        do_settings_sections( 'mba_tco_' . $active_tab );

        submit_button( __( 'Enregistrer', 'mba-tco-calculator' ) );

        echo '</form>';

        echo '<form method="post" style="margin-top:2rem;">';
        wp_nonce_field( 'mba_tco_restore', 'mba_tco_restore_nonce' );
        submit_button( __( 'Restaurer valeurs par défaut', 'mba-tco-calculator' ), 'secondary', 'mba_tco_restore_defaults' );
        echo '</form>';
        echo '</div>';
    }

    /**
     * Render presets table.
     */
    public static function render_presets_field(): void {
        $options = get_options();
        $vehicles = $options['vehicles'];
        echo '<p class="description">' . esc_html__( 'Ajoutez vos véhicules de référence.', 'mba-tco-calculator' ) . '</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        $headers = [ 'label' => __( 'Libellé', 'mba-tco-calculator' ), 'type' => __( 'Type', 'mba-tco-calculator' ), 'acquisition_mode' => __( 'Mode', 'mba-tco-calculator' ), 'price_ttc' => __( 'Prix TTC', 'mba-tco-calculator' ), 'loyer_mensuel' => __( 'Loyer mensuel', 'mba-tco-calculator' ), 'valeur_residuelle' => __( 'Valeur résiduelle', 'mba-tco-calculator' ), 'entretien_an' => __( 'Entretien/an', 'mba-tco-calculator' ), 'pneus_an' => __( 'Pneus/an', 'mba-tco-calculator' ), 'assurance_an' => __( 'Assurance/an', 'mba-tco-calculator' ) ];
        foreach ( $headers as $header ) {
            echo '<th>' . esc_html( $header ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ( $vehicles as $index => $vehicle ) {
            echo '<tr>';
            printf( '<td><input type="text" name="%s[%d][label]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['label'] ) );
            printf( '<td><input type="text" name="%s[%d][type]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['type'] ) );
            printf( '<td><input type="text" name="%s[%d][acquisition_mode]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['acquisition_mode'] ) );
            printf( '<td><input type="number" step="0.01" name="%s[%d][price_ttc]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['price_ttc'] ) );
            printf( '<td><input type="number" step="0.01" name="%s[%d][loyer_mensuel]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['loyer_mensuel'] ) );
            printf( '<td><input type="number" step="0.01" name="%s[%d][valeur_residuelle]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['valeur_residuelle'] ) );
            printf( '<td><input type="number" step="0.01" name="%s[%d][entretien_an]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['entretien_an'] ) );
            printf( '<td><input type="number" step="0.01" name="%s[%d][pneus_an]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['pneus_an'] ) );
            printf( '<td><input type="number" step="0.01" name="%s[%d][assurance_an]" value="%s" /></td>', esc_attr( self::OPTION_NAME . '[vehicles]' ), $index, esc_attr( $vehicle['assurance_an'] ) );
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render energy settings.
     */
    public static function render_energy_field(): void {
        $options = get_options();
        $energy = $options['energy'];
        $charging = $options['charging'];

        echo '<fieldset class="mba-tco-settings-grid">';
        printf( '<label>%s <input type="number" step="0.01" name="%s[energy][carburant_eur_l]" value="%s" /></label>', esc_html__( '€/L carburant', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['carburant_eur_l'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[energy][elec_site]" value="%s" /></label>', esc_html__( '€/kWh site', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['elec_site'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[energy][elec_home]" value="%s" /></label>', esc_html__( '€/kWh domicile', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['elec_home'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[energy][elec_public]" value="%s" /></label>', esc_html__( '€/kWh public', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['elec_public'] ) );
        printf( '<label>%s <input type="number" name="%s[energy][mix_site]" value="%s" /></label>', esc_html__( 'Mix site %', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['mix_site'] ) );
        printf( '<label>%s <input type="number" name="%s[energy][mix_home]" value="%s" /></label>', esc_html__( 'Mix domicile %', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['mix_home'] ) );
        printf( '<label>%s <input type="number" name="%s[energy][mix_public]" value="%s" /></label>', esc_html__( 'Mix public %', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['mix_public'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[energy][loss_factor]" value="%s" /></label>', esc_html__( 'Coefficient pertes élec', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $energy['loss_factor'] ) );

        echo '<hr />';
        printf( '<label>%s <input type="number" step="0.01" name="%s[charging][prix_unitaire_ht]" value="%s" /></label>', esc_html__( 'Prix borne HT', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $charging['prix_unitaire_ht'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[charging][maintenance_an]" value="%s" /></label>', esc_html__( 'Maintenance annuelle', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $charging['maintenance_an'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[charging][subvention_pct]" value="%s" /></label>', esc_html__( 'Subvention Advenir %', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $charging['subvention_pct'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[charging][ratio_vehicule_borne]" value="%s" /></label>', esc_html__( 'Ratio véhicules/borne', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $charging['ratio_vehicule_borne'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[charging][duree_amortissement]" value="%s" /></label>', esc_html__( 'Durée amortissement (ans)', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $charging['duree_amortissement'] ) );
        echo '</fieldset>';
    }

    /**
     * Render fiscality fields.
     */
    public static function render_fiscalite_field(): void {
        $options = get_options();
        $fiscalite = $options['fiscalite'];

        echo '<fieldset class="mba-tco-settings-grid">';
        printf( '<label>%s <input type="number" name="%s[fiscalite][tva_recup]" value="%s" /></label>', esc_html__( 'TVA récupérable %', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $fiscalite['tva_recup'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[fiscalite][bonus_malus]" value="%s" /></label>', esc_html__( 'Bonus / malus', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $fiscalite['bonus_malus'] ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[fiscalite][amort_non_deductible]" value="%s" /></label>', esc_html__( 'Amortissements non déductibles', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $fiscalite['amort_non_deductible'] ) );
        printf( '<label><input type="checkbox" name="%s[fiscalite][inclure_aen]" value="1" %s /> %s</label>', esc_attr( self::OPTION_NAME ), checked( $fiscalite['inclure_aen'], true, false ), esc_html__( 'Inclure AEN dans le TCO par défaut', 'mba-tco-calculator' ) );
        printf( '<label>%s <input type="number" step="0.01" name="%s[fiscalite][aen_montant_annuel]" value="%s" /></label>', esc_html__( 'Montant AEN annuel', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $fiscalite['aen_montant_annuel'] ) );
        echo '</fieldset>';
    }

    /**
     * Render interface customization fields.
     */
    public static function render_interface_field(): void {
        $options = get_options();
        $interface = $options['interface'];

        echo '<fieldset class="mba-tco-settings-grid">';
        printf( '<label>%s <input type="text" name="%s[interface][card_primary_color]" value="%s" class="color-picker" /></label>', esc_html__( 'Couleur principale', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $interface['card_primary_color'] ) );
        printf( '<label>%s <input type="text" name="%s[interface][card_secondary_color]" value="%s" class="color-picker" /></label>', esc_html__( 'Couleur secondaire', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $interface['card_secondary_color'] ) );
        printf( '<label><input type="checkbox" name="%s[interface][enable_fleet]" value="1" %s /> %s</label>', esc_attr( self::OPTION_NAME ), checked( $interface['enable_fleet'], true, false ), esc_html__( 'Activer mode flotte', 'mba-tco-calculator' ) );
        printf( '<label>%s <input type="number" name="%s[interface][default_fleet_count]" value="%s" min="1" /></label>', esc_html__( 'Nombre de véhicules par défaut', 'mba-tco-calculator' ), esc_attr( self::OPTION_NAME ), esc_attr( $interface['default_fleet_count'] ) );
        echo '</fieldset>';
    }

    /**
     * Handle enqueue in admin depending on page.
     */
    public static function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_mba_tco_root' === $hook || 'mba-tco_page_mba_tco_calculator' === $hook ) {
            Assets::enqueue_admin();
        }
    }
}
