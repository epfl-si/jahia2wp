<?PHP

class EPFL404DB
{
    const EPFL404_DB_TABLE              = 'epfl_404';
    const EPFL404_DB_FIELD_ID           = 'epfl_404_id';
    const EPFL404_DB_FIELD_URL          = 'epfl_404_url';
    const EPFL404_DB_FIELD_REFERER      = 'epfl_404_referer';
    const EPFL404_DB_FIELD_SOURCE_IP    = 'epfl_404_source_ip';
    const EPFL404_DB_FIELD_DATE         = 'epfl_404_date';


    protected static function epfl_404_get_charset()
    {
        global $wpdb;

        $charset_collate = '';
        if ( ! empty( $wpdb->charset ) )
        {
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        }

        if ( ! empty( $wpdb->collate ) )
        {
            $charset_collate .= " COLLATE=$wpdb->collate";
        }

        return $charset_collate;
    }

    /*
     * Prepare a SQL request value
     */
    protected static function prepare_sql_value($val)
    {
        return ($val===NULL) ? "NULL": "'".addslashes($val)."'";
    }


    /*
     * To create DB tables at plugin activation
     */
    public static function init()
    {
        global $wpdb;

        $charset_collate = EPFL404DB::epfl_404_get_charset();

        //$wpdb->query("DROP TABLE ".self::EPFL404_DB_TABLE);

        $sql = "CREATE TABLE IF NOT EXISTS `".self::EPFL404_DB_TABLE."` ( ".
               "`".self::EPFL404_DB_FIELD_ID."` int(11) NOT NULL AUTO_INCREMENT, ".
               "`".self::EPFL404_DB_FIELD_URL."` varchar(255) NOT NULL, ".
               "`".self::EPFL404_DB_FIELD_REFERER."` varchar(255) DEFAULT NULL,".
               "`".self::EPFL404_DB_FIELD_SOURCE_IP."` varchar(255) NOT NULL,".
               "`".self::EPFL404_DB_FIELD_DATE."` datetime NOT NULL,".
               "PRIMARY KEY (`".self::EPFL404_DB_FIELD_ID."`)".
               ") ENGINE=InnoDB ".$charset_collate." AUTO_INCREMENT=1;";

        if ( $wpdb->query( $sql ) === false )
        {
            throw new Exception( 'There was a database error installing EPFL-404: ' . $wpdb->print_error() );
        }
    }


    /*
     * Drop 404 log content from Database
     */
    public static function drop()
    {
        global $wpdb;

        $sql = "DROP TABLE ".self::EPFL404_DB_TABLE;

        if ( $wpdb->query( $sql ) === false )
        {
            throw new Exception( 'There was a database error uninstalling EPFL-404: ' . $wpdb->print_error() );
        }
    }


    /*
     * Add 404 log in DB
     * @param string $url
     * @param string $referer (can be NULL)
     * @param string $ip
     */
    public static function log($url, $referer, $ip)
    {
        global $wpdb;

        /* Params for SQL request */
        $params = [
                    self::EPFL404_DB_FIELD_ID         => "''", // auto increment
                    self::EPFL404_DB_FIELD_URL        => self::prepare_sql_value($url),
                    self::EPFL404_DB_FIELD_REFERER    => self::prepare_sql_value($referer),
                    self::EPFL404_DB_FIELD_SOURCE_IP  => self::prepare_sql_value($ip),
                    self::EPFL404_DB_FIELD_DATE       => 'NOW()'
                  ];

        /* Creating request */
        $sql = "INSERT INTO ".self::EPFL404_DB_TABLE." (".implode(",", array_keys($params)).")".
               "VALUES(".implode(",", $params).")";

        if ( $wpdb->query( $sql ) === false )
        {
            /* We don't throw an exception this time, so website continue to works correctly, only 404 logging fails */
            error_log("Error adding 404 info in database: ".$pwdb->print_error());
        }
    }


    /**
     * Retrieve 404’s data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_log( $per_page = 5, $page_number = 1 )
    {

      global $wpdb;

      $sql = "SELECT * FROM ".self::EPFL404_DB_TABLE;

      if ( ! empty( $_REQUEST['orderby'] ) ) {
        $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
        $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
      }

      $sql .= " LIMIT $per_page";

      $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


      $result = $wpdb->get_results( $sql, 'ARRAY_A' );

      return $result;
    }


    /**
     * Delete a 404 log record.
     *
     * @param int $id 404 log ID
     */
    public static function delete_log_entry( $id )
    {
      global $wpdb;


      $wpdb->delete(self::EPFL404_DB_TABLE, [ self::EPFL404_DB_FIELD_ID => $id ], [ '%d' ]);
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count() {
      global $wpdb;

      $sql = "SELECT COUNT(*) FROM ".self::EPFL404_DB_TABLE;

      return $wpdb->get_var( $sql );
    }
}