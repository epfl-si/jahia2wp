<?php
/**
 * Filename: counter-options.php
 * Count Per Day - Options and Administration
 */

if (!defined('ABSPATH'))
	exit;

// check form 
if(!empty($_POST['do']))
{
	switch($_POST['do'])
	{
		// update options
		case 'cpd_update' :
			$_POST['cpd_bots'] = preg_replace('/\r\n\r\n/', '', strip_tags($_POST['cpd_bots']));
			$count_per_day->options['onlinetime'] = $_POST['cpd_onlinetime'];
			$count_per_day->options['user'] = empty( $_POST['cpd_user'] ) ? 0 : 1 ;
			$count_per_day->options['user_level'] = $_POST['cpd_user_level'];
			$count_per_day->options['autocount'] = empty( $_POST['cpd_autocount'] ) ? 0 : 1 ;
			$count_per_day->options['bots'] = $_POST['cpd_bots'];
			$count_per_day->options['posttypes'] = str_replace(' ', '', $_POST['cpd_posttypes']);
			$count_per_day->options['dashboard_posts'] = $_POST['cpd_dashboard_posts'];
			$count_per_day->options['dashboard_last_posts'] = $_POST['cpd_dashboard_last_posts'];
			$count_per_day->options['dashboard_last_days'] = $_POST['cpd_dashboard_last_days'];
			$count_per_day->options['show_in_lists'] = empty( $_POST['cpd_show_in_lists'] ) ? 0 : 1 ;
			$count_per_day->options['chart_days'] = $_POST['cpd_chart_days'];
			$count_per_day->options['chart_height'] = $_POST['cpd_chart_height'];
			$count_per_day->options['startdate'] = $_POST['cpd_startdate'];
			$count_per_day->options['startcount'] = $_POST['cpd_startcount'];
			$count_per_day->options['startreads'] = $_POST['cpd_startreads'];
			$count_per_day->options['anoip'] = empty( $_POST['cpd_anoip'] ) ? 0 : 1 ;
			$count_per_day->options['clients'] = $_POST['cpd_clients'];
			$count_per_day->options['exclude_countries'] = strtolower(str_replace(' ', '', strip_tags($_POST['cpd_exclude_countries'])));
			$count_per_day->options['ajax'] = empty( $_POST['cpd_ajax'] ) ? 0 : 1 ;
			$count_per_day->options['debug'] = empty( $_POST['cpd_debug'] ) ? 0 : 1 ;
			$count_per_day->options['localref'] = empty( $_POST['cpd_localref'] ) ? 0 : 1 ;
			$count_per_day->options['referers'] = empty( $_POST['cpd_referers'] ) ? 0 : 1 ;
			$count_per_day->options['referers_cut'] = empty( $_POST['cpd_referers_cut'] ) ? 0 : 1 ;
			$count_per_day->options['fieldlen'] = min( array(intval($_POST['cpd_fieldlen']), 500) );
			$count_per_day->options['dashboard_referers'] = $_POST['cpd_dashboard_referers'];
			$count_per_day->options['referers_last_days'] = $_POST['cpd_referers_last_days'];
			$count_per_day->options['chart_old'] = empty( $_POST['cpd_chart_old'] ) ? 0 : 1 ;
			$count_per_day->options['no_front_css'] = empty( $_POST['cpd_no_front_css'] ) ? 0 : 1 ;
			$count_per_day->options['whocansee'] = ($_POST['cpd_whocansee'] == 'custom') ? $_POST['cpd_whocansee_custom'] : $_POST['cpd_whocansee'];
			$count_per_day->options['backup_part'] = $_POST['cpd_backup_part'];
			
			if (empty($count_per_day->options['clients']))
				$count_per_day->options['clients'] = 'Firefox, MSIE, Chrome, Safari, Opera';
			
			if ( isset($_POST['cpd_countries']) )
				$count_per_day->options['countries'] = $_POST['cpd_countries'];
			
			update_option('count_per_day', $count_per_day->options);
			
			echo '<div class="updated"><p>'.__('Options updated', 'cpd').'</p></div>';
			break;

	// update countries
	case 'cpd_countries' :
		if ( class_exists('CpdGeoIp') )
		{
			$count_per_day->queries[] = 'cpd_countries - class "CpdGeoIp" exists'; 
			$rest = CpdGeoIp::updateDB();
			$mysiteurl = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'counter-options.php') + 19).'&amp;tab=tools';
			echo '<div class="updated">
				<form name="cpdcountries" method="post" action="'.$mysiteurl.'">
				<p>'.sprintf(__('Countries updated. <b>%s</b> entries in %s without country left', 'cpd'), $rest, $wpdb->cpd_counter);
			if ( $rest > 0 )
				echo '<input type="hidden" name="do" value="cpd_countries" />
					<input type="submit" name="updcon" value="'.__('update next', 'cpd').'" class="button" />';
			if ( $rest > 20 )
			{
				// reload page per javascript until less than 100 entries without country
				if ( !$count_per_day->options['debug'] )
					echo '<script type="text/javascript">document.cpdcountries.submit();</script>';
			}
			echo '</p>
				</form>
				</div>';
			if ( $rest > 20 )
				$count_per_day->flush_buffers();
		}
		else
			$count_per_day->queries[] = '<span style="color:red">cpd_countries - class "CpdGeoIp" NOT exists</span>';
		break;
		
	// download new GeoIP database
	case 'cpd_countrydb' :
		if ( class_exists('CpdGeoIp') )
		{
			$result = CpdGeoIp::updateGeoIpFile();
			echo '<div class="updated"><p>'.$result.'</p></div>';
			if ( file_exists($cpd_geoip_dir.'GeoIP.dat') && filesize($cpd_geoip_dir.'GeoIP.dat') > 1000 )
				$cpd_geoip = 1;
		}
		break;
	
	// install GeoIP addon
	case 'cpd_loadgeoipaddon' :
		$result = $count_per_day->loadGeoIpAddon();
		if ($result)
			echo '<div class="updated"><p>'.$result.'</p></div>';
		if ( file_exists($cpd_path.'geoip.php') && file_exists($cpd_geoip_dir.'geoip.inc') && filesize($cpd_geoip_dir.'geoip.inc') > 1000 )
		{
			include_once($cpd_path.'geoip.php');
			if ( !file_exists($cpd_geoip_dir.'GeoIP.dat') || filesize($cpd_geoip_dir.'GeoIP.dat') < 1000 )
			{
				// download new GeoIP database
				$result = CpdGeoIp::updateGeoIpFile();
				echo $result;
			}
			if ( file_exists($cpd_geoip_dir.'GeoIP.dat') && filesize($cpd_geoip_dir.'GeoIP.dat') > 1000 )
				$cpd_geoip = 1;
		}
		break;		
		
	// delete massbots
	case 'cpd_delete_massbots' :
		if ( isset($_POST['limit']) )
		{
			$bots = $count_per_day->getMassBots($_POST['limit']);
			$sum = 0;
			foreach ($bots as $r)
			{
				$count_per_day->mysqlQuery('', "DELETE FROM $wpdb->cpd_counter WHERE ip = $count_per_day->aton('$r->ip') AND date = '$r->date'", 'deleteMassbots '.__LINE__);
				$sum += $r->posts;
			}
			if ( $sum )
				echo '<div class="updated"><p>'.sprintf(__('Mass Bots cleaned. %s counts deleted.', 'cpd'), $sum).'</p></div>';
		}	
		break;

	// clean database
	case 'cpd_export' :
		$count_per_day->export($_POST['cpd_exportdays']);
		break;
		
	// clean database
	case 'cpd_clean' :
		$rows = $count_per_day->cleanDB();
		echo '<div class="updated"><p>'.sprintf(__('Database cleaned. %s rows deleted.', 'cpd'), $rows).'</p></div>';
		break;

	// reset counter
	case 'cpd_reset' :
		if(trim($_POST['reset_cpd_yes']) == 'yes')
		{
			delete_option('count_per_day_notes');
			delete_option('count_per_day_search');
			delete_option('count_per_day_online');
			delete_option('count_per_day_summary');
			delete_option('count_per_day_collected');
			delete_option('count_per_day_posts');
			$wpdb->query('TRUNCATE TABLE '.$wpdb->cpd_counter);
			$wpdb->query('TRUNCATE TABLE '.$wpdb->cpd_counter_useronline);
			$wpdb->query('TRUNCATE TABLE '.$wpdb->cpd_notes);
			echo '<div class="updated"><p>'.sprintf(__('Counter reseted.', 'cpd'), $rows).'</p></div>';
		}
		break;
		
	//  uninstall plugin
	case __('UNINSTALL Count per Day', 'cpd') :
		if(trim($_POST['uninstall_cpd_yes']) == 'yes')
		{
			count_per_day_uninstall();
			echo '<div class="updated"><p>';
			echo sprintf(__('Table %s deleted', 'cpd'), $wpdb->cpd_counter).'<br/>';
			echo sprintf(__('Table %s deleted', 'cpd'), $wpdb->cpd_counter_useronline).'<br/>';
			echo sprintf(__('Table %s deleted', 'cpd'), $wpdb->cpd_notes).'<br/>';
			echo __('Options deleted', 'cpd').'</p></div>';
			$mode = 'end-UNINSTALL';
		}
		break;

	// backup counter
	case 'cpd_backup' :
		$count_per_day->backup();
		break;
		
	// collect entries
	case 'cpd_collect' :
		// backup first ;)
		$count_per_day->backup();

		set_time_limit(300);
		
		$allnew = (isset($_POST['cpd_new_collection'])) ? 1 : 0;
		if ($allnew)
		{
			delete_option('count_per_day_summary');
			delete_option('count_per_day_collected');
			delete_option('count_per_day_posts');
		}
		
		$keep = (isset($_POST['cpd_keep_month'])) ? intval($_POST['cpd_keep_month']) : 6;
		
		$d = array(); // month data
		$t = array(); // temp country data
		$s = array( // summary
			'reads' => $count_per_day->getCollectedReads(),
			'users' => $count_per_day->getCollectedUsers() );

		echo '<div id="cpd_progress_collection" class="updated"><p>'.__('Collection in progress...', 'cpd').' ';
		$count_per_day->flush_buffers();

		$today = date('Y-m-01');
		
		// reads per month
		$cpd_sql = "
				SELECT	LEFT(date,7) month, COUNT(*) c
				FROM	$wpdb->cpd_counter
				WHERE	date < DATE_SUB( '$today', INTERVAL $keep MONTH )
				GROUP	BY LEFT(date,7)";
		$res = $count_per_day->mysqlQuery('rows', $cpd_sql, "getReadsPerMonthCompress ".__LINE__);
		foreach ($res as $r)
		{
			$month = str_replace('-','',$r->month);
			$d[$month]['reads'] = $r->c;
			$s['reads'] += $r->c;
		}
		unset($res);
		
		// visitors per month
		$cpd_sql = "
				SELECT	LEFT(date,7) month, COUNT(*) c
				FROM	(
					SELECT	date
					FROM	$wpdb->cpd_counter
					WHERE	date < DATE_SUB( '$today', INTERVAL $keep MONTH )
					GROUP	BY date, ip
					) AS t
				GROUP	BY LEFT(date,7)";
		$res = $count_per_day->mysqlQuery('rows', $cpd_sql, "getVisitorsPerMonthCompress ".__LINE__);
		foreach ($res as $r)
		{
			$month = str_replace('-','',$r->month);
			$d[$month]['users'] = $r->c;
			$s['users'] += $r->c;
		}
		unset($res);
		
		// reads per month and country
		$cpd_sql = "
		SELECT	LEFT(date,7) month, COUNT(*) c, country
		FROM	$wpdb->cpd_counter
		WHERE	date < DATE_SUB( '$today', INTERVAL $keep MONTH )
		GROUP	BY LEFT(date,7), country";
		$res = $count_per_day->mysqlQuery('rows', $cpd_sql, "getReadsPerCountryCompress ".__LINE__);
		foreach ($res as $r)
		{
			$month = str_replace('-','',$r->month);
			$country = ($r->country) ? $r->country : '-';
			$t[$month][$country]['reads'] = $r->c;
		}
		unset($res);
	
		// visitors per month and country
		$cpd_sql = "
		SELECT	LEFT(date,7) month, COUNT(*) c, country
		FROM	(
			SELECT	date, country
			FROM	$wpdb->cpd_counter
			WHERE	date < DATE_SUB( '$today', INTERVAL $keep MONTH )
			GROUP	BY date, ip, country
			) AS t
		GROUP	BY LEFT(date,7), country";
		$res = $count_per_day->mysqlQuery('rows', $cpd_sql, "getVisitorsPerCountryCompress ".__LINE__);
		foreach ($res as $r)
		{
			$month = str_replace('-','',$r->month);
			$country = ($r->country) ? $r->country : '-';
			$t[$month][$country]['users'] = $r->c;
		}
		unset($res);

		// format country data as "country:reads|visitors;"
		foreach ($t as $month => $cdata)
		{
			$d[$month]['country'] = '';
			foreach ($cdata as $country => $c)
				$d[$month]['country'] .= $country.':'.$c['reads'].'|'.$c['users'].';';
			$d[$month]['country'] = substr($d[$month]['country'], 0, -1); 
		}
		unset($t);
		
		// add new to collected data
		$d = get_option('count_per_day_collected', array()) + $d;

		// summaries
		$last = max(array_keys($d));
		$s['lastcollectedmonth'] = substr($last, 0, 4).'-'.substr($last, 4, 2);
		$s['mostreads'] = $count_per_day->getDayWithMostReads(0, 1);
		$s['mostusers'] = $count_per_day->getDayWithMostUsers(0, 1);
		$s['firstcount'] = $count_per_day->updateFirstCount();
		
		// visitors per post
		echo "<br />".__('Get Visitors per Post...', 'cpd')."\n";
		$count_per_day->flush_buffers();
		
		$cpd_sql = "
		SELECT	COUNT(*) count, page
		FROM 	$wpdb->cpd_counter
		WHERE	date < DATE_SUB( '$today', INTERVAL $keep MONTH )
		AND		page
		GROUP	BY page";
		$res = $count_per_day->mysqlQuery('rows', $cpd_sql, 'getUsersPerPostCompress '.__LINE__);
		
		$p = get_option('count_per_day_posts',array());
		foreach ($res as $r)
		{
			if (isset($p['p'.$r->page]))
				$p['p'.$r->page] += (int) $r->count;
			else
				$p['p'.$r->page] = (int) $r->count;
		}
		
		// save collection
		echo "<br />".__('Deleting old data...', 'cpd')."\n";
		$count_per_day->flush_buffers();
		
		update_option('count_per_day_summary', $s);
		update_option('count_per_day_collected', $d);
		update_option('count_per_day_posts', $p);
		unset($s);
		unset($d);
		unset($p);
		
		// delete entries
		$sizeold = $count_per_day->getTableSize($wpdb->cpd_counter);
		$cpd_sql = "
		DELETE	FROM $wpdb->cpd_counter
		WHERE	date < DATE_SUB( '$today', INTERVAL $keep MONTH )";
		$count_per_day->mysqlQuery('', $cpd_sql, 'deleteAfterCollection '.__LINE__);
		$count_per_day->mysqlQuery('', "REPAIR TABLE `$wpdb->cpd_counter`", 'repairTable '.__LINE__);
		$sizenew = $count_per_day->getTableSize($wpdb->cpd_counter);
		
		// hide progress
		echo "</p></div>\n";
		echo '<script type="text/javascript">document.getElementById("cpd_progress_collection").style.display="none";</script>'."\n";
		
		echo '<div class="updated"><p>'
			.sprintf(__('Counter entries until %s collected and counter table %s optimized (size before = %s &gt; size after = %s).', 'cpd'),
				$count_per_day->getLastCollectedMonth(), "<code>$wpdb->cpd_counter</code>", $sizeold, $sizenew)
			.'</p></div>';
		$count_per_day->flush_buffers();
		break;

	// reactivation
	case 'cpd_activate' :
		$count_per_day->checkVersion();
		echo '<div class="updated"><p>'.__('Installation of "Count per Day" checked', 'cpd').'</p></div>';
		break;
	
	// delete search strings
	case 'cpd_searchclean' :
		$days = intval($_POST['cpd_keepsearch']);
		$deldate = date('Y-m-d', time() - $days * 86400);
		$searches = get_option('count_per_day_search', array());
		foreach ( $searches as $k => $v )
		{
			if ( $k < $deldate )
				unset($searches[$k]);
		}
		update_option('count_per_day_search', $searches);
		unset($searches);
		echo '<div class="updated"><p>'.__('Old search strings deleted', 'cpd').'</p></div>';
		break;
		
	// delete clients and referers
	case 'cpd_clientsclean' :
		$days = intval($_POST['cpd_keepclients']);
		$deldate = date('Y-m-d', time() - $days * 86400);
		
		$cpd_sql = "
		UPDATE	$wpdb->cpd_counter
		SET		client = '',
				referer = ''
		WHERE	date < '$deldate'";
		$count_per_day->mysqlQuery('', $cpd_sql, 'deleteClients '.__LINE__);
		
		echo '<div class="updated"><p>'.__('Clients and referers deleted', 'cpd').'</p></div>';
		break;
		
	default:
		break;
	}
}

// delete one massbots per click on X
if ( isset($_GET['dmbip']) && isset($_GET['dmbdate']) )
	$count_per_day->mysqlQuery('', "DELETE FROM $wpdb->cpd_counter WHERE ip = '".$_GET['dmbip']."' AND date = '".$_GET['dmbdate']."'", 'deleteMassbot '.__LINE__);

if ( empty($mode) )
	$mode = '';

$nonce = wp_create_nonce('cpdnonce');
	
// restore from backup file
if ( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'cpdnonce')
	&& ( isset($_GET['cpdrestore']) || isset($_GET['cpdadding']) ) )
{
	$count_per_day->restore();
}
	
switch($mode) {
	// deactivation
	case 'end-UNINSTALL':
		$deactivate_url = 'plugins.php?action=deactivate&amp;plugin='.$cpd_dir_name.'/counter.php';
		if ( function_exists('wp_nonce_url') ) 
			$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_'.$cpd_dir_name.'/counter.php');
		echo '<div class="wrap">';
		echo '<h2>'.__('Uninstall', 'cpd').' "Count per Day"</h2>';
		echo '<p><strong><a href="'.$deactivate_url.'">'.__('Click here', 'cpd').'</a> '.__('to finish the uninstall and to deactivate "Count per Day".', 'cpd').'</strong></p>';
		echo '</div>';
		break;
		
	default:
		
// show options page

	$o = $count_per_day->options;
	
	// save massbot limit
	if(isset($_POST['limit']))
	{
		$o['massbotlimit'] = (int) $_POST['limit'];
		update_option('count_per_day', $o);
	}
	
	$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'tools';
	?>
	
	<div id="cpdtoolccs" class="wrap">
	
	<h2><img src="<?php echo $count_per_day->img('cpd_logo.png') ?>" alt="Logo" class="cpd_logo" /> Count per Day</h2>
	
	<h2 class="nav-tab-wrapper">
		<a href="?page=count-per-day/counter-options.php&amp;tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>"><span class="cpd_icon cpd_tools">&nbsp;</span> <?php _e('Tools') ?></a>
		<a href="?page=count-per-day/counter-options.php&amp;tab=options" class="nav-tab <?php echo $active_tab == 'options' ? 'nav-tab-active' : ''; ?>"><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Settings') ?></a>
	</h2>
	
 	<div id="poststuff" class="cpd_settings">
	
	<?php if( $active_tab == 'tools' ) : ?>
	
	<?php $mysiteurl = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'counter-options.php') + 19).'&amp;tab=tools'; ?>
	
	<?php // mass bots ?>
	<div class="postbox">
	<?php
	$limit = (isset($o['massbotlimit'])) ? intval($o['massbotlimit']) : 25;
	$limit = (isset($_POST['limit'])) ? intval($_POST['limit']) : $limit;
	$limit_input = '<input type="text" size="3" name="limit" value="'.$limit.'" style="text-align:center" />';
	
	if ( $limit == 0 )
		$limit = 25;
	$bots = $count_per_day->getMassBots( $limit );
	?>
	<h3><span class="cpd_icon cpd_massbots">&nbsp;</span> <?php _e('Mass Bots', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $mysiteurl ?>">
		<p>
			<?php printf(__('Show all IPs with more than %s page views per day', 'cpd'), $limit_input) ?>
			<input type="submit" name="showmassbots" value="<?php _e('show', 'cpd') ?>" class="button" />
		</p>
		</form>
		
		<form method="post" action="<?php echo $mysiteurl ?>">
		<table class="widefat post">
		<thead>
		<tr>
			<th><?php _e('IP', 'cpd') ?></th>
			<th><?php _e('Date', 'cpd') ?></th>
			<th><?php _e('Client', 'cpd') ?></th>
			<th style="text-align:right"><?php _e('Views', 'cpd') ?></th>
		</tr>
		</thead>
		<?php
		$sum = 0;
		foreach ($bots as $row)
		{
			$ip = $row->ip;
			echo '<tr><td style="white-space:nowrap">';
			if ( $cpd_geoip )
			{
				$c = CpdGeoIp::getCountry($ip);
				echo $c[1].' &nbsp;';
			}
			echo '<a href="?page=count-per-day/counter-options.php&amp;dmbip='.$row->longip.'&amp;dmbdate='.$row->date.'"
				title="'.sprintf(__('Delete these %s counts', 'cpd'), $row->posts).'"
				style="color:red; font-weight: bold;">X</a> &nbsp;';
			echo '<a href="http://www.utrace.de/?query='.$ip.'">'.$ip.'</a></td>'
				.'<td style="white-space:nowrap;">'.mysql2date(get_option('date_format'), $row->date).'</td>'
				.'<td>'.htmlentities($row->client).'</td>'
				.'<td style="text-align:right;"><a href="?page=cpd_massbots&amp;dmbip='.$row->longip.'&amp;dmbdate='.$row->date.'&amp;KeepThis=true&amp;TB_iframe=true" title="Count per Day" class="thickbox">'
					.$row->posts.'</a></td>'
				.'</tr>';
			$sum += $row->posts;
		}
		?>	
		</table>
		<?php if ( $sum ) { ?>
			<p>
				<input type="hidden" name="do" value="cpd_delete_massbots" />
				<input type="hidden" name="limit" value="<?php echo $limit ?>" />
				<input type="submit" name="clean" value="<?php printf(__('Delete these %s counts', 'cpd'), $sum) ?>" class="button" />
			</p>
		<?php } ?>
		</form>
	</div>
	</div>
	
	
	<?php // industrious visitors ?>
	<div class="postbox">
	<?php
	$limit = (!empty($_POST['vislimit'])) ? intval($_POST['vislimit']) : 10;
	$limit_input = '<input type="text" size="3" name="vislimit" value="'.$limit.'" style="text-align:center" />';
	$days = (!empty($_POST['visdays'])) ? intval($_POST['visdays']) : 7;
	$days_input = '<input type="text" size="3" name="visdays" value="'.$days.'" style="text-align:center" />';
	$list = $count_per_day->getLastVisitors( $days, $limit );
	?>
	<h3><span class="cpd_icon cpd_massbots">&nbsp;</span> <?php _e('Most Industrious Visitors', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $mysiteurl ?>#cpdtools">
		<p>
			<?php printf(__('Show the %s most industrious visitors of the last %s days', 'cpd'), $limit_input, $days_input) ?>
			<input type="submit" name="showlastvisitors" value="<?php _e('show', 'cpd') ?>" class="button" />
		</p>
		</form>
		
		<form method="post" action="<?php echo $mysiteurl ?>">
		<table class="widefat post">
		<thead>
		<tr>
			<th><?php _e('IP', 'cpd') ?></th>
			<th><?php _e('Date', 'cpd') ?></th>
			<th><?php _e('Client', 'cpd') ?></th>
			<th style="text-align:right"><?php _e('Views', 'cpd') ?></th>
		</tr>
		</thead>
		<?php
		foreach ($list as $row)
		{
			$ip = $row->ip;
			echo '<tr><td style="white-space:nowrap">';
			if ( $cpd_geoip )
			{
				$c = CpdGeoIp::getCountry($ip);
				echo $c[1].' &nbsp;';
			}
			echo '<a href="?page=count-per-day/counter-options.php&amp;dmbip='.$row->longip.'&amp;dmbdate='.$row->date.'"
				title="'.sprintf(__('Delete these %s counts', 'cpd'), $row->posts).'"
				style="color:red; font-weight: bold;">X</a> &nbsp;';
			echo '<a href="http://www.utrace.de/?query='.$ip.'">'.$ip.'</a></td>'
				.'<td style="white-space:nowrap;">'.mysql2date(get_option('date_format'), $row->date).'</td>'
				.'<td>'.htmlentities($row->client).'</td>'
				.'<td style="text-align:right;"><a href="index.php?page=cpd_massbots&amp;dmbip='.$row->longip.'&amp;dmbdate='.$row->date.'&amp;KeepThis=true&amp;TB_iframe=true" title="Count per Day" class="thickbox">'
					.$row->posts.'</a></td>'
				.'</tr>';
			$sum += $row->posts;
		}
		?>	
		</table>
		</form>
	</div>
	</div>
	
	<!-- left column -->
	<div class="cpd_halfsize" style="margin-right: 2%;">
	
	<!-- Export -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_backup">&nbsp;</span> <?php _e('Export', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $mysiteurl ?>">
		<p>
			<?php printf(__('Export the last %s days as CSV-File', 'cpd'), '<input type="text" size="4" name="cpd_exportdays" value="180" class="code" style="text-align:center" />'); ?>
		</p>
		<p>
			<input type="hidden" name="do" value="cpd_export" />
			<input type="submit" name="cpd_export" value="<?php _e('Export entries', 'cpd') ?>" class="button" />
		</p>
		</form>
	</div>
	</div>
	
	<!-- Backup -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_backup">&nbsp;</span> <?php _e('Backup', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $mysiteurl ?>">
		<p>
			<?php printf(__('Create a backup of the counter table %s in your wp-content directory (if writable).', 'cpd'), '<code>'.$wpdb->cpd_counter.'</code>') ?>
		</p>
		<p>
			<input type="checkbox" name="downloadonly" value="1" /> <?php _e('Download only', 'cpd') ?>
		</p>
		<p>
			<input type="hidden" name="do" value="cpd_backup" />
			<input type="submit" name="backup" value="<?php _e('Backup the database', 'cpd') ?>" class="button" />
		</p>
		</form>
		<?php
		if ( is_writable(WP_CONTENT_DIR) )
		{
			// delete file?
			if ( isset($_GET['cpddel']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'cpdnonce') )
				$delfile = WP_CONTENT_DIR.'/'.$_GET['cpddel'];
			if ( isset($delfile) && preg_match('/count_per_day|cpd_counter/i', $delfile) && file_exists($delfile) )
				@unlink($delfile);
			
			// list backup files
			$d = dir(WP_CONTENT_DIR);
			$dirarray = array();
			while ( ($entry = $d->read()) !== false )
				$dirarray[] = $entry;
			$d->close();
			sort($dirarray); // sort by names on all servers
			$captionO = 0;
			$captionB = 0;
			$link = '<td><a href="?page=count-per-day/counter-options.php&amp;_wpnonce='.$nonce.'&amp;';
			echo "<table class='cpd_backups'>\n";
			foreach ( $dirarray as $entry )
				if ( preg_match('/count_per_day|cpd_counter/i', $entry) )
				{
					if ( strpos($entry, 'count_per_day_options') !== false && !$captionO )
					{
						echo '<tr><td colspan="5" style="font-weight:bold;background:#EAEAEA">'.__('Settings and collections', 'cpd')."</td></tr>\n";
						$captionO = 1;
					}
					else if ( strpos($entry, 'cpd_counter_backup') !== false && !$captionB )
					{
						echo '<tr><td colspan="5" style="font-weight:bold;background:#EAEAEA">'.sprintf(__('Counter table %s', 'cpd'), "<code>$wpdb->cpd_counter</code>")."</td></tr>\n";
						$captionB = 1;
					}
					echo '<tr><td><a href="'.content_url().'/'.$entry.'" style="text-decoration:none">'.$entry."</a></td>\n";
					echo '<td style="text-align:right">'.$count_per_day->formatbytes(filesize(WP_CONTENT_DIR.'/'.$entry))."&nbsp; </td>\n";
					if ( strpos($entry, 'cpd_counter_backup') !== false )
						echo $link.'cpdadding='.$entry.'" class="cpd_green" 
							onclick="return confirm(\''.sprintf(__('Add data from the backup file %s to existing counter table?','cpd'), '\n'.$entry).'\')" title="'.__('Add', 'cpd').'">+</a></td>'."\n";
					else
						echo "<td>&nbsp;</td>\n";
					echo $link.'cpdrestore='.$entry.'" class="cpd_green"
						onclick="return confirm(\''.sprintf(__('Restore data from the backup file %s ?','cpd'), '\n'.$entry).'\')" title="'.__('Restore').'">&uArr;</a></td>'."\n";
					echo $link.'cpddel='.$entry.'"
						style="color:red;font-weight:bold" title="'.__('Delete').'"
						onclick="return confirm(\''.sprintf(__('Delete the backup file %s ?','cpd'), '\n'.$entry).'\')">X</a></td>'."\n";
					echo "</tr>\n";
				}
			echo "</table>\n";
			?>
			<p>
				<span class="cpd_green">+</span> <?php _e('add backup to current counter table', 'cpd') ?><br/>
				<span class="cpd_green">&uArr;</span> <?php _e('replace current counter table with with backup', 'cpd') ?><br/>
				<span style="color:red;font-weight:bold">X&nbsp;</span> <?php _e('delete backup file', 'cpd') ?>
			</p>
			<?php
		}	
		?>
	</div>
	</div>
	
	<!-- Cleaner -->
	<?php if ( $count_per_day->options['referers'] ) : ?>
		<div class="postbox">
		<h3><span class="cpd_icon cpd_clean">&nbsp;</span> <?php _e('Clean the database', 'cpd') ?></h3>
		<div class="inside">
			<form method="post" action="<?php echo $mysiteurl ?>">
			<p>
				<?php _e('You can clean the counter table by delete the "spam data".<br />If you add new bots above the old "spam data" keeps in the database.<br />Here you can run the bot filter again and delete the visits of the bots.', 'cpd') ?>
			</p>
			<p>
				<input type="hidden" name="do" value="cpd_clean" />
				<input type="submit" name="clean" value="<?php _e('Clean the database', 'cpd') ?>" class="button" />
			</p>
			</form>
			
			<form method="post" action="<?php echo $mysiteurl ?>">
			<p style="border-top:1px #ddd solid; padding-top:10px">
				<?php printf(__('Delete search strings older than %s days.', 'cpd'), '<input type="text" size="2" name="cpd_keepsearch" value="14" class="code" />') ?>
			</p>
			<p>
				<input type="hidden" name="do" value="cpd_searchclean" />
				<input type="submit" name="clean" value="<?php _e('Delete search strings', 'cpd') ?>" class="button" />
			</p>
			</form>
			
			<form method="post" action="<?php echo $mysiteurl ?>">
			<p style="border-top:1px #ddd solid; padding-top:10px">
				<?php printf(__('Current size of your counter table %s is %s.', 'cpd'), '<code>'.$wpdb->cpd_counter.'</code>', $count_per_day->getTableSize($wpdb->cpd_counter));?><br/>
				<?php printf(__('Delete clients and referers older than %s days to reduce the size of the counter table.', 'cpd'), '<input type="text" size="2" name="cpd_keepclients" value="90" class="code" />') ?>
			</p>
			<p>
				<input type="hidden" name="do" value="cpd_clientsclean" />
				<input type="submit" name="clean" value="<?php _e('Delete clients and referers', 'cpd') ?>" class="button" />
			</p>
			</form>
		</div>
		</div>
	<?php endif; ?>
	
	<!-- Collect -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_collection">&nbsp;</span> <?php _e('Collect old data', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $mysiteurl ?>">
		<p>
			<?php
			printf(__('Current size of your counter table %s is %s.', 'cpd'), '<code>'.$wpdb->cpd_counter.'</code>', $count_per_day->getTableSize($wpdb->cpd_counter));
			echo '<br/>';
			_e('You can collect old data and clean up the counter table.<br/>Reads and visitors will be saved per month, per country and per post.<br/>Clients and referrers will deleted.', 'cpd');
			echo '<br/>';
			$x = $count_per_day->getLastCollectedMonth();
			$m = __(date( 'F', strtotime($x.'-01'))).' '.substr($x, 0, 4);
			if ($x && $m)
				printf(__('Currently your collection contains data until %s.', 'cpd'), $m);
			?>
		</p>
		<p>
			<?php _e('Normally new data will be added to the collection.', 'cpd') ?>
		</p>
		<?php if ($m) { ?>
			<p style="color:red">
				<input type="checkbox" name="cpd_new_collection" value="1" />
				<?php
				echo __('Delete old collection and create a new one which contains only the data currently in counter table.', 'cpd')
				.' '.sprintf(__('All collected data until %s will deleted.', 'cpd'), $m);
				 ?>
			</p>
		<?php } ?>
		<p>
			<?php printf(__('Keep entries of last %s full months + current month in counter table.', 'cpd'), '<input type="text" size="2" name="cpd_keep_month" value="6" class="code" />') ?>
		</p>
		<p>
			<input type="hidden" name="do" value="cpd_collect" />
			<input type="submit" name="collect" value="<?php _e('Collect old data', 'cpd') ?>" class="button" />
		</p>
		</form>
	</div>
	</div>

	</div> <!-- left column -->
	
	<!-- right column -->
	<div class="cpd_halfsize">
	
	<!-- Countries -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_geoip">&nbsp;</span> <?php _e('GeoIP - Countries', 'cpd') ?></h3>
	<div class="inside">
	
		<?php if ( $cpd_geoip ) {
		
			if ( (!function_exists('inet_pton') || !@inet_pton('::127.0.0.1') ) && !defined('CPD_GEOIP_PATCH') )
				echo '<p style="color:red">'.sprintf(__("ERROR: NO IPv6 SUPPORT<br/>Please load, unzip and copy this patched %s to %s.", 'cpd'), '<a href="http://www.tomsdimension.de/downloads/geoipincpatch">geoip.inc</a>', "<code>$cpd_geoip_dir</code>").'</p>';
			?>
			<p>
			<?php _e('You can get the country data for all entries in database by checking the IP adress against the GeoIP database. This can take a while!', 'cpd') ?>
			</p>
			<form method="post" action="<?php echo $mysiteurl ?>">
			<p>
			<input type="hidden" name="do" value="cpd_countries" />
			<input type="submit" name="updcon" value="<?php _e('Update old counter data', 'cpd') ?>" class="button" />
			</p>
			</form>
		<?php } ?>
		
		<?php if ( class_exists('CpdGeoIp') && ini_get('allow_url_fopen') && function_exists('gzopen') ) {
			// install or update database
			echo '<p>'.__('Download a new version of GeoIP.dat file.', 'cpd').'</p>';
			?>
			<form method="post" action="<?php echo $mysiteurl ?>">
			<p>
			<input type="hidden" name="do" value="cpd_countrydb" />
			<input type="submit" name="updcondb" value="<?php _e('Update GeoIP database', 'cpd') ?>" class="button" />
			</p>
			</form>
		<?php }	?>
		
		<?php if ( !file_exists($cpd_geoip_dir.'geoip.inc') || filesize($cpd_geoip_dir.'geoip.inc') < 1000 ) {
			// install GeoIP Addon
			echo '<p style="color:red">'.__('To get country data by checking the IP addresses you need to install the GeoIP Addon.<br>Because it is not under GPL I had to delete this function from WordPress plugin repository.', 'cpd').'</p>';
			echo '<p>'.sprintf(__('The directory %s will be created.', 'cpd'), '<code>wp-content/count-per-day-geoip</code>').'</p>';
			?>
			<form method="post" action="<?php echo $mysiteurl ?>">
			<p>
			<input type="hidden" name="do" value="cpd_loadgeoipaddon" />
			<input type="submit" name="loadgeoipaddon" value="<?php _e('Install GeoIP addon', 'cpd') ?>" class="button" />
			</p>
			</form>
		<?php } ?>
		<p>
			<span class="cpd-r"><?php _e('More informations about GeoIP', 'cpd') ?>:
			<a href="https://www.maxmind.com">www.maxmind.com</a></span>&nbsp;
		</p>
	</div>
	</div>

	<!-- ReActivation -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_update">&nbsp;</span> <?php _e('ReActivation', 'cpd') ?></h3>
	<div class="inside">
		<p>
			<?php _e('Here you can start the installation functions manually.<br/>Same as deactivate and reactivate the plugin.', 'cpd') ?>
		</p>
		<form method="post" action="<?php echo $mysiteurl ?>">
		<p>
			<input type="hidden" name="do" value="cpd_activate" />
			<input type="submit" name="activate" value="<?php _e('ReActivate the plugin', 'cpd') ?>" class="button" />
		</p>
		</form>
	</div>
	</div>
	
	<!-- Reset DBs -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_reset">&nbsp;</span> <?php _e('Reset the counter', 'cpd') ?></h3>
	<div class="inside">
		<p style="color: red">
			<?php _e('You can reset the counter by empty the table. ALL TO 0!<br />Make a backup if you need the current data!', 'cpd') ?>
		</p>
		
		<form method="post" action="<?php echo $mysiteurl ?>">
		<p>
			<input type="hidden" name="do" value="cpd_reset" />
			<input type="checkbox" name="reset_cpd_yes" value="yes" />&nbsp;<?php _e('Yes', 'cpd'); ?> &nbsp;
			<input type="submit" name="clean" value="<?php _e('Reset the counter', 'cpd') ?>" class="button cpd_red" />
		</p>
		</form>
	</div>
	</div>
	
	<!-- Uninstall -->
	<form method="post" action="<?php echo $mysiteurl ?>"> 
	<div class="postbox">
	<h3><span class="cpd_icon cpd_uninstall">&nbsp;</span> <?php _e('Uninstall', 'cpd') ?></h3>
	<div class="inside"> 
		<p>
			<?php _e('If "Count per Day" only disabled the tables in the database will be preserved.', 'cpd') ?><br/>
			<?php _e('Here you can delete the tables and disable "Count per Day".', 'cpd') ?>
		</p>
		<p style="color: red">
			<strong><?php _e('WARNING', 'cpd') ?>:</strong><br />
			<?php _e('These tables (with ALL counter data) will be deleted.', 'cpd') ?><br />
			<b><?php echo $wpdb->cpd_counter ?></b><br />
			<?php _e('If "Count per Day" re-installed, the counter starts at 0.', 'cpd') ?>
		</p>
		<p>
			<input type="checkbox" name="uninstall_cpd_yes" value="yes" />&nbsp;<?php _e('Yes', 'cpd'); ?> &nbsp;
			<input type="submit" name="do" value="<?php _e('UNINSTALL Count per Day', 'cpd') ?>" class="button cpd_red" onclick="return confirm('<?php _e('You are sure to disable Count per Day and delete all data?', 'cpd') ?>')" />
		</p>
	</div>
	</div>
	</form>

	<!-- Plugin page -->
	<div class="postbox">
	<h3><span class="cpd_icon cpd_help">&nbsp;</span> <?php _e('Support', 'cpd') ?></h3>
	<div class="inside">
		<?php $count_per_day->cpdInfo() ?>
	</div>
	</div>

	</div> <!-- right column -->
	
	
	<?php else : // tools tab ?>
	
	<?php $mysiteurl = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'counter-options.php') + 19).'&amp;tab=options'; ?>
	
	<form method="post" action="<?php echo $mysiteurl ?>">
		
	<?php // counter ?>
	<fieldset>
	<legend><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Counter', 'cpd') ?></legend>
	
	<table class="form-table">
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Online time', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_onlinetime" size="3" value="<?php echo $o['onlinetime']; ?>" /> <?php _e('Seconds for online counter. Used for "Visitors online" on dashboard page.', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Logged on Users', 'cpd') ?>:</th>
		<td>
			<label for="cpd_user"><input type="checkbox" name="cpd_user" id="cpd_user" <?php if($o['user']==1) echo 'checked="checked"'; ?> /> <?php _e('count too', 'cpd') ?></label>
			- <?php _e('until User Level', 'cpd') ?>
			<select name="cpd_user_level">
				<option value="10" <?php selected($o['user_level'], 10) ?>><?php echo translate_user_role('Administrator') ?> (10)</option>
				<option value="7" <?php selected($o['user_level'], 7) ?>><?php echo translate_user_role('Editor') ?> (7)</option>
				<option value="2" <?php selected($o['user_level'], 2) ?>><?php echo translate_user_role('Author') ?> (2)</option>
				<option value="1" <?php selected($o['user_level'], 1) ?>><?php echo translate_user_role('Contributor') ?> (1)</option>
				<option value="0" <?php selected($o['user_level'], 0) ?>><?php echo translate_user_role('Subscriber') ?> (0)</option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Auto counter', 'cpd') ?>:</th>
		<td><label for="cpd_autocount"><input type="checkbox" name="cpd_autocount" id="cpd_autocount" <?php checked($o['autocount'], 1) ?> /> <?php _e('Counts automatically single-posts and pages, no changes on template needed.', 'cpd') ?></label></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Bots to ignore', 'cpd') ?>:</th>
		<td><textarea name="cpd_bots" cols="50" rows="10"><?php echo $o['bots']; ?></textarea></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Exclude Countries', 'cpd') ?>:</th>
		<td>
			<input class="code" type="text" name="cpd_exclude_countries" size="50" value="<?php echo str_replace(',', ', ', $o['exclude_countries']); ?>" /><br/>
			<?php _e('Do not count visitors from these countries. Use the country code (de, us, cn,...) Leave empty to count them all.', 'cpd') ?>
		</td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Anonymous IP', 'cpd') ?>:</th>
		<td><label for="cpd_anoip"><input type="checkbox" name="cpd_anoip" id="cpd_anoip" <?php checked($o['anoip'], 1) ?> /> a.b.c.d &gt; a.b.c.x</label></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Cache', 'cpd') ?> (beta):</th>
		<td><label for="cpd_ajax"><input type="checkbox" name="cpd_ajax" id="cpd_ajax" <?php checked($o['ajax'], 1) ?> /> <?php _e('I use a cache plugin. Count these visits with ajax.', 'cpd') ?></label></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Clients and referrers', 'cpd') ?>:</th>
		<td>
			<label for="cpd_referers"><input type="checkbox" name="cpd_referers" id="cpd_referers" <?php checked($o['referers'], 1) ?> />
			<?php _e('Save and show clients and referrers.<br />Needs a lot of space in the database but gives you more detailed informations of your visitors.', 'cpd') ?> (1000000 <?php _e('Reads', 'cpd') ?> ~ 130 MB)</label><br/>
			<label style="padding-top:10px" for="cpd_referers_cut"><input type="checkbox" name="cpd_referers_cut" id="cpd_referers_cut" <?php checked($o['referers_cut'], 1) ?> />
			<?php _e('Save URL only, no query string.', 'cpd') ?><br/>
			<code>http://example.com/webhp?hl=de#sclient=psy&amp;hl=de...</code> &gt; <code>http://example.com/webhp</code></label><br/>
			<input class="code" style="margin-top:10px" type="text" name="cpd_fieldlen" size="3" value="<?php echo $o['fieldlen'] ?>" />
			<?php _e('Limit the length to reduce database size. (max. 500 chars)', 'cpd') ?><br/>
		</td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Post types', 'cpd') ?>:</th>
		<td>
			<input class="code" type="text" name="cpd_posttypes" size="50" value="<?php echo (isset($o['posttypes'])) ? str_replace(',', ', ', $o['posttypes']) : ''; ?>" /><br/>
			<?php _e('Only count these post types. Leave empty to count them all.', 'cpd') ?><br/>
			<?php printf(__('Current post types: %s', 'cpd'), '<code>'.implode(', ', get_post_types()).'</code>'); ?>
		</td>
	</tr>
	
	
	</table>
	</fieldset>
	
	<?php // dashboard ?>
	<fieldset>
	<legend><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Dashboard') ?></legend>
	
	<script type="text/javascript">
	function checkcustom()
	{
		var b = document.getElementById('cpd_whocansee');
		var i = document.getElementById('cpd_whocansee_custom_div');
		if ( b.value == 'custom' )
			i.style.display = 'block';
		else
			i.style.display = 'none';
	}
	</script>
	
	<table class="form-table">
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Who can see it', 'cpd') ?>:</th>
		<td>
			<?php $cus = (in_array($o['whocansee'], array('manage_options','edit_others_posts','publish_posts','edit_posts','read'))) ? 0 : 1 ?> 
			<select id="cpd_whocansee" name="cpd_whocansee" onchange="checkcustom()">
				<option value="manage_options" <?php selected($o['whocansee'], 'manage_options') ?>><?php echo translate_user_role('Administrator') ?> </option>
				<option value="edit_others_posts" <?php selected($o['whocansee'], 'edit_others_posts') ?>><?php echo translate_user_role('Editor') ?></option>
				<option value="publish_posts" <?php selected($o['whocansee'], 'publish_posts') ?>><?php echo translate_user_role('Author') ?></option>
				<option value="edit_posts" <?php selected($o['whocansee'], 'edit_posts') ?>><?php echo translate_user_role('Contributor') ?></option>
				<option value="read" <?php selected($o['whocansee'], 'read') ?>><?php echo translate_user_role('Subscriber') ?></option>
				<option value="custom" <?php selected($cus) ?>>- <?php echo _e('custom', 'cpd') ?> -</option>
			</select>
			<?php _e('and higher are allowed to see the statistics page.', 'cpd') ?>
			<div id="cpd_whocansee_custom_div" <?php if (!$cus) echo 'style="display:none"' ?>>
			<?php printf(__('Set the %s capability %s a user need:', 'cpd'), '<a href="https://codex.wordpress.org/Roles_and_Capabilities">', '</a>'); ?>
				<input type="text" name="cpd_whocansee_custom" value="<?php echo $o['whocansee'] ?>" />
			</div>
		</td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Visitors per post', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_dashboard_posts" size="3" value="<?php echo $o['dashboard_posts']; ?>" /> <?php _e('How many posts do you want to see on dashboard page?', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Latest Counts - Posts', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_dashboard_last_posts" size="3" value="<?php echo $o['dashboard_last_posts']; ?>" /> <?php _e('How many posts do you want to see on dashboard page?', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Latest Counts - Days', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_dashboard_last_days" size="3" value="<?php echo $o['dashboard_last_days']; ?>" /> <?php _e('How many days do you want look back?', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Chart - Days', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_chart_days" size="3" value="<?php echo $o['chart_days']; ?>" /> <?php _e('How many days do you want look back?', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Chart - Height', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_chart_height" size="3" value="<?php echo $o['chart_height']; ?>" /> px - <?php _e('Height of the biggest bar', 'cpd') ?></td>
	</tr>
	<?php if ($cpd_geoip) { ?>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Countries', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_countries" size="3" value="<?php echo $o['countries']; ?>" /> <?php _e('How many countries do you want to see on dashboard page?', 'cpd') ?></td>
	</tr>
	<?php } ?>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Browsers', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_clients" size="50" value="<?php echo $o['clients']; ?>" /><br/><?php _e('Substring of the user agent, separated by comma', 'cpd') ?></td>
	</tr>		
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Search strings', 'cpd') ?>/<?php _e('Referrers - Entries', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_dashboard_referers" size="3" value="<?php echo $o['dashboard_referers']; ?>" /> <?php _e('How many referrers do you want to see on dashboard page?', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Search strings', 'cpd') ?>/<?php _e('Referrers - Days', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_referers_last_days" size="3" value="<?php echo $o['referers_last_days']; ?>" /> <?php _e('How many days do you want look back?', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Local URLs', 'cpd') ?>:</th>
		<td><label for="cpd_localref"><input type="checkbox" name="cpd_localref" id="cpd_localref" <?php checked($o['localref'], 1) ?> />  <?php _e('Show local referrers too.', 'cpd') ?> (<?php echo bloginfo('url') ?>/...)</label></td>
	</tr>
	</table>
	</fieldset>
	
	<?php // lists ?>
	<fieldset>
	<legend><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Posts') ?> / <?php _e('Pages') ?></legend>
	<table class="form-table">
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Show in lists', 'cpd') ?>:</th>
		<td><label for="cpd_show_in_lists"><input type="checkbox" name="cpd_show_in_lists" id="cpd_show_in_lists" <?php checked($o['show_in_lists'], 1) ?> /> <?php _e('Show "Reads per Post" in a new column in post management views.', 'cpd') ?></label></td>
	</tr>
	</table>
	</fieldset>
	
	<?php // start values ?>
	<fieldset>
	<legend><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Start Values', 'cpd') ?></legend>
	<table class="form-table">
	<tr>
		<th colspan="2">
			<?php _e('Here you can change the date of first count and add a start count.', 'cpd')?>
		</th>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Start date', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_startdate" size="10" value="<?php echo $o['startdate']; ?>" /> <?php _e('Your old Counter starts at?', 'cpd') ?> [yyyy-mm-dd]</td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Start count', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_startcount" size="10" value="<?php echo $o['startcount']; ?>" /> <?php _e('Add this value to "Total visitors".', 'cpd') ?></td>
	</tr>
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Start count', 'cpd') ?>:</th>
		<td><input class="code" type="text" name="cpd_startreads" size="10" value="<?php echo $o['startreads']; ?>" /> <?php _e('Add this value to "Total reads".', 'cpd') ?></td>
	</tr>
	</table>
	</fieldset>
	
	<?php // stylesheet ?>
	<fieldset>
	<legend><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Stylesheet', 'cpd') ?></legend>
	<table class="form-table">
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('NO Stylesheet in Frontend', 'cpd') ?>:</th>
		<td><label for="cpd_no_front_css"><input type="checkbox" name="cpd_no_front_css" id="cpd_no_front_css" <?php checked($o['no_front_css'], 1) ?> /> <?php _e('Do not load the stylesheet "counter.css" in frontend.', 'cpd') ?></label></td>
	</tr>
	</table>
	</fieldset>
	
	<?php // backup ?>
	<fieldset>
	<legend><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Backup', 'cpd') ?></legend>
	<table class="form-table">
	<tr>
		<th scope="row" style="white-space:nowrap"><?php _e('Entries per pass', 'cpd') ?>:</th>
		<td>
			<input class="code" type="text" name="cpd_backup_part" size="10" value="<?php echo $o['backup_part']; ?>" />
			<?php _e('How many entries should be saved per pass? Default: 10000', 'cpd') ?><br/>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<?php _e('If your PHP memory limit less then 50 MB and you get a white page or error messages try a smaller value.', 'cpd') ?>
		</td>
	</tr>
	</table>
	</fieldset>
	
	<?php // debug ?>
	<fieldset>
		<legend style="color:red"><span class="cpd_icon cpd_settings">&nbsp;</span> <?php _e('Debug mode', 'cpd') ?></legend>
		<p style="margin-top:15px;margin-left:10px">
			<label for="cpd_debug"><input type="checkbox" name="cpd_debug" id="cpd_debug" <?php checked($o['debug'], 1) ?> /> <?php _e('Show debug informations at the bottom of all pages.', 'cpd') ?></label>
		</p>
	</fieldset>
	
	<input type="hidden" name="do" value="cpd_update" />
	<input type="submit" name="update" value="<?php _e('Update options', 'cpd') ?>" class="button-primary" style="margin-left: 5px;" />
	</form>
	
	</div><!-- poststuff -->
	
	<?php endif; // tabs ?>
	
	</div><!-- wrap -->

<?php } // End switch($mode)