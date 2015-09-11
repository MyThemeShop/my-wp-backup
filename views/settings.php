<?php if ( ! defined( 'ABSPATH' ) ) { die; } ?>
<div class="wrap page-settings" id="my-wp-backup">

	<h2><?php esc_html_e( 'My WP Backup Settings', 'my-wp-backup' ); ?></h2>

	<?php settings_errors(); ?>

	<?php $options = get_site_option( 'my-wp-backup-options', \MyWPBackup\Admin\Admin::$options ); ?>

	<h2 class="nav-tab-wrapper">
		<a href="#section-configure" class="nav-tab nav-tab-active"><?php esc_html_e( 'Configure', 'my-wp-backup' ); ?></a>
		<a href="#section-import" class="nav-tab"><?php esc_html_e( 'Import', 'my-wp-backup' ); ?></a>
		<a href="#section-export" class="nav-tab"><?php esc_html_e( 'Export', 'my-wp-backup' ); ?></a>
	</h2>

	<div id="section-configure" class="nav-tab-content nav-tab-content-active">
		<form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST">
			<input type="hidden" name="action" value="MyWPBackup_settings">
			<?php wp_nonce_field( 'MyWPBackup_settings' ); ?>
			<h3>Job</h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="time-limit"><?php esc_html_e( 'Time Limit', 'my-wp-backup' ); ?></label></th>
					<td>
						<input name="my-wp-backup-setting[time_limit]" id="time-limit" type="number" value="<?php echo esc_attr( $options['time_limit'] ); ?>"/><br/>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upload-retries"><?php esc_html_e( 'Max Upload Retries', 'my-wp-backup' ); ?></label></th>
					<td>
						<input type="number" name="my-wp-backup-setting[upload_retries]" id="upload-retries" value="<?php echo esc_attr( $options['upload_retries'] ); ?>"><br>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upload-part"><?php esc_html_e( 'Upload Chunk Size', 'my-wp-backup' ); ?></label></th>
					<td>
						<label for="upload-part"><?php printf( __( 'Upload %sbytes at a time.', 'my-wp-backup' ), '<input type="number" name="my-wp-backup-setting[upload_part]" value="' . esc_attr( $options['upload_part'] ) . '">' ); ?></label><br>
					</td>
				</tr>
			</table>
			<h3>Backup</h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="backup-dir"><?php esc_html_e( 'Backup Dir', 'my-wp-backup' ); ?></label></th>
					<td><label for="backup-dir">/<input type="text" id="backup-dir" name="my-wp-backup-setting[backup_dir]" value="<?php echo esc_attr( $options['backup_dir'] ); ?>"></label></td>
				</tr>
			</table>
			<p class="submit"><input type="submit" value="<?php esc_attr_e( 'Save Changes', 'my-wp-backup' ); ?>" class="button-primary" name="Submit"></p>
		</form>
	</div>

	<div id="section-import" class="nav-tab-content">
		<form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" enctype="multipart/form-data">
			<input type="hidden" name="action" value="MyWPBackup_import">
			<?php wp_nonce_field( 'MyWPBackup_import' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="import"><?php esc_html_e( 'Paste Code', 'my-wp-backup' ); ?></label></th>
					<td>
						<textarea name="my-wp-backup-import[text]" id="import" cols="40" rows="10"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="export-ul"><?php esc_html_e( 'Upload File', 'my-wp-backup' ); ?></label></th>
					<td>
						<input type="file" id="import-ul" name="my-wp-backup-import[file]">
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" value="<?php esc_attr_e( 'Import', 'my-wp-backup' ); ?>" class="button-primary" name="Submit"></p>
		</form>
	</div>

	<div id="section-export" class="nav-tab-content">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="export"><?php esc_html_e( 'Copy Code', 'my-wp-backup' ); ?></label></th>
				<td>
					<textarea id="export" cols="40" rows="10"><?php echo serialize( \MyWPBackup\Admin\Admin::get_instance()->export() ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="export-dl"><?php esc_html_e( 'Download File', 'my-wp-backup' ); ?></label></th>
				<td>
					<a id="export-dl" href="<?php echo esc_attr( MyWPBackup\Admin\Admin::get_page_url( 'settings', array( 'action' => 'export' ) ) ); ?>"><?php esc_html_e( 'Click here to download', 'my-wp-backup' ); ?></a>
				</td>
			</tr>
		</table>
	</div>

</div>
