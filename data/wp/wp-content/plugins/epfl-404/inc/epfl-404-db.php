<?PHP

class EPFL404DB
{
    /* Table information/structure */
    const EPFL404_DB_TABLE              = 'epfl_404';
    const EPFL404_DB_FIELD_ID           = 'epfl_404_id';
    const EPFL404_DB_FIELD_URL          = 'epfl_404_url';
    const EPFL404_DB_FIELD_REFERER      = 'epfl_404_referer';
    const EPFL404_DB_FIELD_SOURCE_IP    = 'epfl_404_source_ip';
    const EPFL404_DB_FIELD_DATE         = 'epfl_404_date';

    /* Temporary request fields */
    const EPFL404_QUERY_FIELD_URL_AND_REFERER = 'epfl_404_urlref';
    const EPFL404_QUERY_FIELD_NB_OCCUR        = 'epfl_404_nb_occur';
    const EPFL404_QUERY_FIELD_LAST_OCCUR      = 'epfl_404_last_occur';

    /*
     * Returns DB charset
     */
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
     * @param string $val
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
     * Cleans old records
     * @param int $nb_days_to_keep
     * @param int $nb_entries_to_keep
     */
    public static function clean_old_entries($nb_days_to_keep, $nb_entries_to_keep)
    {
        global $wpdb;

        $sql = "DELETE FROM ".self::EPFL404_DB_TABLE.
               " WHERE ".self::EPFL404_DB_FIELD_DATE."< DATE_SUB(NOW(), INTERVAL ".$nb_days_to_keep." DAY)".
               " OR ".self::EPFL404_DB_FIELD_ID."< (MAX(".self::EPFL404_DB_FIELD_ID.")-".$nb_entries_to_keep.")" ;

        if ( $wpdb->query( $sql ) === false )
        {
            /* We don't throw an exception this time, so website continue to works correctly, only 404 logging fails */
            error_log("epfl-404: Error cleaning old 404 entries: ".$wpdb->print_error());
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
    public static function get_log( $per_page = 10, $page_number = 1 )
    {

      global $wpdb;

      /* First request */
      $sql = "SELECT *, CONCAT(".self::EPFL404_DB_FIELD_URL.", IFNULL(".self::EPFL404_DB_FIELD_REFERER.",'-')) AS '".self::EPFL404_QUERY_FIELD_URL_AND_REFERER."' FROM ".self::EPFL404_DB_TABLE;

      /* We add a surrounding request to have request count and last occurence */
      $sql = "SELECT *, COUNT(".self::EPFL404_QUERY_FIELD_URL_AND_REFERER.")AS '".self::EPFL404_QUERY_FIELD_NB_OCCUR."', ".
             "MAX(".self::EPFL404_DB_FIELD_DATE.") AS '".self::EPFL404_QUERY_FIELD_LAST_OCCUR."' FROM (". $sql .") AS t_tmp ".
             "GROUP BY ".self::EPFL404_QUERY_FIELD_URL_AND_REFERER;


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
     * Delete a 404 log record. In fact we will get an ID from a record and because all records are displayed
       group by URL/referer combination, we will recover URL and referer for givent ID and then delete all
       rows with same URL/referer combination
     *
     * @param int $id entry ID to delete.
     */
    public static function delete_log_entry( $id )
    {
      global $wpdb;

      $sql = "SELECT * FROM ".self::EPFL404_DB_TABLE." WHERE ".self::EPFL404_DB_FIELD_ID."='".$id."'";

      $result = $wpdb->get_results( $sql, 'ARRAY_A' );

      if(sizeof($result)>0)
      {
        /* Data deletion */
        $wpdb->delete(self::EPFL404_DB_TABLE, [ self::EPFL404_DB_FIELD_URL => $result[0][self::EPFL404_DB_FIELD_URL],
                                                self::EPFL404_DB_FIELD_REFERER => $result[0][self::EPFL404_DB_FIELD_REFERER] ]);
      }
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