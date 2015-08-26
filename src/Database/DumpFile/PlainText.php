<?php
namespace MyWPBackup\Database\DumpFile;

class PlainText extends DumpFile {
	function open() {
		return fopen( $this->file_location, 'w' );
	}

	function write( $string ) {
		return fwrite( $this->fh, $string );
	}

	function end() {
		return fclose( $this->fh );
	}
}