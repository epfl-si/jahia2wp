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

// set default values
if ( isset($_POST['month']) )
	$month = (int) $_POST['month'];
else if ( isset($_GET['month']) )
	$month = (int) $_GET['month'];
else	
	$month = date_i18n('m');

if ( isset($_POST['month']) )
	$year = (int) $_POST['year'];
else if ( isset($_GET['year']) )
	$year = (int) $_GET['year'];
else	
	$year = date_i18n('Y');

$date = isset($_POST['date']) ? strip_tags($_POST['date']) : '';
$note = isset($_POST['note']) ? strip_tags($_POST['note']) : '';

// load notes
$n = (array) get_option('count_per_day_notes');

// save changes
$id = isset($_POST['id']) ? (int) strip_tags($_POST['id']) : 0;
if ( isset($_POST['new']) )
	$n[] = array( $date, $note );
else if ( isset($_POST['edit']) )
	$n[$id] = array( $date, $note );
else if ( isset($_POST['delete']) )
	unset($id);
update_option('count_per_day_notes', $n);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<title>CountPerDay</title>
<link rel="stylesheet" type="text/css" href="<?php echo $count_per_day->dir ?>/counter.css" />
</head>
<body class="cpd-thickbox">
<h2><?php _e('Notes', 'cpd') ?></h2>
<form name="cpd_notes_form1" action="" method="post">
<table class="cpd-notes">
<tr>
	<td colspan="3" style="background:#ddd; padding:3px;">
		<select name="month">
			<option value="0">-</option>
			<?php
			for ( $m = 1; $m <= 12; $m++ )
			{
				echo '<option value="'.$m.'" ';
				if ( $m == $month )
					echo 'selected="selected"';
				echo '>'.mysql2date('F', '2000-'.$m.'-01').'</option>';
			}
			?>
		</select>
		<select name="year">
			<option value="0">-</option>
			<?php
			for ( $y = 2010; $y <= date_i18n('Y'); $y++ )
			{
				echo '<option value="'.$y.'" ';
				if ( $y == $year )
					echo 'selected="selected"';
				echo '>'.$y.'</option>';
			}
			?>
		</select>
		<input type="button" name="showmonth" onclick="submit()" value="<?php _e('show', 'cpd') ?>" style="width:auto;" />
	</td>
</tr>
<tr>
	<th style="width:15%"><?php _e('Date') ?></th>
	<th style="width:75%"><?php _e('Notes', 'cpd') ?></th>
	<th style="width:10%"><?php _e('Action') ?></th>
</tr>
<tr>
	<td><input name="date" value="<?php echo date_i18n('Y-m-d') ?>" /></td>
	<td><input name="note" maxlength="250" /></td>
	<td><input type="submit" name="new" value="+" title="<?php _e('add', 'cpd') ?>" class="green" /></td>
</tr>
<?php
foreach ($n as $id => $v)
{
	if ( (!$month || $month == date('m', strtotime($v[0])))
		 && (!$year || $year == date('Y', strtotime($v[0]))) )
	{
		if ( isset($_POST['edit_'.$id]) || isset($_POST['edit_'.$id.'_x']) )
		{
			?>
			<tr style="background: #ccc">
				<td><input name="date" value="<?php echo $v[0] ?>" /></td>
				<td><input name="note" value="<?php echo $v[1] ?>" /></td>
				<td class="nowrap">
					<input type="hidden" name="id" value="<?php echo $id ?>" />
					<input type="submit" name="edit" value="V" title="<?php _e('save', 'cpd') ?>" class="green" style="width:45%;" />
					<input type="submit" name="delete" value="X"title="<?php _e('delete', 'cpd') ?>" class="red" style="width:45%;" />
				</td>
			</tr>
			<?php
		}
		else
		{
			?>
			<tr>
				<td><?php echo $v[0] ?></td>
				<td><?php echo $v[1] ?></td>
				<td><input type="image" src="<?php echo $count_per_day->dir ?>/img/cpd_pen.png" name="edit_<?php echo $id ?>" title="<?php _e('edit', 'cpd') ?>" style="width:auto;" /></td>
			</tr>
			<?php
		}
	}
}
?>
</table>
</form>
<?php if ($count_per_day->options['debug']) $count_per_day->showQueries(); ?>
</body>
</html>