<?php

namespace MyWPBackup;

use MyWPBackup\Admin\Admin;
use MyWPBackup\Admin\Backup;
use MyWPBackup\Admin\Job;

/**
 *
 * @link              https://mythemeshop.com
 * @since             1.0.1
 *
 * @wordpress-plugin
 * Plugin Name:       My WP Backup
 * Plugin URI:        https://mythemeshop.com/plugins/my-my-wp-backup/
 * Description:       My WP Backup is the best way to protect your data and website in the event of server loss, data corruption, hacking or other events, or to migrate your WordPress data quickly and easily.
 * Version:           1.0.1
 * Author:            MyThemeShop
 * Author URI:        https://mythemeshop.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       my-wp-backup
 * Domain Path:       /languages
 * Support URI:       https://community.mythemeshop.com
 * Network:           true
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

register_activation_hook( __FILE__, array( 'MyWPBackup\Install\Activate', 'run' ) );
register_deactivation_hook( __FILE__, array( 'MyWPBackup\Install\Deactivate', 'run' ) );

MyWPBackup::get_instance();

class MyWPBackup {

	/**
	 * An associative array where the key is a namespace prefix and the value
	 * is an array of base directories for classes in that namespace.
	 *
	 * @var array
	 */
	protected $prefixes = array();

	protected static $instance;

	public static $info = array();

	protected function __construct() {

		if ( false !== get_transient( '_my-wp-backup-activated' ) ) {
			delete_transient( '_my-wp-backup-activated' );
//			wp_safe_redirect( Admin::get_page_url( '' ) ); stopped working?
		}

		self::$info = get_file_data( __FILE__, array(
			'name'        => 'Plugin Name',
			'pluginUri'   => 'Plugin URI',
			'supportUri'  => 'Support URI',
			'version'     => 'Version',
			'description' => 'Description',
			'author'      => 'Author',
			'authorUri'   => 'Author URI',
			'textDomain'  => 'Text Domain',
			'domainPath'  => 'Domain Path',
			'slug'        => 'Slug',
			'license'     => 'License',
			'licenseUri'  => 'License URI',
		) );

		Admin::get_instance();

		$options = get_site_option( 'my-wp-backup-options', Admin::$options );

		self::$info['baseDir'] = plugin_dir_path( __FILE__ );
		self::$info['baseDirUrl'] = plugin_dir_url( __FILE__ );
		self::$info['backup_dir'] = trailingslashit( ABSPATH ) . trailingslashit( ltrim( $options['backup_dir'], '/' ) );
		self::$info['root_dir'] = trailingslashit( ABSPATH );


		if ( defined('WP_CLI') && WP_CLI ) {
			\WP_CLI::add_command( 'job', new Cli\Job() );
			\WP_CLI::add_command( 'backup', new Cli\Backup() );
		}

		add_filter( 'cron_schedules', array( $this, '_action_cron_schedules') );

		add_action( 'wp_backup_run_job', array( Job::get_instance(), 'cron_run' ) );
		add_action( 'wp_backup_run_scheduled_job', array( Job::get_instance(), 'cron_scheduled_run' ) );
		add_action( 'wp_backup_restore_backup', array( Backup::get_instance(), 'cron_run' ) );

	}

	public function _action_cron_schedules( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __( 'Once Weekly', 'my-wp-backup' ),
		);
		$schedules['fortnightly'] = array(
			'interval' => 1209600,
			'display' => __( 'Once Fortnightly', 'my-wp-backup' ),
		);
		$schedules['monthly'] = array(
			'interval' => 2678400,
			'display' => __( 'Once Monthly', 'my-wp-backup' ),
		);
		return $schedules;
	}

	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new MyWPBackup();
		}

		return self::$instance;

	}
}