<?php
namespace MyWPBackup\Admin\Table;

use MyWPBackup\Admin\Admin;
use MyWPBackup\Admin\Backup;
use MyWPBackup\Admin\Job as JobPage;


/**
 * Class BackupTable
 * @package MyWPBackup\Admin
 */
class Job extends \WP_List_Table {

	protected $running;

	public function __construct() {

		parent::__construct( array(
			'singular' => 'job',
			'plural' => 'jobs',
		) );

		$this->running = get_transient( 'my-wp-backup-running' );

	}

	public function no_items() {

		esc_html_e( 'No Jobs configured.', 'my-wp-backup' );

	}

	public function prepare_items() {

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->process_bulk_action();

		$data = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );


		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;

	}

	public function get_sortable_columns() {

		return array(
			'id' => array( 'id', true ),
			'name' => array( 'job_name', false ),
			'last_run' => array( 'last_run', false ),
			'next_run' => array( 'next_run', false ),
			'volsize' => array( 'volsize', false ),
		);

	}

	public function table_data() {

		$data = get_site_option( 'my-wp-backup-jobs', array() );

		return is_array( $data ) ?  $data : array();

	}

	public function get_hidden_columns() {

		return array(
			'id',
		);

	}

	function get_columns() {

		return array(
			'cb' => 'cb',
			'id' => __( 'ID', 'my-wp-backup' ),
			'name' => __( 'Name', 'my-wp-backup' ),
			'status' => __( 'Status', 'my-wp-backup' ),
			'dest' => __( 'Destination', 'my-wp-backup' ),
			'rep' => __( 'Reporters', 'my-wp-backup' ),
			'volsize' => __( 'Split Size', 'my-wp-backup' ),
			'last_run' => __( 'Last Run', 'my-wp-backup' ),
			'next_run' => __( 'Next Run', 'my-wp-backup' )
		);

	}

	private function sort_data( $a, $b ) {

		// Set defaults
		$orderby = 'id';
		$order   = 'asc';

		// If orderby is set, use this as the sort column
		if ( ! empty( $_GET['orderby'] ) ) {
			$orderby = sanitize_text_field( $_GET['orderby'] ); // input var okay
		}

		// If order is set use this as the order
		if ( ! empty( $_GET['order'] ) ) {
			$order = sanitize_text_field( $_GET['order'] ); // input var okay
		}

		$result = strnatcmp( $a[ $orderby ], $b[ $orderby ] );

		if ( 'asc' === $order ) {
			return $result;
		}

		return - $result;

	}

	function get_bulk_actions() {

		$actions = array(
			'MyWPBackup_delete_job' => __( 'Delete', 'my-wp-backup' ),
		);

		return $actions;

	}

	protected function display_tablenav( $which ) {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<div class="alignleft actions bulkactions">
				<?php $this->bulk_actions( $which ); ?>
			</div>
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>

			<br class="clear" />
		</div>
	<?php
	}

	function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s" id="%2$s" value="%3$s" />',
			'id[]',
			$item['id'] . '_status',
			$item['id']
		);

	}


	public function column_name( $item ) {

		$delete_link = Admin::get_page_url( 'jobs', array( 'action' => 'delete', 'id' => $item['id'] ) );
		$run_link = Admin::get_page_url( 'jobs', array( 'action' => 'run', 'id' => $item['id'], 'tour' => isset( $_GET['tour'] ) && 'yes' === $_GET['tour'] ? 'yes' : null ) );
		$view_link = Admin::get_page_url( 'jobs', array( 'action' => 'view', 'id' => $item['id'], 'uniqid' => $this->running['uniqid'] ) );

		$actions = array(
			'edit'   => sprintf( '<a href="%s">Edit</a>', Admin::get_page_url( 'jobs', array( 'action' => 'edit', 'id' => $item['id'] ) ) ),
			'delete' => sprintf( '<a href="%s">Delete</a>', $delete_link ),
		);

		if ( false !== $this->running && $this->running['id'] === $item['id'] ) {
			$actions['view'] = sprintf( '<a href="%s">View Progress</a>', $view_link );
		} elseif ( false === $this->running && 'manual' === $item['schedule_type'] ) {
			$actions['run'] = sprintf( '<a href="%s">Run Now</a>', $run_link );
		}

		return sprintf( '%1$s %2$s', $item['job_name'], $this->row_actions( $actions ) );

	}


	public function column_volsize( $item ) {

		return $item['volsize'] > 0 ? $item['volsize'] . ' Mb' : 'None';

	}

	public function column_dest( $item ) {

		return empty( $item['destination'] ) ? 'None' : join( ', ', array_map(function( $destination ) {
			return JobPage::$destinations[ $destination ];
		}, $item['destination']) );

	}

	public function column_rep( $item ) {

		return empty( $item['rep_destination'] ) ? 'None' : join( ', ', array_map(function( $reporter ) {
			return Jobpage::$reporters[ $reporter ];
		}, $item['rep_destination']) );

	}

	function column_status( $item ) {

		if ( is_array( $this->running ) && $this->running['id'] === $item['id'] ) {
			 return sprintf( '<b>%s</b>', __( 'Running', 'my-wp-backup' ) );
		}

		return __( 'Idle', 'my-wp-backup' );
	}


	// TODO
	public function column_last_run( $item ) {

		$backup = Backup::get_instance()->last_from_job( $item['id'] );
		return $backup ? human_time_diff( $backup['timestamp'], time() ) . ' ago' : 'None';

	}

	// TODO
	public function column_next_run( $item ) {

		if ( 'manual' === $item['schedule_type'] ) {
			return 'None';
		}

		$next = wp_next_scheduled( 'wp_backup_run_scheduled_job', array( array( $item['id'] ) ) );

		return $next ? 'in ' . human_time_diff( time(), $next ) : 'None';

	}


}
