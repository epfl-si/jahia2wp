<?PHP

if ( ! class_exists( 'WP_List_Table' ) )
{
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EPFL404Table extends WP_List_Table
{

	/** Class constructor */
	public function __construct()
	{

		parent::__construct( [
			'singular' => '404', //singular name of the listed records
			'plural'   => '404s', //plural name of the listed records
			'ajax'     => false //should this table support ajax?

		] );

	}


    /** Text displayed when no customer data is available */
    public function no_items()
    {
        echo "No 404 entries";
    }


    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_epfl_404_url( $item )
    {

        // create a nonce
        $delete_nonce = wp_create_nonce( 'epfl_404_delete_entry' );

        $title = '<strong>' . $item[EPFL404DB::EPFL404_DB_FIELD_URL] . '</strong>';

        $actions = [
                    'delete' => sprintf('<a href="?page=%s&action=%s&404entry=%s&_wpnonce=%s">Delete</a>',
                                        esc_attr( $_REQUEST['page'] ),
                                        'delete',
                                        absint( $item[EPFL404DB::EPFL404_DB_FIELD_ID] ),
                                        $delete_nonce )
                   ];

        return $title . $this->row_actions( $actions );
    }


    /**
     * Render a column when no column specific method exists.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default( $item, $column_name )
    {
        return $item[ $column_name ];
    }


    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb( $item )
    {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item[EPFL404DB::EPFL404_DB_FIELD_ID]);
    }


    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns()
    {
        $columns = [
            'cb'      => '<input type="checkbox" />',
            EPFL404DB::EPFL404_DB_FIELD_URL             => 'URL',
            EPFL404DB::EPFL404_DB_FIELD_REFERER         => 'Referer',
            EPFL404DB::EPFL404_QUERY_FIELD_NB_OCCUR     => '# occurences',
            EPFL404DB::EPFL404_QUERY_FIELD_LAST_OCCUR   => 'Last occurence'
        ];

        return $columns;
    }


    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            EPFL404DB::EPFL404_DB_FIELD_URL             => array( EPFL404DB::EPFL404_DB_FIELD_URL, true ),
            EPFL404DB::EPFL404_DB_FIELD_REFERER         => array( EPFL404DB::EPFL404_DB_FIELD_REFERER, false ),
            EPFL404DB::EPFL404_QUERY_FIELD_NB_OCCUR     => array( EPFL404DB::EPFL404_QUERY_FIELD_NB_OCCUR, false ),
            EPFL404DB::EPFL404_QUERY_FIELD_LAST_OCCUR   => array( EPFL404DB::EPFL404_QUERY_FIELD_LAST_OCCUR, false ),
        );

      return $sortable_columns;
    }


    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        return [ 'bulk-delete' => 'Delete'];
    }


    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items()
    {

        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( '404_per_page', 5 );
        $current_page = $this->get_pagenum();
        $total_items  = EPFL404DB::record_count();

        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->items = EPFL404DB::get_log( $per_page, $current_page );
    }


    public function process_bulk_action()
    {

        //Detect when a bulk action is being triggered...
        if ( 'delete' === $this->current_action() )
        {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'epfl_404_delete_entry' ) )
            {
                die( 'Go get a life script kiddies' );
            }
            else
            {
                EPFL404DB::delete_log_entry( absint( $_GET['404entry'] ) );

                return;
            }

        }

        // If the delete bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
           || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' ))
        {

            $delete_ids = esc_sql( $_POST['bulk-delete'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id )
            {
                EPFL404DB::delete_log_entry( $id );

            }

        }
    }
}