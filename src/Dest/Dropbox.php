<?php
namespace MyWPBackup\Dest;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Iterator\ChunkedIterator;
use Jstewmc\Chunker\File;

class Dropbox {

	const DROPBOX_CONTENT_BASE_URL = 'https://content.dropboxapi.com/1/';
	const DROPBOX_API_BASE_URL = 'https://api.dropboxapi.com/1/';

	/** @var Client */
	private $client;

	public function __construct( $access_token ) {
		$this->client = new Client( self::DROPBOX_CONTENT_BASE_URL, array(
			'request.options' => array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			),
		) );
	}

	public function upload( $file, $remote_path ) {
		if ( ! file_exists( $file ) || ! is_readable( $file ) || ( ! ( $fp = fopen( $file, 'rb' ) )) ) {
			throw new \Exception( sprintf( __( 'The file %s does not seem to exist or is not readable.', 'my-wp-backup' ), $file ) );
		}

		$upload_id = null;
		$chunk_size = 125829120;

		while ( ! feof( $fp ) ) {
			try {
				$res = $this->client->put( 'chunked_upload', null, array(
					'upload_id' => $upload_id,
					'offset' => ftell( $fp ),
				) )->setBody( wpb_get_file_chunk( $fp, $chunk_size ) )->send()->json();

				if ( null === $upload_id ) {
					$upload_id = $res['upload_id'];
				}
			} catch ( BadResponseException $e ) {
				$body = $e->getResponse()->getBody( true );
				error_log( $e->getRequest()->getRawHeaders() );
				error_log( $body );
				throw new \Exception( $body );
			}
		}

		$req = $this->client->post( 'commit_chunked_upload/auto/' . ltrim( $remote_path, '/' ), null, array(
			'upload_id' => $upload_id,
			'overwrite' => 'true',
		) );

		return $req->send()->json();
	}

	public function download( $path, $destination ) {
		try {
			$this->client->get( 'files/dropbox/' . ltrim( $path, '/' ) )->setResponseBody( $destination )->send();
			return true;
		} catch ( BadResponseException $e ) {
			$body = $e->getResponse()->getBody( true );
			error_log( $e->getRequest()->getRawHeaders() );
			error_log( $body );
			if ( 404 === $e->getResponse()->getStatusCode() ) {
				return false;
			} else {
				throw $e;
			}
		}
	}
}
