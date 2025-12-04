<?php
/**
 * Core plugin orchestrator.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bootstraps the plugin components.
 */
class Trtai_Plugin {

    /**
     * Settings manager instance.
     *
     * @var Trtai_Settings
     */
    protected $settings;

    /**
     * Gemini client instance.
     *
     * @var Trtai_Gemini_Client
     */
    protected $gemini_client;

    /**
     * Product review flow.
     *
     * @var Trtai_Review_Flow
     */
    protected $review_flow;

    /**
     * Guide/how-to flow.
     *
     * @var Trtai_Guide_Flow
     */
    protected $guide_flow;

    /**
     * Deal alert flow.
     *
     * @var Trtai_Deal_Flow
     */
    protected $deal_flow;

    /**
     * Social sharing manager.
     *
     * @var Trtai_Social
     */
    protected $social;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->include_dependencies();
        $this->settings      = new Trtai_Settings();
        $this->gemini_client = new Trtai_Gemini_Client( $this->settings );
        $this->review_flow   = new Trtai_Review_Flow( $this->settings, $this->gemini_client );
        $this->guide_flow    = new Trtai_Guide_Flow( $this->settings, $this->gemini_client );
        $this->deal_flow     = new Trtai_Deal_Flow( $this->settings, $this->gemini_client );
        $this->social        = new Trtai_Social( $this->settings, $this->gemini_client );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Includes required class files.
     */
    protected function include_dependencies() {
        require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-settings.php';
        require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-gemini-client.php';
        require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-review-flow.php';
        require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-guide-flow.php';
        require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-deal-flow.php';
        require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-social.php';
    }

    /**
     * Enqueue admin scripts and styles on plugin pages.
     */
    public function enqueue_admin_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->base, 'trtai' ) === false ) {
            return;
        }

        wp_enqueue_style( 'trtai-admin', TRTAI_PLUGIN_URL . 'assets/css/admin.css', array(), TRTAI_PLUGIN_VERSION );
        wp_enqueue_style( 'trtai-deal-card-preview', TRTAI_PLUGIN_URL . 'assets/css/deal-card.css', array(), TRTAI_PLUGIN_VERSION );
        wp_enqueue_script( 'trtai-admin', TRTAI_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), TRTAI_PLUGIN_VERSION, true );

        wp_localize_script(
            'trtai-admin',
            'trtaiAdmin',
            array(
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'preview_loading' => __( 'Generating preview…', 'trtai' ),
                'preview_error'   => __( 'Unable to generate preview. Please try again.', 'trtai' ),
            )
        );
    }
}
