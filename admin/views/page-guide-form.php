<?php
/**
 * Guide generation form.
 */
?>
<div class="wrap trtai-wrap">
    <h1><?php esc_html_e( 'AI-Powered Guide / How-To', 'trtai' ); ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="trtai-form">
        <input type="hidden" name="action" value="trtai_generate_guide" />
        <?php wp_nonce_field( 'trtai_generate_guide' ); ?>

        <h2><?php esc_html_e( 'Topic', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="topic_title"><?php esc_html_e( 'Topic/title', 'trtai' ); ?></label></th><td><input type="text" name="topic_title" id="topic_title" class="regular-text" required></td></tr>
            <tr><th><label for="topic_type"><?php esc_html_e( 'Type', 'trtai' ); ?></label></th><td>
                <select name="topic_type" id="topic_type">
                    <option value="How-To"><?php esc_html_e( 'How-To', 'trtai' ); ?></option>
                    <option value="Tips & Tricks"><?php esc_html_e( 'Tips & Tricks', 'trtai' ); ?></option>
                    <option value="Explainer"><?php esc_html_e( 'Explainer', 'trtai' ); ?></option>
                    <option value="Troubleshooting"><?php esc_html_e( 'Troubleshooting', 'trtai' ); ?></option>
                </select>
            </td></tr>
            <tr><th><label for="audience_level"><?php esc_html_e( 'Audience level', 'trtai' ); ?></label></th><td>
                <select name="audience_level" id="audience_level">
                    <option value="Beginner"><?php esc_html_e( 'Beginner', 'trtai' ); ?></option>
                    <option value="Intermediate"><?php esc_html_e( 'Intermediate', 'trtai' ); ?></option>
                    <option value="Advanced"><?php esc_html_e( 'Advanced', 'trtai' ); ?></option>
                </select>
            </td></tr>
        </table>

        <h2><?php esc_html_e( 'Structure preferences', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Desired sections', 'trtai' ); ?></th><td>
                <label><input type="checkbox" name="sections[]" value="Intro"> <?php esc_html_e( 'Intro', 'trtai' ); ?></label><br>
                <label><input type="checkbox" name="sections[]" value="Step-by-step"> <?php esc_html_e( 'Step-by-step', 'trtai' ); ?></label><br>
                <label><input type="checkbox" name="sections[]" value="FAQs"> <?php esc_html_e( 'FAQs', 'trtai' ); ?></label><br>
                <label><input type="checkbox" name="sections[]" value="Troubleshooting"> <?php esc_html_e( 'Troubleshooting', 'trtai' ); ?></label><br>
                <label><input type="checkbox" name="sections[]" value="Pros/Cons"> <?php esc_html_e( 'Pros/Cons', 'trtai' ); ?></label>
            </td></tr>
            <tr><th><label for="word_count"><?php esc_html_e( 'Desired word count', 'trtai' ); ?></label></th><td><input type="text" name="word_count" id="word_count" class="regular-text"></td></tr>
        </table>

        <h2><?php esc_html_e( 'SEO', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="guide_target_keyphrase"><?php esc_html_e( 'Target keyphrase', 'trtai' ); ?></label></th><td><input type="text" name="guide_target_keyphrase" id="guide_target_keyphrase" class="regular-text"></td></tr>
            <tr><th><label for="guide_secondary_keyphrases"><?php esc_html_e( 'Secondary keyphrases', 'trtai' ); ?></label></th><td><input type="text" name="guide_secondary_keyphrases" id="guide_secondary_keyphrases" class="regular-text" placeholder="<?php esc_attr_e( 'Comma-separated', 'trtai' ); ?>"></td></tr>
        </table>

        <h2><?php esc_html_e( 'Internal linking', 'trtai' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="internal_links"><?php esc_html_e( 'URLs or IDs to favor', 'trtai' ); ?></label></th><td><textarea name="internal_links" id="internal_links" rows="3" class="large-text"></textarea></td></tr>
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

        <?php submit_button( __( 'Generate Guide Draft', 'trtai' ) ); ?>
    </form>
</div>
