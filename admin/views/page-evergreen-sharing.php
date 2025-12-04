<?php
/**
 * Evergreen sharing page.
 */
?>
<div class="wrap trtai-wrap">
    <h1><?php esc_html_e( 'Evergreen Sharing', 'trtai' ); ?></h1>

    <form method="get" class="trtai-filters">
        <input type="hidden" name="page" value="trtai-evergreen-sharing" />
        <label><?php esc_html_e( 'Category', 'trtai' ); ?>
            <?php wp_dropdown_categories( array(
                'show_option_all' => __( 'All categories', 'trtai' ),
                'hide_empty'      => false,
                'name'            => 'filter_category',
                'selected'        => $category,
            ) ); ?>
        </label>
        <label><?php esc_html_e( 'From', 'trtai' ); ?> <input type="date" name="filter_from" value="<?php echo esc_attr( $from ); ?>" /></label>
        <label><?php esc_html_e( 'To', 'trtai' ); ?> <input type="date" name="filter_to" value="<?php echo esc_attr( $to ); ?>" /></label>
        <?php submit_button( __( 'Filter', 'trtai' ), 'secondary', '', false ); ?>
    </form>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="trtai_evergreen_share" />
        <?php wp_nonce_field( 'trtai_evergreen_share_action' ); ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column"><input type="checkbox" id="trtai-check-all"></td>
                    <th><?php esc_html_e( 'Title', 'trtai' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'trtai' ); ?></th>
                    <th><?php esc_html_e( 'Categories', 'trtai' ); ?></th>
                    <th><?php esc_html_e( 'Recent share log', 'trtai' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $posts ) ) : ?>
                    <?php foreach ( $posts as $post ) : ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $post->ID ); ?>" /></th>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
                            <td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
                            <td><?php echo esc_html( get_the_category_list( ', ', '', $post->ID ) ); ?></td>
                            <td>
                                <?php
                                $log = get_post_meta( $post->ID, '_trtai_social_log', true );
                                $log = $log ? json_decode( $log, true ) : array();
                                if ( ! empty( $log ) ) {
                                    $last = end( $log );
                                    echo esc_html( sprintf( '%s on %s (%s)', ucfirst( $last['network'] ), $last['time'], $last['status'] ) );
                                } else {
                                    esc_html_e( 'No shares yet.', 'trtai' );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No posts match the filters.', 'trtai' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <label><input type="checkbox" name="networks[]" value="threads"> <?php esc_html_e( 'Threads', 'trtai' ); ?></label>
            <label style="margin-left:1em;"><input type="checkbox" name="networks[]" value="facebook"> <?php esc_html_e( 'Facebook', 'trtai' ); ?></label>
        </p>
        <?php submit_button( __( 'Share now', 'trtai' ) ); ?>
    </form>
</div>
<script>
    (function(){
        const checkAll = document.getElementById('trtai-check-all');
        if (checkAll) {
            checkAll.addEventListener('change', function(){
                document.querySelectorAll('input[name="post_ids[]"]').forEach(function(el){
                    el.checked = checkAll.checked;
                });
            });
        }
    })();
</script>
