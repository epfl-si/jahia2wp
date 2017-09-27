<?php
/**
 * Filename: counter-core.php
 * Count Per Day - core functions
 */

if (!defined('ABSPATH'))
	exit;

/**
 * include GeoIP addon if available
 */
$cpd_geoip_dir = WP_CONTENT_DIR.'/count-per-day-geoip/';
if ( file_exists($cpd_geoip_dir.'geoip.inc') && filesize($cpd_geoip_dir.'geoip.inc') > 1000 ) // not empty
	include_once('geoip.php');
$cpd_geoip = ( class_exists('GeoIp') && file_exists($cpd_geoip_dir.'GeoIP.dat') && filesize($cpd_geoip_dir.'GeoIP.dat') > 1000 ) ? 1 : 0;

/**
 * helper functions
 */
class CountPerDayCore
{

var $options;			// options array
var $dir;				// this plugin dir
var $dbcon;				// database connection
var $queries = array();	// queries times for debug
var $page;				// Post/Page-ID
var $installed = false; // CpD installed in subblogs?

/**
 * Constructor
 */
function init()
{
	// variables
	global $wpdb, $path, $cpd_dir_name;
	
	define('CPD_METABOX', 'cpd_metaboxes');
		
	// multisite table names
	foreach ( array('cpd_counter','cpd_counter_useronline','cpd_notes') as $t )
	{
		$wpdb->tables[] = $t;
		$wpdb->$t = $wpdb->get_blog_prefix().$t;
	}

	// use local time, not UTC
	get_option('gmt_offset');
	
	$this->options = get_option('count_per_day');
	
	// manual debug mode
	if (!empty($_GET['debug']) && WP_DEBUG )
		$this->options['debug'] = 1;
	$this->dir = plugins_url('/'.$cpd_dir_name);
	
	$this->queries[0] = 0;

	// update online counter
	add_action('wp', array(&$this,'deleteOnlineCounter'));
	
	// settings link on plugin page
	add_filter('plugin_action_links', array(&$this,'pluginActions'), 10, 2);
	
	// auto counter
	if ($this->options['autocount'])	
		add_action('wp', array(&$this,'count'));

	// javascript to count cached posts
	if ($this->options['ajax'])
	{
		add_action('wp_enqueue_scripts', array(&$this,'addJquery'));
 		add_action('wp_footer', array(&$this,'addAjaxScript'));
	}

	if (is_admin())
	{
		// admin menu
		add_action('admin_menu', array(&$this,'menu'));
		// widget on dashboard page
		add_action('wp_dashboard_setup', array(&$this,'dashboardWidgetSetup'));
		// CpD dashboard page
		add_filter('screen_layout_columns', array(&$this,'screenLayoutColumns'), 10, 2);
		// CpD dashboard
		add_action('admin_menu', array(&$this,'setAdminMenu'));
		// counter column posts lists
		add_action('admin_head', array(&$this,'addPostTypesColumns'));
		// adds javascript
		add_action('admin_head', array(&$this,'addJS'));
		// check version
		add_action('admin_head', array(&$this,'checkInstalledVersion'));
	}
	
	// locale support
	load_plugin_textdomain('cpd', false, $cpd_dir_name.'/locale');
		 
	// adds stylesheet
	if (is_admin())
		add_action('admin_head', array(&$this,'addCss'));
	if ( empty($this->options['no_front_css']) )
		add_action('wp_head', array(&$this,'addCss'));
	
	// widget setup
	add_action('widgets_init', array( &$this,'register_widgets'));
	
	// activation hook
	register_activation_hook(ABSPATH.PLUGINDIR.'/count-per-day/counter.php', array(&$this,'checkVersion'));
	
	// update hook
	if (function_exists('register_update_hook'))
		register_update_hook(ABSPATH.PLUGINDIR.'/count-per-day/counter.php', array(&$this,'checkVersion'));
	
	// uninstall hook
	register_uninstall_hook($path.'counter.php', 'count_per_day_uninstall');
	
	// query times debug
	if ($this->options['debug'])
	{
		add_action('wp_footer', array(&$this,'showQueries'));
		add_action('admin_footer', array(&$this,'showQueries'));
	}
	
	// add shortcode support
	$this->addShortcodes();
	
	// thickbox in backend only
	if (strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/') !== false )
		add_action('admin_enqueue_scripts', array(&$this,'addThickbox'));
	
	$this->aton = 'INET_ATON';
	$this->ntoa = 'INET_NTOA';

	// include scripts
	if (is_admin())
	{
		add_action('init', array(&$this,'addCpdIncludes'));
	}
}

/**
 * include scripts
 */
function addCpdIncludes()
{
	global $count_per_day, $wpdb, $cpd_geoip, $cpd_geoip_dir;
	
	if (empty($_GET['page']))
		return;

	switch ($_GET['page'])
	{
	case 'cpd_notes':
		include_once('notes.php');
		exit;
	case 'cpd_massbots':
		include_once('massbots.php');
		exit;
	case 'cpd_userperspan':
		include_once('userperspan.php');
		exit;
	case 'cpd_map':
		include_once('map/map.php');
		exit;
	case 'cpd_ajax':
		include_once('ajax.php');
		exit;
	case 'cpd_download':
		include_once('download.php');
		exit;
	}
}

/**
 * adds counter columns to posts list
 */
function addPostTypesColumns()
{
	$post_types = get_post_types(array('public'=>true),'objects');
	foreach ($post_types as $post_type )
	{
		$name = trim($post_type->name);
		add_action('manage_'.$name.'s_custom_column', array(&$this,'cpdColumnContent'), 10, 2);
		add_filter('manage_edit-'.$name.'_columns', array(&$this,'cpdColumn'));
	}
}

function addJquery()
{
	wp_enqueue_script('jquery');
}

function addThickbox()
{
	wp_enqueue_script('thickbox');
}

/**
 * get result from database
 * @param string $kind kind of result
 * @param string|array $sql sql query [and args]
 * @param string $func name for debug info
 */
function mysqlQuery( $kind = '', $sql = '', $func = '' )
{
	global $wpdb;
	if (empty($sql))
		return;
	$t = microtime(true);
	
	if ( is_array($sql) )
	{
		$sql = array_shift($sql);
		$args = $sql;
		$preparedSql = $wpdb->prepare($sql, $args);
	}
	else
		$preparedSql = $sql;
	
	if (empty($preparedSql))
		return;
	
	$r = false;
	if ($kind == 'var')
		$r = $wpdb->get_var( $preparedSql );
	else if ($kind == 'count')
		$r = $wpdb->get_var('SELECT COUNT(*) FROM ('.trim($preparedSql,';').') t');
	else if ($kind == 'rows')
		$r = $wpdb->get_results( $preparedSql );
	else
		$wpdb->query( $preparedSql );

	if ( $this->options['debug'] )
	{
		$con = $wpdb->dbh;
		$errno = (isset($con->errno)) ? $con->errno : mysqli_errno($con);
		$error = (isset($con->error)) ? $con->error : mysqli_error($con);
		$d = number_format( microtime(true) - $t , 5);
		$m = sprintf("%.2f", memory_get_usage()/1048576).' MB';
		$error = (!$r && $errno) ? '<b style="color:red">ERROR:</b> '.$errno.' - '.$error.' - ' : '';
		$this->queries[] = $func." : <b>$d</b> - $m<br/><code>$kind - $preparedSql</code><br/>$error";
		$this->queries[0] += $d;
	}
	
	return $r;
}

/**
 * update DB if neccessary
 */
function checkInstalledVersion()
{
	global $cpd_version, $cpd_dir_name;
	if ( $this->options['version'] != $cpd_version )
	{
		$this->checkVersion();
		echo '<div class="updated">
			<p>'.sprintf(__('"Count per Day" updated to version %s.', 'cpd'), $cpd_version).'</p>
			<p>'.sprintf(__('Please check the %s section!', 'cpd'), '<a href="options-general.php?page=count-per-day%2Fcounter-options.php&tab=tools">GeoIP</a>').'</p>
			</div>';
	}
}

/**
 * anonymize IP address (last bit) if option is set
 * @param $ip real IP address
 * @return new IP address
 */
function anonymize_ip( $ip )
{
	// only IPv4
	if( !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) )
		return $ip;
	
	if ( $this->options['debug'] )
		$this->queries[] = 'called Function: <b style="color:blue">anonymize_ip</b> IP: <code>'.$ip.'</code>';
	if ($this->options['anoip'])
	{
		$i = explode('.', $ip);
		if (isset($i[3]))
		{
			$i[3] += round( array_sum($i) / 4 + date_i18n('d') );
			if ( $i[3] > 255 )
				$i[3] -= 255;
		}
		return implode('.', $i);	
	}
	else
		return $ip;
}

/**
 * gets PostID
 */
function getPostID()
{
	global $wp_query;
	// find PostID
	if ( !is_404() ) :
		// index, date, search and other "list" pages will count only once
		$p = 0;
		if ( $this->options['autocount'] && is_singular() )
		{
			// single page with autocount on
			// make loop before regular loop is defined
			if (have_posts()) :
				while ( have_posts() && empty($p) ) :
					the_post();
					$p = get_the_ID();
				endwhile;
			endif;
			rewind_posts();
		}
		else if (is_singular())
			// single page with template tag show() or count()
			$p = get_the_ID();
		// "index" pages only with autocount
		else if ( is_category() || is_tag() )
			// category or tag => negativ ID in CpD DB
			$p = 0 - $wp_query->get_queried_object_id();
		$this->page = $p;
		if ( $this->options['debug'] )
			$this->queries[] = 'called Function: <b style="color:blue">getPostID</b> page ID: <code>'.$p.'</code>';
		return $p;
	endif;
	
	return false;
}

/**
 * bot or human?
 * @param string $client USER_AGENT
 * @param array $bots strings to check
 * @param string $ip IP adress
 * @param string $ref referrer
 */
function isBot( $client = '', $bots = '', $ip = '', $ref = '' )
{
	if ( empty($client) && isset($_SERVER['HTTP_USER_AGENT']) )
		$client = $_SERVER['HTTP_USER_AGENT'];
	if (empty($ip))
		$ip = $_SERVER['REMOTE_ADDR'];
	if ( empty($ref) && isset($_SERVER['HTTP_REFERER']) )
		$ref = $_SERVER['HTTP_REFERER'];

	// empty/short client -> not normal browser -> bot
	if ( empty($client) || strlen($client) < 20 )
		return true;
	
	if (empty($bots))
		$bots = explode( "\n", $this->options['bots'] );

	$isBot = false;
	foreach ( $bots as $bot )
	{
		if (!$isBot) // loop until first bot was found only
		{
			$b = trim($bot);
			if ( !empty($b)
				&& (
					$ip == $b
					|| strpos( $ip, $b ) === 0
					|| strpos( strtolower($client), strtolower($b) ) !== false
					|| strpos( strtolower($ref), strtolower($b) ) !== false 
				)
			)
				$isBot = true;
		}
	}
	return $isBot;
}

/**
 * checks installation in sub blogs 
 */
function checkVersion()
{
	global $wpdb;
	if ( function_exists('is_multisite') && is_multisite() )
	{
		// check if it is a network activation
		if (!empty($_GET['networkwide']))
		{
			$old_blog = $wpdb->blogid;
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM %s", $wpdb->blogs));
			foreach ($blogids as $blog_id)
			{
				// create tables in all sub blogs
				switch_to_blog($blog_id);
				$this->createTables();
			}
			switch_to_blog($old_blog);
			return;
		}	
	}
	// create tables in main blog
	$this->createTables();
}

/**
 * creates tables if not exists
 */
function createTables()
{
	global $wpdb;
	// for plugin activation, creates $wpdb
	require_once(ABSPATH.'wp-admin/includes/upgrade.php');
	
	// variables for subblogs
	$cpd_c = $wpdb->cpd_counter;
	$cpd_o = $wpdb->cpd_counter_useronline;
	$cpd_n = $wpdb->cpd_notes;

	if (!empty ($wpdb->charset))
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	if (!empty ($wpdb->collate))
		$charset_collate .= " COLLATE $wpdb->collate";
 	
	// table "counter"
	$sql = "CREATE TABLE IF NOT EXISTS `$cpd_c` (
	`id` int(10) NOT NULL auto_increment,
	`ip` int(10) unsigned NOT NULL,
	`client` varchar(500) NOT NULL,
	`date` date NOT NULL,
	`page` mediumint(9) NOT NULL,
	`country` CHAR(2) NOT NULL,
	`referer` varchar(500) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_page` (`page`),
	KEY `idx_dateip` (`date`,`ip`) )
	$charset_collate";
	$this->mysqlQuery('', $sql, 'createTables '.__LINE__);
	
	// update fields in old table
	$field = $this->mysqlQuery('rows', "SHOW FIELDS FROM `$cpd_c` LIKE 'ip'", 'createTables '.__LINE__);
	$row = $field[0];
	if ( strpos(strtolower($row->Type), 'int') === false )
	{
		$queries = array (
		"ALTER TABLE `$cpd_c` ADD `ip2` INT(10) UNSIGNED NOT NULL AFTER `ip`",
		"UPDATE `$cpd_c` SET ip2 = $this->aton(ip)",
		"ALTER TABLE `$cpd_c` DROP `ip`",
		"ALTER TABLE `$cpd_c` CHANGE `ip2` `ip` INT( 10 ) UNSIGNED NOT NULL",
		"ALTER TABLE `$cpd_c` CHANGE `date` `date` date NOT NULL",
		"ALTER TABLE `$cpd_c` CHANGE `page` `page` mediumint(9) NOT NULL",
		"ALTER TABLE `$cpd_c` CHANGE `client` `client` VARCHAR( 250 ) NOT NULL",
		"ALTER TABLE `$cpd_c` CHANGE `referer` `referer` VARCHAR( 250 ) NOT NULL"
		);
		foreach ($queries as $sql)
			$this->mysqlQuery('', $sql, 'update old fields '.__LINE__);
	}
	
	// change textfield limit
	$sql = "ALTER TABLE `$cpd_c`
		CHANGE `client` `client` VARCHAR( 500 ) NOT NULL ,
		CHANGE `referer` `referer` VARCHAR( 500 ) NOT NULL;";
	$this->mysqlQuery('', $sql, 'change textfield limit '.__LINE__);
	
	// make new keys
	$keys = $this->mysqlQuery('rows', "SHOW KEYS FROM `$cpd_c`", 'make keys '.__LINE__);
	$s = array();
	foreach ($keys as $row)
		if ( $row->Key_name != 'PRIMARY' )
			$s[] = "DROP INDEX `$row->Key_name`";
	$s = array_unique($s);
		
	$sql = "ALTER TABLE `$cpd_c` ";
	if (sizeof($s))
		$sql .= implode(',', $s).', ';
	$sql .= 'ADD KEY `idx_dateip` (`date`,`ip`), ADD KEY `idx_page` (`page`)';
	$this->mysqlQuery('', $sql, 'make keys '.__LINE__);
	
	// delete table "counter-online", since v3.0
	$this->mysqlQuery('', "DROP TABLE IF EXISTS `$cpd_o`", 'table online '.__LINE__);
			
	// delete table "notes", since v3.0
	if (!get_option('count_per_day_notes'))
	{
		$table = $this->mysqlQuery('rows', "SHOW TABLES LIKE '$cpd_n'", 'table notes '.__LINE__);
		if (!empty($table))
		{
			$ndb = $this->mysqlQuery('rows', "SELECT * FROM $cpd_n", 'table notes '.__LINE__);
			$n = array();
			foreach ($ndb as $note)
				$n[] = array( $note->date, $note->note );
			update_option('count_per_day_notes', $n);
		}
	}
	$this->mysqlQuery('', "DROP TABLE IF EXISTS `$cpd_n`", 'table notes '.__LINE__);
	
	// update options to array
	$this->updateOptions();
}

/**
 * calls widget class
 */
function register_widgets()
{
	register_widget('CountPerDay_Widget');
	register_widget('CountPerDay_PopularPostsWidget');
	
}

/**
 * shows debug infos
 */
function showQueries()
{
	global $wpdb, $cpd_path, $cpd_version, $cpd_geoip_dir;
	
	$serverinfo = (isset($wpdb->dbh->server_info)) ? $wpdb->dbh->server_info : mysqli_get_server_info($wpdb->dbh);
	$clientinfo = (isset($wpdb->dbh->client_info)) ? $wpdb->dbh->client_info : mysqli_get_client_info();
	
	echo '<div style="position:absolute;margin:10px;padding:10px;border:1px red solid;background:#fff;clear:both">
		<b>Count per Day - DEBUG: '.round($this->queries[0], 3).' s</b><ol>'."\n";
	echo '<li>'
		.'<b>Server:</b> '.$_SERVER['SERVER_SOFTWARE'].'<br/>'
		.'<b>PHP:</b> '.phpversion().'<br/>'
		.'<b>mySQL Server:</b> '.$serverinfo.'<br/>'
		.'<b>mySQL Client:</b> '.$clientinfo.'<br/>'
		.'<b>WordPress:</b> '.get_bloginfo('version').'<br/>'
		.'<b>Count per Day:</b> '.$cpd_version.'<br/>'
		.'<b>Time for Count per Day:</b> '.date_i18n('Y-m-d H:i').'<br/>'
		.'<b>URL:</b> '.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'].'<br/>'
		.'<b>Referrer:</b> '.(isset($_SERVER['HTTP_REFERER']) ? htmlentities($_SERVER['HTTP_REFERER']) : '').'<br/>'
		.'<b>PHP-Memory:</b> peak: '.$this->formatBytes(memory_get_peak_usage()).', limit: '.ini_get('memory_limit')
		.'</li>';
	echo "\n<li><b>POST:</b><br/>\n";
	var_dump($_POST);
	echo '</li>';
	echo '</li>';
	echo "\n<li><b>Table:</b><br /><b>$wpdb->cpd_counter</b>:\n";
	$res = $this->mysqlQuery('rows', "SHOW FIELDS FROM `$wpdb->cpd_counter`", 'showFields' );
	foreach ($res as $c)
		echo '<span style="color:blue">'.$c->Field.'</span> = '.$c->Type.' &nbsp; ';
	echo "\n</li>";
	echo "\n<li><b>Options:</b><br />\n";
	foreach ( $this->options as $k=>$v )
		if ( $k != 'bots') // hoster restrictions
			echo "$k  = $v<br />\n";
	echo "</li>";
	foreach($this->queries as $q)
		if ($q != $this->queries[0])
			echo "\n<li>$q</li>";
	echo "</ol>\n";
	?>
	<p>GeoIP: 
		dir=<?php echo substr(decoct(fileperms($cpd_geoip_dir)), -3) ?>
		file=<?php echo (is_file($cpd_geoip_dir.'GeoIP.dat')) ? substr(decoct(fileperms($cpd_geoip_dir.'GeoIP.dat')), -3) : '-'; ?>
		fopen=<?php echo (function_exists('fopen')) ? 'true' : 'false' ?>
		gzopen=<?php echo (function_exists('gzopen')) ? 'true' : 'false' ?>
		allow_url_fopen=<?php echo (ini_get('allow_url_fopen')) ? 'true' : 'false' ?>
	</p>
	<?php
	echo '</div>';
}

/**
 * adds style sheet to admin header
 */
function addCss()
{
	global $text_direction;
	echo "\n".'<link rel="stylesheet" href="'.$this->dir.'/counter.css" type="text/css" />'."\n";
	if ( $text_direction == 'rtl' ) 
		echo "\n".'<link rel="stylesheet" href="'.$this->dir.'/counter-rtl.css" type="text/css" />'."\n";
	// thickbox style here because add_thickbox() breaks RTL in he_IL
	if ( strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/') !== false )
		echo '<link rel="stylesheet" href="'.get_bloginfo('wpurl').'/wp-includes/js/thickbox/thickbox.css" type="text/css" />'."\n";
}

/**
 * adds javascript to admin header
 */
function addJS()
{
	if (strpos($_SERVER['QUERY_STRING'], 'cpd_metaboxes') !== false )
		echo '<!--[if IE]><script type="text/javascript" src="'.$this->dir.'/js/excanvas.min.js"></script><![endif]-->'."\n";
}

/**
 * adds ajax script to count cached posts
 */
function addAjaxScript()
{
	$this->getPostID();
	$wp = ADMIN_COOKIE_PATH;
	echo <<< JSEND
<script type="text/javascript">
// Count per Day
//<![CDATA[
var cpdTime = new Date().getTime() / 1000;
jQuery(document).ready( function()
{
	jQuery.get('{$wp}/?page=cpd_ajax&f=count&cpage={$this->page}&time='+cpdTime, function(text)
	{
		var cpd_funcs = text.split('|');
		for(var i = 0; i < cpd_funcs.length; i++)
		{
			var cpd_daten = cpd_funcs[i].split('===');
			var cpd_field = document.getElementById('cpd_number_' + cpd_daten[0].toLowerCase());
			if (cpd_field != null) { cpd_field.innerHTML = cpd_daten[1]; }
		}
	});
} );
//]]>
</script>
JSEND;
}

/**
 * deletes spam in table, if you add new bot pattern you can clean the db
 */
function cleanDB()
{
	global $wpdb;
	
	// get trimed bot array
	function trim_value(&$value) { $value = trim($value); }
	$bots = explode( "\n", $this->options['bots'] );
	array_walk($bots, 'trim_value');
	
	$rows_before = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter", 'cleanDB '.__LINE__);

	// delete by ip
	foreach( $bots as $ip )
		if ( intval($ip) > 0 )
			$this->mysqlQuery('', "DELETE FROM $wpdb->cpd_counter WHERE {$this->ntoa}(ip) LIKE '".$ip."%'", 'clenaDB_ip '.__LINE__);
	
	// delete by client
	foreach ($bots as $bot)
		if ( intval($bot) == 0 )
			$this->mysqlQuery('', "DELETE FROM $wpdb->cpd_counter WHERE client LIKE '%".$bot."%'", 'cleanDB_client '.__LINE__);
	
	// delete if a previously countered page was deleted
	$this->mysqlQuery('', "DELETE FROM $wpdb->cpd_counter WHERE page NOT IN ( SELECT id FROM $wpdb->posts) AND page > 0", 'cleanDB_delPosts '.__LINE__);
	
	$rows_after = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter", 'cleanDB '.__LINE__);
	return $rows_before - $rows_after;
}

/**
 * adds menu entry to backend
 * @param string $content WP-"Content"
 */
function menu($content)
{
	global $cpd_dir_name;
	if (function_exists('add_options_page'))
	{
		$menutitle = 'Count per Day';
		add_options_page('CountPerDay', $menutitle, 'manage_options', $cpd_dir_name.'/counter-options.php') ;
	}
}
	
/**
 * adds an "settings" link to the plugins page
 */
function pluginActions($links, $file)
{
	global $cpd_dir_name;
	if( $file == $cpd_dir_name.'/counter.php'
		&& strpos( $_SERVER['SCRIPT_NAME'], '/network/') === false ) // not on network plugin page
	{
		$link = '<a href="options-general.php?page='.$cpd_dir_name.'/counter-options.php">'.__('Settings').'</a>';
		array_unshift( $links, $link );
	}
	return $links;
}

/**
 * combines the options to one array, update from previous versions
 */
function updateOptions()
{
	global $cpd_version;
	
	$o = get_option('count_per_day', array());
	$this->options = array('version' => $cpd_version);
	$odefault = array(
	'onlinetime' => 300,
	'user' => 0,
	'user_level' => 0,
	'autocount' => 1,
	'bots' => "bot\nspider\nsearch\ncrawler\nask.com\nvalidator\nsnoopy\nsuchen.de\nsuchbaer.de\nshelob\nsemager\nxenu\nsuch_de\nia_archiver\nMicrosoft URL Control\nnetluchs",
	'dashboard_posts' => 20,
	'dashboard_last_posts' => 20,
	'dashboard_last_days' => 7,
	'show_in_lists' => 1,
	'chart_days' => 60,
	'chart_height' => 200,
	'countries' => 20,
	'exclude_countries' => '',
	'startdate' => '',
	'startcount' => '',
	'startreads' => '',
	'anoip' => 0,
	'massbotlimit' => 25,
	'clients' => 'Firefox, Edge, MSIE, Chrome, Safari, Opera',
	'ajax' => 0,
	'debug' => 0,
	'referers' => 1,
	'referers_cut' => 0,
	'fieldlen' => 150,
	'localref' => 1,
	'dashboard_referers' => 20,
	'referers_last_days' => 7,
	'no_front_css' => 0,
	'whocansee' => 'publish_posts',
	'backup_part' => 10000
	);
	foreach ($odefault as $k => $v)
		$this->options[$k] = (isset($o[$k])) ? $o[$k] : $v;
	update_option('count_per_day', $this->options);
}

/**
 * adds widget to dashboard page
 */
function dashboardWidgetSetup()
{
	$can_see = str_replace(
		// administrator, editor, author, contributor, subscriber
		array(10, 7, 2, 1, 0),
		array('manage_options', 'moderate_comments', 'edit_published_posts', 'edit_posts', 'read'),
		$this->options['show_in_lists']);
	if ( current_user_can($can_see) )
		wp_add_dashboard_widget( 'cpdDashboardWidget', 'Count per Day', array(&$this,'dashboardWidget') );
}

/**
 * gets image recource with given name
 */
function img( $r )
{
	return trailingslashit( $this->dir ).'img/'.$r;
}

/**
 * sets columns on dashboard page
 */ 
function screenLayoutColumns($columns, $screen)
{
	if ( isset($this->pagehook) && $screen == $this->pagehook )
		$columns[$this->pagehook] = 4;
	return $columns;
}

/**
 * extends the admin menu 
 */
function setAdminMenu()
{
	$menutitle = 'Count per Day';
	$this->pagehook = add_submenu_page('index.php', 'CountPerDay', $menutitle, $this->options['whocansee'], CPD_METABOX, array(&$this, 'onShowPage'));
	add_action('load-'.$this->pagehook, array(&$this, 'onLoadPage'));
}

/**
 * backlink to Plugin homepage
 */
function cpdInfo()
{
	global $cpd_version;
	$t = '<span style="white-space:nowrap">'.date_i18n('Y-m-d H:i').'</span>';
	echo '<p style="margin:0">Count per Day: <code>'.$cpd_version.'</code><br/>';
	printf(__('Time for Count per Day: <code>%s</code>.', 'cpd'), $t);
	echo '<br />'.__('Bug? Problem? Question? Hint? Praise?', 'cpd').' ';
	printf(__('Write a comment on the <a href="%s">plugin page</a>.', 'cpd'), 'http://www.tomsdimension.de/wp-plugins/count-per-day');
	echo '<br />'.__('License').': <a href="http://www.tomsdimension.de/postcards">Postcardware :)</a>';
	echo '<br /><a href="'.$this->dir.'/readme.txt?KeepThis=true&amp;TB_iframe=true" title="Count per Day - Readme.txt" class="thickbox"><strong>Readme.txt</strong></a></p>';
}

/**
 * function calls from metabox default parameters
 */
function getMostVisitedPostsMeta()		{ $this->getMostVisitedPosts(); }
function getUserPerPostMeta()			{ $this->getUserPerPost(); }
function getVisitedPostsOnDayMeta()		{ $this->getVisitedPostsOnDay(0, 100); }
function dashboardChartMeta()			{ $this->dashboardChart(0, false, false); }
function dashboardChartVisitorsMeta()	{ $this->dashboardChartVisitors(0, false, false); }
function getCountriesMeta()				{ $this->getCountries(0, false); }
function getCountriesVisitorsMeta()		{ $this->getCountries(0, false, true); }
function getReferersMeta()				{ $this->getReferers(0, false, 0); }
function getUserOnlineMeta()			{ $this->getUserOnline(false, true); }
function getUserPerMonthMeta()			{ $this->getUserPerMonth(); }
function getReadsPerMonthMeta()			{ $this->getReadsPerMonth(); }
function getSearchesMeta()				{ $this->getSearches(); }

/**
 * will be executed if wordpress core detects this page has to be rendered
 */
function onLoadPage()
{
	global $cpd_geoip;
	// needed javascripts
	wp_enqueue_script('common');
	wp_enqueue_script('wp-lists');
	wp_enqueue_script('postbox');

	// add the metaboxes
	add_meta_box('reads_at_all', '<span class="cpd_icon cpd_summary">&nbsp;</span> '.__('Total visitors', 'cpd'), array(&$this,'dashboardReadsAtAll'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('user_online', '<span class="cpd_icon cpd_online">&nbsp;</span> '.__('Visitors online', 'cpd'), array(&$this,'getUserOnlineMeta'), $this->pagehook, 'cpdrow1', 'default');
	add_meta_box('user_per_month', '<span class="cpd_icon cpd_user">&nbsp;</span> '.__('Visitors per month', 'cpd'), array(&$this,'getUserPerMonthMeta'), $this->pagehook, 'cpdrow2', 'default');
	add_meta_box('reads_per_month', '<span class="cpd_icon cpd_reads">&nbsp;</span> '.__('Reads per month', 'cpd'), array(&$this,'getReadsPerMonthMeta'), $this->pagehook, 'cpdrow3', 'default');
	add_meta_box('reads_per_post', '<span class="cpd_icon cpd_post">&nbsp;</span> '.__('Visitors per post', 'cpd'), array(&$this,'getUserPerPostMeta'), $this->pagehook, 'cpdrow3', 'default');
	add_meta_box('last_reads', '<span class="cpd_icon cpd_calendar">&nbsp;</span> '.__('Latest Counts', 'cpd'), array(&$this,'getMostVisitedPostsMeta'), $this->pagehook, 'cpdrow4', 'default');
	add_meta_box('day_reads', '<span class="cpd_icon cpd_day">&nbsp;</span> '.__('Visitors per day', 'cpd'), array(&$this,'getVisitedPostsOnDayMeta'), $this->pagehook, 'cpdrow4', 'default');
	add_meta_box('searches', '<span class="cpd_icon cpd_help">&nbsp;</span> '.__('Search strings', 'cpd'), array(&$this,'getSearchesMeta'), $this->pagehook, 'cpdrow1', 'default');
	add_meta_box('cpd_info', '<span class="cpd_icon cpd_help">&nbsp;</span> '.__('Plugin'), array(&$this,'cpdInfo'), $this->pagehook, 'cpdrow1', 'low');
	if ($this->options['referers'])
	{
		add_meta_box('browsers', '<span class="cpd_icon cpd_computer">&nbsp;</span> '.__('Browsers', 'cpd'), array(&$this,'getClients'), $this->pagehook, 'cpdrow2', 'default');
		add_meta_box('referers', '<span class="cpd_icon cpd_referrer">&nbsp;</span> '.__('Referrer', 'cpd'), array(&$this,'getReferersMeta'), $this->pagehook, 'cpdrow3', 'default');
	}
	if ($cpd_geoip)
	{
		add_meta_box('countries', '<span class="cpd_icon cpd_reads">&nbsp;</span> '.__('Reads per Country', 'cpd'), array(&$this,'getCountriesMeta'), $this->pagehook, 'cpdrow2', 'default');
		add_meta_box('countries2', '<span class="cpd_icon cpd_user">&nbsp;</span> '.__('Visitors per Country', 'cpd'), array(&$this,'getCountriesVisitorsMeta'), $this->pagehook, 'cpdrow2', 'default');
	}
}

/**
 * creates dashboard page
 */
function onShowPage()
{
	global $screen_layout_columns, $count_per_day;
	if ( empty($screen_layout_columns) )
		$screen_layout_columns = 4;
	$data = '';
	?>
	<div id="cpd-metaboxes" class="wrap">
		<h2><img src="<?php echo $count_per_day->img('cpd_logo.png') ?>" alt="Logo" class="cpd_logo" style="margin-left:8px" /> Count per Day - <?php _e('Statistics', 'cpd') ?></h2>
		<?php
		wp_nonce_field('cpd-metaboxes');
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		$css = 'style="width:'.round(100 / $screen_layout_columns, 1).'%;"';
		$this->getFlotChart();
		?>
		<div id="dashboard-widgets" class="metabox-holder cpd-dashboard">
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow1', $data); ?></div>
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow2', $data); ?></div>
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow3', $data); ?></div>
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow4', $data); ?></div>
			<br class="clear"/>
		</div>	
	</div>
	<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function() {
		jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
		postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
	});
	//]]>
	</script>
	<?php
}

/**
 * adds some shortcodes to use functions in frontend
 */
function addShortcodes()
{
	add_shortcode('CPD_READS_THIS', array(&$this,'shortShow'));
	add_shortcode('CPD_READS_TOTAL', array(&$this,'shortReadsTotal'));
	add_shortcode('CPD_READS_TODAY', array(&$this,'shortReadsToday'));
	add_shortcode('CPD_READS_YESTERDAY', array(&$this,'shortReadsYesterday'));
	add_shortcode('CPD_READS_LAST_WEEK', array(&$this,'shortReadsLastWeek'));
	add_shortcode('CPD_READS_PER_MONTH', array(&$this,'shortReadsPerMonth'));
	add_shortcode('CPD_READS_THIS_MONTH', array(&$this,'shortReadsThisMonth'));
	add_shortcode('CPD_VISITORS_TOTAL', array(&$this,'shortUserAll'));
	add_shortcode('CPD_VISITORS_ONLINE', array(&$this,'shortUserOnline'));
	add_shortcode('CPD_VISITORS_TODAY', array(&$this,'shortUserToday'));
	add_shortcode('CPD_VISITORS_YESTERDAY', array(&$this,'shortUserYesterday'));
	add_shortcode('CPD_VISITORS_LAST_WEEK', array(&$this,'shortUserLastWeek'));
	add_shortcode('CPD_VISITORS_THIS_MONTH', array(&$this,'shortUserThisMonth'));
	add_shortcode('CPD_VISITORS_PER_DAY', array(&$this,'shortUserPerDay'));
	add_shortcode('CPD_FIRST_COUNT', array(&$this,'shortFirstCount'));
	add_shortcode('CPD_CLIENTS', array(&$this,'shortClients'));
	add_shortcode('CPD_VISITORS_PER_MONTH', array(&$this,'shortUserPerMonth'));
	add_shortcode('CPD_VISITORS_PER_POST', array(&$this,'shortUserPerPost'));
	add_shortcode('CPD_COUNTRIES', array(&$this,'shortCountries'));
	add_shortcode('CPD_COUNTRIES_USERS', array(&$this,'shortCountriesUsers'));
	add_shortcode('CPD_MOST_VISITED_POSTS', array(&$this,'shortMostVisitedPosts'));
	add_shortcode('CPD_REFERERS', array(&$this,'shortReferers'));
	add_shortcode('CPD_POSTS_ON_DAY', array(&$this,'shortPostsOnDay'));
	add_shortcode('CPD_MAP', array(&$this,'shortShowMap'));
	add_shortcode('CPD_DAY_MOST_READS', array(&$this,'shortDayWithMostReads'));
	add_shortcode('CPD_DAY_MOST_USERS', array(&$this,'shortDayWithMostUsers'));
	add_shortcode('CPD_SEARCHSTRINGS', array(&$this,'shortGetSearches'));
	add_shortcode('CPD_FLOTCHART', array(&$this,'shortFlotChart'));
}
function shortShow()			{ return $this->show('', '', false, false); }
function shortReadsTotal()		{ return $this->getReadsAll(true); }
function shortReadsToday()		{ return $this->getReadsToday(true); }
function shortReadsYesterday()	{ return $this->getReadsYesterday(true); }
function shortReadsThisMonth()	{ return $this->getReadsThisMonth(true); }
function shortReadsLastWeek()	{ return $this->getReadsLastWeek(true); }
function shortReadsPerMonth()	{ return $this->getReadsPerMonth(true, true); }
function shortUserAll()			{ return $this->getUserAll(true); }
function shortUserOnline()		{ return $this->getUserOnline(false, false, true); }
function shortUserToday()		{ return $this->getUserToday(true); }
function shortUserYesterday()	{ return $this->getUserYesterday(true); }
function shortUserLastWeek()	{ return $this->getUserLastWeek(true); }
function shortUserThisMonth()	{ return $this->getUserThisMonth(true); }
function shortUserPerDay()		{ return $this->getUserPerDay($this->options['dashboard_last_days'], true); }
function shortFirstCount()		{ return $this->getFirstCount(true); }
function shortClients()			{ return $this->getClients(true); }
function shortUserPerMonth()	{ return $this->getUserPerMonth(true, true); }
function shortUserPerPost()		{ return $this->getUserPerPost(0, true, true); }
function shortCountries()		{ return $this->getCountries(0, true, false, true); }
function shortCountriesUsers(){ return $this->getCountries(0, true, true, true); }
function shortReferers()		{ return $this->getReferers(0, true, 0); }
function shortDayWithMostReads(){ return $this->getDayWithMostReads(true, true); }
function shortDayWithMostUsers(){ return $this->getDayWithMostUsers(true, true); }
function shortFlotChart()			{ return $this->getFlotChart(); }
function shortMostVisitedPosts( $atts )
{
	extract( shortcode_atts( array(
			'days' => 0,
			'limit' => 0,
			'postsonly' => 0,
			'posttypes' => ''
	), $atts) );
	return $this->getMostVisitedPosts( $days, $limit, true, $postsonly, false, $posttypes );
}
function shortGetSearches( $atts )
{
	extract( shortcode_atts( array(
		'limit' => 0,
		'days' => 0
	), $atts) );
	return $this->getSearches( $limit, $days, true );
}
function shortPostsOnDay( $atts )
{
	extract( shortcode_atts( array(
		'date' => 0,
		'limit' => 0
	), $atts) );
	return $this->getVisitedPostsOnDay( $date, $limit, false, false, true, true );
}
function shortShowMap( $atts )
{
	extract( shortcode_atts( array(
		'width' => 500,
		'height' => 340,
		'what' => 'reads'
	), $atts) );
	return $this->getMap( $what, $width, $height );
}

/**
 * adds charts to lists on dashboard
 * @param string $id HTML-id of the DIV
 * @param array $data data
 * @param string $html given list code to add the chart
 */
function includeChartJS( $id, $data, $html )
{
	$d = array_reverse($data);
	$d = '[['.implode(',', $d).']]';
	$code = '<div id="'.$id.'" class="cpd-list-chart" style="width:100%;height:50px"></div>
		<script type="text/javascript">
		//<![CDATA[
		if (jQuery("#'.$id.'").width() > 0)
			jQuery(function(){jQuery.plot(jQuery("#'.$id.'"),'.$d.',{series:{lines:{fill:true,lineWidth:1}},colors:["red"],grid:{show:false}});});
		//]]>
		</script>
		'.$html;
	return $code;
}

/**
 * get mass bots
 */
function getMassBots( $limit )
{
	global $wpdb;
	$sql = $wpdb->prepare("
	SELECT	t.id, t.ip AS longip, $this->ntoa(t.ip) AS ip, t.date, t.posts, c.client
	FROM (	SELECT	id, ip, date, count(*) posts
			FROM	$wpdb->cpd_counter
			GROUP	BY ip, date
			ORDER	BY posts DESC ) AS t
	LEFT	JOIN	$wpdb->cpd_counter c
			ON		c.id = t.id
	WHERE	posts > %d", (int) $limit );
	return $this->mysqlQuery('rows', $sql, 'getMassBots '.__LINE__);
}


/**
 * backup the counter table to wp-content dir, gz-compressed if possible
 */
function export( $days = 180 )
{
	global $wpdb;
	$days = intval($days);
	$t = $wpdb->cpd_counter;
	$tname = $t.'_last_'.$days.'_days_'.date_i18n('Y-m-d_H-i-s').'.csv';
	$path = tempnam(sys_get_temp_dir(), 'cpdexport');
	
	// open file
	$f = fopen($path,'w');
	
	if (!$f) :
		echo '<div class="error"><p>'.__('Export failed! Cannot open file.', 'cpd').' '.$path.'.</p></div>';
	else :
		$part = (int) $this->options['backup_part'];
		if (empty($part))
			$part = 10000;
		// check free memory, save 8MB for script, 5000 entries needs ~ 10MB
		$freeMemory = ($this->getBytes(ini_get('memory_limit')) - memory_get_usage()) - 8000000;
		$part = min(array( round($freeMemory/1000000)*500, $part ));
		$start = 0;
		
		fwrite($f, "date;ip;country;client;referer;post_cat_id;post_name;cat_tax_name;tax\r\n");
		
		do
		{
			$sql = "SELECT	c.*,
							c.page post_id,
							p.post_title post,
							t.name tag_cat_name,
							x.taxonomy tax
					FROM	`$t` c
					LEFT	JOIN $wpdb->posts p
							ON p.id = c.page
					LEFT	JOIN $wpdb->terms t
							ON t.term_id = 0 - c.page
					LEFT	JOIN $wpdb->term_taxonomy x
							ON x.term_id = t.term_id
					WHERE	c.date >= DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL $days DAY)
					GROUP	BY c.id
					ORDER	BY c.date
					LIMIT	$start, $part";
			$rows = $this->mysqlQuery('rows', $sql, 'export '.__LINE__);
			foreach ($rows as $row)
			{
				$row = (array) $row;
				// protect referer and client fields against CSV injection
				if($row['referer'][0] === "=" || $row['referer'][0] === "+" || $row['referer'][0] === "-" || $row['referer'][0] === "@"){
					$row['referer'] = "'".$row['referer'];
				}
				if($row['client'][0] === "=" || $row['client'][0] === "+" || $row['client'][0] === "-" || $row['client'][0] === "@"){
					$row['client'] = "'".$row['client'];
				}
				
				$line = '"'.$row['date'].'";"'.long2ip($row['ip']).'";"'.$row['country'].'";"'
					.str_replace('"', ' ', $row['client']).'";"'.str_replace('"', ' ', $row['referer']).'";"'
					.abs($row['page']).'";"'.str_replace('"', ' ', $row['post']).'";"'.str_replace('"', ' ', $row['tag_cat_name']).'";"'.$row['tax'].'"'."\r\n";
				
				fwrite($f, $line);
			}
			$start += $part;
		}
		while (count($rows) == $part);
		
		fclose($f);		
		
		// show download link
		$tfile = basename($path);
		echo '<div class="updated"><p>';
		_e('Download the export file:', 'cpd');
		echo ' <a href="index.php?page=cpd_download&amp;f='.$tfile.'&amp;n='.$tname.'">'.$tname.'</a><br/>';
		echo '</p></div>';
		
	endif;
}


/**
 * backup the counter table to wp-content dir, gz-compressed if possible
 */
function backup()
{
	global $wpdb;
	
	$t = $wpdb->cpd_counter;
	$gz = ( function_exists('gzopen') && is_writable(WP_CONTENT_DIR) ) ? 1 : 0;
	$tname = $t.'_backup_'.date_i18n('Y-m-d_H-i-s').'.sql';
	if ($gz) $tname .= '.gz';
	$name = '/'.$tname;

	// wp-content or tempdir?
	$path = ( empty($_POST['downloadonly']) && is_writable(WP_CONTENT_DIR) ) ? WP_CONTENT_DIR.$name : tempnam(sys_get_temp_dir(), 'cpdbackup');
	
	// open file
	$f = ($gz) ? gzopen($path,'w9') : fopen($path,'w');
	
	if (!$f) :
		echo '<div class="error"><p>'.__('Backup failed! Cannot open file', 'cpd').' '.$path.'.</p></div>';
	else :
		set_time_limit(300);
		$this->flush_buffers();
		
		// write backup to file
		$d = '';
		($gz) ? gzwrite($f, $d) : fwrite($f, $d);
		if ( $res = $this->mysqlQuery('rows', "SHOW CREATE TABLE `$t`", 'backupCollect'.__LINE__) )
		{
			// create table command
			$create = $res[0];
			$create->{'Create Table'} .= ';';
			$line = str_replace("\n", "", $create->{'Create Table'})."\n";
			($gz) ? gzwrite($f, $line) : fwrite($f, $line);
			$line = false;
			
			// number of entries
			$entries = $this->mysqlQuery('count', "SELECT 1 FROM `$t`", 'backupCollect'.__LINE__);
			$part = (int) $this->options['backup_part'];
			if (empty($part))
				$part = 10000;
			// check free memory, save 8MB for script, 5000 entries needs ~ 10MB
			$freeMemory = ($this->getBytes(ini_get('memory_limit')) - memory_get_usage()) - 8000000;
			$part = min(array( round($freeMemory/1000000)*500, $part ));

			// show progress
			echo '<div id="cpd_progress" class="updated"><p>'.sprintf(__('Backup of %s entries in progress. Every point comprises %s entries.', 'cpd'), $entries, $part).'<br />';
			$this->flush_buffers();
			
			// get data
			for ($i = 0; $i <= $entries; $i = $i + $part)
			{
				if ( $data = $this->mysqlQuery('rows', "SELECT * FROM `$t` LIMIT $i, $part", 'backupCollect'.__LINE__) )
				{
					foreach ($data as $row)
					{
						$row = (array) $row;
						
						// columns names
						if (empty($cols))
							$cols = array_keys($row);
						
						// create line
						if (!$line)
						{
							$line = "INSERT INTO `$t` (`".implode('`,`',$cols)."`) VALUES\n";
							if (isset($v))
								$line .= "$v\n";
						}
							
						// add values
						$v = '';
						foreach ($row as $val)
							$v .= "'".esc_sql($val)."',";
						$v = '('.substr($v,0,-1).'),';
						
						if ( strlen($line) < 50000 - strlen($v) )
							$line .= "$v\n";
						else
						{
							$line = substr($line,0,-2).";\n";
							($gz) ? gzwrite($f, $line) : fwrite($f, $line);
							$line = false;
						}
					}
				}
				echo '| ';
				$this->flush_buffers();
			}
			
			// write leftover
			if ($line)
			{
				$line = substr($line,0,-2).";\n";
				($gz) ? gzwrite($f, $line) : fwrite($f, $line);
			}
			
			// reindex command
			$line = "REPAIR TABLE `$t`;";
			($gz) ? gzwrite($f, $line) : fwrite($f, $line); 

			echo '</p></div>';
			
			// hide progress
			echo '<script type="text/javascript">'
				.'document.getElementById("cpd_progress").style.display="none";'
				.'</script>'."\n";
			$this->flush_buffers();
		}
		
		// close file
		($gz) ? gzclose($f) : fclose($f);
		
		// save collection and options
		$toname = 'count_per_day_options_'.date_i18n('Y-m-d_H-i-s').'.txt';
		if ($gz) $toname .= '.gz';
		$oname = '/'.$toname;
		$opath = ( empty($_POST['downloadonly']) && is_writable(WP_CONTENT_DIR) ) ? WP_CONTENT_DIR.$oname : tempnam(sys_get_temp_dir(), 'cpdbackup');
		$f = ($gz) ? gzopen($opath,'w9') : fopen($opath,'w');
		
		foreach (array('count_per_day', 'count_per_day_summary', 'count_per_day_collected', 'count_per_day_posts', 'count_per_day_notes') as $o)
		{
			$c = get_option($o);
			$line = "=== begin $o ===\n".serialize($c)."\n=== end $o ===\n\n";
			($gz) ? gzwrite($f, $line) : fwrite($f, $line);
		}
		($gz) ? gzclose($f) : fclose($f);
		
		// message
		echo '<div class="updated"><p>';
		if ( strpos($path, WP_CONTENT_DIR) === false )
		{
			// show download links
			_e('Your can download the backup files here and now.', 'cpd');
			echo '<br/>';
			$tfile = basename($path);
			$tofile = basename($opath);
			echo sprintf(__('Backup of counter table saved in %s.', 'cpd'),
				'<a href="index.php?page=cpd_download&amp;f='.$tfile.'&amp;n='.$tname.'">'.$tname.'</a>').'<br/>';
			echo sprintf(__('Backup of counter options and collection saved in %s.', 'cpd'),
				'<a href="index.php?page=cpd_download&amp;f='.$tofile.'&amp;n='.$toname.'">'.$toname.'</a>');
		}
		else
		{
			// show link
			echo sprintf(__('Backup of counter table saved in %s.', 'cpd'),
				'<a href="'.content_url().$name.'">'.content_url().$name.'</a>').'<br/>';
			echo sprintf(__('Backup of counter options and collection saved in %s.', 'cpd'),
				'<a href="'.content_url().$oname.'">'.content_url().$oname.'</a>');
		}
		echo '</p></div>';
	endif;
	$this->flush_buffers();
}


/**
* restores backup data to the counter table or options
*/
function restore ()
{
	global $wpdb;
	
	if ( empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'cpdnonce')
		|| ( empty($_GET['cpdrestore']) && empty($_GET['cpdadding']) ) )
		return;
	
	$doadding = (isset($_GET['cpdadding'])) ? 1 : 0;
	$path = WP_CONTENT_DIR.'/'.(($doadding) ? $_GET['cpdadding'] : $_GET['cpdrestore']);
	
	if ( isset($path) && preg_match('/count_per_day|cpd_counter/i', $path) && file_exists($path) )
	{
		$gz = (substr($path, -3) == '.gz') ? 1 : 0;
		$f = ($gz) ? gzopen($path, 'r') : fopen($path, 'r');
	
		if ( strpos($path, 'counter_backup') )
		{
			// counter table
			$cpd_sep = array('DROP TABLE', 'CREATE TABLE', 'INSERT INTO', 'REPAIR TABLE');
			$sql = '';
			while ( ($cpd_line = ($gz)?gzgets($f):fgets($f)) !== false )
			{
				// new query?
				$newsql = 0;
				foreach ( $cpd_sep as $s )
					if ( strpos($cpd_line, $s) !== false && strpos($cpd_line, $s) < 5 )
						$newsql = 1;
				if ($newsql)
				{
					// execute query, do not recreate table while adding data
					if (!empty($sql))
					{
						if ($doadding)
							$sql = str_replace('INSERT INTO', 'REPLACE INTO', $sql);
						if ( !$doadding || ( strpos($sql, 'DROP TABLE') === false && strpos($sql, 'CREATE TABLE')  === false ) )
							$this->mysqlQuery('', $sql, 'restoreSql '.__LINE__);
					}
					$sql = $cpd_line;
				}
				else
					$sql .= $cpd_line;
			}
			if (!feof($f)) {
				echo '<div class="error"><p>'.__('Error while reading backup file!', 'cpd')."</p></div>\n";
			}
			unset($sql);
			if ($doadding)
				echo '<div class="updated"><p>'.sprintf(__('The backup was added to counter table %s.', 'cpd'), "<code>$wpdb->cpd_counter</code>")."</p></div>\n";
			else
				echo '<div class="updated"><p>'.sprintf(__('The counter table %s was restored from backup.', 'cpd'), "<code>$wpdb->cpd_counter</code>")."</p></div>\n";
		}
		elseif ( strpos($path, 'count_per_day_options') )
		{
			// options
			$backup = ($gz) ? gzread($f, 500000) : fread($f, filesize($path));
			$entries = array('count_per_day', 'count_per_day_summary', 'count_per_day_collected', 'count_per_day_posts', 'count_per_day_notes');
			foreach ( $entries as $entry )
			{
				$s = strpos($backup, "=== begin $entry ===") + strlen($entry) + 14;
				$e = strpos($backup, "=== end $entry ===");
				$option = trim(substr($backup, $s, $e - $s));
				update_option($entry, unserialize($option));
			}
			$this->options = get_option('count_per_day');
			unset($backup);
			unset($option);
			echo '<div class="updated"><p>'.__('Options restored from backup.', 'cpd')."</p></div>\n";
		}
		($gz) ? gzclose($f) : fclose($f);
	}

}

function addCollectionToCountries( $visitors, $limit = false )
{
	global $wpdb;
	if ( $visitors )
		// visitors
		$sql = "SELECT country, COUNT(*) c
				FROM (	SELECT country, COUNT(*) c
						FROM $wpdb->cpd_counter
						WHERE ip > 0
						GROUP BY country, date, ip ) as t
				GROUP BY country
				ORDER BY c desc";
	else
		// reads
		$sql = "SELECT	country, COUNT(*) c
				FROM	$wpdb->cpd_counter
				WHERE	ip > 0
				GROUP	BY country
				ORDER	BY c DESC";
	$res = $this->mysqlQuery('rows', $sql, 'getCountries '.__LINE__);

	$temp = array();
	foreach ( $res as $r )
		$temp[$r->country] = $r->c;
		
	// add collection values
	$coll = get_option('count_per_day_collected');
	if ($coll)
	{
		foreach ($coll as $month)
		{
			$countries = explode(';', $month['country']);
			// country:reads|visitors	
			foreach ($countries as $v)
			{
				if (!empty($v))
				{
					$x = explode(':', $v);
					$country = $x[0];
					$y = explode('|', $x[1]);
					$value = ($visitors) ? $y[1] : $y[0];
					if (isset($temp[$country]))
						$temp[$country] += $value;
					else
						$temp[$country] = $value;
				}
			}
		}
	}
	
	// max $limit biggest values
	$keys = array_keys($temp);
	array_multisort($temp, SORT_NUMERIC, SORT_DESC, $keys);
	if ($limit)
		$temp = array_slice($temp, 0, $limit);
	
	return $temp;
}

/* get collected data */

function getLastCollectedMonth()
{
	$s = get_option('count_per_day_summary');
	return (isset($s['lastcollectedmonth'])) ? $s['lastcollectedmonth'] : false;
}

function getCollectedReads()
{
	$s = get_option('count_per_day_summary');
	return (isset($s['reads'])) ? $s['reads'] : 0;
}

function getCollectedUsers()
{
	$s = get_option('count_per_day_summary');
	return (isset($s['users'])) ? $s['users'] : 0;
}

function getCollectedDayMostReads()
{
	$s = get_option('count_per_day_summary');
	return (isset($s['mostreads'])) ? $s['mostreads'] : 0;
}

function getCollectedDayMostUsers()
{
	$s = get_option('count_per_day_summary');
	return (isset($s['mostusers'])) ? $s['mostusers'] : 0;
}

function getCollectedData( $month ) // YYYYMM
{
	$d = get_option('count_per_day_collected');
	if ($d)
	{
		$m = $d[$month];
		unset($d);
		return $m;
	}
}

/**
 * gets reads per post from collected data
 *
 * @param int $postID post ID
 * @return int reads of the post
 */
function getCollectedPostReads( $postID = -1 )
{
	if ($postID < 0)
		return 0;
	$postID = (int) $postID;
	$collected = (array) get_option('count_per_day_posts');
	return (int) (isset($collected) && isset($collected['p'.$postID])) ? $collected['p'.$postID] : 0;
}

/* update if new count is bigger than collected */

function updateCollectedDayMostReads( $new )
{
	$n = array ($new->date, $new->count);
	$s = get_option('count_per_day_summary', array());
	if ( empty($s['mostreads']) || $n[1] > $s['mostreads'][1] )
		$s['mostreads'] = $n;
	update_option('count_per_day_summary', $s);
	return $s['mostreads'];
}

function updateCollectedDayMostUsers( $new )
{
	$n = array ($new->date, $new->count);
	$s = get_option('count_per_day_summary', array());
	if ( empty($s['mostusers']) || $n[1] > $s['mostusers'][1] )
		$s['mostusers'] = $n;
	update_option('count_per_day_summary', $s);
	return $s['mostusers'];
}

/**
 * sets first date in summary
 */
function updateFirstCount()
{
	global $wpdb;
	// first day in summary
	$s = get_option('count_per_day_summary', array());
	if ( empty($s['firstcount']) )
	{
		// first day from table
		$res = $this->mysqlQuery('var', "SELECT MIN(date) FROM $wpdb->cpd_counter", 'getFirstCount'.__LINE__);
		if ($res)
		{
			$s['firstcount'] = $res;
			update_option('count_per_day_summary', $s);
		}
	}
	return $s['firstcount'];
}

/**
 * returns table size in KB or MB
 */
function getTableSize( $table )
{
	$res = $this->mysqlQuery('rows', "SHOW TABLE STATUS");
	if ($res)
		foreach ($res as $row)
			if ($row->Name == $table)
				$size = $this->formatBytes( $row->Data_length + $row->Index_length );
	if ($size)
		return $size;
}

/**
 * formats byte integer to e.g. x.xx MB
 */
function formatBytes( $size )
{
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++)
    	$size /= 1024;
    return round($size, 2).$units[$i];
}

/**
 * flush buffers, the hard way ;)
 */
function flush_buffers()
{
	echo "\n<!--".str_repeat(' ', 4100)."-->\n";
	$levels = ob_get_level();
	for ( $i = 0; $i < $levels; $i++ )
	{
		$b = ob_get_status();
		if ( strpos($b['name'], 'zlib') === false )
		{
			@ob_end_flush();
			@ob_flush();
			@flush();
		}
	}
	@ob_start();
}

/**
 * formats e.g. 64MB to byte integer
 */
function getBytes($val) {
    $val = trim($val);
    $last = strtolower($val{strlen($val)-1});
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

/**
 * try to get the search strings from referrer
 */
function getSearchString()
{
	if (empty($_SERVER['HTTP_REFERER']))
		return false;
	$ref = parse_url(rawurldecode(wp_strip_all_tags($_SERVER['HTTP_REFERER'])));
	if ( empty($ref['host']) || empty($ref['query']) )
		return false;
	$keys = array('p','q','s','query','search','prev','qkw','qry');
	parse_str($ref['query'], $query);
	foreach ($keys as $key)
		if (isset($query[$key]))
			$search = str_ireplace(array('/search?q=','/images?q='), '', $query[$key]);
	if (isset($search))
		$search = wp_strip_all_tags($search);
	if (empty($search) || is_numeric($search)) // non WordPress postID
		$search = '';
	return trim($search);
}

/**
 * add counter column to page/post lists
 */
function cpdColumn($defaults)
{
	if ( $this->options['show_in_lists']  )
		$defaults['cpd_reads'] = '<img src="'.$this->img('cpd_logo.png').'" alt="'.__('Reads', 'cpd').'" title="'.__('Reads', 'cpd').'" style="width:16px;height:16px;" />';
	return $defaults;
}

/**
 * adds content to the counter column
 */
function cpdColumnContent($column_name, $id = 0)
{
	global $wpdb;
	if( $column_name == 'cpd_reads' )
	{
		$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE page='$id'", 'cpdColumn_'.$id.'_'.__LINE__);
		$coll = get_option('count_per_day_posts');
		if ( $coll && isset($coll['p'.$id]) )
			$c += $coll['p'.$id];
		echo $c;
	}
}

/**
 * load GeoIP Script from Maxmind GIT
 */
function loadGeoIpAddon()
{
	global $cpd_path, $cpd_geoip_dir;
	
	// create dir
	if (!is_dir($cpd_geoip_dir))
		if (!mkdir($cpd_geoip_dir))
		{
			echo '<div class="error"><p>'.sprintf(__('Could not create directory %s!', 'cpd'), '<code>wp-content/count-per-day-geoip</code>').'</p></div>';
			return false;
		};
		
	// function checks
	if ( !ini_get('allow_url_fopen') )
	{
		echo '<div class="error"><p>'.__('Sorry, <code>allow_url_fopen</code> is disabled!', 'cpd').'</p></div>';
		return false;
	}

	$source = 'https://raw.githubusercontent.com/maxmind/geoip-api-php/master/src/geoip.inc';
	$dest = $cpd_geoip_dir.'geoip.inc';
	
	// get remote file
	$file = file_get_contents($source, 'r');
	
	// write new locale file
	if ( is_writable($cpd_geoip_dir) )
		file_put_contents($dest, $file);
	
	if (is_file($dest))
	{
		@chmod($dest, 0755);
		return __('GeoIP Addon installed.', 'cpd');
	}
	else
		echo '<div class="error"><p>'.sprintf(__('Sorry, an error occurred. Load the file from %s and copy it to wp-content/count-per-day-geoip/ directory.', 'cpd'), '<a href="'.$source.'" target="_blank">'.$source.'</a>').'</p></div>';
}

/**
 * Windows server < PHP 5.3
 * PHP without IPv6 support
 */ 
static function inetPton($ip)
{
	// convert to IPv6 e.g. '::62.149.142.175' or '62.149.142.175'
	if ( strpos($ip, '.') !== FALSE )
	{
		$ip = explode(':', $ip);
		$ip = $ip[count($ip) - 1];
		$ip = explode('.', $ip);
		$ip = '::FFFF:'.sprintf("%'.02s", dechex($ip[0])).sprintf("%'.02s", dechex($ip[1])).':'.sprintf("%'.02s", dechex($ip[2])).sprintf("%'.02s", dechex($ip[3]));
	}
	
	// IPv6
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
	{
		$ip = explode(':', $ip);
		$parts = 8 - count($ip);
		$res = '';
		$replaced = 0;
		foreach ( $ip as $seg )
		{
			if ( $seg != '' )
				$res .= str_pad($seg, 4, '0', STR_PAD_LEFT);
			elseif ( $replaced == 0 )
			{
				for ( $i = 0; $i <= $parts; $i++ )
					$res .= '0000';
					$replaced = 1;
			}
			elseif ( $replaced == 1 )
				$res .= '0000';
		}
		$ip = pack('H'.strlen($res), $res);
	}
	else
		// Dummy IP if no valid IP found
		$ip = inetPton('127.0.0.1');
	
	return $ip;
}

} // class

