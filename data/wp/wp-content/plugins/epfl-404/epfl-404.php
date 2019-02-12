<?php
/**
 * Plugin Name: EPFL-404
 * Description: To log 404 pages
 * @version: 0.2
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once('inc/epfl-404-db.php');
require_once('inc/epfl-404-table.php');


class EPFL404
{
    static $instance;

    const DAYS_TO_KEEP_DEFAULT = 15;
    const NB_ENTRIES_TO_KEEP_DEFAULT = 1000;
    const NB_DAYS_TO_KEEP_OPTION = 'epfl-404:nb_days_to_keep';
    const NB_ENTRIES_TO_KEEP_OPTION = 'epfl-404:nb_entries_to_keep';

    public $display_table = null;
    public $nb_days_to_keep = self::DAYS_TO_KEEP_DEFAULT;

    // class constructor
	public function __construct()
	{
		add_filter( 'set-screen-option', [__CLASS__, 'set_screen'], 10, 3 );
		add_action( 'admin_menu', [$this, 'setup_admin_menu'] );

		add_action( 'template_redirect', [$this, 'log_404_calls'] );

        /* To avoid to fill database with information, we set limits */
        $this->nb_days_to_keep = get_option(self::NB_DAYS_TO_KEEP_OPTION, self::DAYS_TO_KEEP_DEFAULT);
        $this->nb_entries_to_keep = get_option(self::NB_ENTRIES_TO_KEEP_OPTION, self::NB_ENTRIES_TO_KEEP_DEFAULT);

	}


    /** Singleton instance */
    public static function get_instance()
    {
        if ( ! isset( self::$instance ) )
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /*
     * To create DB tables at plugin activation
     */
    public static function plugin_activate()
    {
        EPFL404DB::init();

        /* Add options with default value */
        update_option(self::NB_DAYS_TO_KEEP_OPTION, self::DAYS_TO_KEEP_DEFAULT);
        update_option(self::NB_ENTRIES_TO_KEEP_OPTION, self::NB_ENTRIES_TO_KEEP_DEFAULT);
    }

    /*
     * To delete plugin data
     */
    public static function plugin_uninstall()
    {
        EPFL404DB::drop();
    }


    /*
     * Logs a 404 page request
     */
    function log_404_calls()
    {
        if(!is_404()) return;

        EPFL404DB::log($_SERVER['REQUEST_URI'],
                       isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : NULL,
                       $_SERVER['REMOTE_ADDR']);

        /* Cleaning old entries */
        EPFL404DB::clean_old_entries($this->nb_days_to_keep, $this->nb_entries_to_keep);
    }


    /*
     * Create links in admin menu
     */
    public function setup_admin_menu()
    {
        $hook = add_management_page( 'EPFL 404',
                                     'EPFL 404',
                                     'manage_options',
                                     basename( __FILE__),
                                     [$this, 'render_404_list'] );

        add_action( 'load-' . $hook, [$this, 'screen_option'] );
    }


    /*
     * Displays 404 log list in a table
     */
    function render_404_list()
    {

        ?>
        <div class="wrap">
            <h2>404 List <small>(for last <?PHP echo $this->nb_days_to_keep; ?> days)</small></h2>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
                                <?php
                                $this->display_table->prepare_items();
                                $this->display_table->display(); ?>
                            </form>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        </div>
        <?PHP
    }


    public static function set_screen( $status, $option, $value )
    {
        return $value;
    }


    function screen_option()
    {
        $option = 'per_page';
        $args   = [
            'label'   => '404 URLs per page',
            'default' => 50,
            'option'  => '404_per_page'
        ];

        add_screen_option( $option, $args );

        $this->display_table = new EPFL404Table();

    }
}

add_action( 'plugins_loaded', function () {
	EPFL404::get_instance();
} );

/* Activation and uninstall */
register_activation_hook(__FILE__, ['EPFL404', 'plugin_activate']);
register_uninstall_hook( __FILE__, ['EPFL404', 'plugin_uninstall']);





