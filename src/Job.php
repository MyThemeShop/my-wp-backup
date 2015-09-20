<?php

namespace MyWPBackup;

use Melihucar\FtpClient\FtpClient;
use MyWPBackup\Dest\Dropbox;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

class Job implements \ArrayAccess {

	/** @var array  */
	private $properties = array();

	/** @var  string */
	private $jobdir;

	/** @var  string */
	private $basedir;

	/** @var  \SplFileObject */
	private $logfile;

	/** @var  \SplFileObject */
	private $hashfile;

	/** @var  string */
	private $filename;

	/** @var  string */
	private $uniqid;

	/** @var  Archive */
	private $archive;

	public $start = null;
	public $end = null;

	private $destinations = array();

	private $type = 'full';

	/** @var \MyWPBackup\Backup|null */
	private $backup = null;

	const UPLOAD_ROOT_FOLDER = 'My WP Backup';

	/**
	 * If the backup is differential, this is a reference to the last full backup
	 */
	private $last;
	private $files;

	/** @var bool For echoing debug messages on the cli */
	public $is_verbose = false;

	/** @var string Points to the path to the export sql file */
	private $db = '';

	public function __construct( $properties, $is_backup = false ) {

		if ( $is_backup ) {
			$this->properties = $properties['job'];
			$this->backup = $properties;
			if ( 'diferential' === $properties['type'] ) {
				$this->set_type( 'differential' );
			}
		} else {
			$this->properties = $properties;
			if ( '1' === $properties['differential'] ) {
				$this->set_type( 'differential' );
			}
		}

		$this->properties = wpb_array_merge_recursive_distinct( Admin\Job::$form_defaults, $this->properties );

		$this->jobdir = MyWPBackup::$info['backup_dir'] . $this['id'] . '/';
		$this->files = array(
			'filtered' => array(),
			'unchanged' => array(),
			'iterator' => array(),
			'overwritten' => array(),
		);


	}

	public function running( $uniqid ) {

		$this->start = microtime( true );

		$this->basedir = $this->jobdir . $uniqid . '/';
		$this->uniqid = $uniqid;

		if ( ! is_dir( $this->basedir ) &&  ! wp_mkdir_p( $this->basedir ) ) {
			throw new \Exception( __( 'Unable to create directory: %s.', 'my-wp-backup' ) );
		}

		if ( is_null( $this->backup ) ) {
			$this->logfile = new \SplFileObject( $this->basedir . 'log.txt', 'a+' );
			$this->hashfile = new \SPLFileObject( $this->basedir . 'hashes.txt' , 'w' );
		} else {
			$this->logfile = new \SplFileObject( $this->basedir . 'restore.txt', 'w' );
		}


		$this->filename = $this->basedir . $this->do_filename();

		return $this;
	}

	public function read_logfile( $uniqid ) {

		if ( is_null( $this->backup ) ) {
			$logfile = new \SplFileObject( $this->jobdir . $uniqid . '/log.txt', 'r' );
		} else {
			$logfile = new \SplFileObject( $this->jobdir . $uniqid . '/restore.txt', 'r' );
		}

		return $logfile;

	}

	public function read_hashfile( $uniqid ) {

		$hashfile = new \SplFileObject( $this->jobdir . $uniqid . '/hashes.txt', 'r' );

		return $hashfile;

	}

	public function move_hashfile( $tmp ) {

		rename( $tmp, $this->jobdir . $this->uniqid . '/hashes.txt' );

	}

	public function finish() {

		$this->end = microtime( true );

		if ( is_null( $this->backup ) ) {
			$item = array(
				'job' => $this->toArray(),
				'type' => $this->type,
				'uniqid' => $this->uniqid,
				'timestamp' => time(),
				'duration' => $this->end - $this->start,
				'size' => $this->archive->size,
				'destinations' => $this->destinations,
				'archives' => array_map( 'basename', $this->archive->get_archives() ),
			);

			if ( 'full' !== $this->type ) {
				$item['last'] = $this->last['uniqid'];
			}

			/** @var \SPLFileObject $fp */
			$backup = Admin\Backup::get_instance();
			$backup->add( $item );

			if ( '1' === $this['delete_local'] && ! empty( $this['destination'] ) ) {
				$this->log( __( 'Deleting archives from local folder', 'my-wp-backup' ) );

				foreach ( $this->archive->get_archives() as $filepath ) {
					$this->log( sprintf( __( 'Deleting %s...', 'my-wp-backup' ), $filepath ), 'debug' );
					if ( unlink( $filepath ) ) {
						$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
					} else {
						$this->log( sprintf( __( 'Failed to delete %s', 'my-wp-backup' ), $filepath ), 'error' );
					}
				}
			}
		}
	}

	/**
	 * @param $text
	 * @param string $level <p>
	 *  possible values:
	 *
	 *  - debug
	 *  - info
	 *  - warn
	 *  - error
	 * </p>
	 */
	public function log( $text, $level = 'info' ) {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( is_array( $text ) ) {
				foreach ( $text as $line ) {
					$linetext = $line['level'] . ': ' . $line['text'] . "\n";
					if ( isset( $line['level'] ) && isset( $line['text'] ) ) {
						echo $this->is_verbose ? $linetext : ( $level === 'debug' ? '' : $linetext );
					}
				}
			} else {
				$line = $level . ': ' . $text . "\n";
				echo $this->is_verbose ? $line : ( $level === 'debug' ? '' : $line );
			}
		}

		if ( is_array( $text ) ) {
			$this->logfile->fwrite( implode( "\n", array_map( 'wp_json_encode', $text ) ) );
		} else {
			$this->logfile->fwrite( wp_json_encode( array( 'text' => $text, 'level' => $level ) ) . "\n" );
		}

	}

	public function get_logfile() {

		return $this->logfile;

	}

	public function get_hashfile() {

		return $this->hashfile;

	}

	public function get_filename() {

		return $this->filename;

	}

	public function set_archive( Archive $archive ) {

		$this->archive = $archive;
	}

	public function upload() {

		foreach ( $this['destination'] as $destination ) {

			$options = $this['destination_options'][ $destination ];
			$settings = get_site_option( 'my-wp-backup-options', Admin\Admin::$options );
			$maxretries = $settings['upload_retries'];
			$retries = 0;

			if ( method_exists( $this, 'upload_' . $destination ) ) {
				$failed = true;
				while ( $failed && ++$retries <= $maxretries ) {
					try {
						if ( $retries >= 2 ) {
							$this->log( __( 'Retrying upload.', 'my-wp-backup' ) );
						}
						$this->destinations[ $destination ] = array();
						call_user_func( array( $this, 'upload_' . $destination ), $options, $settings );
						$failed = false;
					} catch ( \Exception $e ) {
						unset( $this->destinations[ $destination ] );
						$this->log( sprintf( __( 'Failed to upload to %s: %s', 'my-wp-backup' ), $destination, $e->getMessage() ), 'error' );
						error_log( $e );
					}
				}
			} else {
				trigger_error( esc_html( sprintf( __( 'Missing upload function: %s', 'my-wp-backup' ), 'upload_' . $destination ) ), E_USER_NOTICE );
			}
		}
	}

	public function upload_ftp( $options ) {

		$this->log( __( 'Uploading backup via ftp', 'my-wp-backup' ) );

		$ftp = new FtpClient();

		$this->log( __( 'Connecting to FTP host..', 'my-wp-backup' ), 'debug' );
		$ftp->connect( $options['host'], '1' === $options['ssl'], $options['port'] );
		$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		$this->log( __( 'Logging in..', 'my-wp-backup' ), 'debug' );
		$ftp->login( $options['username'], $options['password'] );
		$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		$ftp->binary( true );
		$ftp->passive( true );
		$ftp->changeDirectory( $options['folder'] );

		$root = $options['folder'] . self::UPLOAD_ROOT_FOLDER;
		$basedir = wpb_join_remote_path( $root, $this->uniqid );
		$folders = array( $root, $basedir );

		foreach ( $folders as $dir ) {
			try {
				$ftp->changeDirectory( $dir );
			} catch ( \Exception $e ) {
				$this->log( sprintf( __( 'Creating directory %s...', 'my-wp-backup' ), $dir ), 'debug' );
				$ftp->createDirectory( $dir );
				$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
			}
		}

		foreach ( $this->archive->get_archives() as $path ) {
			$basename = basename( $path );
			$remote_filepath = wpb_join_remote_path( $basedir, $basename );

			$this->log( sprintf( __( 'Uploading %s -> %s...', 'my-wp-backup' ), $path, $remote_filepath ), 'debug' );

			$ftp->put( $remote_filepath, $path );
			$this->destinations['ftp'][ $basename ] = array(
				'path' => $remote_filepath,
			);

			$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
		}

		$this->log( __( 'Done ftp upload', 'my-wp-backup' ) );

	}

	public function upload_dropbox( $options, $settings ) {

		$this->log( __( 'Uploading backup via dropbox', 'my-wp-backup' ) );

		$client = new Dropbox( $options['token'], 'my-wp-backup' );

		$rootdir = '/' . self::UPLOAD_ROOT_FOLDER;
		$basedir = wpb_join_remote_path( $rootdir, $this->uniqid );
		$chunk_size = $settings['upload_part'];

		foreach ( $this->archive->get_archives() as $path ) {
			$basename = basename( $path );
			$remote_filepath  = wpb_join_remote_path( $basedir, urlencode( $basename ) );

			$this->log( sprintf( __( 'Uploading %s -> %s...', 'my-wp-backup' ), $path, $remote_filepath ), 'debug' );

			$client->upload( $path, $remote_filepath, $chunk_size );
			$this->destinations['dropbox'][ $basename ] = array(
				'path' => $remote_filepath,
			);

			$this->log( sprintf( __( 'Ok.', 'my-wp-backup' ), $basename ), 'debug' );
		}

		$this->log( __( 'Done dropbox upload', 'my-wp-backup' ) );

	}

	public function upload_googledrive( $options, $settings ) {

		$this->log( __( 'Uploading backup via google drive', 'my-wp-backup' ) );

		$client = Admin\Job::get_drive_client();
		$client->setAccessToken( html_entity_decode( $options['token_json'] ) );

		$service = new \Google_Service_Drive( $client );
		$data = &$this->destinations['googledrive'];
		$root = '';

		$files = $service->files->listFiles( array(
			'q' => 'mimeType="application/vnd.google-apps.folder" AND trashed=false AND "root" IN parents',
		) );

		/** @var \Google_Service_Drive_DriveFile $driveFolder */
		foreach ( $files->getItems() as $driveFolder ) {
			if ( $driveFolder->getTitle() === self::UPLOAD_ROOT_FOLDER ) {
				$root = $driveFolder->getId();
				break;
			}
		}

		$create_folder = function ( $title, $parent = null ) use ( $service ) {
			$newFolder = new \Google_Service_Drive_DriveFile();
			$newFolder->setTitle( $title );
			$newFolder->setMimeType( 'application/vnd.google-apps.folder' );

			if ( ! is_null( $parent ) ) {
				$parentFolder = new \Google_Service_Drive_ParentReference();
				$parentFolder->setId( $parent );
				$newFolder->setParents( array( $parentFolder ) );
			}

			$insert = $service->files->insert( $newFolder, array(
				'mimeType' => 'application/vnd.google-apps.folder',
			) );

			return $insert->getId();
		};

		if ( empty( $root ) ) {
			$root = $create_folder( self::UPLOAD_ROOT_FOLDER );
		}

		$basedir = new \Google_Service_Drive_ParentReference();
		$basedir->setId( $create_folder( $this->uniqid, $root ) );

		$data['parent'] = $basedir->getId();

		$client->setDefer( true );

		foreach ( $this->archive->get_archives() as $path ) {
			$filename = basename( $path );
			$fp = fopen( $path, 'rb' );
			$status = false;
			$size = filesize( $path );
			$chunkSizeBytes = $settings['upload_part'];


			$this->log( sprintf( __( 'Uploading %s -> %s...', 'my-wp-backup' ), $path, $filename ), 'debug' );

			$file = new \Google_Service_Drive_DriveFile();
			$file->setTitle( $filename );
			$file->setParents( array( $basedir ) );

			/** @var \Google_Http_Request $request */
			$request = $service->files->insert( $file );
			$media = new \Google_Http_MediaFileUpload( $client, $request, '', null, true, $chunkSizeBytes );
			$media->setFileSize( $size );

			while ( ! $status && ! feof( $fp ) ) {
				// read until you get $chunkSizeBytes from TESTFILE
				// fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
				// An example of a read buffered file is when reading from a URL
				$chunk  = wpb_get_file_chunk( $fp, $chunkSizeBytes );
				$status = $media->nextChunk( $chunk );
			}

			if ( false !== $status ) {
				$data['files'][ $filename ] = array(
					'filename' => $filename,
					'id' => $status['id'],
				);
			}

			if ( is_resource( $fp ) ) {
				fclose( $fp );
			}

			$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
		}

		$this->log( __( 'Done google drive upload.', 'my-wp-backup' ) );
	}

	public function report() {

		foreach ( $this['rep_destination'] as $reporter ) {

			if ( 'none' === $reporter ) {
				continue;
			}

			$options = $this['reporter_options'][ $reporter ];

			if ( method_exists( $this, 'report_' . $reporter ) ) {
				call_user_func_array( array( $this, 'report_' . $reporter ), array( $options ) );
			} else {
				trigger_error( esc_html( sprintf( __( 'Missing upload function: %s', 'my-wp-backup' ), 'upload_' . $reporter ) ), E_USER_NOTICE );
			}

		}

	}

	public function report_mail( $options ) {

		$this->log( __( 'Reporting via e-mail', 'my-wp-backup' ) );

		if ( 'default' === $options['method'] ) {

			$this->log( __( 'Sending email via default method', 'my-wp-backup' ), 'debug' );

			$attachments = array();

			if ( '1' === $options['attach'] ) {
				array_push( $attachments, $this->logfile->getPathname() );
			}

			add_filter( 'wp_mail_from', function() use ($options) {
				return $options['from'];
			} );

			add_filter( 'wp_mail_from_name', function() use ($options) {
				return $options['name'];
			} );

			wp_mail( $options['address'], $this->format_message( $options['title'] ), $this->format_message( $options['message'] ), array(), $attachments );

		} elseif ( 'smtp' === $options['method'] ) {

			$this->log( __( 'Sending email via SMTP', 'my-wp-backup' ), 'debug' );

			$security = 'none' === $options['smtp_protocol'] ? null : $options['smtp_protocol'];

			$transport = new \Swift_SmtpTransport( $options['smtp_server'], $options['smtp_port'], $security );
			$mailer = new \Swift_Mailer( $transport );
			$message = new \Swift_Message();

			$transport
				->setUsername( $options['smtp_username'] )
				->setPassword( $options['smtp_password'] );

			$message
				->setSubject( $this->format_message( $options['title'] ) )
				->setFrom( array( $options['from'] => $options['name'] ) )
				->setTo( array( $options['address'] ) )
				->setBody( $this->format_message( $options['message'] ) );

			if ( '1' === $options['attach'] ) {
				$logfile = fopen( $this->basedir . 'log.txt', 'r' );
				$log = '';


				while ( ! feof( $logfile ) && ( $line = fgets( $logfile ) ) ) {
					$line = json_decode( $line, true );
					$log .= $line['text'] . "\n";
				}

				fclose( $logfile );

				$attachment = new \Swift_Attachment( $log, 'log.txt', 'text/plain' );
				$message->attach( $attachment );
			}

			$mailer->send( $message );

		} else {

			$this->log( sprintf( __( 'Unknown e-mail sending method: %s', 'my-wp-backup' ), $options['method'] ), 'error' );

		}

		$this->log( __( 'Done e-mail report', 'my-wp-backup' ) );

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

	/**
	 * @return string
	 */
	public function do_filename() {

		$filename = preg_replace_callback( '/%(\w)/', function( $matches ) {
			return date( $matches[1] );
		}, $this['filename'] );

		return sanitize_file_name( $filename );

	}

	public function format_message( $message ) {

		$values = $this->properties;
		$values['duration'] = human_time_diff( $this->start, $this->end );

		$vars = array(
			'{{name}}' => $this->properties['job_name'],
			'{{duration}}' => human_time_diff( $this->start, $this->end ),
			'{{time_start}}' => human_time_diff( $this->start, time() ) . ' ago',
			'{{time_end}}' => human_time_diff( $this->end, time() ) . ' ago',
		);

		return str_replace( array_keys( $vars ), array_values( $vars ), $message );
	}

	public function toArray() {

		return $this->properties;

	}

	/**
	 * @param array $previous_files
	 *
	 * @return \Iterator
	 */
	public function do_files( array $previous_files ) {

		$this->log( __( 'Comparing files...', 'my-wp-backup' ), 'debug' );

		$excludes = array();

		foreach ( $this['file_filters'] as $exclude ) {
			$exclude = Path::makeAbsolute( $exclude, MyWPBackup::$info['root_dir'] );
			array_push( $excludes, Glob::toRegEx( $exclude ) );
		}

		$filtered = &$this->files['filtered'];
		$unchanged = &$this->files['unchanged'];
		$overwritten = &$this->files['overwritten'];

		$exclude_uploads = '1' !== $this['backup_uploads'];
		$wp_upload_dir = wp_upload_dir();
		$uploads_dir = $wp_upload_dir['basedir'] ;

		/**
		 * @param \SplFileInfo $file
		 * @return bool True if you need to recurse or if the item is acceptable
		 */
		$filter = function ($file) use ( $excludes, $uploads_dir, $exclude_uploads, $previous_files, &$filtered, &$unchanged, &$overwritten ) {
			$filePath = $file->getPathname();
			$relativePath = substr( $filePath, strlen( MyWPBackup::$info['root_dir'] ) );

			if ( '.my-wp-backup' === $relativePath ) {
				return false;
			}

			// Exclude backup directory.
			if ( false !== strpos( $filePath, MyWPBackup::$info['backup_dir'] ) ) {
				$filtered[ $relativePath ] = true;
				return false;
			}

			if ( $exclude_uploads && false !== strpos( $filePath, $uploads_dir ) ) {
				$filtered[ $relativePath ] = true;
				return false;
			}

			foreach ( $excludes as $exclude ) {
				if ( preg_match( $exclude, $filePath ) ) {
					$filtered[ $relativePath ] = true;
					return false;
				}
			}

			if ( isset( $previous_files[ $relativePath ] )  ) {
				if ( hash_file( 'crc32b', $file ) === $previous_files[ $relativePath ] ) {
					$unchanged[ $relativePath ] = true;
					return false;
				} else {
					$overwritten[ $relativePath ] = true;
					return true;
				}
			}

			return true;
		};

		if ( '1' === $this['backup_files'] ) {
			$base_iterator = new \RecursiveDirectoryIterator( MyWPBackup::$info['root_dir'], \RecursiveDirectoryIterator::SKIP_DOTS );
		} else {
			$base_iterator = new RecursiveArrayOnlyIterator( array(
				MyWPBackup::$info['root_dir'] . Database\ExportFile::FILENAME => new \SplFileInfo( MyWPBackup::$info['root_dir'] . Database\ExportFile::FILENAME ),
			) );
		}

		$this->files['iterator'] = new \RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator( $base_iterator, $filter )
		);

		$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		return $this->files;

	}

	/**
	 * @return \Iterator
	 */
	public function get_files() {

		return $this->files;

	}

	/**
	 * @param $last
	 * @return void
	 */
	public function set_last( $last ) {

		$this->last = $last;

	}

	/**
	 * @param $string
	 * @return void
	 */
	public function set_type( $string ) {

		$this->type = $string;

	}

	public function get_basedir() {

		return $this->basedir;

	}

	public function get_backup() {

		return $this->backup;

	}

	public function get_type() {

		return $this->type;

	}

	public function download( $destination ) {

		if ( 'local' === $destination ) {
			return;
		}

		if ( ! isset( Admin\Job::$destinations[ $destination ] ) ) {
			$this->log( sprintf( __( 'Unable to restore backup from %s', 'my-wp-backup' ), $destination ), 'error' );
		}

		$options = $this['destination_options'][ $destination ];

		call_user_func( array( $this, 'download_' . $destination ), $options );

	}

	public function download_ftp( $options ) {

		$this->log( __( 'Downloading backup via ftp', 'my-wp-backup' ) );

		$ftp = new FtpClient();

		$this->log( __( 'Connecting to FTP host..', 'my-wp-backup' ), 'debug' );
		$ftp->connect( $options['host'], '1' === $options['ssl'], $options['port'] );
		$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		$this->log( __( 'Logging in..', 'my-wp-backup' ), 'debug' );
		$ftp->login( $options['username'], $options['password'] );
		$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );

		$ftp->passive( true );
		$ftp->binary( true );
		$ftp->changeDirectory( $options['folder'] );

		$info = $this->backup['destinations']['ftp'];

		foreach ( $this->backup['archives'] as $archive ) {
			$remote_filename = $info[ $archive ]['path'];
			$local_filename = $this->basedir . $archive;

			$this->log( sprintf( __( 'Downloading %s -> %s...', 'my-wp-backup' ), $remote_filename, $local_filename ), 'debug' );
			$ftp->get( $local_filename, $remote_filename );
			$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
		}

		$this->log( __( 'Done ftp download', 'my-wp-backup' ) );

	}

	public function download_dropbox( $options ) {

		$this->log( __( 'Downloading backup via dropbox', 'my-wp-backup' ) );

		$client = new Dropbox( $options['token'], 'my-wp-backup' );
		$info = $this->backup['destinations']['dropbox'];

		foreach ( $this->backup['archives'] as $archive ) {
			$remote_filename = $info[ $archive ]['path'];
			$local_filename = $this->basedir . $archive;

			$this->log( sprintf( __( 'Downloading %s -> %s...', 'my-wp-backup' ), $remote_filename, $local_filename ), 'debug' );

			$local = fopen( $local_filename, 'wb' );

			if ( ! $client->download( $remote_filename, $local ) ) {
				throw new \Exception( sprintf( __( '%s is missing from Dropbox. Select another destination!', 'my-wp-backup' ), $remote_filename ) );
			}

			if ( is_resource( $local ) ) {
				fclose( $local );
			}
			$this->log( sprintf( __( 'Ok.', 'my-wp-backup' ), $archive ), 'debug' );
		}

		$this->log( __( 'Done dropbox download', 'my-wp-backup' ) );

	}

	public function download_googledrive( $options ) {

		$this->log( __( 'Downloading backup via google drive', 'my-wp-backup' ) );

		$client = Admin\Job::get_drive_client();
		$client->setAccessToken( html_entity_decode( $options['token_json'] ) );

		$service = new \Google_Service_Drive( $client );

		$info = $this->backup['destinations']['googledrive'];

		foreach ( $this->backup['archives'] as $archive ) {
			$file = $info['files'][ $archive ];
			$local_filename = $this->basedir . $archive;

			$this->log( sprintf( __( 'Downloading %s -> %s...', 'my-wp-backup' ), $file['id'], $local_filename ), 'debug' );

			$local = fopen( $local_filename, 'wb' );

			$url = $service->files->get( $file['id'] )->getDownloadUrl();

			$token = json_decode( $client->getAccessToken(), true );
			if ( $client->isAccessTokenExpired() ) {
				$client->refreshToken( $token['refresh_token'] );
			}
			$token = json_decode( $client->getAccessToken(), true );

			$httpclient = new \Guzzle\Http\Client();
			$httpclient->setDefaultOption( 'headers/Authorization', 'Bearer ' . $token['access_token'] );
			$httpclient->get( $url )->setResponseBody( $local )->send();

			if ( is_resource( $local ) ) {
				fclose( $local );
			}

			$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
		}

		$this->log( __( 'Done google drive download', 'my-wp-backup' ) );

	}

	public function set_backup( $backup ) {

		$this->backup = $backup;

	}

	public function set_basedir( $basedir ) {

		$this->basedir = $basedir;

	}

	public function set_dbpath( $filePath ) {

		$this->db = $filePath;

	}

	public function get_dbpath() {

		return $this->db;

	}

	public function __destruct() {
		if ( null === $this->backup && null !== $this->start && null === $this->end ) {
			$this->log( __( 'Deleting created archives', 'my-wp-backup' ), 'debug' );
			foreach ( $this->archive->get_archives() as $filepath ) {
				$this->log( sprintf( __( 'Deleting archive %s...', 'my-wp-backup' ), $filepath ), 'debug' );
				unlink( $filepath );
				$this->log( __( 'Ok.', 'my-wp-backup' ), 'debug' );
			}
			$this->log( __( 'Done delete archives', 'my-wp-backup' ), 'debug' );
		}
	}

}
