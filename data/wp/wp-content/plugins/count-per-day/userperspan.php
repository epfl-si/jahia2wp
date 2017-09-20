<?php 
if (!defined('ABSPATH'))
	exit;

// check user
$o = get_option('count_per_day');
$can_see = str_replace(
	// administrator, editor, author, contributor, subscriber
	array(10, 7, 2, 1, 0),
	array('manage_options', 'moderate_comments', 'edit_published_posts', 'edit_posts', 'read'),
	$o['show_in_lists']);
if ( !current_user_can($can_see) )
	die();

$cpd_datemin = ( !empty($_REQUEST['datemin']) ) ? wp_strip_all_tags($_REQUEST['datemin']) : date_i18n('Y-m-d', time() - 86400 * 14); // 14 days
$cpd_datemax = ( !empty($_REQUEST['datemax']) ) ? wp_strip_all_tags($_REQUEST['datemax']) : date_i18n('Y-m-d');
$cpd_page = ( isset($_REQUEST['cpage']) ) ? intval($_REQUEST['cpage']) : 0;

$sql = $wpdb->prepare(
		"SELECT	p.post_title,
				COUNT(*) AS count,
				c.page,
				c.date
		FROM	$wpdb->cpd_counter c
		LEFT	JOIN $wpdb->posts p
				ON p.ID = c.page
		WHERE	c.page = %s
		AND		c.date >= %s
		AND		c.date <= %s
		GROUP	BY c.date
		ORDER	BY c.date desc",
		$cpd_page, $cpd_datemin, $cpd_datemax);
$cpd_visits = $count_per_day->mysqlQuery('rows', $sql, 'getUserPerPostSpan '.__LINE__);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<title>Count per Day</title>
<link rel="stylesheet" type="text/css" href="<?php echo $count_per_day->dir ?>/counter.css" />
</head>
<body class="cpd-thickbox">

<h2><?php _e('Visitors per day', 'cpd') ?></h2>

<form action="" method="post">
<p style="background:#ddd; padding:3px;">
	<?php _e('Start', 'cpd'); ?>:
	<input type="text" name="datemin" value="<?php echo $cpd_datemin; ?>" size="10" />
	<?php _e('End', 'cpd'); ?>:
	<input type="text" name="datemax" value="<?php echo $cpd_datemax; ?>" size="10" />
	<?php _e('PostID', 'cpd'); ?>:
	<input type="text" name="cpage" value="<?php echo $cpd_page; ?>" size="5" />
	<input type="submit" value="<?php _e('show', 'cpd') ?>" />  
</p>
</form>

<?php
if ( !count($cpd_visits) )
	_e('no data found', 'cpd');
else
{
	$cpd_maxcount = 1;
	foreach ($cpd_visits as $r)
		$cpd_maxcount = max( array( $cpd_maxcount, (int) $r->count ) );
	$cpd_faktor = 300 / $cpd_maxcount; 
	
	foreach ($cpd_visits as $r)
	{
		if ( !isset($cpd_new) )
		{
			if ( $cpd_page == 0 )
				echo  '<h2>'.__('Front page displays').'</h2';
			else
				echo "<h2>$r->post_title</h2>";
			echo '<ol class="cpd-dashboard" style="padding: 0;">';
		}
		else
		{
			if ( $cpd_new < $r->count )
				$cpd_style = 'style="color:#b00;"';
			else if ( $cpd_new > $r->count )
				$cpd_style = 'style="color:#0a0;"';
			else
				$cpd_style = '';
		
			$cpd_bar = $cpd_new * $cpd_faktor;
			$cpd_trans = 300 - $cpd_bar;
			$cpd_imgbar = '<img src="'.$count_per_day->img('cpd_rot.png').'" alt="" style="width:'.$cpd_bar.'px;height:23px;padding-left:10px;" />';
			$cpd_imgtrans = '<img src="'.$count_per_day->img('cpd_trans.png').'" alt="" style="width:'.$cpd_trans.'px;height:10px;padding-right:10px;" />';
			
			echo '<li>';
			echo '<b>'.$cpd_imgbar.$cpd_imgtrans.'</b>';
			echo '<b '.$cpd_style.'>'.$cpd_new.'</b>';
			echo $cpd_date_str.'</li>';
		}
		$cpd_date_str = mysql2date(get_option('date_format'), $r->date);
		$cpd_new = (int) $r->count;
	}

	$cpd_bar = $cpd_new * $cpd_faktor;
	$cpd_trans = 300 - $cpd_bar;
	$cpd_imgbar = '<img src="'.$count_per_day->img('cpd_rot.png').'" alt="" style="width:'.$cpd_bar.'px;height:23px;padding-left:10px;" />';
	$cpd_imgtrans = '<img src="'.$count_per_day->img('cpd_trans.png').'" alt="" style="width:'.$cpd_trans.'px;height:10px;padding-right:10px;" />';

	echo '<li>';
	echo '<b>'.$cpd_imgbar.$cpd_imgtrans.'</b>';
	echo '<b>'.$cpd_new.'</b>';
	echo $cpd_date_str.'</li>';
}
echo '</ol>';
if ($count_per_day->options['debug']) $count_per_day->showQueries();
?>
</body>
</html>