<?php
use MyWPBackup\Admin\Admin;
use MyWPBackup\Admin\Table\Backup;

if ( ! defined( 'ABSPATH' ) ) { die; }
?>
<div class="page-backups wrap" id="my-wp-backup">

    <h2><?php esc_html_e( 'Backups', 'my-wp-backup' ); ?> <a href="<?php echo esc_attr( Admin::get_page_url( 'jobs' ) ); ?>" class="add-new-h2"><?php esc_html_e( 'Run a backup job', 'my-wp-backup' ); ?></a></h2>

	<?php settings_errors(); ?>

    <?php if ( isset( $_GET['uniqid'] ) && isset( $_GET['id'] ) && isset( $_GET['action'] ) ) : ?>

	    <?php $uniqid = sanitize_key( $_GET['uniqid'] ); //input var okay ?>
	    <?php $id = intval( $_GET['id'] ); //input var okay ?>
	    <?php $action = sanitize_key( $_GET['action'] ); //input var okay ?>

	    <?php if ( 'log' === $action ) : ?>

		    <label for="show-verbose"><input type="checkbox" value="yes" id="show-verbose"><?php esc_html_e( 'Verbose Output', 'my-wp-backup' ); ?></label>
		    <pre class="terminal"><code><?php
		    try {
			    $file = \MyWPBackup\Admin\Backup::get_instance()->get( $id, $uniqid )->get_log();

			    while ( ! $file->eof() ) {
				    $current = $file->current();
				    if ( ! empty( $current ) ) {
					    $line = json_decode( $current );
					    echo '<span class="' . esc_attr( $line->level ) . '">' . esc_html( $line->text ) . '<br></span>';
					    $file->next();
				    } else {
					    break;
				    }
			    }
		    } catch ( \Exception $e ) {
			    esc_html_e( 'Log file missing.', 'my-wp-backup' );
		    }
		    ?></code></pre>

	    <?php elseif ( 'list' === $action ) : ?>

		    <pre class="terminal"><code>Files:<br><?php
		    $files = \MyWPBackup\Admin\Backup::get_instance()->get( $id, $uniqid )->get_files();
		    if ( is_array( $files ) ) {
			    foreach ( $files as $file => $hash ) {
				    echo esc_html( $file ) . "\n";
			    }
		    } else {
			    esc_html_e( 'Unable to list files, hashes.txt missing/modified from backup directory.', 'my-wp-backup' );
		    }
		    ?></code></pre>

	    <?php elseif ( 'delete' === $action ) : ?>

		    <?php $backup = \MyWPBackup\Admin\Backup::get_instance()->get( $id, $uniqid ); ?>
		    <form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" accept-charset="utf-8">
			    <?php wp_nonce_field( 'my-wp-backup-delete-backup' ); ?>
			    <input type="hidden" name="backup_ident[]" value="<?php echo esc_attr( $id ); ?>_<?php echo esc_attr( $uniqid ); ?>">
			    <input name="action" value="MyWPBackup_delete_backup" type="hidden"/>
			    <p><?php esc_html_e( 'You are about to delete a backup created on:', 'my-wp-backup' ); ?></p>
			    <ul class="ul-disc">
				    <li><strong><?php echo esc_html( date( 'F j, Y, g:i a', $backup['timestamp'] ) ); ?></strong></li>
			    </ul>
			    <p><?php esc_html_e( 'Are you sure you want to do this?', 'my-wp-backup' ); ?></p>
			    <?php submit_button( 'Yes, Delete this backup', 'delete', 'submit', false ); ?>
			    <a class="button button-cancel" href="<?php echo esc_attr( Admin::get_page_url( 'backup' ) ); ?>"><?php esc_html_e( 'No, Return me to backup list', 'my-wp-backup' ); ?></a>
		    </form>

	    <?php elseif ( 'restore' === $action ) : ?>

		    <?php $backup = \MyWPBackup\Admin\Backup::get_instance()->get( $id, $uniqid ); ?>
		    <form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" accept-charset="utf-8">
			    <?php wp_nonce_field( 'my-wp-backup-restore-backup' ); ?>
			    <input type="hidden" name="job_id" value="<?php echo esc_attr( $id ); ?>">
			    <input type="hidden" name="backup_uniqid" value="<?php echo esc_attr( $uniqid ); ?>">
			    <input name="action" value="MyWPBackup_restore_backup" type="hidden"/>
			    <p><?php esc_html_e( 'You are about to delete a restore to a backup created on:', 'my-wp-backup' ); ?></p>
			    <ul class="ul-disc">
				    <li><strong><?php echo esc_html( date( 'F j, Y, g:i a', $backup['timestamp'] ) ); ?></strong></li>
			    </ul>
			    <label for="restore-method"><?php esc_html_e( 'Select where to restore the backup from:', 'my-wp-backup' ); ?></label><br><br>
			    <select name="method" id="restore-method">
				    <?php wpb_select_options( $backup->available_destinations() ); ?>
			    </select><br><br>
			    <?php submit_button( 'Start', null, 'submit', false ); ?>
			    <a class="button button-cancel" href="<?php echo esc_attr( Admin::get_page_url( 'backup' ) ); ?>"><?php esc_html_e( 'Cancel', 'my-wp-backup' ); ?></a>
		    </form>

	    <?php elseif ( 'viewprogress' === $action ) : ?>

		    <label for="show-verbose"><input type="checkbox" value="yes" id="show-verbose"><?php esc_html_e( 'Verbose Output', 'my-wp-backup' ); ?></label>
			<pre class="terminal" ><code id="view-progress"><?php esc_html_e( 'Please Wait...', 'my-wp-backup' ); ?></code></pre>

	    <?php endif; ?>

	<?php else : ?>

	    <form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" accept-charset="utf-8">
		    <?php wp_nonce_field( 'my-wp-backup-delete-backup' ); ?>
		    <?php $backups_table = new Backup(); ?>
		    <?php $backups_table->prepare_items(); ?>
		    <?php  $backups_table->views(); ?>
		    <?php $backups_table->display(); ?>
	    </form>

	<?php endif; ?>

</div>
