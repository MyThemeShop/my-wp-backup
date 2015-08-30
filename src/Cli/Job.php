<?php
namespace MyWPBackup\Cli;

use WP_CLI;

/**
 * Run or delete a job.
 */
class Job extends \WP_CLI_Command {

	/**
	 * Run a job.
	 * @synopsis <id> [--verbose]
	 *
	 * @param $args
	 * @param $assoc
	 *
	 * @return bool
	 */
	public function run( $args, $assoc ) {

		list( $id ) = $args;

		declare( ticks = 1 );

		if ( function_exists( 'pcntl_signal' ) ) {
			pcntl_signal( SIGINT, function () {
				echo 'woops' . "\n";
				delete_transient( 'my-wp-backup-running' );
				exit;
			} );
		}

		$job = \MyWPBackup\Admin\Job::get_instance();
		$job->cron_run( array( $id, uniqid(), isset( $assoc['verbose'] ) ) );

		return true;
	}

	/**
	 * Delete a job.
	 * @synopsis <id> [--confirm]
	 *
	 * @param $args
	 * @param $assoc
	 *
	 * @return bool
	 */
	public function delete( $args, $assoc ) {

		list( $id ) = $args;

		if ( isset( $assoc['confirm'] ) ) {
			$job = \MyWPBackup\Admin\Job::get( $id );

			if ( ! $job ) {
				echo sprintf( __( 'No such job with ID %s.', 'my-wp-backup' ), $id ) . "\n";

				return false;
			}

			\MyWPBackup\Admin\Job::delete( $id );
			echo sprintf( __( 'Deleted job %s.', 'my-wp-backup' ), $job['job_name'] ) . "\n";
		} else {
			echo 'Please add --confirm to really delete the job.' . "\n";
		}

		return true;

	}

	public function stopall() {

		delete_transient( 'my-wp-backup-running' );

	}

}
