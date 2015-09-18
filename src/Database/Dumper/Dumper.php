<?php

namespace MyWPBackup\Database\Dumper;

use MyWPBackup\Database\Connection\Connection;
use MyWPBackup\Job;


abstract class Dumper {
	/**
	 * Maximum length of single insert statement
	 */
	const INSERT_THRESHOLD = 838860;

	/**
	 * @var Connection
	 */
	public $db;
	/**
	 * @var \MyWPBackup\Database\DumpFile\DumpFile
	 */
	public $dump_file;
	/**
	 * End of line style used in the dump
	 */
	public $eol = "\r\n";
	/**
	 * Specificed tables to include
	 */
	public $include_tables;
	/**
	 * Specified tables to exclude
	 */
	public $exclude_tables = array();

	protected $job;

	/**
	 * @param array $db_options
	 *
	 * @param Job $job
	 *
	 * @return Native|Shell
	 */
	static function create( array $db_options, Job $job ) {
		$db = Connection::create( $db_options );
		$db->connect();
		if ( self::has_shell_access()
		     && ! self::is_shell_command_available( 'mysqldump' )
		     && ! self::is_shell_command_available( 'gzip' )
		) {
			$dumper = new Shell( $db, $job );
			$job->log( __( 'Using mysqldump.', 'my-wp-backup' ), 'debug' );
		} else {
			$dumper = new Native( $db, $job );
			$job->log( __( 'Using native dumper.', 'my-wp-backup' ), 'debug' );
		}
		if ( isset( $db_options['include_tables'] ) ) {
			$dumper->include_tables = $db_options['include_tables'];
		}
		if ( isset( $db_options['exclude_tables'] ) ) {
			$dumper->exclude_tables = $db_options['exclude_tables'];
		}

		return $dumper;
	}

	function __construct( Connection $db, Job $job ) {
		$this->db = $db;
		$this->job = $job;
	}

	public static function has_shell_access() {
		if ( ! is_callable( 'shell_exec' ) ) {
			return false;
		}
		$disabled_functions = ini_get( 'disable_functions' );

		return stripos( $disabled_functions, 'shell_exec' ) === false;
	}

	public static function is_shell_command_available( $command ) {
		if ( preg_match( '~win~i', PHP_OS ) ) {
			$binary_locator = 'where';
		} else {
			$binary_locator = 'which';
		}
		$binary_location = $binary_locator . ' ' . $command;

		return ! empty( $binary_location );
	}

	/**
	 * Create an export file from the tables with that prefix.
	 *
	 * @param string $export_file_location the file to put the dump to.
	 *        Note that whenever the file has .gz extension the dump will be comporessed with gzip
	 * @param string $table_prefix Allow to export only tables with particular prefix
	 *
	 * @return void
	 */
	abstract public function dump( $export_file_location, $table_prefix = '' );

	protected function get_tables( $table_prefix ) {
		if ( ! empty( $this->include_tables ) ) {
			return $this->include_tables;
		}
		$tables      = $this->db->fetch_numeric( '
			SHOW TABLES LIKE "' . $this->db->escape_like( $table_prefix ) . '%"
		' );
		$tables_list = array();
		foreach ( $tables as $table_row ) {
			$table_name = $table_row[0];
			if ( ! in_array( $table_name, $this->exclude_tables ) ) {
				$tables_list[] = $table_name;
			}
		}

		return $tables_list;
	}
}
