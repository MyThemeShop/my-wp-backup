<?php
namespace MyWPBackup\Database\DumpFile;

class Gzip extends DumpFile {
	function open() {
		return gzopen( $this->file_location, 'wb9' );
	}

	function write( $string ) {
		return gzwrite( $this->fh, $string );
	}

	function end() {
		return gzclose( $this->fh );
	}
}