<?php
namespace MyWPBackup\Database;

use MyWPBackup\Job;
use MyWPBackup\MyWPBackup;

class ExportFile {

	private $job;

	private $filePath;

	const FILENAME = 'database.sql';

	public function __construct( Job $job ) {

		$this->job = $job;

		$this->filePath = null === $job->get_backup() ? tempnam( sys_get_temp_dir(), 'my-wp-backup-export' ) : MyWPBackup::$info['root_dir'] . self::FILENAME;

		$job->set_dbpath( $this->filePath );

	}

	public function export() {

		if ( '0' === $this->job['export_db'] ) {
			$this->job->log( __( 'Skipping database export', 'my-wp-backup' ) );
			return;
		}

		global $wpdb;

		$this->job->log( __( 'Exporting database', 'my-wp-backup' ) );

		$this->job->log( sprintf( __( 'Dumping database to temporary file %s', 'my-wp-backup' ), $this->filePath ), 'debug' );

		if ( count( $this->job['table_filters'] ) > 0 ) {
			$this->job->log( sprintf( __( 'Excluded tables "%s"', 'my-wp-backup' ), implode( ',', $this->job['table_filters'] ) ), 'debug' );
		} else {
			$this->job->log( __( 'No tables excluded, exporting all', 'my-wp-backup' ) );
		}

		$dumper = Dumper\Dumper::create( array(
			'host'     => DB_HOST,
			'username' => DB_USER,
			'password' => DB_PASSWORD,
			'db_name'  => DB_NAME,
			'exclude_tables' => $this->job['table_filters'],
		), $this->job );

		$dumper->dump( $this->filePath, $wpdb->prefix );

		$this->job->log( __( 'Done database export', 'my-wp-backup' ) );

	}

	public function import() {

		if ( '0' === $this->job['export_db'] ) {
			$this->job->log( __( 'Skipping database import', 'my-wp-backup' ) );
			return;
		}

		global $wpdb;

		$file = new \SplFileObject( MyWPBackup::$info['root_dir'] . self::FILENAME, 'r' );
		$query = '';

		while ( ! $file->eof() ) {
			$line = trim( $file->fgets() );

			if ( '' === $line || '--' === substr( $line, 0, 2 ) ) {
				continue;
			}
			$query .= $line;
			if ( ';' === substr( $query, -1 ) ) {
				if ( false === $wpdb->query( $query ) ) {
					$this->job->log( sprintf( __( 'Failed to execute query: %s', 'my-wp-backup' ), $line ), 'error' );
				}
				$query = '';
			}
		}

	}

	public function delete() {

		if ( '0' === $this->job['export_db'] ) {
			$this->job->log( __( 'Database not exported, nothing to delete', 'my-wp-backup' ), 'debug' );

			return;
		}

		$this->job->log( sprintf( __( 'Deleting temporary file at %s', 'my-wp-backup' ), $this->filePath ), 'debug' );
		unlink( $this->filePath );

	}
}
