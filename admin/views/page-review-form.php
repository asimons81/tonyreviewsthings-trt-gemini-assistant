<?php
/**
 * Review generation form.
 */
?>
<div class="wrap trtai-wrap">
    <h1><?php esc_html_e( 'AI-Powered Product Review', 'trtai' ); ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="trtai-form">
        <input type="hidden" name="action" value="trtai_generate_review" />
        <?php wp_nonce_field( 'trtai_generate_review' ); ?>

        <h2><?php esc_html_e( 'Product basics', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="product_name"><?php esc_html_e( 'Product name', 'trtai' ); ?></label></th><td><input type="text" name="product_name" id="product_name" class="regular-text" required></td></tr>
            <tr><th><label for="brand"><?php esc_html_e( 'Brand', 'trtai' ); ?></label></th><td><input type="text" name="brand" id="brand" class="regular-text"></td></tr>
            <tr><th><label for="product_type"><?php esc_html_e( 'Product type/category', 'trtai' ); ?></label></th><td><input type="text" name="product_type" id="product_type" class="regular-text"></td></tr>
            <tr><th><label for="urls"><?php esc_html_e( 'URL(s)', 'trtai' ); ?></label></th><td><textarea name="urls" id="urls" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'One per line', 'trtai' ); ?>"></textarea></td></tr>
            <tr><th><label for="price"><?php esc_html_e( 'Price', 'trtai' ); ?></label></th><td><input type="text" name="price" id="price" class="regular-text"> <input type="text" name="currency" placeholder="<?php esc_attr_e( 'Currency', 'trtai' ); ?>" class="small-text"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Your experience', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="liked"><?php esc_html_e( 'What you liked', 'trtai' ); ?></label></th><td><textarea name="liked" id="liked" rows="3" class="large-text"></textarea></td></tr>
            <tr><th><label for="disliked"><?php esc_html_e( 'What you disliked', 'trtai' ); ?></label></th><td><textarea name="disliked" id="disliked" rows="3" class="large-text"></textarea></td></tr>
            <tr><th><label for="surprise"><?php esc_html_e( 'Biggest surprise', 'trtai' ); ?></label></th><td><textarea name="surprise" id="surprise" rows="2" class="large-text"></textarea></td></tr>
            <tr><th><label for="dealbreaker"><?php esc_html_e( 'Any deal-breakers?', 'trtai' ); ?></label></th><td><textarea name="dealbreaker" id="dealbreaker" rows="2" class="large-text"></textarea></td></tr>
        </table>

        <h2><?php esc_html_e( 'Context', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="usage_duration"><?php esc_html_e( 'How long you used it', 'trtai' ); ?></label></th><td><input type="text" name="usage_duration" id="usage_duration" class="regular-text"></td></tr>
            <tr><th><label for="competitors"><?php esc_html_e( 'Competing products to compare', 'trtai' ); ?></label></th><td><textarea name="competitors" id="competitors" rows="2" class="large-text"></textarea></td></tr>
        </table>

        <h2><?php esc_html_e( 'SEO', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="target_keyphrase"><?php esc_html_e( 'Target keyphrase', 'trtai' ); ?></label></th><td><input type="text" name="target_keyphrase" id="target_keyphrase" class="regular-text"></td></tr>
            <tr><th><label for="secondary_keyphrases"><?php esc_html_e( 'Secondary keyphrases', 'trtai' ); ?></label></th><td><input type="text" name="secondary_keyphrases" id="secondary_keyphrases" class="regular-text" placeholder="<?php esc_attr_e( 'Comma-separated', 'trtai' ); ?>"></td></tr>
            <tr><th><label for="desired_word_count"><?php esc_html_e( 'Desired word count', 'trtai' ); ?></label></th><td><input type="text" name="desired_word_count" id="desired_word_count" class="regular-text" placeholder="1500-2500"></td></tr>
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

        <?php submit_button( __( 'Generate Review Draft', 'trtai' ) ); ?>
    </form>
</div>
