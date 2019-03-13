<?php

class EPFLQuota
{

    static $instance;

    /* Option name */
    const OPTION_USAGE = 'epfl-quota:usage';

    /* Indexes for structure which stores information */
    const OPTION_USAGE_VERSION  = 'version';
    const OPTION_USAGE_LIMIT    = 'limit';
    const OPTION_USAGE_USED     = 'used';
    const OPTION_USAGE_NB_FILES = 'nbFiles';

    /* If nothing defined when activated, there's no limit */
    const DEFAULT_LIMIT = 0;

    const CURRENT_DATA_VERSION = 1;

    /* For development purpose */
    const DEBUG = false;


    /*
        Class constructor
    */
    public function __construct()
    {
        /* Nothing to do here */
    }


    /*
        For development purpose
    */
    protected function debug($msg)
    {
        if(self::DEBUG)
        {
            error_log('epfl-quota: '.$msg);
        }
    }


    /*
        Singleton instance
    */
    public static function get_instance()
    {
        if ( ! isset( self::$instance ) )
        {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /*
        List all medias (the ones in DB) and get their size on disk to know what is used.

        Returns an array with used size in MB and number of files
    */
    public static function get_usage_on_disk()
    {
        /* Preparing query to get all attachments */
        $query_attachments = array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            );

        /* Listing all attachmenets */
        $attachments = get_posts( $query_attachments );

        /* Calculating total used size */
        $attachments_size_mb = 0;
        foreach ( $attachments as $att )
        {
            $attachments_size_mb += filesize(get_attached_file($att->ID))/1024/1024;
        }
        return [self::OPTION_USAGE_USED     => $attachments_size_mb,
                self::OPTION_USAGE_NB_FILES => sizeof($attachments)];
    }


    /*
        Initialize or update plugin infos in database. Add option if doesn't exists or update it if exists.
        - Get size used on disk by medias
        - Initialize plugin options if doesn't exists
        - Update plugin options (used size) if exists
    */
    public static function init_update()
    {
        $used_infos = EPFLQuota::get_usage_on_disk();

        /* If option is not in database, */
        if(!($current_usage = get_option(self::OPTION_USAGE)))
        {
            /* We add it */
            add_option(self::OPTION_USAGE, [self::OPTION_USAGE_VERSION  => self::CURRENT_DATA_VERSION,
                                            self::OPTION_USAGE_USED     => $used_infos[self::OPTION_USAGE_USED],
                                            self::OPTION_USAGE_NB_FILES => $used_infos[self::OPTION_USAGE_NB_FILES],
                                            self::OPTION_USAGE_LIMIT    => self::DEFAULT_LIMIT]);
        }
        else /* Option exists in DB */
        {

            /* TODO: if data structure changes in the future, you will have to check for existing version and update it as needed */

            /* We update used size */
            $current_usage[self::OPTION_USAGE_USED]     = $used_infos[self::OPTION_USAGE_USED];
            $current_usage[self::OPTION_USAGE_NB_FILES] = $used_infos[self::OPTION_USAGE_NB_FILES];

            EPFLQuota::set_current_usage($current_usage);
        }
    }


    /*
        Called before an upload starts and checks if there's enough quota left to upload the file.
        If not, we just add an 'error' entry in $file array.

        NOTE: when multiple files are selected for upload, they are processed one by one and this function
              is called for each one

        IN : $file -> associative array with file information.

        Returns given $file associative array update with 'error' key added if not enough quota.
    */
    public function check_upload_allowed($file)
    {
        $current_usage = $this->get_current_usage();

        /* If there's no limit */
        if($current_usage[self::OPTION_USAGE_LIMIT] == 0)
        {
            $this->debug("No quota, upload of '".$file['name']."' allowed");
            return $file;
        }

        $filesize_mb = $file['size']/1024/1024;

        /* If not enough quota for upload */
        if($current_usage[self::OPTION_USAGE_USED] + $filesize_mb > $current_usage[self::OPTION_USAGE_LIMIT])
        {
            /* Adding an 'error' entry to $file will make WordPress displaying it as an error so the user will be
            informed. */
            $file['error'] = sprintf(__('You reached the limit of %s MB quota', 'epfl-quota'), $current_usage[self::OPTION_USAGE_LIMIT]);
            $this->debug("NOT enough quota, upload of '".$file['name']."' (".$filesize_mb." MB) denied");
        }
        else /* Enough quota */
        {
            $this->debug("Enough quota, upload of '".$file['name']."' allowed");
        }

        return $file;
    }


    /*
        Called when an upload is done. It updates used size in the database

        IN : $attachment_id -> ID of added file.
    */
    public function after_upload($attachment_id)
    {
        $current_usage = $this->get_current_usage();

        $this->debug("Updating used size after upload - old = ".$current_usage[self::OPTION_USAGE_USED]." MB");
        $current_usage[self::OPTION_USAGE_USED] += filesize(get_attached_file($attachment_id))/1024/1024;
        $current_usage[self::OPTION_USAGE_NB_FILES] += 1;
        $this->debug("Updating used size after upload - new = ".$current_usage[self::OPTION_USAGE_USED]." MB");

        EPFLQuota::set_current_usage($current_usage);
    }


    /*
        Called when a request is done to delete an attachment and WordPress calls it BEFORE the attachment is
        deleted from disk (and removed from DB).
        This function updates disk used size by going through all attachment. We could just decrease used size
        to the total used size (like we do in 'after_upload' function but sometimes rebuilding all information
        from "scratch" is useful. it can take some resources depending on number of medias so we avoid to do it
        too frequently.

        IN : $attachment_id -> ID of file that will be deleted.
    */
    public function delete_attachment($attachment_id)
    {
        $current_usage = $this->get_current_usage();

        $this->debug("Updating used size after deletion- old = ".$current_usage[self::OPTION_USAGE_USED]." MB");

        /* We get current usage on disk */
        $used_infos = EPFLQuota::get_usage_on_disk();

        /* Because file hasn't been deleted yet, we have to remove informations from stats */
        $used_infos[self::OPTION_USAGE_NB_FILES] -= 1;

        /* If there's no file anymore */
        if($used_infos[self::OPTION_USAGE_NB_FILES] == 0)
        {
            /* We set usage to 0 instead of substracting removed file size, to avoid rounding problems */
            $used_infos[self::OPTION_USAGE_USED] = 0;
        }
        else
        {
            $used_infos[self::OPTION_USAGE_USED] -= filesize(get_attached_file($attachment_id))/1024/1024;
        }

        $current_usage[self::OPTION_USAGE_USED]     = $used_infos[self::OPTION_USAGE_USED];
        $current_usage[self::OPTION_USAGE_NB_FILES] = $used_infos[self::OPTION_USAGE_NB_FILES];
        $this->debug("Updating used size after deletion - new = ".$current_usage[self::OPTION_USAGE_USED]." MB (calculated from real disk usage)");

        EPFLQuota::set_current_usage($current_usage);
    }


    /*
        Returns sizes information stored in database. If information doesn't exists in database, we call plugin
        activation function which will create the option with correct values.
    */
    protected function get_current_usage()
    {
        if( !($current = get_option(self::OPTION_USAGE)))
        {
            EPFLQuota::init_update();
            $current = get_option(self::OPTION_USAGE);
        }

        return $current;
    }


    /*
        Save usage information in database.

        IN  : $current_usage  -> array with information
    */
    public static function set_current_usage($current_usage)
    {
        /* Update option in database */
        update_option(self::OPTION_USAGE, $current_usage);

        /* Update gauge information for stats (we transform sizes to have bytes instead of MB) */
        do_action('epfl_stats_media_size_and_count',
                  $current_usage[self::OPTION_USAGE_USED]*1024*1024,
                  $current_usage[self::OPTION_USAGE_LIMIT]*1024*1024,
                  $current_usage[self::OPTION_USAGE_NB_FILES]);
    }


    /*
        Adds a notice on 'upload' admin page to tell user which size is used by medias.
    */
    public function display_current_size_usage()
    {
        if(get_current_screen()->id == 'upload')
        {
            $current_usage = $this->get_current_usage();

            $current_used = number_format($current_usage[self::OPTION_USAGE_USED], 2);

            /* If quota is unlimited */
            if($current_usage[self::OPTION_USAGE_LIMIT] == 0)
            {
                $quota_status = sprintf(__('You are using %s MB for medias', 'epfl-quota' ), $current_used);
            }
            else
            {
                $percent_used = number_format($current_usage[self::OPTION_USAGE_USED]*100/$current_usage[self::OPTION_USAGE_LIMIT], 1);
                $quota_status = sprintf(__('You are using %s MB of %s MB quota (%s %%)', 'epfl-quota' ), $current_used, $current_usage[self::OPTION_USAGE_LIMIT] ,$percent_used);
            }

    ?>
    <div class="notice notice-info">
        <img src="<?php echo plugins_url( 'img/gauge.svg', __FILE__ ); ?>" style="height:32px; width:32px; float:left; margin:3px 15px 3px 0px;">
        <p><?php echo $quota_status; ?></p>
    </div>
    <?php
        }
    }

}


add_action( 'plugins_loaded', function () {
	$instance = EPFLQuota::get_instance();

    /* Add different filters */
	add_filter('wp_handle_upload_prefilter', [$instance, 'check_upload_allowed'] );
    add_filter('add_attachment', [$instance, 'after_upload'], 0, 1);
    add_filter('delete_attachment', [$instance, 'delete_attachment'], 0, 1);
    add_action( 'admin_notices', [$instance, 'display_current_size_usage'] );

    /* Loads translations */
    load_plugin_textdomain( 'epfl-quota', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
} );

/* If manual init/update process has been triggered,
 NOTE: this can be done by adding "epflquotainitupdate" as GET parameter in an URL */
if(array_key_exists('epflquotainitupdate', $_GET))
{

    EPFLQuota::init_update();
}

