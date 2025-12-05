<?php
/**
 * Product review generation flow.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin UI and generation of product reviews.
 */
class Trtai_Review_Flow {

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
        add_action( 'admin_post_trtai_generate_review', array( $this, 'handle_generation' ) );
        add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
    }

    /**
     * Register submenu for review generation.
     */
    public function register_menu() {
        add_submenu_page(
            'trtai-main',
            __( 'New Review (AI)', 'trtai' ),
            __( 'New Review (AI)', 'trtai' ),
            'manage_options',
            'trtai-new-review',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the review generation form.
     */
    public function render_page() {
        $settings    = $this->settings->get_settings();
        $categories  = get_categories( array( 'hide_empty' => false ) );
        $default_cat = $this->find_category_id_by_name( 'Reviews', $categories );
        include TRTAI_PLUGIN_DIR . 'admin/views/page-review-form.php';
    }

    /**
     * Handle review generation submission.
     */
    public function handle_generation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'trtai' ) );
        }

        check_admin_referer( 'trtai_generate_review' );

        $payload = array(
            'product' => array(
                'name'     => isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '',
                'brand'    => isset( $_POST['brand'] ) ? sanitize_text_field( wp_unslash( $_POST['brand'] ) ) : '',
                'type'     => isset( $_POST['product_type'] ) ? sanitize_text_field( wp_unslash( $_POST['product_type'] ) ) : '',
                'urls'     => isset( $_POST['urls'] ) ? array_map( 'esc_url_raw', array_filter( array_map( 'trim', explode( "\n", wp_unslash( $_POST['urls'] ) ) ) ) ) : array(),
                'price'    => isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '',
                'currency' => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '',
            ),
            'experience' => array(
                'liked'        => isset( $_POST['liked'] ) ? sanitize_textarea_field( wp_unslash( $_POST['liked'] ) ) : '',
                'disliked'     => isset( $_POST['disliked'] ) ? sanitize_textarea_field( wp_unslash( $_POST['disliked'] ) ) : '',
                'surprise'     => isset( $_POST['surprise'] ) ? sanitize_textarea_field( wp_unslash( $_POST['surprise'] ) ) : '',
                'dealbreaker'  => isset( $_POST['dealbreaker'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dealbreaker'] ) ) : '',
            ),
            'context' => array(
                'usage_duration' => isset( $_POST['usage_duration'] ) ? sanitize_text_field( wp_unslash( $_POST['usage_duration'] ) ) : '',
                'competitors'    => isset( $_POST['competitors'] ) ? sanitize_textarea_field( wp_unslash( $_POST['competitors'] ) ) : '',
            ),
            'seo' => array(
                'target_keyphrase'    => isset( $_POST['target_keyphrase'] ) ? sanitize_text_field( wp_unslash( $_POST['target_keyphrase'] ) ) : '',
                'secondary_keyphrases' => isset( $_POST['secondary_keyphrases'] ) ? sanitize_text_field( wp_unslash( $_POST['secondary_keyphrases'] ) ) : '',
                'desired_word_count'  => isset( $_POST['desired_word_count'] ) ? sanitize_text_field( wp_unslash( $_POST['desired_word_count'] ) ) : '',
            ),
            'post_settings' => array(
                'category' => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
                'tags'     => isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '',
            ),
        );

        if ( empty( $payload['product']['name'] ) ) {
            wp_safe_redirect( add_query_arg( 'trtai_message', 'missing_product', wp_get_referer() ) );
            exit;
        }

        $instruction = 'Return strict JSON with keys: title, slug, meta_description, focus_keyphrase, excerpt, content_html, pros (array), cons (array), social_captions (object with threads, facebook, generic), schema (Draft 07 JSON Schema for the Article-style response). Write in HTML within content_html with H2/H3 structure, keeping the JSON shape and HTML layout intact. Include the JSON Schema object in the schema field describing the response structure you returned. Use a confident, conversational editor voice with varied sentence lengths. Open with a strong intro that explains why the product matters. Add descriptive H2/H3 labels and cover design, performance, features, software, battery, price/value, and verdict sections. Keep JSON valid. Match the confident, conversational tech-journalism tone of The Verge, Engadget, and Android Police.';

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
            'post_title'   => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : $payload['product']['name'],
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
            $cat_id = $this->find_category_id_by_name( 'Reviews' );
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

        update_post_meta( $post_id, '_trtai_generated_review_payload', wp_json_encode( $data ) );
        if ( isset( $data['social_captions'] ) ) {
            update_post_meta( $post_id, '_trtai_social_captions', wp_json_encode( $data['social_captions'] ) );
        }
        if ( isset( $data['pros'] ) || isset( $data['cons'] ) ) {
            update_post_meta( $post_id, '_trtai_review_pros_cons', wp_json_encode( array(
                'pros' => isset( $data['pros'] ) ? $data['pros'] : array(),
                'cons' => isset( $data['cons'] ) ? $data['cons'] : array(),
            ) ) );
        }

        $schema_saved = $this->store_json_schema( $post_id, $data, 'review' );

        $redirect = add_query_arg(
            array(
                'post'           => $post_id,
                'action'         => 'edit',
                'trtai_message'  => 'review_success',
            ),
            admin_url( 'post.php' )
        );

        if ( ! $schema_saved ) {
            $redirect = add_query_arg( 'trtai_schema_issue', 'invalid', $redirect );
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Render notices when present.
     */
    public function maybe_render_notice() {
        if ( empty( $_GET['trtai_message'] ) && empty( $_GET['trtai_schema_issue'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $message = isset( $_GET['trtai_message'] ) ? sanitize_text_field( wp_unslash( $_GET['trtai_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $schema_issue = isset( $_GET['trtai_schema_issue'] ) ? sanitize_text_field( wp_unslash( $_GET['trtai_schema_issue'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $class   = 'notice-info';
        $text    = '';

        if ( 'review_success' === $message ) {
            $class = 'notice-success';
            $text  = __( 'Draft review created from Gemini output.', 'trtai' );
        } elseif ( 'missing_product' === $message ) {
            $class = 'notice-error';
            $text  = __( 'Product name is required to generate a review.', 'trtai' );
        } elseif ( 'error' === $message && ! empty( $_GET['trtai_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $class = 'notice-error';
            $text  = sprintf( __( 'Gemini error: %s', 'trtai' ), esc_html( wp_unslash( $_GET['trtai_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ( $schema_issue ) {
            $schema_text = __( 'Gemini response did not include a valid JSON schema. Please review the generated content and regenerate if needed.', 'trtai' );
            $text        = $text ? $text . ' ' . $schema_text : $schema_text;
            $class       = 'notice-warning';
        }

        if ( $text ) {
            printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
        }
    }

    /**
     * Find category ID by name.
     *
     * @param string $name Category name.
     * @param array  $categories Optional pre-fetched categories.
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

    /**
     * Store JSON Schema for generated payloads.
     *
     * @param int    $post_id Post ID.
     * @param array  $data    Gemini response data.
     * @param string $context Context label for logging.
     * @return bool Whether the schema validated and was saved.
     */
    protected function store_json_schema( $post_id, $data, $context ) {
        if ( empty( $data['schema'] ) ) {
            $this->log_schema_issue( $context, 'Missing schema field', $data );
            return false;
        }

        $schema = $data['schema'];

        if ( is_string( $schema ) ) {
            $decoded = json_decode( $schema, true );
            if ( JSON_ERROR_NONE === json_last_error() ) {
                $schema = $decoded;
            }
        }

        if ( ! is_array( $schema ) ) {
            $this->log_schema_issue( $context, 'Schema is not a valid object', $schema );
            return false;
        }

        $is_valid = ! empty( $schema['$schema'] ) && ! empty( $schema['type'] ) && ! empty( $schema['properties'] );

        if ( ! $is_valid ) {
            $this->log_schema_issue( $context, 'Schema is missing required fields', $schema );
        }

        $encoded_schema = wp_json_encode( $schema );

        if ( ! $encoded_schema ) {
            $this->log_schema_issue( $context, 'Failed to encode schema', $schema );
            return false;
        }

        update_post_meta( $post_id, '_trtai_json_schema', $encoded_schema );

        return $is_valid;
    }

    /**
     * Log schema validation issues for debugging.
     *
     * @param string     $context Context label.
     * @param string     $reason  Reason for the issue.
     * @param array|bool $schema  Schema payload.
     */
    protected function log_schema_issue( $context, $reason, $schema ) {
        $message = sprintf( 'TRTAI %s schema issue: %s', $context, $reason );
        $payload = wp_json_encode( $schema );

        if ( $payload ) {
            $message .= ' Payload: ' . $payload;
        }

        error_log( $message );
    }
}
