<?php
namespace MyWPBackup\Database\Connection;

class Connection {
	public $host;
	public $username;
	public $password;
	public $name;
	protected $connection;
	function __construct( $options ) {
		$this->host = $options['host'];
		if ( empty( $this->host ) ) {
			$this->host = '127.0.0.1';
		}
		$this->username = $options['username'];
		$this->password = $options['password'];
		$this->name     = $options['db_name'];
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
