<?php
namespace MyWPBackup\Install;

class Deactivate {

	public static function run() {
		
		delete_transient( 'my-wp-backup-running' );
		delete_transient( 'my-wp-backup-finished' );

	}

}
