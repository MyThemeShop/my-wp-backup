<?php
namespace MyWPBackup\Install;

class Deactivate {

	public static function run() {

		delete_option( 'my-wp-backup-jobs' );
		delete_option( 'my-wp-backup-reporter' );
		delete_option( 'my-wp-backup-options' );
		delete_transient( 'my-wp-backup-running' );
		delete_transient( 'my-wp-backup-finished' );

	}

}
