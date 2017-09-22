<?php
/*
 * Download backup files
 * f = filename in tempdir
 * n = download filename
 */

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
	die('no way');
	
if ( empty($_GET['f']) || empty($_GET['n']) )
	die('no way');
$file = sys_get_temp_dir().'/'.strip_tags($_GET['f']);
if ( strpos($file, '..') !== false )
	die('no way');
if ( strpos(basename($file), 'cpdexport') !== 0
	&& strpos(basename($file), 'cpdbackup') !== 0 )
	die('no way');
if (!file_exists($file))
	die('file not found');
$name = stripslashes(strip_tags($_GET['n']));
if (substr($name, -2) == 'gz')
	header('Content-Type: application/x-gzip');
else if (substr($name, -3) == 'csv')
	header('Content-Type: text/csv');
else
	header('Content-Type: text/plain');
header("Content-Disposition: attachment; filename=\"$name\"");
readfile($file);
