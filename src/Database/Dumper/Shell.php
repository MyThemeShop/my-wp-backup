<?php
namespace MyWPBackup\Database\Dumper;

use MyWPBackup\Database\DumpFile\DumpFile;

class Shell extends Dumper {
	function dump( $export_file_location, $table_prefix = '' ) {
		$command = 'mysqldump' .
			' -u ' . escapeshellarg( $this->db->username ) .
			' --password=' . escapeshellarg( $this->db->password ) .
			' --add-drop-table ' .
			escapeshellarg( $this->db->name );

		if ( null !== $this->db->port ) {
			$command .= ' -h ' . escapeshellarg( $this->db->host ) .
			            ' --port=' . escapeshellarg( $this->db->port );
		} elseif ( null !== $this->db->socket ) {
			$command .= ' --socket=' . escapeshellarg( $this->db->socket );
		}

		$include_all_tables = empty( $table_prefix ) &&
			empty( $this->include_tables ) &&
			empty( $this->exclude_tables );
		if ( ! $include_all_tables ) {
			$tables = $this->get_tables( $table_prefix );
			$command .= ' ' . implode( ' ', array_map( 'escapeshellarg', $tables ) );
		}
		$error_file = tempnam( sys_get_temp_dir(), 'err' );
		$command .= ' 2> ' . escapeshellarg( $error_file );
		if ( DumpFile::is_gzip( $export_file_location ) ) {
			$command .= ' | gzip';
		}
		$command .= ' > ' . escapeshellarg( $export_file_location );
		exec( $command, $output, $return_val );
		if ( 0 !== $return_val ) {
			$error_text = file_get_contents( $error_file );
			@unlink( $error_file );
			$this->job->log( sprintf( __( 'Couldn\'t export database: %s', 'my-wp-backup' ), $error_text ), 'error' );
		}
		@unlink( $error_file );
	}
}
