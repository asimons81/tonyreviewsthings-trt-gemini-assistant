<?php
/**
 * Main landing page for TRT Gemini Assistant.
 */
?>
<div class="wrap trtai-wrap">
    <h1><?php esc_html_e( 'TRT Gemini Assistant', 'trtai' ); ?></h1>
    <p><?php esc_html_e( 'Generate AI-assisted reviews, guides, and deals with Gemini 2.5 Flash, then share them across social.', 'trtai' ); ?></p>
    <ul class="trtai-card-grid">
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=trtai-new-review' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create a Review', 'trtai' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=trtai-new-guide' ) ); ?>" class="button"><?php esc_html_e( 'Create a Guide', 'trtai' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=trtai-new-deal' ) ); ?>" class="button"><?php esc_html_e( 'Create a Deal Alert', 'trtai' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=trtai-evergreen-sharing' ) ); ?>" class="button"><?php esc_html_e( 'Evergreen Sharing', 'trtai' ); ?></a></li>
    </ul>
    <p><?php esc_html_e( 'Configure Gemini and social settings under Settings.', 'trtai' ); ?></p>
</div>
