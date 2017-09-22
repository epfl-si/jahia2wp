<?php
if (!defined('ABSPATH'))
	exit;

// answer only for 20 seconds after calling
if ( empty($_GET['time']) || time() - $_GET['time'] > 20 )
{
	header("HTTP/1.0 403 Forbidden");
	die('wrong request');
}

if ( $_GET['f'] == 'count' )
{
	$cpd_funcs = array ( 'show',
	'getReadsAll', 'getReadsToday', 'getReadsYesterday', 'getReadsLastWeek', 'getReadsThisMonth',
	'getUserAll', 'getUserToday', 'getUserYesterday', 'getUserLastWeek', 'getUserThisMonth',
	'getUserPerDay', 'getUserOnline', 'getFirstCount' );
	
	$page = (int) $_GET['cpage'];
	if ( is_numeric($page) )
	{
		$count_per_day->count( '', $page );
		foreach ( $cpd_funcs as $f )
		{
			if ( ($f == 'show' && $page) || $f != 'show' )
			{
				echo $f.'===';
				if ( $f == 'getUserPerDay' )
					echo $count_per_day->getUserPerDay($count_per_day->options['dashboard_last_days']);
				else if ( $f == 'show' )
					echo $count_per_day->show('', '', false, false, $page);
				else
					echo $count_per_day->{$f}();
				echo '|';
			}
		}
	}
}
