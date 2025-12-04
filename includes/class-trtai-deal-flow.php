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
     * Cached settings array.
     *
     * @var array
     */
    protected $settings_data = array();

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
        $this->settings_data = $settings->get_settings();
        $this->gemini_client = $gemini_client;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_trtai_generate_deal', array( $this, 'handle_generation' ) );
        add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_deal_styles' ) );
        add_filter( 'the_content', array( $this, 'maybe_append_deal_card' ) );
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
        $settings    = $this->settings_data;
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

        $raw_url   = isset( $_POST['deal_url'] ) ? esc_url_raw( wp_unslash( $_POST['deal_url'] ) ) : '';
        $norm_url  = $this->normalize_affiliate_url( $raw_url );
        $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
        $card_meta = array(
            'cta_text'   => isset( $_POST['cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_text'] ) ) : '',
            'store_name' => isset( $_POST['store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['store_name'] ) ) : '',
            'deal_type'  => isset( $_POST['deal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['deal_type'] ) ) : '',
            'summary'    => isset( $_POST['deal_summary'] ) ? wp_kses_post( wp_unslash( $_POST['deal_summary'] ) ) : '',
        );

        $payload = array(
            'link' => $norm_url,
            'image_url' => $image_url,
            'card_meta' => $card_meta,
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
        $data['pricing'] = $payload['pricing'];
        $data['image_url'] = ! empty( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : $payload['image_url'];
        $data['card_meta'] = $payload['card_meta'];
        if ( ! empty( $payload['card_meta']['cta_text'] ) ) {
            $data['cta_text'] = $payload['card_meta']['cta_text'];
        }
        if ( ! empty( $payload['card_meta']['store_name'] ) ) {
            $data['store']      = $payload['card_meta']['store_name'];
            $data['store_name'] = $payload['card_meta']['store_name'];
        }
        if ( ! empty( $payload['card_meta']['deal_type'] ) ) {
            $data['deal_type'] = $payload['card_meta']['deal_type'];
        }
        if ( ! empty( $payload['card_meta']['summary'] ) ) {
            $data['tagline'] = $payload['card_meta']['summary'];
        }
        if ( $norm_url && strpos( $content, $norm_url ) === false ) {
            $content .= $this->build_product_card_html( $data, $norm_url );
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
        if ( ! empty( $data['image_url'] ) ) {
            update_post_meta( $post_id, '_trtai_deal_image_url', esc_url_raw( $data['image_url'] ) );
        }
        if ( ! empty( array_filter( $payload['card_meta'] ) ) ) {
            update_post_meta( $post_id, '_trtai_deal_card_meta', wp_json_encode( $payload['card_meta'] ) );
        }
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
        $tag  = isset( $this->settings_data['amazon_tag'] ) ? $this->settings_data['amazon_tag'] : '';

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
     * Build the product card HTML snippet.
     *
     * @param array  $data     Gemini response data.
     * @param string $norm_url Normalized affiliate URL.
     * @return string
     */
    protected function build_product_card_html( $data, $norm_url ) {
        $card_meta = isset( $data['card_meta'] ) && is_array( $data['card_meta'] ) ? $data['card_meta'] : array();

        $title    = ! empty( $data['title'] ) ? sanitize_text_field( $data['title'] ) : __( 'Featured deal', 'trtai' );
        $tagline  = ! empty( $card_meta['summary'] ) ? wp_kses_post( $card_meta['summary'] ) : ( ! empty( $data['tagline'] ) ? wp_kses_post( $data['tagline'] ) : '' );
        $excerpt  = ! empty( $data['excerpt'] ) ? wp_kses_post( $data['excerpt'] ) : '';
        $summary  = $tagline ? $tagline : $excerpt;
        $image    = ! empty( $data['image_url'] ) ? esc_url( $data['image_url'] ) : '';
        $cta_text = ! empty( $card_meta['cta_text'] ) ? sanitize_text_field( $card_meta['cta_text'] ) : ( ! empty( $data['cta_text'] ) ? sanitize_text_field( $data['cta_text'] ) : __( 'Get this deal', 'trtai' ) );

        $pricing        = isset( $data['pricing'] ) && is_array( $data['pricing'] ) ? $data['pricing'] : array();
        $current_price  = ! empty( $pricing['current_price'] ) ? sanitize_text_field( $pricing['current_price'] ) : '';
        $original_price = ! empty( $pricing['original_price'] ) ? sanitize_text_field( $pricing['original_price'] ) : '';
        $currency       = ! empty( $pricing['currency'] ) ? sanitize_text_field( $pricing['currency'] ) : '';
        $coupon         = ! empty( $pricing['coupon'] ) ? sanitize_text_field( $pricing['coupon'] ) : '';
        $expires        = ! empty( $pricing['expires'] ) ? sanitize_text_field( $pricing['expires'] ) : '';

        $formatted_current = trim( $currency . ' ' . $current_price );
        $formatted_regular = trim( $currency . ' ' . $original_price );

        $host = wp_parse_url( $norm_url, PHP_URL_HOST );
        if ( $host ) {
            $host = preg_replace( '/^www\./', '', strtolower( $host ) );
        }

        $store_name = ! empty( $card_meta['store_name'] ) ? sanitize_text_field( $card_meta['store_name'] ) : ( ! empty( $data['store'] ) ? sanitize_text_field( $data['store'] ) : '' );
        if ( empty( $store_name ) && ! empty( $data['store_name'] ) ) {
            $store_name = sanitize_text_field( $data['store_name'] );
        }
        if ( empty( $store_name ) && $host ) {
            $store_name = ucfirst( $host );
        }
        if ( empty( $store_name ) ) {
            $store_name = __( 'Online store', 'trtai' );
        }

        $deal_type = ! empty( $card_meta['deal_type'] ) ? sanitize_text_field( $card_meta['deal_type'] ) : ( ! empty( $data['deal_type'] ) ? sanitize_text_field( $data['deal_type'] ) : '' );
        if ( empty( $deal_type ) && isset( $data['style'] ) && is_array( $data['style'] ) && ! empty( $data['style']['post_type'] ) ) {
            $deal_type = sanitize_text_field( $data['style']['post_type'] );
        }
        if ( empty( $deal_type ) ) {
            $deal_type = __( 'Limited-time deal', 'trtai' );
        }

        $current_numeric  = $current_price ? floatval( preg_replace( '/[^0-9\.]/', '', $current_price ) ) : 0;
        $original_numeric = $original_price ? floatval( preg_replace( '/[^0-9\.]/', '', $original_price ) ) : 0;
        $discount_percent = 0;
        if ( $current_numeric > 0 && $original_numeric > 0 && $original_numeric > $current_numeric ) {
            $discount_percent = round( ( ( $original_numeric - $current_numeric ) / $original_numeric ) * 100 );
        }

        $discount_label = $discount_percent ? sprintf( __( '%s%% OFF', 'trtai' ), $discount_percent ) : ( $coupon ? __( 'Stack coupon', 'trtai' ) : __( 'Deal price', 'trtai' ) );

        $savings_amount_text = '';
        if ( $current_numeric > 0 && $original_numeric > $current_numeric ) {
            $savings_amount = $original_numeric - $current_numeric;
            $savings_amount_text = $currency ? trim( $currency . ' ' . number_format_i18n( $savings_amount, 2 ) ) : number_format_i18n( $savings_amount, 2 );
        }

        static $style_printed = false;
        $style_block          = '';

        if ( ! $style_printed ) {
            $style_block    = $this->get_deal_card_style_block();
            $style_printed  = true;
        }

        ob_start();
        ?>
        <?php echo $style_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <article class="trt-deal-card" style="background: #111827; border: 1px solid #1f2937; border-radius: 18px; padding: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.35); color: #f9fafb; display: flex; flex-direction: column; gap: 12px; font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
            <header class="trt-deal-header" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; width: 100%; color: #9ca3af; gap: 10px;">
                <span class="trt-store-badge" style="background: rgba(255,255,255,0.06); border: 1px solid #1f2937; padding: 6px 10px; border-radius: 10px; font-weight: bold; color: #f9fafb;">
                    <?php echo esc_html( $store_name ); ?>
                </span>
                <span class="trt-deal-type" style="color: #4fb7a0; font-weight: bold; margin-left: auto!important; display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; text-align: right; white-space: nowrap;">
                    <?php echo esc_html( $deal_type ); ?>
                </span>
            </header>

            <div class="trt-deal-main" style="display: flex; flex-direction: column; gap: 14px; align-items: stretch;">
                <div class="trt-deal-image-wrapper" style="position: relative; overflow: hidden; border-radius: 14px; background: #0b1017; border: 1px solid #1f2937; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; padding: 10px; text-align: center;">
                    <?php if ( $image ) : ?>
                        <img class="trt-deal-image" style="max-width: 100%; max-height: 100%; object-fit: contain; object-position: center; width: auto; height: auto; display: block; margin: auto;" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
                    <?php endif; ?>
                    <?php if ( $discount_label ) : ?>
                        <span class="trt-discount-badge" style="position: absolute; top: 10px; left: 10px; border-radius: 10px; box-shadow: 0 10px 20px rgba(79,183,160,0.4); background: #4fb7a0; color: #031418; font-weight: 800; padding: 6px 10px;">
                            <?php echo esc_html( $discount_label ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="trt-deal-content" style="display: flex; flex-direction: column; gap: 10px;">
                    <h2 class="trt-deal-title" style="font-size: 1.5rem; margin: 0; line-height: 1.2; color: #f9fafb;">
                        <?php echo esc_html( $title ); ?>
                    </h2>
                    <?php if ( $summary ) : ?>
                        <p class="trt-deal-tagline" style="color: #9ca3af; margin: 0;">
                            <?php echo wp_kses_post( $summary ); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ( $formatted_current || $formatted_regular || $savings_amount_text ) : ?>
                        <div class="trt-price-row" style="display: flex; align-items: baseline; gap: 10px;">
                            <?php if ( $formatted_current ) : ?>
                                <span class="trt-price-current" style="color: #4fb7a0; font-size: 1.8rem; font-weight: 900;">
                                    <?php echo esc_html( $formatted_current ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $formatted_regular ) : ?>
                                <span class="trt-price-original" style="color: #9ca3af; text-decoration: line-through;">
                                    <?php echo esc_html( $formatted_regular ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $savings_amount_text ) : ?>
                                <span class="trt-price-savings" style="color: #d4f5ed; font-weight: bold;">
                                    <?php printf( esc_html__( 'You save %s', 'trtai' ), esc_html( $savings_amount_text ) ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $coupon || $expires ) : ?>
                        <div class="trt-meta-row" style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if ( $coupon ) : ?>
                                <span class="trt-meta-chip" style="background: rgba(255,255,255,0.04); color: #9ca3af; border: 1px solid #1f2937; padding: 6px 10px; border-radius: 999px; font-size: 0.9rem;">
                                    <?php printf( esc_html__( 'Coupon: %s', 'trtai' ), esc_html( $coupon ) ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $expires ) : ?>
                                <span class="trt-meta-chip" style="background: rgba(255,255,255,0.04); color: #9ca3af; border: 1px solid #1f2937; padding: 6px 10px; border-radius: 999px; font-size: 0.9rem;">
                                    <?php printf( esc_html__( 'Expires %s', 'trtai' ), esc_html( $expires ) ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $coupon ) : ?>
                        <div class="trt-promo-card" style="background: #0b1017; border: 1px dashed #d4f5ed; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; gap: 1rem;">
                            <div class="trt-promo-left" style="display: flex; flex-direction: column; gap: 4px;">
                                <span class="trt-promo-label" style="font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.4px; font-size: 0.85rem;">
                                    <?php esc_html_e( 'Promo code', 'trtai' ); ?>
                                </span>
                                <span class="trt-promo-code" style="font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; background: #020617; border: 1px solid #4fb7a0; padding: 6px 10px; border-radius: 10px; letter-spacing: 0.6px; font-weight: 800;">
                                    <?php echo esc_html( $coupon ); ?>
                                </span>
                                <?php if ( $expires ) : ?>
                                    <span class="trt-promo-expiry" style="color: #9ca3af; font-size: 0.9rem;">
                                        <?php printf( esc_html__( 'Expires %s', 'trtai' ), esc_html( $expires ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="trt-cta-row" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-start; align-items: center; text-align: center;">
                        <a class="trt-cta-button" style="display: flex!important; align-items: center!important; justify-content: center!important; text-align: center!important; line-height: 1!important; min-width: 220px; max-width: 100%; border-radius: 10px; padding: 12px 16px; font-weight: 800; text-decoration: none!important; cursor: pointer; border: 1px solid transparent; transition: transform 0.15s ease,box-shadow 0.15s ease; background: #4fb7a0; color: #031418; box-shadow: 0 12px 32px rgba(79,183,160,0.35); margin: 0 auto;" href="<?php echo esc_url( $norm_url ); ?>" target="_blank" rel="nofollow sponsored noopener noreferrer">
                            <?php echo esc_html( $cta_text ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <footer class="trt-deal-footer" style="border-top: 1px solid #1f2937; padding-top: 10px; color: #9ca3af; font-size: 0.95rem;">
                <span>
                    <?php esc_html_e( 'Prices and availability can change quickly—double-check at checkout.', 'trtai' ); ?>
                </span>
            </footer>
        </article>
        <?php

        return trim( ob_get_clean() );
    }

    /**
     * Return the inline style block for deal cards.
     *
     * @return string
     */
    protected function get_deal_card_style_block() {
        $style = <<<'STYLE'
<style>
  :root {
    --trt-bg: #05070a;
    --trt-bg-elevated: #0b1017;
    --trt-card-bg: #111827;
    --trt-card-border: #1f2937;
    --trt-accent: #4fb7a0;
    --trt-accent-soft: #d4f5ed;
    --trt-text-main: #f9fafb;
    --trt-text-muted: #9ca3af;
    --trt-badge-bg: #111827;
    --trt-danger: #f97373;
    --trt-warning: #fbbf24;
  }
  body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
  .trt-app-shell { background: radial-gradient(circle at top, #111827 0, #020617 55%, #000 100%); color: var(--trt-text-main); }
  .trt-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border: 1px solid var(--trt-card-border); border-radius: 14px; background: var(--trt-card-bg); box-shadow: 0 20px 40px rgba(0,0,0,0.25); margin-bottom: 18px; }
  .trt-logo { font-weight: 800; letter-spacing: 0.2px; font-size: 1.1rem; }
  .trt-header-actions { display: flex; align-items: center; gap: 10px; }
  .trt-pill { background: rgba(255,255,255,0.06); color: var(--trt-text-muted); border: 1px solid var(--trt-card-border); border-radius: 999px; padding: 6px 12px; font-size: 0.85rem; }
  .trt-new-btn { background: var(--trt-accent); color: #031418; border: none; border-radius: 12px; padding: 10px 14px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 30px rgba(79,183,160,0.3); }
  .trt-new-btn:hover { transform: translateY(-1px); box-shadow: 0 16px 36px rgba(79,183,160,0.35); }
  .trt-panel { background: var(--trt-card-bg); border: 1px solid var(--trt-card-border); border-radius: 16px; padding: 14px; box-shadow: 0 12px 32px rgba(0,0,0,0.25); }
  .trt-panel h3 { margin-top: 6px; }
  .trt-section { border: 1px solid var(--trt-card-border); border-radius: 14px; padding: 12px; margin-bottom: 12px; background: rgba(255,255,255,0.02); }
  .trt-section h4 { margin-bottom: 6px; color: var(--trt-text-muted); text-transform: uppercase; letter-spacing: 0.2px; font-size: 0.9rem; }
  .trt-mini-card { border: 1px solid var(--trt-card-border); border-radius: 12px; padding: 10px; margin-bottom: 10px; background: rgba(255,255,255,0.02); }
  .trt-mini-header { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
  .trt-mini-title { font-weight: 700; }
  .trt-badge { background: var(--trt-badge-bg); border: 1px solid var(--trt-card-border); border-radius: 999px; padding: 4px 8px; font-size: 0.8rem; color: var(--trt-text-muted); }
  .trt-discount-badge { background: var(--trt-accent); color: #031418; font-weight: 800; padding: 4px 8px; border-radius: 10px; font-size: 0.9rem; }
  .trt-status { font-size: 0.8rem; color: var(--trt-text-muted); }
  .trt-mini-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
  .trt-ghost-btn { border: 1px solid var(--trt-card-border); background: rgba(255,255,255,0.04); color: var(--trt-text-main); border-radius: 8px; padding: 6px 10px; font-size: 0.9rem; cursor: pointer; }
  .trt-ghost-btn:hover { border-color: var(--trt-accent); color: var(--trt-accent); }
  .trt-deal-card { background: var(--trt-card-bg); border: 1px solid var(--trt-card-border); border-radius: 18px; padding: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.35); color: var(--trt-text-main); display: flex; flex-direction: column; gap: 12px; }
  .trt-deal-header { display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; color: var(--trt-text-muted); gap: 10px; width: 100%; }
  .trt-store-badge { background: rgba(255,255,255,0.06); border: 1px solid var(--trt-card-border); padding: 6px 10px; border-radius: 10px; font-weight: 700; color: var(--trt-text-main); }
  .trt-deal-type { color: var(--trt-accent); font-weight: 700; margin-left: auto !important; display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; text-align: right; white-space: nowrap; }
  .trt-deal-main { display: flex; flex-direction: column; gap: 14px; align-items: stretch; }
  .trt-deal-image-wrapper { position: relative; overflow: hidden; border-radius: 14px; background: #0b1017; border: 1px solid var(--trt-card-border); aspect-ratio: 16 / 9; display: flex; align-items: center; justify-content: center; padding: 10px; text-align: center; }
  .trt-deal-image { max-width: 100%; max-height: 100%; object-fit: contain; object-position: center; width: auto; height: auto; display: block; margin: auto; }
  .trt-deal-image-wrapper .trt-discount-badge { position: absolute; top: 10px; left: 10px; border-radius: 10px; box-shadow: 0 10px 20px rgba(79,183,160,0.4); }
  .trt-deal-content { display: flex; flex-direction: column; gap: 10px; }
  .trt-deal-title { font-size: 1.5rem; margin: 0; line-height: 1.2; }
  .trt-deal-tagline { color: var(--trt-text-muted); margin: 0; }
  .trt-price-row { display: flex; align-items: baseline; gap: 10px; }
  .trt-price-current { color: var(--trt-accent); font-size: 1.8rem; font-weight: 900; }
  .trt-price-original { color: var(--trt-text-muted); text-decoration: line-through; }
  .trt-price-savings { color: var(--trt-accent-soft); font-weight: 700; }
  .trt-meta-row { display: flex; gap: 8px; flex-wrap: wrap; }
  .trt-meta-chip { background: rgba(255,255,255,0.04); color: var(--trt-text-muted); border: 1px solid var(--trt-card-border); padding: 6px 10px; border-radius: 999px; font-size: 0.9rem; }
  .trt-promo-card { background: var(--trt-bg-elevated); border: 1px dashed var(--trt-accent-soft); border-radius: 12px; display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; gap: 1rem; }
  .trt-promo-left { display: flex; flex-direction: column; gap: 4px; }
  .trt-promo-label { font-weight: 700; color: var(--trt-text-muted); text-transform: uppercase; letter-spacing: 0.4px; font-size: 0.85rem; }
  .trt-promo-code { font-family: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; background: #020617; border: 1px solid var(--trt-accent); padding: 6px 10px; border-radius: 10px; letter-spacing: 0.6px; font-weight: 800; }
  .trt-promo-expiry { color: var(--trt-text-muted); font-size: 0.9rem; }
  .trt-promo-right { display: flex; align-items: center; gap: 10px; }
  .trt-promo-copy-button { background: var(--trt-accent); color: #031418; border: none; padding: 8px 12px; font-weight: 800; border-radius: 8px; cursor: pointer; }
  .trt-promo-copy-button:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(79,183,160,0.3); }
  .trt-promo-copied-toast { color: var(--trt-accent-soft); font-size: 0.85rem; }
  .trt-cta-row { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-start; align-items: center; text-align: center; }
  .trt-cta-button { display: flex !important; align-items: center !important; justify-content: center !important; text-align: center !important; line-height: 1 !important; min-width: 220px; max-width: 100%; border-radius: 10px; padding: 12px 16px; font-weight: 800; text-decoration: none !important; cursor: pointer; border: 1px solid transparent; transition: transform 0.15s ease, box-shadow 0.15s ease; background: var(--trt-accent); color: #031418; box-shadow: 0 12px 32px rgba(79,183,160,0.35); margin: 0 auto; }
  .trt-cta-button:hover { transform: translateY(-1px); box-shadow: 0 14px 36px rgba(0,0,0,0.25); }
  .trt-deal-footer { border-top: 1px solid var(--trt-card-border); padding-top: 10px; color: var(--trt-text-muted); font-size: 0.95rem; }
  @media (max-width: 1024px) {
    .trt-deal-main { flex-direction: column; }
  }
</style>
STYLE;

        return $style;
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
     * Enqueue front-end styles for deal cards on generated posts.
     */
    public function enqueue_deal_styles() {
        if ( ! is_singular( 'post' ) ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        $deal_url = get_post_meta( $post_id, '_trtai_deal_url', true );
        if ( empty( $deal_url ) ) {
            return;
        }

        wp_enqueue_style( 'trtai-deal-card', TRTAI_PLUGIN_URL . 'assets/css/deal-card.css', array(), TRTAI_PLUGIN_VERSION );
    }

    /**
     * Append a generated deal card to the content if missing.
     *
     * @param string $content Post content.
     * @return string
     */
    public function maybe_append_deal_card( $content ) {
        if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $content;
        }

        $deal_url = get_post_meta( $post_id, '_trtai_deal_url', true );
        if ( empty( $deal_url ) ) {
            return $content;
        }

        if ( false !== strpos( $content, 'trtai-deal-card' ) || false !== strpos( $content, 'trt-deal-card' ) || false !== strpos( $content, $deal_url ) ) {
            return $content;
        }

        $payload = get_post_meta( $post_id, '_trtai_generated_deal_payload', true );
        $data    = json_decode( $payload, true );

        if ( ! is_array( $data ) || empty( $data ) ) {
            return $content;
        }

        if ( empty( $data['excerpt'] ) ) {
            $data['excerpt'] = get_the_excerpt( $post_id );
        }

        $card_html = $this->build_product_card_html( $data, $deal_url );

        if ( ! $card_html ) {
            return $content;
        }

        return $content . "\n" . $card_html;
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
