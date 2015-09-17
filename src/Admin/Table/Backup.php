<?php
namespace MyWPBackup\Admin\Table;

use MyWPBackup\Admin\Admin;
use MyWPBackup\Admin\Job;

class Backup extends \WP_List_Table {

	private $all = array();

	public function __construct() {

		parent::__construct( array(
			'singular' => 'backup',
			'plural' => 'backups',
		) );

	}

	public function prepare_items() {

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$data = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );


		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;

	}

	public function get_sortable_columns() {

		return array(
			'date' => array( 'timestamp', true ),
			'type' => array( 'type', false ),
		);

	}

	public function table_data() {

		$this->all = array_map( function( $value ) {

			return $value;

		}, \MyWPBackup\Admin\Backup::get_instance()->all() );

		$data = $this->all;

		return $data;

	}

	public function get_hidden_columns() {

		return array();

	}

	function get_columns() {

		return array(
			'cb' => 'cb',
//			'filename' => __( 'Filename', 'my-wp-backup' ),
			'date' => __( 'Created', 'my-wp-backup' ),
			'size' => __( 'Size', 'my-wp-backup' ),
			'type' => __( 'Type', 'my-wp-backup' ),
			'destination' => __( 'Destination', 'my-wp-backup' ),
			'duration' => __( 'Job Duration', 'my-wp-backup' ),
			'job_name' => __( 'Job Name', 'my-wp-backup' ),
		);

	}

	private function sort_data( $a, $b ) {

		// Set defaults
		$orderby = 'timestamp';
		$order   = 'desc';

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
			'MyWPBackup_delete_backup' => __( 'Delete', 'my-wp-backup' ),
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
			'backup_ident[]',
			$item['uniqid'] . '_status',
			$item['job']['id'] . '_' . $item['uniqid']
		);

	}

	/*function column_filename( $item ) {

		$actions = array(
			'log'   => sprintf( '<a href="%s">Review Log</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'log', 'id' => $item['job_id'] ) ) ),
			'list'   => sprintf( '<a href="%s">List Files</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'list', 'id' => $item['job_id'] ) ) ),
			'delete' => sprintf( '<a href="%s">Delete</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'delete', 'id' => $item['job_id'] ) ) ),
			'restore' => sprintf( '<a href="%s">Restore</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'restore', 'id' => $item['job_id'] ) ) ),
		);

		return sprintf( '%1$s %2$s', $item['job']->do_filename() . '.zip', $this->row_actions( $actions ) );

	}*/

	function column_job_name( $item ) {

		return $item['job']['job_name'];

	}

	function column_type( $item ) {

		return 'full' === $item['type'] ? __( 'Full Backup', 'my-wp-backup' ) : __( 'Differential Backup', 'my-wp-backup' );
	}

	function column_date( $item ) {

		$actions = array(
			'log'   => sprintf( '<a href="%s">Review Log</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'log', 'id' => $item['job']['id'] ) ) ),
			'list'   => sprintf( '<a href="%s">List Files</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'list', 'id' => $item['job']['id'] ) ) ),
			'delete' => sprintf( '<a href="%s">Delete</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'delete', 'id' => $item['job']['id'] ) ) ),
			'restore' => sprintf( '<a href="%s">Restore</a>', Admin::get_page_url( 'backup', array( 'uniqid' => $item['uniqid'], 'action' => 'restore', 'id' => $item['job']['id'] ) ) ),
		);

		return sprintf( '%1$s ago %2$s', human_time_diff( $item['timestamp'], time() ), $this->row_actions( $actions ) );


	}

	function column_size( $item ) {

		return size_format( $item['size'] );

	}

	function column_duration( $item ) {

		$duration = (int) $item['duration'];

		return $duration > 60 ? human_time_diff( 0, $duration ) . ' (' . $duration . ' sec)' : $duration . ' sec';
	}

	function column_destination( $item ) {

		$dests = array_map( function( $dest ) {
			return Job::$destinations[ $dest ];
		}, array_keys( $item['destinations'] ) );

		if ( '1' !== $item['job']['delete_local'] ) {
			array_unshift( $dests, __( 'Local Folder', 'my-wp-backup' ) );
		}

		return join( ', ', $dests );

	}

	public function no_items() {

		esc_html_e( 'No Backups created.', 'my-wp-backup' );

	}


}
