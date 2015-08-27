<?php
namespace MyWPBackup\Install;

use MyWPBackup\Admin\Admin;

class Activate {

	const MIN_VER = '5.3.0';

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

		update_site_option( 'my-wp-backup-jobs', array() );
		update_site_option( 'my-wp-backup-reporter', array() );
		update_site_option( 'my-wp-backup-options', Admin::$options );

		if ( version_compare( PHP_VERSION, self::MIN_VER, '<' ) ) {
			self::err( sprintf( __( 'Your PHP version must be atleast %s for this plugin to function correctly. You have %s.', 'my-wp-backup' ), self::MIN_VER, PHP_VERSION ) );
		}

		if ( strlen( (string) PHP_INT_MAX ) < 19 ) {
			self::err( __( 'This plugin requires the 64-bit version of PHP to function correctly.', 'my-wp-backup' ) );
		}

		$extensions = array( 'zlib', 'bz2', 'SPL', 'curl', 'mbstring' );

		foreach ( $extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				self::err( sprintf( __( 'This plugin requires the %s PHP extension to function correctly.', 'my-wp-backup' ), $extension ) );
			}
		}

		set_transient( '_my-wp-backup-activated', true, 30 );

		wp_redirect( Admin::get_page_url( '' ) );

	}

}
