<?php
/**
 * Guide and how-to generation flow.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin UI and generation of guides/how-tos.
 */
class Trtai_Guide_Flow {

    /**
     * Settings instance.
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
     * Constructor.
     *
     * @param Trtai_Settings      $settings Settings manager.
     * @param Trtai_Gemini_Client $gemini_client Gemini client.
     */
    public function __construct( Trtai_Settings $settings, Trtai_Gemini_Client $gemini_client ) {
        $this->settings      = $settings;
        $this->gemini_client = $gemini_client;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_trtai_generate_guide', array( $this, 'handle_generation' ) );
        add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
    }

    /**
     * Register submenu for guide generation.
     */
    public function register_menu() {
        add_submenu_page(
            'trtai-main',
            __( 'New Guide (AI)', 'trtai' ),
            __( 'New Guide (AI)', 'trtai' ),
            'manage_options',
            'trtai-new-guide',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the guide generation form.
     */
    public function render_page() {
        $settings    = $this->settings->get_settings();
        $categories  = get_categories( array( 'hide_empty' => false ) );
        $default_cat = $this->find_category_id_by_name( 'Guides', $categories );
        include TRTAI_PLUGIN_DIR . 'admin/views/page-guide-form.php';
    }

    /**
     * Handle guide generation submission.
     */
    public function handle_generation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'trtai' ) );
        }

        check_admin_referer( 'trtai_generate_guide' );

        $payload = array(
            'topic' => array(
                'title'   => isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '',
                'type'    => isset( $_POST['topic_type'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_type'] ) ) : '',
                'audience'=> isset( $_POST['audience_level'] ) ? sanitize_text_field( wp_unslash( $_POST['audience_level'] ) ) : '',
            ),
            'structure' => array(
                'sections'    => isset( $_POST['sections'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sections'] ) ) : array(),
                'word_count'  => isset( $_POST['word_count'] ) ? sanitize_text_field( wp_unslash( $_POST['word_count'] ) ) : '',
            ),
            'seo' => array(
                'target_keyphrase'    => isset( $_POST['guide_target_keyphrase'] ) ? sanitize_text_field( wp_unslash( $_POST['guide_target_keyphrase'] ) ) : '',
                'secondary_keyphrases' => isset( $_POST['guide_secondary_keyphrases'] ) ? sanitize_text_field( wp_unslash( $_POST['guide_secondary_keyphrases'] ) ) : '',
            ),
            'internal_links' => isset( $_POST['internal_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_links'] ) ) : '',
            'post_settings' => array(
                'category' => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
                'tags'     => isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '',
            ),
        );

        if ( empty( $payload['topic']['title'] ) ) {
            wp_safe_redirect( add_query_arg( 'trtai_message', 'missing_topic', wp_get_referer() ) );
            exit;
        }

        $instruction = 'Return JSON with keys: title, slug, meta_description, focus_keyphrase, excerpt, content_html, faq (array of question and answer objects), social_captions (threads, facebook, generic). Use HTML for content_html with H2/H3 and optional lists/steps. Keep tone confident and clear with the Verge/Engadget/Android Police voice. Include concise, helpful FAQ entries.';

        $result = $this->gemini_client->generate_content( $instruction, $payload );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( array( 'trtai_message' => 'error', 'trtai_error' => rawurlencode( $result->get_error_message() ) ), wp_get_referer() ) );
            exit;
        }

        $data = is_array( $result['parsed'] ) ? $result['parsed'] : json_decode( $result['text'], true );
        if ( ! is_array( $data ) ) {
            wp_safe_redirect( add_query_arg( array( 'trtai_message' => 'error', 'trtai_error' => rawurlencode( __( 'Invalid response from Gemini.', 'trtai' ) ) ), wp_get_referer() ) );
            exit;
        }

        $postarr = array(
            'post_title'   => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : $payload['topic']['title'],
            'post_content' => isset( $data['content_html'] ) ? wp_kses_post( $data['content_html'] ) : '',
            'post_excerpt' => isset( $data['excerpt'] ) ? sanitize_text_field( $data['excerpt'] ) : '',
            'post_status'  => 'draft',
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $postarr, true );
        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( array( 'trtai_message' => 'error', 'trtai_error' => rawurlencode( $post_id->get_error_message() ) ), wp_get_referer() ) );
            exit;
        }

        $cat_id = $payload['post_settings']['category'];
        if ( ! $cat_id ) {
            $cat_id = $this->find_category_id_by_name( 'Guides' );
        }
        if ( $cat_id ) {
            wp_set_post_categories( $post_id, array( $cat_id ) );
        }

        if ( ! empty( $payload['post_settings']['tags'] ) ) {
            $tags = array_map( 'trim', explode( ',', $payload['post_settings']['tags'] ) );
            wp_set_post_tags( $post_id, $tags );
        }

        if ( ! empty( $data['meta_description'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $data['meta_description'] ) );
        }
        if ( ! empty( $data['focus_keyphrase'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $data['focus_keyphrase'] ) );
        }
        if ( ! empty( $data['title'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $data['title'] ) );
        }

        update_post_meta( $post_id, '_trtai_generated_guide_payload', wp_json_encode( $data ) );
        if ( isset( $data['social_captions'] ) ) {
            update_post_meta( $post_id, '_trtai_social_captions', wp_json_encode( $data['social_captions'] ) );
        }
        if ( isset( $data['faq'] ) ) {
            update_post_meta( $post_id, '_trtai_faq', wp_json_encode( $data['faq'] ) );
        }

        $redirect = add_query_arg(
            array(
                'post'           => $post_id,
                'action'         => 'edit',
                'trtai_message'  => 'guide_success',
            ),
            admin_url( 'post.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Render notices.
     */
    public function maybe_render_notice() {
        if ( empty( $_GET['trtai_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $message = sanitize_text_field( wp_unslash( $_GET['trtai_message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $class   = 'notice-info';
        $text    = '';

        if ( 'guide_success' === $message ) {
            $class = 'notice-success';
            $text  = __( 'Draft guide created from Gemini output.', 'trtai' );
        } elseif ( 'missing_topic' === $message ) {
            $class = 'notice-error';
            $text  = __( 'Topic is required to generate a guide.', 'trtai' );
        } elseif ( 'error' === $message && ! empty( $_GET['trtai_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $class = 'notice-error';
            $text  = sprintf( __( 'Gemini error: %s', 'trtai' ), esc_html( wp_unslash( $_GET['trtai_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( $text ) {
            printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
        }
    }

    /**
     * Find category ID by name.
     *
     * @param string $name Category name.
     * @param array  $categories Optional categories.
     * @return int
     */
    protected function find_category_id_by_name( $name, $categories = null ) {
        if ( null === $categories ) {
            $categories = get_categories( array( 'hide_empty' => false ) );
        }
        foreach ( $categories as $cat ) {
            if ( strtolower( $cat->name ) === strtolower( $name ) ) {
                return (int) $cat->term_id;
            }
        }
        return 0;
    }
}
