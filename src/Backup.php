<?php

namespace MyWPBackup;

use MyWPBackup\Admin\Job;

class Backup implements \ArrayAccess {

	private $properties = array();

	public function __construct( array $properties ) {

		$this->properties = $properties;
		$this->basedir = MyWPBackup::$info['backup_dir'] . $this['job']['id'] . '/' . $this['uniqid'] . '/';

	}

	public function get_log( ) {

		$file = new \SplFileObject( $this->basedir . 'log.txt', 'r' );

		return $file;

	}

	/**
	 * @return array|bool
	 */
	public function get_files( ) {

		$files = array();
		$hashfile = $this->basedir . 'hashes.txt';

		if ( ! is_readable( $hashfile ) ) {
			return false;
		}

		$file = file_get_contents( $hashfile );
		$file = preg_split("/\r\n|\n|\r/", $file );

		foreach ( $file as $line ) {
			if ( empty( $line ) ) {
				continue;
			}

			if ( preg_match( '/(\w+)\s(.*)/', $line, $matches ) ) {
				$files[ $matches[2] ] = $matches[1];
			} else {
				return false;
			}
		}

		return $files;

	}

	public function has_archives() {

		$basedir = MyWPBackup::$info['backup_dir'] . $this['job']['id'] . '/' . $this['uniqid'] . '/';

		foreach ( $this['archives'] as $archive ) {
			if ( ! is_readable( $basedir . $archive ) ) {
				return false;
			}
		}

		return true;

	}

	/**
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 *
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists( $offset ) {

		return isset( $this->properties[ $offset ] );
	}

	/**
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 *
	 * @return mixed Can return all value types.
	 */
	public function offsetGet( $offset ) {

		return $this->properties[ $offset ];

	}

	/**
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 *
	 * @return void
	 */
	public function offsetSet( $offset, $value ) {

		$this->properties[ $offset ] = $value;

	}

	/**
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 *
	 * @return void
	 */
	public function offsetUnset( $offset ) {

		unset( $this->properties[ $offset ] );
	}

	public function toArray() {

		return $this->properties;

	}

	public function available_destinations() {

		$dests = array();

		if ( $this->has_archives() ) {
			$dests['local'] = 'Local Folder';
		}

		foreach ( $this['destinations'] as $key => $value ) {
			if ( isset( Job::$destinations[ $key ] ) ) {
				$dests[ $key ] = Job::$destinations[ $key ];
			}
		}

		return $dests;

	}

	public function get_archives() {

		$id = $this['job']['id'];
		$uniqid = $this['uniqid'];

		return array_map( function( $filename ) use( $id, $uniqid ) {
			return Admin\Job::get_instance()->get_basedir( $id, $uniqid ) . $filename;
		}, $this['archives'] );

	}

}
