<?php
/*
Plugin Name: Courses Search Engine plugin
File : admin_menu.php
Description: function to display admin_menu page
Version: 0.1
Author: CAPE - Ludovic Bonivento
*/

include 'functions.php';

function global_callback() {
	_e( 'Global options', 'epflcse-plugin' );
}

function years_callback() {
	
	$settings = (array) get_option( 'epflcse-settings' );
	$field = "years";
	$value = esc_attr( $settings[$field] );
	
	echo "<input type='text' name='epflcse-settings[$field]' value='$value' />";
}
function section_callback() {
	
	$settings = (array) get_option( 'epflcse-settings' );
	$field = "section";
	$value = esc_attr( $settings[$field] );
	
	echo "<input type='text' name='epflcse-settings[$field]' value='$value' />";
}
function use_polyperspectives_callback() {
	
	$settings = (array) get_option( 'epflcse-settings' );
	$field = "use_polyperspectives";
	$value = esc_attr( $settings[$field] );
?>
	<input type='checkbox' name='epflcse-settings[use_polyperspectives]' <?php checked( $settings['use_polyperspectives'], 1 ); ?> value='1'/>
<?php
}
function use_keywords_callback() {
	
	$settings = (array) get_option( 'epflcse-settings' );
	$field = "use_keywords";
	$value = esc_attr( $settings[$field] );
?>
	<input type='checkbox' name='epflcse-settings[use_keywords]' <?php checked( $settings['use_keywords'], 1 ); ?> value='1'/>
<?php
}

function updateFromISA()
{
	$settings = (array) get_option( 'epflcse-settings' );
	$section = esc_attr( $settings['section'] );
	$years = esc_attr( $settings['years'] );
	
	$csv_path = plugin_dir_path(__DIR__)."csv/";
	
// 	echo '<div id="message" class="updated fade"><p>Updating data from ISA, please wait !</p></div>';
	
	$result = updateCoursesFromISAByYearSection($years,$section,$csv_path);
	
	if($result===true){
		echo '<div id="message" class="updated fade"><p>Data updated !</p></div>';
	}else{
		echo '<div id="message" class="updated fade"><p>Error during update !'.$result.'</p></div>';
	}
}



function updateFromCSV($courses_array)
{

	$result = parseCoursesArray($courses_array);
	
	if($result===true){
		echo '<div id="message" class="updated fade"><p>Data updated !</p></div>';
	}else{
		echo '<div id="message" class="updated fade"><p>Error during update !'.$result.'</p></div>';
	}
	
}



function get_admin_menu_div() {

ob_start(); ?>

<div class="wrap">
<h1>EPFL Courses Search Engine - Administration</h1>

<form method="POST" action="options.php">
	<?php settings_fields('epflcse-settings-group'); ?>
    <?php do_settings_sections('epflcse-plugin'); ?>
    <?php submit_button(); ?>
</form>

<hr>

<h2>Data management</h2>
<h4>Please see at the bottom of the page for CSV file requirements<h4>

<?php

// Check whether the button has been pressed AND also check the nonce
if (isset($_POST['updateISA_button']) && check_admin_referer('updateISA_button_clicked')) {
	
	//copy old file 
	$target_file = plugin_dir_path(__DIR__)."csv/courses_data.csv";
    copy($target_file,plugin_dir_path(__DIR__)."csv/old_courses_data.csv");
        
	//Update courses from ISA and create csv data file
	updateFromISA();
	
}
if(isset($_POST["updateFromCSV_button"]) && check_admin_referer('updateFromCSV_button_clicked')) {
    $csv_array=array();
    $tmpName = $_FILES['csvFile']['tmp_name'];
    if (($file = fopen($tmpName, "r")) !== FALSE) {
 		while (($course = fgetcsv($file, 1000, ",")) !== FALSE) {
 			
				array_push($csv_array, $course);
		}
	}

	updateFromCSV($csv_array);

}

if(isset($_POST["initKeywordsPolyperspectivesSemestersData_button"]) && check_admin_referer('initKPSData_button_clicked')) {
    $result = initKeywordsPolyperspectivesSemesters();
    if($result===true){
		echo '<div id="message" class="updated fade"><p>Init done !</p></div>';
	}else{
		echo '<div id="message" class="updated fade"><p>Error during init !'.$result.'</p></div>';
	}
}

if(isset($_POST["downloadCSV_button"]) && check_admin_referer('downloadCSV_button_clicked')) {
	downloadCoursesDataCSV();
}

echo '<div style="border:1px solid lightgrey;padding:10px;">';
echo '<h4>Update from ISA</h4>';
echo '<form action="admin.php?page=epflcse-admin" method="post">';
// this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
wp_nonce_field('updateISA_button_clicked');
echo '<input type="hidden" value="true" name="updateISA_button" />';
submit_button('Start update !');
echo '</form>';
echo '<a href="'.plugin_dir_url(__DIR__).'csv/update_data.log" target="_blank">log file</a>';
echo '</div>';

echo '<div style="border:1px solid lightgrey;padding:10px;">';
echo '<h4>Update from CSV</h4>';
echo '<form action="admin.php?page=epflcse-admin" method="post" enctype="multipart/form-data">';
wp_nonce_field('updateFromCSV_button_clicked');
echo '<input type="file" value="true" name="csvFile"  />';
echo '<input type="submit" value="Start update !" name="updateFromCSV_button" class="button button-primary" />';
echo '</form>';
echo '<a href="'.plugin_dir_url(__DIR__).'csv/update_data.log" target="_blank">log file</a>';
echo '</div>';


echo '<form action="admin.php?page=epflcse-admin" method="post" enctype="multipart/form-data">';
wp_nonce_field('downloadCSV_button_clicked');
//echo '<form action="'.plugin_dir_url(__DIR__).'csv/courses_data.csv" method="get">';
echo '<input type="hidden" value="true" name="downloadCSV_button"/>';
submit_button('Download CSV data file');
echo '</form>';
echo '</div>';

echo '<div style="border:1px solid lightgrey;padding:10px;">';
echo '<h4>Reset DB (This will delete courses data and init Keywords, Polyperspectives and Semesters Data !)</h4>';
echo '<form action="admin.php?page=epflcse-admin" method="post">';
wp_nonce_field('initKPSData_button_clicked');
echo '<input type="hidden" value="true" name="initKeywordsPolyperspectivesSemestersData_button" />';
submit_button('Start init !');
echo '</form>';
echo '</div>';

?>

<div style="border:1px solid lightgrey;padding:10px;">
<h4>CSV File requirements</h4>
<p>The CSV file to update plugin data must be a comma separated CSV file ',' with UTF-8 encoding.</p>
<p>First line must contains headers, with columns defined as below :</p>
<ol>
<li>CODE --&gt; course code ( eg. 'HUM-427(a)' )</li>
<li>SEMESTER --&gt; the semester code in the defined list {BA2,BA3,BA4,BA5,BA6,MA1,MA2}</li>
<li>TITLE --&gt; the general course title</li>
<li>TITLE_FR --&gt; the course title in French</li>
<li>TITLE_EN --&gt; the course title in English</li>
<li>RESUME_FR--&gt; the course resume in French with double-quotted text delimiter</li>
<li>RESUME_EN --&gt; the course resume in English with double-quotted text delimiter</li>
<li>PROFESSORS --&gt; course professor(s) : multiple professors are separated by a pipe '|'</li>
<li>and contain 3 informations 'sciper:firstname:lastname' ( eg. '123456:John:Doe|654321:Freddy:Kruger' ) KEYWORDS --&gt; the course keywords : multiple keywords are sepearated by a pipe '|'</li>
<li>POLYPERSPECTIVES --&gt; the course POLY-perspectives Names, separated by a pipe '|'{Interdisciplinary,Global,Citizen,Creative}</li>
</ol>
<p>If KEYWORDS and POLYPERSPECTIVES are not used, columns need to be blank.</p>
<p>Example :</p>
<p><span style="font-family: 'andale mono', times; font-size: xx-small;" data-mce-mark="1">-------------------------------------------------------------- myFile.csv ---------------------------------------------------------------- <br />
CODE,SEMESTER,TITLE,TITLE_FR,TITLE_EN,RESUME_FR,RESUME_EN,PROFESSORS,KEYWORDS,POLYPERSPECTIVES<br />HUM-999,My title,My title fr,"My title en, My resume fr","My resume en",123456:John:Doe|654321:Freddy:Kruger,BIG DATA|DESIGN|HISTORY,1|3<br />...<br /> ------------------------------------------------------------------------------------------------------------------------------------------</span></p>

</div>

<?php 

	$admin_menu_div = ob_get_contents();
	
	ob_end_clean();
	
	return $admin_menu_div;} ?>