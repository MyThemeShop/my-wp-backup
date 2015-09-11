<?php
namespace MyWPBackup\Install;

use MyWPBackup\Admin\Admin;

class Activate {

	const MIN_VER = '5.3.3';

	public static function err( $message, $errno = E_USER_ERROR ) {

		if ( isset( $_GET['action'] ) && 'error_scrape' === $_GET['action'] ) { // input var okay, sanitization okay

			echo '<strong>' . esc_html( $message ) . '</strong>';

			exit;

		} else {

			trigger_error( esc_html( $message ), $errno );

		}

	}

	public static function run() {

		Admin::get_instance();

		if ( ! get_site_option( 'my-wp-backup-jobs', false ) ) {
			update_site_option( 'my-wp-backup-jobs', array() );
		}
		if ( ! get_site_option( 'my-wp-backup-options', false ) ) {
			update_site_option( 'my-wp-backup-options', Admin::$options );
		}

		if ( version_compare( PHP_VERSION, self::MIN_VER, '<' ) ) {
			self::err( sprintf( __( 'PHP\'s version is too old. You have %s but My WP Backup requires atleast %s.', 'my-wp-backup' ), PHP_VERSION, self::MIN_VER ) );
		}

		$extensions = array( 'zlib', 'bz2', 'SPL', 'curl', 'mbstring', 'ftp' );

		foreach ( $extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				self::err( sprintf( __( 'Error: Your PHP installation does not have the %s extension which is required for this plugin to function correctly.', 'my-wp-backup' ), $extension ) );
			}
		}

		set_transient( '_my-wp-backup-activated', true, 30 );

	}

}
