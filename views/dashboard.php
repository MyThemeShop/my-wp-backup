<?php if ( ! defined( 'ABSPATH' ) ) { die; } ?>
<div class="wrap page-about" id="my-wp-backup">
	<h1><?php echo esc_html( sprintf( __( 'Welcome to My WP Backup %s', 'my-wp-backup' ), \MyWPBackup\MyWPBackup::$info['version'] ) ); ?></h1>

	<div style="margin-top: 20px;">
		<a class="button button-primary" href="<?php echo esc_attr( \MyWPBackup\Admin\Admin::get_page_url( 'jobs' ) ); ?>"><?php esc_html_e( 'Perform a backup', 'my-wp-backup' ); ?></a>
		<a class="button" href="<?php echo esc_attr( \MyWPBackup\Admin\Admin::get_page_url( 'backup' ) ); ?>"><?php esc_html_e( 'Restore from a backup', 'my-wp-backup' ); ?></a>
		<?php $last = \MyWPBackup\Admin\Backup::get_instance()->last(); ?>
		<?php if ( $last ) : ?>
			<p><em><?php esc_html_e( 'Last backup was created', 'my-wp-backup' ); ?></em>: <?php echo esc_html( sprintf( __( '%s ago.', 'my-wp-backup' ), human_time_diff( $last['timestamp'], time() ) ) ); ?></p>
		<?php else : ?>
			<p><em><?php esc_html_e( 'No backup yet. Create one now!', 'my-wp-backup' ); ?></em></p>
		<?php endif; ?>
	</div>

	<div class="row">

        <div class="col col-md-4">
            <div class="postbox">
                <h3 class="hndle"><span><?php esc_html_e( 'Getting Started', 'my-wp-backup' ); ?></span></h3>
                <div class="inside">
                    <p><?php esc_html_e( 'New to WP Backup?', 'my-wp-backup' ); ?> <br><br><a class="button" href="<?php echo esc_attr( \MyWPBackup\Admin\Admin::get_page_url( 'jobs', array( 'tour' => 'yes' ) ) ); ?>"><?php esc_html_e( 'Take Tour', 'my-wp-backup' ); ?></a></p>
                </div>
            </div>
        </div>

	</div>

</div>
