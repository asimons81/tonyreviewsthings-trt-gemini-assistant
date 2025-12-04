<?php
/**
 * Social sharing manager.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles auto-sharing and evergreen sharing UI.
 */
class Trtai_Social {

    /**
     * Settings manager.
     *
     * @var Trtai_Settings
     */
    protected $settings;

    /**
     * Gemini client.
     *
     * @var Trtai_Gemini_Client
     */
    protected $gemini_client;

    /**
     * Constructor.
     *
     * @param Trtai_Settings      $settings Settings instance.
     * @param Trtai_Gemini_Client $gemini_client Gemini client.
     */
    public function __construct( Trtai_Settings $settings, Trtai_Gemini_Client $gemini_client ) {
        $this->settings      = $settings;
        $this->gemini_client = $gemini_client;

        add_action( 'transition_post_status', array( $this, 'maybe_autoshare' ), 10, 3 );
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_trtai_evergreen_share', array( $this, 'handle_evergreen_share' ) );
        add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
    }

    /**
     * Register evergreen sharing page.
     */
    public function register_menu() {
        add_submenu_page(
            'trtai-main',
            __( 'Evergreen Sharing', 'trtai' ),
            __( 'Evergreen Sharing', 'trtai' ),
            'manage_options',
            'trtai-evergreen-sharing',
            array( $this, 'render_page' )
        );
    }

    /**
     * Attempt to auto-share newly published posts.
     *
     * @param string  $new_status New status.
     * @param string  $old_status Old status.
     * @param WP_Post $post       Post object.
     */
    public function maybe_autoshare( $new_status, $old_status, $post ) {
        if ( 'publish' !== $new_status || in_array( $old_status, array( 'publish', 'trash' ), true ) ) {
            return;
        }

        if ( 'post' !== $post->post_type ) {
            return;
        }

        $settings = $this->settings->get_settings();
        if ( empty( $settings['social_autoshare_enabled'] ) || ( empty( $settings['threads_token'] ) && empty( $settings['facebook_token'] ) ) ) {
            return;
        }

        $caption = $this->get_caption_for_post( $post->ID );
        $permalink = get_permalink( $post );

        $networks = array();
        if ( ! empty( $settings['threads_token'] ) ) {
            $networks[] = 'threads';
        }
        if ( ! empty( $settings['facebook_token'] ) ) {
            $networks[] = 'facebook';
        }

        foreach ( $networks as $network ) {
            $result = $this->share_to_network( $network, $post->ID, $caption, $permalink );
            $this->log_share_result( $post->ID, $network, $result );
        }
    }

    /**
     * Render evergreen sharing page.
     */
    public function render_page() {
        $settings = $this->settings->get_settings();
        $category = isset( $_GET['filter_category'] ) ? absint( $_GET['filter_category'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $from     = isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $to       = isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( $category ) {
            $args['cat'] = $category;
        }

        if ( $from || $to ) {
            $args['date_query'] = array();
            if ( $from ) {
                $args['date_query'][] = array( 'after' => $from );
            }
            if ( $to ) {
                $args['date_query'][] = array( 'before' => $to );
            }
        }

        $query = new WP_Query( $args );
        $posts = $query->posts;

        include TRTAI_PLUGIN_DIR . 'admin/views/page-evergreen-sharing.php';
    }

    /**
     * Handle evergreen share submission.
     */
    public function handle_evergreen_share() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'trtai' ) );
        }

        check_admin_referer( 'trtai_evergreen_share_action' );

        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['post_ids'] ) ) : array();
        $networks = isset( $_POST['networks'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['networks'] ) ) : array();

        foreach ( $post_ids as $post_id ) {
            $caption   = $this->get_caption_for_post( $post_id );
            $permalink = get_permalink( $post_id );
            foreach ( $networks as $network ) {
                $result = $this->share_to_network( $network, $post_id, $caption, $permalink );
                $this->log_share_result( $post_id, $network, $result );
            }
        }

        wp_safe_redirect( add_query_arg( 'trtai_message', 'evergreen_shared', admin_url( 'admin.php?page=trtai-evergreen-sharing' ) ) );
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

        if ( 'evergreen_shared' === $message ) {
            $class = 'notice-success';
            $text  = __( 'Selected posts shared to chosen networks.', 'trtai' );
        }

        if ( $text ) {
            printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
        }
    }

    /**
     * Get or generate caption for a post.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    protected function get_caption_for_post( $post_id ) {
        $stored = get_post_meta( $post_id, '_trtai_social_captions', true );
        if ( $stored ) {
            $captions = json_decode( $stored, true );
            if ( isset( $captions['generic'] ) ) {
                return sanitize_text_field( $captions['generic'] );
            }
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        $instruction = 'Return JSON with a single key "caption" containing a short, catchy social post (max ~220 characters) suitable for general networks. Use Verge/Engadget/Android Police tone, include a hook and 1-2 purposeful hashtags, avoid emojis unless necessary.';
        $payload     = array(
            'title'   => $post->post_title,
            'excerpt' => $post->post_excerpt,
            'url'     => get_permalink( $post ),
        );

        $result = $this->gemini_client->generate_content( $instruction, $payload, array( 'temperature' => 0.6 ) );
        if ( is_wp_error( $result ) ) {
            return $post->post_title;
        }

        $data = is_array( $result['parsed'] ) ? $result['parsed'] : json_decode( $result['text'], true );
        if ( isset( $data['caption'] ) ) {
            update_post_meta( $post_id, '_trtai_social_evergreen_caption', sanitize_text_field( $data['caption'] ) );
            return sanitize_text_field( $data['caption'] );
        }

        return $post->post_title;
    }

    /**
     * Share to specific network.
     *
     * @param string $network Network name.
     * @param int    $post_id Post ID.
     * @param string $caption Caption.
     * @param string $url     URL.
     *
     * @return array|WP_Error
     */
    protected function share_to_network( $network, $post_id, $caption, $url ) {
        if ( 'threads' === $network ) {
            return $this->share_to_threads( $post_id, $caption, $url );
        }
        if ( 'facebook' === $network ) {
            return $this->share_to_facebook( $post_id, $caption, $url );
        }

        return new WP_Error( 'trtai_unknown_network', __( 'Unknown network selected.', 'trtai' ) );
    }

    /**
     * Placeholder share to Threads.
     */
    protected function share_to_threads( $post_id, $caption, $url ) {
        $settings = $this->settings->get_settings();
        if ( empty( $settings['threads_token'] ) ) {
            return new WP_Error( 'trtai_threads_missing', __( 'Threads token not configured.', 'trtai' ) );
        }

        // TODO: Implement Threads API call.
        return array(
            'network' => 'threads',
            'status'  => 'pending-implementation',
            'caption' => $caption,
            'url'     => $url,
        );
    }

    /**
     * Placeholder share to Facebook.
     */
    protected function share_to_facebook( $post_id, $caption, $url ) {
        $settings = $this->settings->get_settings();
        if ( empty( $settings['facebook_token'] ) ) {
            return new WP_Error( 'trtai_facebook_missing', __( 'Facebook token not configured.', 'trtai' ) );
        }

        // TODO: Implement Facebook Graph API call.
        return array(
            'network' => 'facebook',
            'status'  => 'pending-implementation',
            'caption' => $caption,
            'url'     => $url,
        );
    }

    /**
     * Log share result to post meta.
     *
     * @param int          $post_id Post ID.
     * @param string       $network Network name.
     * @param array|WP_Error $result Result.
     */
    protected function log_share_result( $post_id, $network, $result ) {
        $log = get_post_meta( $post_id, '_trtai_social_log', true );
        $log = $log ? json_decode( $log, true ) : array();

        $log[] = array(
            'network' => $network,
            'time'    => current_time( 'mysql' ),
            'status'  => is_wp_error( $result ) ? 'error' : ( isset( $result['status'] ) ? $result['status'] : 'ok' ),
            'message' => is_wp_error( $result ) ? $result->get_error_message() : '',
        );

        update_post_meta( $post_id, '_trtai_social_log', wp_json_encode( $log ) );
    }
}
