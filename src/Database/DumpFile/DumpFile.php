<?php
namespace MyWPBackup\Database\DumpFile;

abstract class DumpFile {
	/**
	 * File Handle
	 */
	protected $fh;
	/**
	 * Location of the dump file on the disk
	 */
	protected $file_location;

	abstract function open();

	abstract function write( $string );

	abstract function end();

	/**
	 * @param $filename
	 *
	 * @return Gzip|PlainText
	 */
	static function create( $filename ) {
		if ( self::is_gzip( $filename ) ) {
			return new Gzip( $filename );
		}

		return new PlainText( $filename );
	}

	function __construct( $file ) {
		$this->file_location = $file;
		$this->fh            = $this->open();
		if ( ! $this->fh ) {
			error_log( 'Couldn\'t create gz file' );
		}
	}

	public static function is_gzip( $filename ) {
		return preg_match( '~gz$~i', $filename );
	}
}