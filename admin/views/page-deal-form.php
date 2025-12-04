<?php
/**
 * Deal generation form.
 */
?>
<div class="wrap trtai-wrap">
    <h1><?php esc_html_e( 'AI-Powered Deal Alert', 'trtai' ); ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="trtai-form">
        <input type="hidden" name="action" value="trtai_generate_deal" />
        <?php wp_nonce_field( 'trtai_generate_deal' ); ?>

        <h2><?php esc_html_e( 'Product link', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="deal_url"><?php esc_html_e( 'URL', 'trtai' ); ?></label></th><td><input type="url" name="deal_url" id="deal_url" class="regular-text" required></td></tr>
        </table>

        <h2><?php esc_html_e( 'Pricing context', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="current_price"><?php esc_html_e( 'Current price', 'trtai' ); ?></label></th><td><input type="text" name="current_price" id="current_price" class="regular-text"></td></tr>
            <tr><th><label for="original_price"><?php esc_html_e( 'Original price', 'trtai' ); ?></label></th><td><input type="text" name="original_price" id="original_price" class="regular-text"></td></tr>
            <tr><th><label for="currency"><?php esc_html_e( 'Currency', 'trtai' ); ?></label></th><td><input type="text" name="currency" id="currency" class="small-text"></td></tr>
            <tr><th><label for="coupon"><?php esc_html_e( 'Coupon code', 'trtai' ); ?></label></th><td><input type="text" name="coupon" id="coupon" class="regular-text"></td></tr>
            <tr><th><label for="expires"><?php esc_html_e( 'Deal expiration', 'trtai' ); ?></label></th><td><input type="text" name="expires" id="expires" class="regular-text" placeholder="<?php esc_attr_e( 'YYYY-MM-DD or soon', 'trtai' ); ?>"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Media', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="image_url"><?php esc_html_e( 'Image URL', 'trtai' ); ?></label></th><td><input type="url" name="image_url" id="image_url" class="regular-text" placeholder="<?php esc_attr_e( 'https://example.com/product.jpg', 'trtai' ); ?>"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Deal card (optional overrides)', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="cta_text"><?php esc_html_e( 'CTA text', 'trtai' ); ?></label></th><td><input type="text" name="cta_text" id="cta_text" class="regular-text" placeholder="<?php esc_attr_e( 'Get this deal', 'trtai' ); ?>"></td></tr>
            <tr><th><label for="store_name"><?php esc_html_e( 'Store/brand name', 'trtai' ); ?></label></th><td><input type="text" name="store_name" id="store_name" class="regular-text" placeholder="<?php esc_attr_e( 'Amazon', 'trtai' ); ?>"></td></tr>
            <tr><th><label for="deal_type"><?php esc_html_e( 'Deal type / badge label', 'trtai' ); ?></label></th><td><input type="text" name="deal_type" id="deal_type" class="regular-text" placeholder="<?php esc_attr_e( 'Lightning deal', 'trtai' ); ?>"></td></tr>
            <tr><th><label for="deal_summary"><?php esc_html_e( 'Summary / tagline', 'trtai' ); ?></label></th><td><textarea name="deal_summary" id="deal_summary" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'A quick hook for the deal card', 'trtai' ); ?>"></textarea></td></tr>
        </table>

        <h2><?php esc_html_e( 'Style', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="post_type"><?php esc_html_e( 'Post type', 'trtai' ); ?></label></th><td>
                <select name="post_type" id="post_type">
                    <option value="quick"><?php esc_html_e( 'Quick deal alert', 'trtai' ); ?></option>
                    <option value="mini-review"><?php esc_html_e( 'Mini review deal', 'trtai' ); ?></option>
                </select>
            </td></tr>
        </table>

        <h2><?php esc_html_e( 'SEO', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="deal_keyphrase"><?php esc_html_e( 'Custom keyphrase', 'trtai' ); ?></label></th><td><input type="text" name="deal_keyphrase" id="deal_keyphrase" class="regular-text"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Post settings', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="category"><?php esc_html_e( 'Category', 'trtai' ); ?></label></th><td>
                <?php
                wp_dropdown_categories(
                    array(
                        'show_option_none' => __( 'Select category', 'trtai' ),
                        'hide_empty'      => false,
                        'name'            => 'category',
                        'id'              => 'category',
                        'selected'        => $default_cat,
                    )
                );
                ?>
            </td></tr>
            <tr><th><label for="tags"><?php esc_html_e( 'Tags', 'trtai' ); ?></label></th><td><input type="text" name="tags" id="tags" class="regular-text" placeholder="<?php esc_attr_e( 'Comma-separated', 'trtai' ); ?>"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Preview', 'trtai' ); ?></h2>
        <p><?php esc_html_e( 'Generate a preview to approve the output before saving the draft.', 'trtai' ); ?></p>
        <div class="trtai-preview-actions">
            <button type="button" class="button button-secondary trtai-deal-preview-btn"><?php esc_html_e( 'Preview post output', 'trtai' ); ?></button>
            <span class="description"><?php esc_html_e( 'Preview uses your inputs and the AI output without saving a draft.', 'trtai' ); ?></span>
        </div>
        <div id="trtai-deal-preview" class="trtai-preview-box" aria-live="polite">
            <p class="description"><?php esc_html_e( 'Your preview will appear here.', 'trtai' ); ?></p>
        </div>

        <?php submit_button( __( 'Generate Deal Draft', 'trtai' ) ); ?>
    </form>
</div>
