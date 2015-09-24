<?php

namespace MyWPBackup;

use MyWPBackup\Database\ExportFile;
use splitbrain\PHPArchive\Archive as Pharchive;
use splitbrain\PHPArchive\FileInfo;
use splitbrain\PHPArchive\Tar;
use splitbrain\PHPArchive\Zip;

class Archive {


	protected $job;

	protected $backup;

	protected $extension;

	protected $format;

	protected $compression;

	protected $password;

	protected $split_size = 0;

	protected $archives = array();

	public $size = 0;

	/**
	 * @param Job $job
	 *
	 * @throws \Exception
	 *
	 */
	public function __construct( Job $job ) {

		$this->job = $job;
		$this->backup = $job->get_backup();

		switch ( $job['compression'] ) {

			case 'zip':

				$this->extension = 'zip';
				$this->format = 'zip';
				$this->compression = Pharchive::COMPRESS_NONE;
				break;

			case 'tar':

				$this->extension = 'tar';
				$this->format = 'tar';
				$this->compression = Pharchive::COMPRESS_NONE;
				break;

			case 'gz':

				$this->extension = 'tar.gz';
				$this->format = 'tar';
				$this->compression = Pharchive::COMPRESS_GZIP;
				break;

			case 'bz2':

				$this->extension = 'tar.bz2';
				$this->format = 'tar';
				$this->compression = Pharchive::COMPRESS_BZIP;
				break;

			default:

				throw new \Exception( 'Unknown compression: ' . $job['compression'] );

		}

		$this->password = $job['password'];
		$this->split_size = $job['volsize'] > 0 ? $job['volsize'] * 1024 * 1024 : 0;

		if ( ! is_null( $this->backup ) ) {
			$this->archives = $this->backup->get_archives();
		}

	}

	/**
	 * @throws \Exception
	 * @throws \splitbrain\PHPArchive\ArchiveIOException
	 */
	public function create() {

		$this->job->log( __( 'Creating archive', 'my-wp-backup' ) );

		$archive_name = $this->job->get_filename() . '.' . $this->extension;

		if ( 'zip' === $this->format ) {
			$archive = new Zip();
			$archive->create( $archive_name );
		} else {
			$archive = new Tar();

			if ( $this->is_compressed() ) {
				$this->job->log( sprintf( __( 'Compressing archive with %s', 'my-wp-backup' ), Admin\Job::$compression_methods [ $this->job['compression'] ] ) );
			}

			$archive->setCompression( 9, $this->compression );
			$archive->create( $archive_name );
		}

		$this->archives = array( $archive_name );

		if ( '1' === $this->job['backup_files'] ) {
			$files = $this->job->get_files();
			/**
			 * @var string $name filename
			 * @var \DirectoryIterator $file
			 */
			foreach ( $files['iterator'] as $name => $file ) {
				// Skip directories (they would be added automatically)
				if ( $file->isDir() ) {
					continue;
				}

				// Get real and relative path for current file
				$filePath     = $file->getRealPath();
				$relativePath = substr( $filePath, strlen( MyWPBackup::$info['root_dir'] ) );

				if ( false === $relativePath ) {
					$this->job->log( sprintf( __( 'Skipping "%s", not in root dir', 'my-wp-backup' ), $filePath ), 'error' );
					continue;
				}

				$this->job->log( sprintf( __( 'Adding file: %s....', 'my-wp-backup' ), $relativePath ), 'debug' );
				$archive->addFile( $filePath, $relativePath );
				$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
				$this->job->get_hashfile()->fwrite( "ok $relativePath\n" );
			}
		} else {
			$this->job->log( __( 'Only backing up database file.', 'my-wp-backup' ) );
		}

		$archive->addFile( $this->job->get_hashfile()->getPathname(), '.my-wp-backup' );

		if ( '1' === $this->job['export_db'] ) {
			$this->job->log( __( 'Adding database export file...', 'my-wp-backup' ), 'debug' );
			$archive->addFile( $this->job->get_dbpath(), ExportFile::FILENAME );
			$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
		}

		$this->job->log( __( 'Closing archive...', 'my-wp-backup' ), 'debug' );
		$archive->close();
		$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		$this->calc_size();

		if ( $this->split_size > 0 && $this->size > $this->split_size ) {
			$this->job->log( __( 'Splitting archive', 'my-wp-backup' ) );
			$root_archive = fopen( $this->archives[0], 'rb' );
			$this->archives = array();
			$part = 0;

			if ( ! $root_archive ) {
				throw new \Exception( __( 'Unable to split archive.', 'my-wp-backup' ) );
			}

			while ( ! feof( $root_archive ) ) {
				$buffer = fread( $root_archive, $this->split_size );
				$filename = sprintf( '%s.vol%s.%s', $this->job->get_filename(), ++$part, $this->extension );
				$handle = fopen( $filename, 'wb' );

				$this->job->log( sprintf( __( 'Creating volume %s...', 'my-wp-backup' ), $part ), 'debug' );

				if ( ! $handle ) {
					throw new \Exception( sprintf( __( 'Unable to create volume %s.', 'my-wp-backup' ), $part ) );
				}

				fwrite( $handle, $buffer );
				fclose( $handle );
				array_push( $this->archives, $filename );

				$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
				$this->job->log( sprintf( __( 'Created volume %s', 'my-wp-backup' ), $part ) );
			}

			fclose( $root_archive );
			unlink( $archive_name );
		}

		$this->job->log( sprintf( __( 'Archive location: %s', 'my-wp-backup' ), implode( ',', $this->archives ) ), 'debug' );

		$this->job->log( __( 'Done create archive', 'my-wp-backup' ) );

		$this->job->set_archive( $this );

	}

	/**
	 * @return array
	 */
	public function get_archives() {

		return $this->archives;

	}

	public function pre_restore() {

		$this->job->log( __( 'Preparing backup for restore...', 'my-wp-backup' ), 'debug' );

		if ( $this->job['volsize'] > 0 && count( $this->archives ) > 1 ) {

			$this->job->log( sprintf( __( 'Joining %d-part archives.', 'my-wp-backup' ), count( $this->archives ) ) );

			$main_filename = $this->job->get_filename() . '.' . $this->extension;
			$main  = fopen( $main_filename, 'wb' );

			if ( ! $main ) {
				throw new \Exception( __( 'Unable to read backup file.', 'my-wp-backup' ) );
			}

			foreach ( $this->archives as $index => $archive ) {
				$this->job->log( sprintf( __( 'Processing volume %d.', 'my-wp-backup' ), $index ), 'debug' );

				$vol = fopen( $archive, 'rb' );

				if ( ! $vol ) {
					throw new \Exception( __( 'Unable to read archive volume.', 'my-wp-backup' ) );
				}

				while ( $chunk = fread( $vol, 1048576 ) ) {
					fwrite( $main, $chunk );
				}

				fclose( $vol );

				$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
			}

			fclose( $main );
			$this->archives = array( $main_filename );

		}

		$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
	}

	public function restore( ) {

		foreach ( $this->archives as $path ) {

			$this->job->log( sprintf( __( 'Extracting %s', 'my-wp-backup' ), $path ) );

			if ( 'zip' === $this->format ) {
				$archive = new Zip();
			} else {
				$archive = new Tar();

				if ( $this->is_compressed() ) {
					$this->job->log( sprintf( __( 'Decompressing archive compressed with %s', 'my-wp-backup' ), Admin\Job::$compression_methods [ $this->job['compression'] ] ) );
					$archive->setCompression( 9, $this->compression );
				}
			}

			$archive->open( $path );
			$contents = $archive->contents();

			/** @var FileInfo $file */
			foreach ( $contents as $file ) {
				$this->job->log( sprintf( __( 'Extract file: %s', 'my-wp-backup' ), $file->getPath() ), 'debug' );
			}

			$archive->open( $path );
			$archive->extract( MyWPBackup::$info['root_dir'] );

			$this->job->log( __( 'Ok.', 'my-wp-backup' ) );

		}

	}

	public function get_hashes() {

		$this->job->log( __( 'Getting checksum file from archive...', 'my-wp-backup' ), 'debug' );

		if ( 'zip' === $this->format ) {
			$archive = new Zip();
		} else {
			$archive = new Tar();

			if ( $this->is_compressed() ) {
				$archive->setCompression( 9, $this->compression );
			}
		}

		$archive->open( $this->archives[0] );

		$archive->extract( sys_get_temp_dir(), '', '', '#\.my-wp-backup$#' );
		$this->job->move_hashfile( sys_get_temp_dir() . '/.my-wp-backup' );

		$this->job->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		return $this->backup->get_files();

	}

	private function calc_size() {

		$this->size = filesize( $this->archives[0] );

		if ( ! $this->size ) {

			$fp = fopen( $this->archives[0], 'rb' );

			$return = 0;
			if ( is_resource( $fp ) ) {
				if ( PHP_INT_SIZE < 8 ) {
					// 32bit
					if ( 0 === fseek( $fp, 0, SEEK_END ) ) {
						$return = 0.0;
						$step   = 0x7FFFFFFF;
						while ( $step > 0 ) {
							if ( 0 === fseek( $fp, - $step, SEEK_CUR ) ) {
								$return += floatval( $step );
							} else {
								$step >>= 1;
							}
						}
					}
				} elseif ( 0 === fseek( $fp, 0, SEEK_END ) ) {
					// 64bit
					$return = ftell( $fp );
				}
			}

			$this->size = $return;

		}
	}

	public function is_compressed() {

		return 'tar' === $this->format && Pharchive::COMPRESS_NONE !== $this->compression;

	}

	public function set_archives( array $archives ) {

		$this->archives  = $archives;

	}
}
