<?php
/**
 * Elementor widget integration.
 *
 * @package MBA\TCO
 */

namespace MBA\TCO;

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

use function __;

/**
 * Elementor widget for the TCO calculator.
 */
class Elementor_Widget extends Widget_Base {
    /**
     * Widget slug.
     */
    public function get_name() {
        return 'mba_tco_calculator';
    }

    /**
     * Widget title.
     */
    public function get_title() {
        return __( 'MBA – Calculateur TCO', 'mba-tco-calculator' );
    }

    /**
     * Icon.
     */
    public function get_icon() {
        return 'eicon-calculator';
    }

    /**
     * Categories.
     */
    public function get_categories() {
        return [ 'general' ];
    }

    /**
     * Register controls.
     */
    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Configuration', 'mba-tco-calculator' ),
            ]
        );

        $this->add_control(
            'compare',
            [
                'label'   => __( 'Nombre de véhicules à comparer', 'mba-tco-calculator' ),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 2,
                'max'     => 4,
                'default' => 3,
            ]
        );

        $this->add_control(
            'mode',
            [
                'label'   => __( 'Mode', 'mba-tco-calculator' ),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'simple' => __( 'Simple', 'mba-tco-calculator' ),
                    'pro'    => __( 'Mode pro', 'mba-tco-calculator' ),
                ],
                'default' => 'simple',
            ]
        );

        $this->add_control(
            'presets',
            [
                'label'       => __( 'Presets (IDs séparés par des virgules)', 'mba-tco-calculator' ),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => 'veh_thermique_1,veh_bev_1',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        echo Shortcode::render( $settings );
    }

    /**
     * Register widget with Elementor when plugin loads.
     */
    public static function register(): void {
        add_action(
            'elementor/widgets/register',
            static function ( $widgets_manager ) {
                $widgets_manager->register( new self() );
            }
        );
    }
}
