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

if ( isset($_GET['dmbip']) && isset($_GET['dmbdate']) )
{
	$sql = $wpdb->prepare("
	SELECT	c.page post_id, p.post_title post,
			t.name tag_cat_name,
			t.slug tag_cat_slug,
			x.taxonomy tax
	FROM	$wpdb->cpd_counter c
	LEFT	JOIN $wpdb->posts p
			ON p.ID = c.page
	LEFT	JOIN $wpdb->terms t
			ON t.term_id = 0 - c.page
	LEFT	JOIN $wpdb->term_taxonomy x
			ON x.term_id = t.term_id
	WHERE	c.ip = %s
	AND		c.date = %s
	ORDER	BY p.ID",
	$_GET['dmbip'], $_GET['dmbdate'] );
	$massbots = $count_per_day->mysqlQuery('rows', $sql, 'showMassbotPosts');
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<title>Count per Day</title>
<link rel="stylesheet" type="text/css" href="<?php echo $count_per_day->dir ?>/counter.css" />
</head>
<body class="cpd-thickbox">
<h2><?php _e('Mass Bots', 'cpd') ?></h2>
<ol>
<?php
foreach ( $massbots as $r )
{
	if ( $r->post_id < 0 && $r->tax == 'category' )
	{
		$name = '- '.$r->tag_cat_name.' -';
		$link = get_bloginfo('url').'?cat='.abs($r->post_id);
	}
	else if ( $r->post_id < 0 )
	{
		$name = '- '.$r->tag_cat_name.' -';
		$link = get_bloginfo('url').'?tag='.$r->tag_cat_slug;
	}
	else if ( $r->post_id == 0 )
	{
		$name = '- '.__('Front page displays').' -';
		$link =	get_bloginfo('url');
	}
	else
	{
		$postname = $r->post;
		if ( empty($postname) ) 
			$postname = '---';
		$name = $postname;
		$link =	get_permalink($r->post_id);
	}
	echo '<li><a href="'.$link.'" target="_blank">'.$name.'</a></li>';
}
?>
</ol>
<?php if ($count_per_day->options['debug']) $count_per_day->showQueries(); ?>
</body>
</html>