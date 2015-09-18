<?php
namespace MyWPBackup\Database\Connection;

class Connection {

	public $host;
	public $raw_host;
	public $username;
	public $password;
	public $name;
	public $port = null;
	public $socket = null;

	protected $connection;
	function __construct( $options ) {
		$this->raw_host = $options['host'];
		if ( empty( $this->raw_host ) ) {
			$this->raw_host = '127.0.0.1';
		}

		$this->username = $options['username'];
		$this->password = $options['password'];
		$this->name     = $options['db_name'];

		$port_or_socket = strstr( $this->raw_host, ':' );
		if ( ! empty( $port_or_socket ) ) {
			$this->host = substr( $this->raw_host, 0, strpos( $this->raw_host, ':' ) );
			$port_or_socket = substr( $port_or_socket, 1 );
			if ( 0 !== strpos( $port_or_socket, '/' ) ) {
				$this->port = intval( $port_or_socket );
				$maybe_socket = strstr( $port_or_socket, ':' );
				if ( ! empty( $maybe_socket ) ) {
					$this->socket = substr( $maybe_socket, 1 );
				}
			} else {
				$this->socket = $port_or_socket;
			}
			if ( empty( $this->host ) ) {
				$this->host = '127.0.0.1';
			}
		} else {
			$this->host = $this->raw_host;
		}
	}

	/**
	 * @param $options
	 *
	 * @return Mysql|Mysqli
	 */
	static function create( $options ) {
		if ( class_exists( 'mysqli' ) ) {
			return new Mysqli( $options );
		} else {
			return new Mysql( $options );
		}
	}
}
