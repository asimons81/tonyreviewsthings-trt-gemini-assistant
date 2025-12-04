<?php
/**
 * Settings page view.
 */
?>
<div class="wrap trtai-wrap">
    <h1><?php esc_html_e( 'TRT Gemini Settings', 'trtai' ); ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
        <?php settings_fields( 'trtai_settings_group' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="trtai-gemini-api-key"><?php esc_html_e( 'Gemini API Key', 'trtai' ); ?></label></th>
                <td><input type="password" name="<?php echo esc_attr( Trtai_Settings::OPTION_KEY ); ?>[gemini_api_key]" id="trtai-gemini-api-key" value="<?php echo esc_attr( $settings['gemini_api_key'] ); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="trtai-gemini-model"><?php esc_html_e( 'Gemini Model', 'trtai' ); ?></label></th>
                <td><input type="text" name="<?php echo esc_attr( Trtai_Settings::OPTION_KEY ); ?>[gemini_model]" id="trtai-gemini-model" value="<?php echo esc_attr( $settings['gemini_model'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="trtai-amazon-tag"><?php esc_html_e( 'Amazon Affiliate Tag', 'trtai' ); ?></label></th>
                <td><input type="text" name="<?php echo esc_attr( Trtai_Settings::OPTION_KEY ); ?>[amazon_tag]" id="trtai-amazon-tag" value="<?php echo esc_attr( $settings['amazon_tag'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="trtai-threads-token"><?php esc_html_e( 'Threads Token', 'trtai' ); ?></label></th>
                <td><input type="text" name="<?php echo esc_attr( Trtai_Settings::OPTION_KEY ); ?>[threads_token]" id="trtai-threads-token" value="<?php echo esc_attr( $settings['threads_token'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="trtai-facebook-token"><?php esc_html_e( 'Facebook Token', 'trtai' ); ?></label></th>
                <td><input type="text" name="<?php echo esc_attr( Trtai_Settings::OPTION_KEY ); ?>[facebook_token]" id="trtai-facebook-token" value="<?php echo esc_attr( $settings['facebook_token'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Auto-share on Publish', 'trtai' ); ?></th>
                <td><label><input type="checkbox" name="<?php echo esc_attr( Trtai_Settings::OPTION_KEY ); ?>[social_autoshare_enabled]" value="1" <?php checked( $settings['social_autoshare_enabled'], true ); ?> /> <?php esc_html_e( 'Automatically share posts when published.', 'trtai' ); ?></label></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
