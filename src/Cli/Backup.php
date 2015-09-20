<?php
namespace MyWPBackup\Cli;

use WP_CLI;

/**
 * Restore or delete a backup.
 */
class Backup extends \WP_CLI_Command {

    /**
     * Restore a backup.
     * @synopsis <uniqid> --job=<id> [--method=<local>] [--verbose]
     * @param $args
     * @param $assoc
     * @return bool
     */
    function restore( $args, $assoc ) {

        list( $uniqid ) = $args;

        if ( ! isset( $assoc['method'] ) ) {
            $assoc['method'] = 'local';
        }

        $admin = \MyWPBackup\Admin\Backup::get_instance();
        $backup = $admin->get( $assoc['job'], $uniqid );

        if ( ! isset( $backup['duration'] ) ) {
            echo __( 'No such backup exists.', 'my-wp-backup' ). "\n";
            return false;
        }

        $dests = $backup->available_destinations();
        if ( ! isset( $dests[ $assoc['method'] ] ) ) {
            echo sprintf( __( 'Only the following restore methods are available: %s', 'my-wp-backup' ), join( ', ', array_keys( $dests ) ) ) . "\n";
            return false;
        }

        $admin->cron_run( array( $assoc['job'], $uniqid, $assoc['method'], isset( $assoc['verbose'] ) ) );

        return true;

    }

    /**
     * Delete a backup.
     * @synopsis <uniqid> --job=<id> [--confirm]
     *
     * @param $args
     * @param $assoc
     *
     * @return bool
     */
    function delete( $args, $assoc ) {

        if ( isset( $assoc['confirm'] ) ) {
            $uniqid = $args[0];
            $id = $assoc['job'];

            $backup = \MyWPBackup\Admin\Backup::get_instance()->get( $id, $uniqid );

            if ( ! isset( $backup['duration'] ) ) {
                echo __( 'No such backup exists.', 'my-wp-backup' ). "\n";
                return false;
            }

            \MyWPBackup\Admin\Backup::get_instance()->delete( $id, $uniqid );

            echo sprintf( __( 'Deleted backup with unique id %s.', 'my-wp-backup' ), $uniqid ) . "\n";
        } else {
            echo 'Please add --confirm to really delete the backup.' .  "\n";
        }

        return true;

    }
}
