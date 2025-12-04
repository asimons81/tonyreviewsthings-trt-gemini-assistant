<?php
/**
 * Deal alert generation and affiliate handling.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles deal alert admin UI and post creation.
 */
class Trtai_Deal_Flow {

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
        add_action( 'admin_post_trtai_generate_deal', array( $this, 'handle_generation' ) );
        add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
    }

    /**
     * Register submenu.
     */
    public function register_menu() {
        add_submenu_page(
            'trtai-main',
            __( 'New Deal (AI)', 'trtai' ),
            __( 'New Deal (AI)', 'trtai' ),
            'manage_options',
            'trtai-new-deal',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render deal form.
     */
    public function render_page() {
        $settings    = $this->settings->get_settings();
        $categories  = get_categories( array( 'hide_empty' => false ) );
        $default_cat = $this->find_category_id_by_name( 'Deals', $categories );
        include TRTAI_PLUGIN_DIR . 'admin/views/page-deal-form.php';
    }

    /**
     * Handle deal generation submission.
     */
    public function handle_generation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'trtai' ) );
        }

        check_admin_referer( 'trtai_generate_deal' );

        $raw_url  = isset( $_POST['deal_url'] ) ? esc_url_raw( wp_unslash( $_POST['deal_url'] ) ) : '';
        $norm_url = $this->normalize_affiliate_url( $raw_url );

        $payload = array(
            'link' => $norm_url,
            'pricing' => array(
                'current_price'  => isset( $_POST['current_price'] ) ? sanitize_text_field( wp_unslash( $_POST['current_price'] ) ) : '',
                'original_price' => isset( $_POST['original_price'] ) ? sanitize_text_field( wp_unslash( $_POST['original_price'] ) ) : '',
                'currency'       => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '',
                'coupon'         => isset( $_POST['coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon'] ) ) : '',
                'expires'        => isset( $_POST['expires'] ) ? sanitize_text_field( wp_unslash( $_POST['expires'] ) ) : '',
            ),
            'style' => array(
                'post_type' => isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'quick',
            ),
            'seo' => array(
                'keyphrase' => isset( $_POST['deal_keyphrase'] ) ? sanitize_text_field( wp_unslash( $_POST['deal_keyphrase'] ) ) : '',
            ),
            'post_settings' => array(
                'category' => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
                'tags'     => isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '',
            ),
        );

        if ( empty( $norm_url ) ) {
            wp_safe_redirect( add_query_arg( 'trtai_message', 'missing_url', wp_get_referer() ) );
            exit;
        }

        $instruction = 'Return JSON with keys: title, slug, meta_description, focus_keyphrase, excerpt, content_html, cta_text, social_captions (threads, facebook, generic). Content should be short, newsy, urgent, and explain why the deal matters. Include HTML for content_html, and keep CTA clear. Do not include markdown.';

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

        $content = isset( $data['content_html'] ) ? $data['content_html'] : '';
        if ( $norm_url && strpos( $content, $norm_url ) === false ) {
            $cta_text = ! empty( $data['cta_text'] ) ? sanitize_text_field( $data['cta_text'] ) : __( 'Get this deal', 'trtai' );
            $content .= sprintf( '<p><a class="trtai-deal-link" href="%s" target="_blank" rel="nofollow sponsored">%s</a></p>', esc_url( $norm_url ), esc_html( $cta_text ) );
        }

        $postarr = array(
            'post_title'   => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : __( 'New Deal', 'trtai' ),
            'post_content' => wp_kses_post( $content ),
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
            $cat_id = $this->find_category_id_by_name( 'Deals' );
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

        update_post_meta( $post_id, '_trtai_generated_deal_payload', wp_json_encode( $data ) );
        update_post_meta( $post_id, '_trtai_deal_url', esc_url_raw( $norm_url ) );
        if ( isset( $data['social_captions'] ) ) {
            update_post_meta( $post_id, '_trtai_social_captions', wp_json_encode( $data['social_captions'] ) );
        }

        $redirect = add_query_arg(
            array(
                'post'           => $post_id,
                'action'         => 'edit',
                'trtai_message'  => 'deal_success',
            ),
            admin_url( 'post.php' )
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Normalize affiliate URL (Amazon focus).
     *
     * @param string $url Raw URL.
     * @return string
     */
    public function normalize_affiliate_url( $url ) {
        if ( empty( $url ) ) {
            return '';
        }

        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['host'] ) ) {
            return esc_url_raw( $url );
        }

        $host = strtolower( $parsed['host'] );
        $tag  = isset( $this->settings->get_settings()['amazon_tag'] ) ? $this->settings->get_settings()['amazon_tag'] : '';

        if ( false === strpos( $host, 'amazon.' ) || empty( $tag ) ) {
            return esc_url_raw( $url );
        }

        $query_args = array();
        if ( ! empty( $parsed['query'] ) ) {
            wp_parse_str( $parsed['query'], $query_args );
        }

        $query_args['tag'] = $tag;

        foreach ( array_keys( $query_args ) as $key ) {
            if ( 0 === strpos( $key, 'utm_' ) || in_array( $key, array( 'ref', 'ascsubtag', 'tracking_id' ), true ) ) {
                unset( $query_args[ $key ] );
            }
        }

        $parsed['query'] = http_build_query( $query_args );

        $rebuilt = ( ! empty( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : 'https://' );
        $rebuilt .= $parsed['host'];
        if ( ! empty( $parsed['path'] ) ) {
            $rebuilt .= $parsed['path'];
        }
        if ( ! empty( $parsed['query'] ) ) {
            $rebuilt .= '?' . $parsed['query'];
        }
        if ( ! empty( $parsed['fragment'] ) ) {
            $rebuilt .= '#' . $parsed['fragment'];
        }

        return esc_url_raw( $rebuilt );
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

        if ( 'deal_success' === $message ) {
            $class = 'notice-success';
            $text  = __( 'Draft deal post created from Gemini output.', 'trtai' );
        } elseif ( 'missing_url' === $message ) {
            $class = 'notice-error';
            $text  = __( 'Deal URL is required.', 'trtai' );
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
