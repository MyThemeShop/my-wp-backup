<?php
use MyWPBackup\Admin\Job;
use MyWPBackup\Admin\Table\Job as JobTable;
use MyWPBackup\Admin\Admin as Admin;

if ( ! defined( 'ABSPATH' ) ) { die; }
?>
<div class="page-backups wrap" id="my-wp-backup">

    <h2><?php esc_html_e( 'Jobs', 'my-wp-backup' ); ?> <a href="<?php esc_attr_e( \MyWPBackup\Admin\Admin::get_page_url( 'jobs', array( 'action' => 'new', 'tour' => isset( $_GET['tour'] ) && 'yes' === $_GET['tour'] ? 'yes' : null ) ) ); ?>" class="add-new-h2"><?php esc_html_e( 'Add New', 'my-wp-backup' ); ?></a></h2>

	<?php settings_errors(); ?>

	<?php if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && 'run' === $_GET['action'] ) : ?>

		<form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" accept-charset="utf-8">
			<?php $job = Job::get( absint( $_GET['id'] ) ); ?>
			<?php wp_nonce_field( 'my-wp-backup-run-job' ); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $job['id'] ); ?>">
			<input type="hidden" name="action" value="MyWPBackup_run_job">
			<p><?php echo esc_html( sprintf( __( 'You are about to run a job named %s.', 'my-wp-backup' ), $job['job_name'] ) ); ?></p>
			<?php submit_button( 'Start', null, 'submit', false ); ?>
			<a class="button button-cancel" href="<?php echo esc_attr( Admin::get_page_url( 'jobs' ) ); ?>"><?php esc_html_e( 'Cancel', 'my-wp-backup' ); ?></a>
		</form>

	<?php elseif ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && 'view' === $_GET['action'] ) : ?>

		<?php
		$job_attributes = Job::get( intval( $_GET['id'] ) );
		?>

		<label for="show-verbose"><input type="checkbox" value="yes" id="show-verbose"><?php esc_html_e( 'Verbose Output', 'my-wp-backup' ); ?></label>
		<div class="spinner is-active"></div>
		<pre id="backup-progress" class="terminal"><code><?php esc_html_e( 'Please wait...', 'my-wp-backup' ); ?></code></pre>

	<?php elseif ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && 'delete' === $_GET['action'] ) : ?>

		<?php $job = Job::get( absint( $_GET['id'] ) ); ?>
		<form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" accept-charset="utf-8">
			<?php wp_nonce_field( 'MyWPBackup_delete_job' ); ?>
			<input type="hidden" name="id[]" value="<?php echo esc_attr( $job['id'] ); ?>">
			<input name="action" value="MyWPBackup_delete_job" type="hidden"/>
			<p><?php esc_html_e( 'You are about to delete the job:', 'my-wp-backup' ); ?></p>
			<ul class="ul-disc">
				<li><strong><?php echo esc_html( $job['job_name'] ); ?></strong></li>
			</ul>
			<p><?php esc_html_e( 'Are you sure you want to do this?', 'my-wp-backup' ); ?></p>
			<?php submit_button( 'Yes, Delete this job', 'delete', 'submit', false ); ?>
			<a class="button button-cancel" href="<?php echo esc_attr( \MyWPBackup\Admin\Admin::get_page_url( 'jobs' ) ); ?>"><?php esc_html_e( 'No, Return me to job list', 'my-wp-backup' ); ?></a>
		</form>


    <?php elseif ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'new', 'edit' ) ) ) : // input var okay, sanitization okay ?>
		<?php $action = sanitize_text_field( $_GET['action'] ); // input var okay ?>
		<?php $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; // input var okay ?>

        <h2 class="nav-tab-wrapper">
            <a href="#section-general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'my-wp-backup' ); ?></a>
            <a href="#section-content" class="nav-tab"><?php esc_html_e( 'Content', 'my-wp-backup' ); ?></a>
            <a href="#section-schedule" class="nav-tab"><?php esc_html_e( 'Schedule', 'my-wp-backup' ); ?></a>
            <a href="#section-destination" class="nav-tab"><?php esc_html_e( 'Destination', 'my-wp-backup' ); ?></a>
            <a href="#section-report" class="nav-tab"><?php esc_html_e( 'Report', 'my-wp-backup' ); ?></a>
        </h2>

        <form method="post" action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" id="my-wp-backup-form">

	        <?php wp_nonce_field( 'MyWPBackup_job' ); ?>

	        <input name="action" value="MyWPBackup_job" type="hidden"/>
	        <input type="hidden" name="my-wp-backup-jobs[id]" value="<?php echo esc_attr( $id ); ?>">
	        <input type="hidden" name="my-wp-backup-jobs[action]" value="<?php echo esc_attr( $action ); ?>">
	        <input type="hidden" name="tour" value="<?php echo esc_attr( isset( $_GET['tour'] ) && 'yes' === $_GET['tour'] ? 'yes' : 'no' ); ?>">

	        <?php
	        if ( 'edit' === $action ) {
		        $job = Job::get( $id );
	        } else {
		        $job = Job::$form_defaults;
	        }

	        ?>

            <div id="section-general" class="nav-tab-content nav-tab-content-active">
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="job-name"><?php esc_html_e( 'Job Name', 'my-wp-backup' ); ?></label>
                        </th>
                        <td>
                            <input type="text" placeholder="Name" name="my-wp-backup-jobs[job_name]" id="job-name" value="<?php echo esc_attr( $job['job_name'] ); ?>"><br>
	                        <span class="description"><?php esc_html_e( 'e.g. Daily or Weekly Backup', 'my-wp-backup' ); ?></span>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <h3 class="title"><?php esc_html_e( 'Archive', 'my-wp-backup' ); ?></h3>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="filename"><?php esc_html_e( 'Filename', 'my-wp-backup' ); ?></label></th>
                        <td>
                            <input type="text" name="my-wp-backup-jobs[filename]" id="filename" value="<?php echo esc_attr( $job['filename'] ); ?>"><br>
                            <span class="description"><?php echo esc_html( sprintf( __( 'Filename: %s', 'my-wp-backup' ), is_array( $job ) ? 'my-wp-backup_2015-05-17T03:53:29+00:00' : $job->do_filename() ) ); ?></span>
                        </td>
                    </tr>
                    <tr>
	                    <th scope="row"><label for="compression"><?php esc_html_e( 'Compression', 'my-wp-backup' ); ?></label></th>
	                    <td>
		                    <select name="my-wp-backup-jobs[compression]" id="compression">
			                    <?php wpb_select_options( Job::$compression_methods, $job['compression'] ); ?>
		                    </select>
	                    </td>
                    </tr>
                    <!--<tr>
                        <th scope="row"><label for="pass"><?php esc_html_e( 'Password-protect', 'my-wp-backup' ); ?></label></th>
                        <td>
                            <input id="pass" name="my-wp-backup-jobs[password]" type="password" value="<?php echo esc_attr( $job['password'] ); ?>"><br>
	                        <span class="description">Leave empty to not not set a password.</span>
                        </td>
                    </tr>-->
                    <tr>
                        <th scope="row">
                            <label for="volsize"><?php esc_html_e( 'Split into volumes', 'my-wp-backup' ); ?></label>
                        </th>
                        <td>
                            <label for="volsize"><?php esc_html_e( 'Split the backup into', 'my-wp-backup' ); ?><input type="number" name="my-wp-backup-jobs[volsize]" id="volsize" style="width:100px" value="<?php echo esc_attr( $job['volsize'] ); ?>"><?php esc_html_e( 'Mb volumes.', 'my-wp-backup' ); ?></label>
	                        <br>
                            <span class="description"><?php esc_html_e( 'Splitting the backup into smaller volumes specially helps on a slow/unreliable network which might cause failures or interrupt a large data upload.', 'my-wp-backup' ); ?></span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
	        <div id="section-schedule" class="nav-tab-content">
		        <table class="form-table">
			        <tbody>
			        <tr>
				        <th scope="row">
					        <label><?php esc_html_e( 'Run job', 'my-wp-backup' ); ?></label>
					        <p class="description" style="font-weight:normal"><?php esc_html_e( 'Cron/Interval backup is available in Pro Version', 'my-wp-backup' ); ?></p>
				        </th>
				        <td>
					        <label for="schedule-manual"><input type="radio" value="manual" name="my-wp-backup-jobs[schedule_type]" id="schedule-manual" <?php echo 'manual' === $job['schedule_type'] ? 'checked' : ''; ?>><?php esc_html_e( 'Manual', 'my-wp-backup' ); ?></label><br/>
				        </td>
			        </tr>
			        </tbody>
		        </table>
	        </div>
	        <div id="section-content" class="nav-tab-content">
		        <h3 class="title"><?php esc_html_e( 'Files', 'my-wp-backup' ); ?></h3>
		        <table class="form-table">
			        <tbody>
			        <tr>
				        <th scope="row"><label for="backup-files"><?php esc_html_e( 'Backup Files', 'my-wp-backup' ); ?></label></th>
				        <td><input type="checkbox" id="backup-files" name="my-wp-backup-jobs[backup_files]" value="1" <?php checked( '1', $job['backup_files'], true ); ?>><label
						        for="backup-files"><?php esc_html_e( 'Enable', 'my-wp-backup' ); ?></label></td>
			        </tr>
			        <tr>
				        <th scope="row"><label for="backup-uploads"><?php esc_html_e( 'Backup Uploads Dir', 'my-wp-backup' ); ?></label></th>
				        <td>
					        <input type="checkbox" id="backup-uploads" name="my-wp-backup-jobs[backup_uploads]" value="1" <?php checked( '1', $job['backup_uploads'], true ); ?>><label
						        for="backup-uploads"><?php esc_html_e( 'Enable', 'my-wp-backup' ); ?></label><br>
					        <p class="description"><?php esc_html_e( 'Note: Will not work if "Backup Files" is not checked.', 'my-wp-backup' ); ?></p>
				        </td>
			        </tr>
			        <tr>
				        <th scope="row"><label for="enable-exclude"><?php esc_html_e( 'Exclude File Filters', 'my-wp-backup' ); ?></label></th>
				        <td>
					        <input type="checkbox" class="enable-exclude" name="my-wp-backup-jobs[exclude_files]" id="enable-exclude-files" value="1" <?php checked( '1', $job['exclude_files'], true ); ?>><label for="enable-exclude-files"><?php esc_html_e( 'Enable', 'my-wp-backup' ); ?></label>
					        <div class="exclude-filters">
						        <p class="description"><?php esc_html_e( 'Exclude files whose path matches any of the following glob patterns (one per line):', 'my-wp-backup' ); ?></p><br>
						        <textarea id="file-filters" rows="8" cols="50" name="my-wp-backup-jobs[file_filters]"><?php echo isset( $job['file_filters'] ) ? esc_textarea( implode( "\n", $job['file_filters'] ) ) : ''; ?></textarea>
						        <a class="button thickbox" href="#TB_inline?width=1200&height=650&inlineId=my-wp-backup-test-filters" id="file-exclude-link">Test</a>
					        </div>
				        </td>
			        </tr>
			        </tbody>
		        </table>
		        <h3 class="title"><?php esc_html_e( 'Database', 'my-wp-backup' ); ?></h3>
		        <table class="form-table">
			        <tbody>
			        <tr>
				        <th scope="row"><label for="backup-db"><?php esc_html_e( 'Export Database', 'my-wp-backup' ); ?></label></th>
				        <td>
					        <input type="checkbox" id="backup-db" value="1" name="my-wp-backup-jobs[export_db]" <?php checked( '1', $job['export_db'], true ); ?>><label for="backup-db"><?php esc_html_e( 'Enable', 'my-wp-backup' ); ?></label><br>
				            <p class="description"><?php esc_html_e( 'Include a database export file to the backup.', 'my-wp-backup' ); ?></p>
				        </td>
			        </tr>
			        <tr>
				        <th scope="row"><label for="enable-exclude"><?php esc_html_e( 'Exclude Table Filters', 'my-wp-backup' ); ?></label></th>
				        <td>
					        <input type="checkbox" class="enable-exclude" name="my-wp-backup-jobs[exclude_tables]" id="enable-exclude-tables" value="1" <?php checked( '1', $job['exclude_tables'], true ); ?>><label for="enable-exclude-tables"><?php esc_html_e( 'Enable', 'my-wp-backup' ); ?></label>
					        <div class="exclude-filters">
						        <p class="description"><?php esc_html_e( 'Exclude the following database tables:', 'my-wp-backup' ); ?></p><br>
						        <?php foreach ( \MyWPBackup\Admin\Admin::get_tables() as $table ) : ?>
									<input name="my-wp-backup-jobs[table_filters][]" type="checkbox" id="table_<?php echo esc_attr( $table ); ?>" value="<?php echo esc_attr( $table ); ?>" <?php checked( true,  in_array( $table, $job['table_filters'] ), true ); ?>><label for="table_<?php echo esc_attr( $table ); ?>"><?php echo esc_html( $table ); ?></label>
								<?php endforeach; ?>
					        </div>
				        </td>
			        </tr>
			        </tbody>
		        </table>
	        </div>
            <div id="section-destination" class="nav-tab-content">
                <table class="form-table">
                    <tbody>
                    <tr>
	                    <th scope="row"><label for="dest-local"><?php esc_html_e( 'Local Copy', 'my-wp-backup' ); ?></label></th>
	                    <td>
		                    <input type="checkbox" id="dest-local" value="1" name="my-wp-backup-jobs[delete_local]" <?php checked( '1', $job['delete_local'], true ); ?>>
		                    <label for="dest-local"><?php esc_html_e( 'Delete the local copy of the archive when upload completes.', 'my-wp-backup' ); ?></label><br>
		                    <span class="description"><?php esc_html_e( 'You need to select atleast 1 destination below for this option to work.', 'my-wp-backup' ); ?></span>
	                    </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="destination"><?php esc_html_e( 'Backup Destination(Optional)', 'my-wp-backup' ); ?></label>
	                        <p class="description" style="font-weight:normal"><?php esc_html_e( 'If you want to save the backup on the same server then, you don\'t need to choose any listed service.', 'my-wp-backup' ); ?></p>
                        </th>

                        <td>
                            <select id="destination" name="my-wp-backup-jobs[destination][]" multiple size="<?php echo esc_attr( count( Job::$destinations ) + 1 ); ?>">
	                            <?php wpb_select_options( Job::$destinations, isset( $job['destination'] ) ? $job['destination'] : array() ); ?>
                            </select>
                            <br>
                            <span class="description"><?php esc_html_e( 'Press Ctrl to select more than 1 destination.', 'my-wp-backup' ); ?></span>
                        </td>
                    </tr>
                    </tbody>
                </table>

	            <div class="select-section section-ftp <?php echo in_array( 'ftp', $job['destination'] ) ? 'section-active' : ''; ?>">
		            <h3 class="title"><?php esc_html_e( 'FTP Details', 'my-wp-backup' ); ?></h3>
		            <table class="form-table">
			            <tbody>
			            <tr>
				            <th scope="row"><label for="ftp-host"><?php esc_html_e( 'Host', 'my-wp-backup' ); ?></label></th>
				            <td><input id="ftp-host" name="my-wp-backup-jobs[destination_options][ftp][host]" value="<?php echo esc_attr( $job['destination_options']['ftp']['host'] ); ?>" type="text"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="ftp-user"><?php esc_html_e( 'Username', 'my-wp-backup' ); ?></label></th>
				            <td><input id="ftp-user" name="my-wp-backup-jobs[destination_options][ftp][username]" value="<?php echo esc_attr( $job['destination_options']['ftp']['username'] ); ?>" type="text"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="ftp-password"><?php esc_html_e( 'Password', 'my-wp-backup' ); ?></label></th>
				            <td><input id="ftp-password" name="my-wp-backup-jobs[destination_options][ftp][password]" value="<?php echo esc_attr( $job['destination_options']['ftp']['password'] ); ?>" type="password"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="ftp-ssl"><?php esc_html_e( 'Explicit SSL', 'my-wp-backup' ); ?></label></th>
				            <td><input id="ftp-ssl" name="my-wp-backup-jobs[destination_options][ftp][ssl]" value="1" type="checkbox" <?php checked( '1', $job['destination_options']['ftp']['ssl'], true ); ?>/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="ftp-port"><?php esc_html_e( 'Port', 'my-wp-backup' ); ?></label></th>
				            <td><input id="ftp-port" name="my-wp-backup-jobs[destination_options][ftp][port]" type="number" value="<?php echo esc_attr( $job['destination_options']['ftp']['port'] ); ?>"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="ftp-folder"><?php esc_html_e( 'Root Folder', 'my-wp-backup' ); ?></label></th>
				            <td>
					            <input id="ftp-folder" name="my-wp-backup-jobs[destination_options][ftp][folder]" value="<?php echo esc_attr( $job['destination_options']['ftp']['folder'] ); ?>" type="text"/>
				            </td>
			            </tr>
			            </tbody>
		            </table>
	            </div>

	            <div class="select-section section-googledrive <?php echo in_array( 'googledrive', $job['destination'] ) ? 'section-active' : ''; ?>">
		            <h3 class="title"><?php esc_html_e( 'Google Drive Details', 'my-wp-backup' ); ?></h3>
		            <table class="form-table">
			            <tbody>
			            <tr>
				            <th scope="row"><label for="drive-token"><?php esc_html_e( 'Access Token', 'my-wp-backup' ); ?></label></th>
				            <td>
					            <input value="<?php echo esc_attr( $job['destination_options']['googledrive']['token'] ); ?>" name="my-wp-backup-jobs[destination_options][googledrive][token]" id="drive-token" type="text"/>
					            <a target="_blank" id="drive-token-link" href="#TB_inline?width=600&height=140&inlineId=my-wp-backup-drive-content" class="thickbox button"><?php esc_html_e( 'Connect Google Drive Account', 'my-wp-backup' ); ?></a>
				                <input value="<?php echo filter_var( $job['destination_options']['googledrive']['token_json'], FILTER_SANITIZE_SPECIAL_CHARS ); ?>" name="my-wp-backup-jobs[destination_options][googledrive][token_json]" id="drive-token-json" type="hidden"/>
				            </td>
			            </tr>
			            <!--<tr>
				            <th scope="row"><label for="drive-folder">Google Drive Root Folder</label></th>
				            <td><input name="my-wp-backup-jobs[destination_options][googledrive][folder]" id="drive-folder" type="text" value="<?php /*echo esc_attr( $job['destination_options']['googledrive']['folder'] ); */?>"/></td>
			            </tr>-->
			            </tbody>
		            </table>
	            </div>

	            <div class="select-section section-dropbox <?php echo in_array( 'dropbox', $job['destination'] ) ? 'section-active' : ''; ?>">
		            <h3 class="title"><?php esc_html_e( 'Dropbox Details', 'my-wp-backup' ); ?></h3>
		            <table class="form-table">
			            <tbody>
			            <tr>
				            <th scope="row"><label for="dropbox-token"><?php esc_html_e( 'Access Token', 'my-wp-backup' ); ?></label></th>
				            <td><input name="my-wp-backup-jobs[destination_options][dropbox][token]" value="<?php echo esc_attr( $job['destination_options']['dropbox']['token'] ); ?>" id="dropbox-token" type="text"/> <a target="_blank" id="dropbox-token-link" href="#TB_inline?width=600&height=140&inlineId=my-wp-backup-dropbox-content" class="thickbox button"><?php esc_html_e( 'Connect Dropbox Account', 'my-wp-backup' ); ?></a></td>
			            </tr>
			            <!--<tr>
				            <th scope="row"><label for="dropbox-folder">Dropbox Root Folder</label></th>
				            <td><input name="my-wp-backup-jobs[destination_options][dropbox][folder]" id="dropbox-folder" type="text" value="<?php echo esc_attr( $job['destination_options']['dropbox']['folder'] ); ?>"/></td>
			            </tr>-->
			            </tbody>
		            </table>
	            </div>
            </div>
            <div id="section-report" class="nav-tab-content">
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="rep_destination"><?php esc_html_e( 'Reporters (optional)', 'my-wp-backup' ); ?></label></th>
                        <td>
                            <select id="rep_destination" name="my-wp-backup-jobs[rep_destination][]" multiple size="<?php echo esc_attr( count( Job::$reporters ) + 1 ); ?>">
	                            <?php wpb_select_options( Job::$reporters, $job['rep_destination'] ); ?>
                            </select>
                            <br>
                            <span class="description"><?php esc_html_e( 'Press Ctrl to select more than 1 reporter.', 'my-wp-backup' ); ?></span>
                        </td>
                    </tr>
                    </tbody>
                </table>

	            <div class="select-section section-mail <?php echo in_array( 'mail', $job['rep_destination'] ) ? 'section-active' : ''; ?>">
		            <h3 class="title"><?php esc_html_e( 'E-Mail Details', 'my-wp-backup' ); ?></h3>
		            <table class="form-table">
			            <tr>
				            <th scope="row"><label for="mail-from"><?php esc_html_e( 'Sender Address', 'my-wp-backup' ); ?></label></th>
				            <td><input id="mail-from" name="my-wp-backup-jobs[reporter_options][mail][from]" value="<?php echo esc_attr( $job['reporter_options']['mail']['from'] ); ?>" type="email"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="mail-name"><?php esc_html_e( 'Sender Name', 'my-wp-backup' ); ?></label></th>
				            <td><input id="mail-name" name="my-wp-backup-jobs[reporter_options][mail][name]" value="<?php echo esc_attr( $job['reporter_options']['mail']['name'] ); ?>" type="text"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="mail-address"><?php esc_html_e( 'Recipient Address', 'my-wp-backup' ); ?></label></th>
				            <td><input id="mail-address" name="my-wp-backup-jobs[reporter_options][mail][address]" value="<?php echo esc_attr( $job['reporter_options']['mail']['address'] ); ?>" type="email"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="mail-title"><?php esc_html_e( 'Subject', 'my-wp-backup' ); ?></label></th>
				            <td><input id="mail-title" name="my-wp-backup-jobs[reporter_options][mail][title]" value="<?php echo esc_attr( $job['reporter_options']['mail']['title'] ); ?>" type="text"/></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="mail-message"><?php esc_html_e( 'Body', 'my-wp-backup' ); ?></label></th>
				            <td><textarea name="my-wp-backup-jobs[reporter_options][mail][message]" id="mail-message" cols="40" rows="10"><?php echo esc_textarea( $job['reporter_options']['mail']['message'] ); ?></textarea></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="mail-attach"><?php esc_html_e( 'Log File', 'my-wp-backup' ); ?></label></th>
				            <td><input id="mail-attach" name="my-wp-backup-jobs[reporter_options][mail][attach]" value="1" type="checkbox" <?php echo checked( '1', $job['reporter_options']['mail']['attach'], true ); ?>/><label for="mail-attach">Attach log file</label></td>
			            </tr>
			            <tr>
				            <th scope="row"><label for="mail-method"><?php esc_html_e( 'Method', 'my-wp-backup' ); ?></label></th>
				            <td>
					            <select name="my-wp-backup-jobs[reporter_options][mail][method]" id="mail-method">
									<?php wpb_select_options( Job::$email_methods, $job['reporter_options']['mail']['method'] ); ?>
					            </select>
				            </td>
			            </tr>
		            </table>
		            <div class="select-section section-smtp <?php echo 'smtp' === $job['reporter_options']['mail']['method'] ? 'section-active' : ''; ?>">
			            <h4 class="title"><?php esc_html_e( 'SMTP Details', 'my-wp-backup' ); ?></h4>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="smtp-server"><?php esc_html_e( 'Server', 'my-wp-backup' ); ?></label></th>
								<td><input id="smtp-server" name="my-wp-backup-jobs[reporter_options][mail][smtp_server]" value="<?php echo esc_attr( $job['reporter_options']['mail']['smtp_server'] ); ?>" type="text"/></td>
							</tr>
							<tr>
								<th scope="row"><label for="smtp-port"><?php esc_html_e( 'Port', 'my-wp-backup' ); ?></label></th>
								<td><input id="smtp-port" name="my-wp-backup-jobs[reporter_options][mail][smtp_port]" value="<?php echo esc_attr( $job['reporter_options']['mail']['smtp_port'] ); ?>" type="text"/></td>
							</tr>
							<tr>
								<th scope="row"><label for="smtp-security"><?php esc_html_e( 'Protocol', 'my-wp-backup' ); ?></label></th>
								<td>
									<select id="smtp-security" name="my-wp-backup-jobs[reporter_options][mail][smtp_protocol]">
										<?php wpb_select_options( array(
											'none' => __( 'None', 'my-wp-backup' ),
											'tls' => 'TLS',
											'ssl' => 'SSL',
										), $job['reporter_options']['mail']['smtp_protocol'] ); ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="smtp-username"><?php esc_html_e( 'Username', 'my-wp-backup' ); ?></label></th>
								<td><input id="smtp-username" name="my-wp-backup-jobs[reporter_options][mail][smtp_username]" value="<?php echo esc_attr( $job['reporter_options']['mail']['smtp_username'] ); ?>" type="text"/></td>
							</tr>
							<tr>
								<th scope="row"><label for="smtp-password"><?php esc_html_e( 'Password', 'my-wp-backup' ); ?></label></th>
								<td><input id="smtp-password" name="my-wp-backup-jobs[reporter_options][mail][smtp_password]" value="<?php echo esc_attr( $job['reporter_options']['mail']['smtp_password'] ); ?>" type="password"/></td>
							</tr>
						</table>
		            </div>
	            </div>
            </div>
	        <?php submit_button( __( 'Save Changes', 'my-wp-backup' ) ); ?>
        </form>

    <?php else : ?>

		<form action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>" method="POST" accept-charset="utf-8">
			<?php wp_nonce_field( 'MyWPBackup_delete_job' ); ?>
			<?php $backups_table = new JobTable(); ?>
			<?php $backups_table->prepare_items(); ?>
			<?php $backups_table->display(); ?>
		</form>

    <?php endif; ?>

	<div id="my-wp-backup-dropbox-content" style="display:none">
		<form data-action="wp_backup_dropbox_token" data-target="#dropbox-token">
			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="dropbox-js-auth-code"><?php esc_html_e( 'Dropbox Authorization Code', 'my-wp-backup' ); ?></label></th>
					<td>
						<input id="dropbox-js-auth-code" class="my-wp-backup-access-token" type="text"/> <?php submit_button( 'Get Access Token', 'primary', 'submit', false ); ?>
						<br>
						<p class="description"><?php esc_html_e( 'You will have been directed to Dropbox on a new tab. Kindly click "Allow" and paste the authorization code here.', 'my-wp-backup' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>
		</form>
	</div>

	<div id="my-wp-backup-drive-content" style="display:none">
		<form data-action="wp_backup_drive_token" data-target="#drive-token" data-target-hidden="#drive-token-json">
			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="drive-js-auth-code"><?php esc_html_e( 'Google Drive Authorization Code', 'my-wp-backup' ); ?></label></th>
					<td>
						<input id="drive-js-auth-code" class="my-wp-backup-access-token" type="text"/> <?php submit_button( 'Get Access Token', 'primary', 'submit', false ); ?>
						<br>
						<p class="description"><?php esc_html_e( 'You will have been directed to Google on a new tab. Kindly click "Allow" and paste the authorization code here.', 'my-wp-backup' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>
		</form>
	</div>

	<div id="my-wp-backup-test-filters" style="display:none">
		<h4 style="text-align:center"><?php esc_html_e( 'Exclude Files', 'my-wp-backup' ); ?></h4>
		<p class="description"><?php esc_html_e( 'The following files/folders will be excluded from the backup:', 'my-wp-backup' ); ?></p>
		<pre class="terminal excluded" style="height:100%;max-height:36em"><code><?php esc_html_e( 'Please wait...', 'my-wp-backup' ); ?></code></pre>
	</div>

</div>
