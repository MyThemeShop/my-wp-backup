<?php
namespace MyWPBackup\Dest;
use Guzzle\Http\Client;
use Guzzle\Http\QueryString;

class DropboxClientAuth {

	const AUTHORIZE_URL = 'https://www.dropbox.com/1/oauth2/authorize';
	const TOKEN_REQUEST_URL = 'https://api.dropboxapi.com/1/oauth2/token';

	private $id;
	private $secret;

	public function __construct( $id, $secret ) {
		$this->id = $id;
		$this->secret = $secret;
	}

	public function get_authorize_url() {
		$url = new QueryString( array(
			'response_type' => 'code',
			'client_id' => $this->id,
		) );

		return self::AUTHORIZE_URL . '?' . $url;
	}

	public function authorize( $code ) {

		$client = new Client();
		$req = $client->post( self::TOKEN_REQUEST_URL, null, array(
			'code' => $code,
			'grant_type' => 'authorization_code',
			'client_id' => $this->id,
			'client_secret' => $this->secret,
		) );

		return $req->send()->json();
	}
}
