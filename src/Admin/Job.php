<?php
namespace MyWPBackup\Admin;

use Cron\CronExpression;
use Dropbox\AppInfo;
use Dropbox\WebAuthNoRedirect;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;
use MyWPBackup\Archive;
use MyWPBackup\Database\ExportFile;
use MyWPBackup\Job as Model;
use MyWPBackup\MyWPBackup;

class Job {

	public static $form_defaults;
	public static $compression_methods;
	public static $destinations;
	public static $reporters;
	public static $email_methods;

	protected static $instance;

	protected $admin;

	protected function __construct() {

		self::$form_defaults = array(
			'job_name' => '',
			'filename' => 'my-wp-backup_%c',
			'password' => '',
			'split' => '0',
			'volsize' => 0,
			'compression' => 'zip',
			'schedule_type' => 'manual',
			'cron_type' => 'simple',
			'schedule_simple' => 'daily',
			'schedule_advanced' => '',
			'backup_files' => '1',
			'backup_uploads' => '1',
			'exclude_files' => '1',
			'file_filters' => array(
				'**/.git',
				'**/.DS_Store',
				'**/*.log',
				'**/*.tmp',
			),
			'export_db' => '1',
			'exclude_tables' => '0',
			'table_filters' => array(),
			'differential' => '0',
			'destination' => array(),
			'destination_options' => array(
				'ftp' => array(
					'host' => '',
					'username' => '',
					'password' => '',
					'port' => 21,
					'folder' => '',
					'ssl' => '0',
				),
				'dropbox' => array(
					'token' => '',
					'folder' => '/My Wp Backup/',
				),
				'googledrive' => array(
					'token' => '',
					'token_json' => '',
					'folder' => '/My Wp Backup/',
				),
			),
			'rep_destination' => array( 'none' ),
			'reporter_options' => array(
				'mail' => array(
					'from' => get_bloginfo( 'admin_email' ),
					'name' => get_bloginfo( 'name' ),
					'address' => '',
					'title' => __( 'Hi, your site backup is complete!', 'my-wp-backup' ),
					'message' => __( 'Job {{name}} finished in {{duration}}', 'my-wp-backup' ),
					'attach' => '0',
					'method' => 'default',
					'smtp_server' => '',
					'smtp_port' => '',
					'smtp_protocol' => 'none',
					'smtp_username' => '',
					'smtp_password' => '',
				),
			),
		);

		self::$compression_methods = array(
			'zip' => 'Zip',
			'tar' => 'Tar',
			'gz' => 'Tar (gz)',
			'bz2' => 'Tar (bz2)',
		);

		self::$destinations = array(
			'ftp' => __( 'FTP', 'my-wp-backup' ),
//			'email' => 'E-Mail',
			'dropbox' => __( 'Dropbox', 'my-wp-backup' ),
			'googledrive' => __( 'Google Drive', 'my-wp-backup' ),
		);

		self::$reporters = array(
			'none' => __( 'None', 'my-wp-backup' ),
			'mail' => __( 'E-Mail', 'my-wp-backup' ),
		);

		self::$email_methods = array(
			'default' => __( 'Default', 'my-wp-backup' ),
			'smtp' => __( 'SMTP', 'my-wp-backup' ),
		);

		$this->admin = Admin::get_instance();

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_post_MyWPBackup_run_job', array( $this, 'post_run_job' ) );

	}

	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new Job();
		}

		return self::$instance;
	}

	public function admin_init() {

		add_action( 'load-' . $this->admin->get_hook( 'jobs' ), array( $this, 'page_jobs' ) );
		add_action( 'wp_ajax_wp_backup_run_job', array( $this, 'ajax_run' ) );
		add_action( 'wp_ajax_wp_backup_dropbox_token', array( $this, 'dropbox_token' ) );
		add_action( 'wp_ajax_wp_backup_drive_token', array( $this, 'drive_token' ) );
		add_action( 'wp_ajax_wp_backup_try_file_filters', array( $this, 'try_file_filter' ) );

		add_action( 'admin_post_MyWPBackup_job', array( $this, 'post_create' ) );
		add_action( 'admin_post_MyWPBackup_delete_job', array( $this, 'post_delete' ) );

	}

	public function post_create() {

		if ( ! check_admin_referer( 'MyWPBackup_job' )  || ! isset( $_POST['my-wp-backup-jobs'] ) ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		$job = $this->validate( $_POST['my-wp-backup-jobs'] ); // Input var okay. Sanitization okay.
		$jobs = get_site_option( 'my-wp-backup-jobs', array() );

		$jobs[ 'job-' . $job['id'] ] = $job;
		update_site_option( 'my-wp-backup-jobs', $jobs );

		$action = isset( $_POST['my-wp-backup-jobs']['action'] ) && 'new' === $_POST['my-wp-backup-jobs']['action'] ? 'created' : 'updated'; // Input var okay.

		// Clear schedules if the job was changed from scheduled to manual.
		if ( 'updated' === $action && 'manual' === $job['schedule_type'] ) {
			wp_clear_scheduled_hook( 'wp_backup_run_scheduled_job', array( array( $job['id'] ) ) );
		}

		if ( 'cron' === $job['schedule_type'] ) {
			if ( false === self::schedule( $job ) ) {
				$job['schedule_type'] = 'manual';
				add_settings_error( '', '', __( 'Failed to schedule job (invalid cron pattern?). Changed job scheduling to "manual".', 'my-wp-backup' ) );
			} else {
				$next = wp_next_scheduled( 'wp_backup_run_scheduled_job', array( array( $job['id'] ) ) );
				add_settings_error( '', '', sprintf( __( '%s scheduled to run in %s.', 'my-wp-backup' ), $job['job_name'], human_time_diff( time(), $next ) ), 'updated' );
			}
		}

		add_settings_error( '', '', sprintf( __( 'Job "%s" %s.', 'my-wp-backup' ), $job['job_name'], $action ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors() );

		wp_safe_redirect( Admin::get_page_url( 'jobs', array( 'settings-updated' => 1, 'tour' => isset( $_POST['tour'] ) && 'yes' === $_POST['tour'] ? 'yes' : null ) ) );

	}

	public function post_delete() {

		if ( ! check_admin_referer( 'MyWPBackup_delete_job' ) || ! isset( $_POST['id'] ) || ! is_array( $_POST['id'] ) ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		$ids = array_map( 'absint', $_POST['id'] );
		$running = get_transient( 'my-wp-backup-running' );

		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
			}
			if ( $running['id'] === $id ) {
				delete_transient( 'my-wp-backup-running' );
			}
		}

		Job::delete( $ids );

		add_settings_error( '', '', _n( 'Job deleted.', 'Jobs deleted', count( $ids ), 'my-wp-backup' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors() );
		wp_safe_redirect( $this->admin->get_page_url( 'jobs', array( 'settings-updated' => 1 ) ) );

	}

	public function post_run_job() {

		if ( ! isset( $_POST['_wpnonce'] ) || ! isset( $_POST['id'] ) ) {
			return;
		}

		$nonce  = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
		if ( ! wp_verify_nonce( $nonce, 'my-wp-backup-run-job' ) ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		$id = absint( $_POST['id'] ); //input var okay

		$job = Job::get( $id );

		if ( ! $job ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		if ( false !== ( $running_job = get_transient( 'my-wp-backup-running' ) ) ) {
			if ( $id !== $running_job['id'] ) {
				add_settings_error( '', '',sprintf( __( 'Job "%s" is currently running.', 'my-wp-backup' ), $running_job['job_name'] ) );
				set_transient( 'settings_errors', get_settings_errors() );
				wp_safe_redirect( $this->admin->get_page_url( 'jobs', array( 'settings-updated' => 1 ) ) );
			}
		}

		if ( 'manual' === $job['schedule_type'] ) {
			$uniqid = uniqid();
			wp_schedule_single_event( time(), 'wp_backup_run_job', array( array( $id, $uniqid ) ) );
			wp_safe_redirect( $this->admin->get_page_url( 'jobs', array(
				'action' => 'view',
				'uniqid' => $uniqid,
				'id' => $id,
			) ) );
		} else {
			add_settings_error( '', '',sprintf( __( 'Cannot manually run job of schedule type "%s".', 'my-wp-backup' ), $job['schedule_type'] ) );
			set_transient( 'settings_errors', get_settings_errors() );
			wp_safe_redirect( $this->admin->get_page_url( 'jobs', array( 'settings-updated' => 1 ) ) );
		}


	}

	public function page_jobs() {

		if ( isset($_GET['action'] ) && in_array( $_GET['action'], array( 'new', 'edit' ) ) ) { // input var okay, sanitization okay
			add_thickbox();
			wp_enqueue_script( 'my-wp-backup-newjob', MyWPBackup::$info['baseDirUrl'] . 'js/new-job.js', array( 'jquery', 'my-wp-backup-nav-tab', 'my-wp-backup-select-section' ), null, true );
			wp_localize_script( 'my-wp-backup-newjob', 'MyWPBackupAuthUrl', array(
				'dropbox' => self::get_dropbox_auth()->start(),
				'drive' => self::get_drive_client()->createAuthUrl(),
			));
			wp_localize_script( 'my-wp-backup-newjob', 'fileFilter', array(
				'nonce' => wp_create_nonce( 'my-wp-backup-fileFilter' ),
			));
			wp_localize_script( 'my-wp-backup-newjob', 'MyWPBackupi18n', array(
				'form_unsaved' => __( 'There is unsaved form data.', 'my-wp-backup' ),
				'failed' => __( 'Something went wrong.', 'my-wp-backup' ),
				'test_complete' => __( 'Test Complete.', 'my-wp-backup' ),
			) );

			$screen = get_current_screen();
			//$screen->remove_help_tabs();
			$screen->add_help_tab( array(
				'id'      => 'my-wp-backup-general',
				'title'   => __( 'General', 'my-wp-backup' ),
				'content' => '
				<dl>
					<dt>Job Name</dt>
					<dd>A string to easily identify this job (Optional).</dd>
				</dl>
				<h3>Archive</h3>
				<dl>
					<dt>Filename</dt>
					<dd>To insert a custom date format, use <strong>%</strong>+<strong>character</strong>. <a href="http://php.net/manual/en/function.date.php">See here</a> for the list of date characters.</dd>
				</dl>
			',
			) );
			$screen->add_help_tab( array(
				'id'      => 'my-wp-backup-content',
				'title'   => __( 'Content', 'my-wp-backup' ),
				'content' => '
				<h3>Globbing</h3>
				<ul>
					<li><code>*</code> matches zero or more characters, except <code>/</code></li>
					<li><code>/**/</code> matches zero or more directory names</li>
					<li><code>{ab,cd}</code> matches <code>ab</code> or <code>cd</code></li>
				</ul>
			',
			) );
			$screen->add_help_tab( array(
				'id'      => 'my-wp-backup-selection',
				'title'   => __( 'Selection', 'my-wp-backup' ),
				'content' => '
				<h3>Selection</h3>
				<p>When selecting destinations/reporters, you can select multiple items or deactivate all of them by pressing ctrl while clicking on an item.</p>
			',
			) );
			//add more help tabs as needed with unique id's

			// Help sidebars are optional
			$screen->set_help_sidebar( '
				<p><strong>' . __( 'For more information:', 'my-wp-backup' ) . '</strong></p>
				<p><a href="' . esc_attr( MyWPBackup::$info['pluginUri'] ) . '" target="_blank">' . __( 'Plugin Homepage', 'my-wp-backup' ) . '</a></p>
				<p><a href="' . esc_attr( MyWPBackup::$info['supportUri'] ) . '" target="_blank">' . __( 'Support Forums', 'my-wp-backup' ) . '</a></p>
			' );
		}

		if ( isset( $_GET['id'] ) && isset( $_GET['action'] ) && 'view' === $_GET['action'] ) {

			$id = intval( $_GET['id'] ); // input var okay

			$ajax_nonce = wp_create_nonce( 'my-wp-backup-runjob' );

			wp_enqueue_script( 'my-wp-backup-runjob', MyWPBackup::$info['baseDirUrl'] . 'js/run-job.js', array( 'jquery' ), null, true );
			wp_localize_script( 'my-wp-backup-runjob', 'MyWPBackupJob', array(
				'key' => 0,
				'id' => $id,
				'nonce' => $ajax_nonce,
				'action' => 'wp_backup_run_job',
				'uniqid' => isset( $_GET['uniqid'] ) ? sanitize_text_field( $_GET['uniqid'] ) : '', // input var okay
			) );
			wp_localize_script( 'my-wp-backup-runjob', 'MyWPBackupi18n', array(
				'failed' => __( 'Something went wrong.', 'my-wp-backup' ),
			) );
		}

	}

	/**
	 * @return WebAuthNoRedirect
	 */
	public static function get_dropbox_auth() {

		$appinfo = new AppInfo( base64_decode( 'dmVrMGw2ZGJ6d3gyeDh1' ), base64_decode( 'cHRicDhxdjh2Ymw4ajNx' ) );
		$webauth = new WebAuthNoRedirect( $appinfo, 'my-wp-backup' );

		return $webauth;

	}

	/**
	 * @return \Google_Client
	 */
	public static function get_drive_client() {

		$client = new \Google_Client();
		$client->setApplicationName( 'My WP Backup' );
		$client->setClientid( base64_decode( 'ODkwOTA4NDc4NDUzLXNhN3MybHU4ZzVidnBrNXYxMTM2ODJpNmtpdmEyMzEzLmFwcHMuZ29vZ2xldXNlcmNvbnRlbnQuY29t' ) );
		$client->setClientSecret( base64_decode( 'amp6ek04QUxJd0sxTW5DNjJBbDRlUmFG' ) );
		$client->setScopes( array( 'https://www.googleapis.com/auth/drive' ) );
		$client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
		$client->setAccessType( 'offline' );

		return $client;
	}

	/**
	 * AJAX callback when starting a backup
	 *
	 * @return void
	 */
	public function ajax_run() {

		if ( ! check_ajax_referer( 'my-wp-backup-runjob', 'nonce' ) ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		if ( isset( $_GET['id'] ) && isset( $_GET['uniqid'] ) ) {

			$job = self::get( intval( $_GET['id'] ) ); //input var okay
			$uniqid = sanitize_text_field( $_GET['uniqid'] ); //input var okay

			$key = isset( $_GET['key'] ) ? absint( $_GET['key'] ) : 0; //input var okay

			// The job has not started if this throws
			// an exception
			try {
				$file = $job->read_logfile( $uniqid );
			} catch ( \Exception $e ) {
				wp_send_json( array(
					'key' => 0,
					'lines' => array(),
				) );
				die( '0' );
			}

			$response = array();

			if ( 0 !== $key ) {
				$file->seek( $key );
			}

			while ( ! $file->eof() && ( $line = $file->fgets() ) && count( $response ) < 10000 ) {
				array_push( $response, json_decode( $line ) );
			}

			if ( get_transient( 'my-wp-backup-finished' ) === $uniqid && empty( $response ) ) {
				header( 'HTTP/1.1 410 Gone' );
				wp_die();
			}

			wp_send_json( array(
				'key' => $file->key(),
				'lines' => $response,
			) );

		}
	}

	/**
	 * Cron task
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public function cron_run( $args ) {

		if ( false !== get_transient( 'my-wp-backup-running' ) ) {
			error_log( __( 'A job is already running', 'my-wp-backup' ) );
			return;
		}

		$id = $args[0];
		$uniqid = $args[1];

		$is_verbose = isset( $args[2] ) ? $args[2] : false;

		$job = self::get( $id );
		$job->is_verbose = $is_verbose;
		$job['uniqid'] = $uniqid;

		try {

			$options = get_site_option( 'my-wp-backup-options', Admin::$options );
			set_time_limit( $options['time_limit'] );
			$job->running( $uniqid );

			$files = array();

			$job->log( __( 'Performing full backup', 'my-wp-backup' ) );

			$sql = new ExportFile( $job );
			$archive = new Archive( $job );

			// Export database into wp directory.
			$sql->export();

			set_transient( 'my-wp-backup-running', $job->toArray(), 0 );

			// Create a list of files to be backed up.
			// This excludes unchanged files if the backup is differential.
			$job->do_files( $files );

			// Create an archive.
			$archive->create();

			// Upload all created archives.
			$job->upload();

			// Deleted sql file from wp directory.
			$sql->delete();

			// Commit the backup information into file.
			$job->finish();

			// Send reports.
			$job->report();

		} catch ( \Exception $e ) {
			$job->log( $e->getMessage(), 'error' );
			error_log( $e );
		}

		delete_transient( 'my-wp-backup-running' );
		set_transient( 'my-wp-backup-finished', $uniqid, 0 );

		$job->log( sprintf( __( 'Finished running job in %.1f seconds.', 'my-wp-backup' ), $job->end - $job->start ) );

	}

	public function cron_scheduled_run( $args ) {

		$job_id = $args[0];
		$uniqid = uniqid();

		$this->cron_run( array( $job_id, $uniqid ) );

		$job = self::get( $job_id );

		if ( 'advanced' === $job['cron_type'] ) {
			$pattern = CronExpression::factory( $job['schedule_advanced'] );

			$job->running( $uniqid );
			$job->log( sprintf( __( 'Rescheduling job to run at %s', 'my-wp-backup' ), $pattern->getNextRunDate()->format( 'c' ) ), 'debug' );

			wp_schedule_single_event( $pattern->getNextRunDate()->getTimestamp(), 'wp_backup_run_scheduled_job', array( $args ) );
		}

	}

	public function dropbox_token() {

		if ( isset( $_POST['code'] ) ) {

			$code = sanitize_text_field( $_POST['code'] ); //input var okay

			list($accesstoken, $userid) = self::get_dropbox_auth()->finish( $code );

			echo esc_html( $accesstoken );
			wp_die();
		}
	}

	public function drive_token() {

		if ( isset( $_POST['code'] ) ) {

			$code = sanitize_text_field( $_POST['code'] ); //input var okay
			$client = self::get_drive_client();
			$res = $client->authenticate( $code );
			wp_send_json( json_decode( $res, true ) );
		}
	}

	/**
	 * @param array $attributes
	 *
	 * @return array|void
	 */
	public function validate( $attributes ) {

		$jobs = get_site_option( 'my-wp-backup-jobs', array() );
		$new_id = end( $jobs )['id'] + 1;
		$values = self::$form_defaults;
		$cron_types = array( 'simple' => 'simple', 'advanced' => 'advanced' );
		$shedule_types = array( 'manual' => 'manual', 'cron' => 'cron' );

		$id = $values['id'] = isset( $attributes['id'] )  ? '0' !== $attributes['id'] ? absint( $attributes['id'] ) : $new_id : $new_id;


		if ( ! $id ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		$job_name = sanitize_text_field( $attributes['job_name'] );
		if ( '' === $job_name ) {
			$job_name = 'Job ' . ( $id );
		}
		$values['job_name'] = $job_name;

		$file_name = trim( $attributes['filename'] );
		if ( '' !== $file_name ) {
			$values['filename'] = $file_name;
		}

		if ( isset( self::$compression_methods[ $attributes['compression'] ] ) ) {
			$values['compression'] = $attributes['compression'];
		}

		$values['password'] = isset( $attributes['password'] ) ? sanitize_text_field( $attributes['password'] ) : '';
		$values['split'] = isset( $attributes['split'] ) && '1' === $attributes['split'] ? '1' : '0';
		$values['volsize'] = intval( $attributes['volsize'] );

		if ( isset( $attributes['differential'] ) && '1' === $attributes['differential'] ) {
			$values['differential'] = '1';
		}

		$schedule_type = sanitize_text_field( $attributes['schedule_type'] );
		$values['schedule_type'] = isset( $shedule_types[ $schedule_type ] ) ? $shedule_types[ $schedule_type ] : self::$form_defaults['schedule_type'];

		if ( isset( $attributes['cron_type'] )  ) {
			$cron_type = sanitize_text_field( $attributes['cron_type'] );
			if ( isset( $cron_types[ $cron_type ] ) ) {
				$values['cron_type'] = $cron_types[ $cron_type ];
			}
		}
		if ( isset( $attributes['schedule_simple'] ) ) {
			$simple = sanitize_text_field( $attributes['schedule_simple'] );
			if ( isset( self::$simple_scheds[ $simple ] ) ) {
				$values['schedule_simple'] = $simple;
			}

		}
		if ( isset( $attributes['schedule_advanced'] ) ) {
			$advanced = sanitize_text_field( $attributes['schedule_advanced'] );
			if ( isset( self::$simple_scheds[ $advanced ] ) ) {
				$values['schedule_advanced'] = $advanced;
			}

		}

		$values['destination'] = isset( $attributes['destination'] ) ? $attributes['destination'] : array();
		$values['rep_destination'] = isset( $attributes['rep_destination'] ) ? $attributes['rep_destination'] : array();

		$values['backup_files'] = isset( $attributes['backup_files'] ) && '1' === $attributes['backup_files'] ? '1' : '0';
		$values['backup_uploads'] = isset( $attributes['backup_uploads'] ) && '1' === $attributes['backup_uploads'] ? '1' : '0';
		if ( isset( $attributes['exclude_files'] ) ) {
			$values['exclude_files'] = '1' === $attributes['exclude_files'] ? '1' : '0';
		}
		if ( isset( $attributes['file_filters'] ) ) {
			$values['file_filters'] = array_filter( explode( "\r\n", $attributes['file_filters'] ), function( $filter ) {
				$filter = str_replace( "\r", '', sanitize_text_field( $filter ) );
				return empty( $filter ) ? false : $filter;
			});
		}
		$values['export_db'] = isset( $attributes['export_db'] ) && '1' === $attributes['export_db'] ? '1' : '0';
		if ( isset( $attributes['exclude_tables'] ) ) {
			$values['exclude_tables'] = '1' === $attributes['exclude_tables'] ? '1' : '0';
		}
		if ( isset( $attributes['table_filters'] ) ) {
			if ( is_array( $attributes['table_filters'] ) ) {
				$tables = Admin::get_tables();
				$values['table_filters'] = array_filter( $attributes['table_filters'], function( $filter ) use ( $tables ) {
					return in_array( $filter, $tables ) ? $filter : false;
				} );
			}
		}
		if ( isset( $attributes['destination_options'] ) && is_array( $attributes['destination_options'] ) ) {
			foreach ( $attributes['destination_options'] as $destination => $options ) {
				switch ( $destination ) {

					case 'ftp':
						if ( isset( $options['host'] ) ) {
							$values['destination_options'][ $destination ]['host'] = sanitize_text_field( $options['host'] );
						}
						if ( isset( $options['username'] ) ) {
							$values['destination_options'][ $destination ]['username'] = sanitize_text_field( $options['username'] );
						}
						if ( isset( $options['password'] ) ) {
							$values['destination_options'][ $destination ]['password'] = sanitize_text_field( $options['password'] );
						}
						if ( isset( $options['port'] ) ) {
							$values['destination_options'][ $destination ]['port'] = absint( $options['port'] );
						}
						if ( isset( $options['folder'] ) ) {
							$values['destination_options'][ $destination ]['folder'] = sanitize_text_field( $options['folder'] );
						}
						if ( isset( $options['ssl'] ) && '1' === $options['ssl'] ) {
							$values['destination_options'][ $destination ]['ssl'] = '1';
						}
						break;

					case 'dropbox':
						if ( isset( $options['token'] ) ) {
							$values['destination_options'][ $destination ]['token'] = sanitize_text_field( $options['token'] );
						}
						if ( isset( $options['folder'] ) ) {
							$values['destination_options'][ $destination ]['folder'] = sanitize_text_field( $options['folder'] );
						}
						break;

					case 'googledrive':
						if ( isset( $options['token'] ) ) {
							$values['destination_options'][ $destination ]['token'] = sanitize_text_field( $options['token'] );
						}
						if ( isset( $options['token_json'] ) ) {
							$values['destination_options'][ $destination ]['token_json'] = sanitize_text_field( $options['token_json'] );
						}
						if ( isset( $options['folder'] ) ) {
							$values['destination_options'][ $destination ]['folder'] = sanitize_text_field( $options['folder'] );
						}
						break;

				}
			}
		}
		if ( isset( $attributes['reporter_options'] ) && is_array( $attributes['reporter_options'] ) ) {
			foreach ( $attributes['reporter_options'] as $reporter => $options ) {
				$reporter = sanitize_key( $reporter );
				$values['reporter_options'][ $reporter ] = array_map( 'sanitize_text_field', $options );

				if ( 'mail' === $reporter ) {
					$values['reporter_options'][ $reporter ]['attach'] = isset( $attributes['reporter_options'][ $reporter ]['attach'] ) ? '1' : '0';
				}
			}
		}

		return $values;

	}

	/**
	 * @param int $id
	 *
	 * @return Model
	 */
	public static function get( $id ) {

		$id = (int) $id;
		$jobs = get_site_option( 'my-wp-backup-jobs', array() );
		$return = null;

		foreach ( $jobs as $job ) {
			if ( $id === $job['id'] ) {
				$return = $job;
				break;
			}
		}

		if ( null === $return ) {
			return false;
		}

		return new Model( $return );

	}

	/**
	 * @param int|array $ids
	 *
	 * @return void
	 */
	public static function delete( $ids ) {

		$ids = (array) $ids;

		if ( empty( $ids) ) {
			return;
		}

		$jobs = get_site_option( 'my-wp-backup-jobs', array() );

		foreach ( $ids as $id ) {
			wp_clear_scheduled_hook( 'wp_backup_run_scheduled_job', array( array( $id ) ) );
			unset( $jobs[ 'job-' . $id ] );
		}

		update_site_option( 'my-wp-backup-jobs', $jobs );

	}

	public function try_file_filter() {

		if ( ! check_ajax_referer( 'my-wp-backup-fileFilter', 'nonce' ) ) {
			wp_die( esc_html__( 'Nope! Security check failed!', 'my-wp-backup' ) );
		}

		if ( ! isset( $_POST['filters'] ) ) { // input var okay
			wp_die();
		}

		$filters = array_map( 'sanitize_text_field', explode( "\n", $_POST['filters'] ) ); //input var okay;
		$excluded = array();

		/**
		 * @param \SplFileInfo $file
		 * @param mixed $key
		 * @param \RecursiveCallbackFilterIterator $iterator
		 * @return bool True if you need to recurse or if the item is acceptable
		 */
		$filter = function ($file, $key, $iterator) use ($filters,&$excluded) {
			$filePath = $file->getPathname();
			$relativePath = substr( $filePath, strlen( MyWPBackup::$info['root_dir'] ) );

			if ( $file->isDir() ) {
				$relativePath .= '/';
			}

			foreach ( $filters as $exclude ) {
				if ( Glob::match( $filePath, Path::makeAbsolute( $exclude, MyWPBackup::$info['root_dir'] ) ) ) {
					array_push( $excluded, $relativePath );
					return false;
				}
			}

			return true;
		};

		$files = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator( new \RecursiveDirectoryIterator( MyWPBackup::$info['root_dir'], \RecursiveDirectoryIterator::SKIP_DOTS ), $filter )
		);

		iterator_to_array( $files );

		wp_send_json( $excluded );
	}

	public static function schedule( array $job ) {

		$old = self::get( $job['id'] );
		$args = array( array( $job['id'] ) );


		// Don't reschedule if the schedule was not changed.
		if ( false !== $old && $old['schedule_simple'] === $job['schedule_simple'] && false !== wp_next_scheduled( 'wp_backup_run_scheduled_job', $args ) ) {
			return true;
		}

		wp_clear_scheduled_hook( 'wp_backup_run_scheduled_job', $args );

		return wp_schedule_event( time() + 60, $job['schedule_simple'], 'wp_backup_run_scheduled_job', $args );

	}

	public function get_basedir( $jobid, $uniqid ) {

		return  MyWPBackup::$info['backup_dir'] . $jobid . '/' . $uniqid . '/';
	}
}
