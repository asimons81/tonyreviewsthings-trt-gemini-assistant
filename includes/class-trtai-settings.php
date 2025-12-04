<?php
/**
 * Handles plugin settings and admin menus.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings manager and admin menu registration.
 */
class Trtai_Settings {

    /**
     * Option key for storing settings.
     */
    const OPTION_KEY = 'trtai_settings';

    /**
     * Cached settings array.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings = $this->get_settings();
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Get settings with defaults.
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'gemini_api_key'           => '',
            'gemini_model'             => 'gemini-2.5-flash',
            'amazon_tag'               => '',
            'threads_token'            => '',
            'facebook_token'           => '',
            'social_autoshare_enabled' => false,
        );

        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Register the settings with WordPress.
     */
    public function register_settings() {
        register_setting( 'trtai_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
    }

    /**
     * Sanitize incoming settings.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $output                              = array();
        $output['gemini_api_key']            = isset( $input['gemini_api_key'] ) ? sanitize_text_field( $input['gemini_api_key'] ) : '';
        $output['gemini_model']              = isset( $input['gemini_model'] ) ? sanitize_text_field( $input['gemini_model'] ) : 'gemini-2.5-flash';
        $output['amazon_tag']                = isset( $input['amazon_tag'] ) ? sanitize_text_field( $input['amazon_tag'] ) : '';
        $output['threads_token']             = isset( $input['threads_token'] ) ? sanitize_text_field( $input['threads_token'] ) : '';
        $output['facebook_token']            = isset( $input['facebook_token'] ) ? sanitize_text_field( $input['facebook_token'] ) : '';
        $output['social_autoshare_enabled']  = ! empty( $input['social_autoshare_enabled'] );
        return $output;
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {
        add_menu_page(
            __( 'TRT Gemini Assistant', 'trtai' ),
            __( 'TRT Gemini', 'trtai' ),
            'manage_options',
            'trtai-main',
            array( $this, 'render_main_page' ),
            'dashicons-art'
        );

        add_submenu_page(
            'trtai-main',
            __( 'Settings', 'trtai' ),
            __( 'Settings', 'trtai' ),
            'manage_options',
            'trtai-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render main page view.
     */
    public function render_main_page() {
        $settings = $this->get_settings();
        include TRTAI_PLUGIN_DIR . 'admin/views/page-main.php';
    }

    /**
     * Render settings page view.
     */
    public function render_settings_page() {
        $settings = $this->get_settings();
        include TRTAI_PLUGIN_DIR . 'admin/views/page-settings.php';
    }
}
