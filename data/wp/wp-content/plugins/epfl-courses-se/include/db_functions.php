<?php

require_once('isa.php');
require_once('db_init.php');

function clearCoursesData(){
	
	global $wpdb;
	
	$sql = "DELETE FROM epfl_courses_se_course_teacher;";

	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when clearing Courses Data (function clearCoursesData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	$sql = "DELETE FROM epfl_courses_se_course_polyperspective;";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when clearing Courses Data (function clearCoursesData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	$sql = "DELETE FROM epfl_courses_se_course_keyword;";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when clearing Courses Data (function clearCoursesData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	$sql = "DELETE FROM epfl_courses_se_teacher;";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when clearing Courses Data (function clearCoursesData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	$sql = "DELETE FROM epfl_courses_se_course;";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when clearing Courses Data (function clearCoursesData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
    
    return true;
}

function addCourseData($course){
	
	$course_code = $course[0];
	
	global $wpdb;
	$wpdb->query('SET foreign_key_checks = 0;');
		
	//insert into course
	$sql = "INSERT INTO epfl_courses_se_course (`code`,`semester_code`,`title`,`language`,`title_fr`,`title_en`,`resume_fr`,`resume_en`,`credits`,`lecture_period`,`exercice_period`,`project_period`,`tp_period`,`labo_period`,`exam_info`) VALUES('";
	
	$countFields = count($course);
	for ($c=0; $c < 8; $c++) {
		
		if($c>0) {
			$sql .= "','";
		}
        $sql .= str_replace("'","''",$course[$c]);
    }

    $sql.= "'";
    
    for ($c=9; $c < 16; $c++) {
    
		if(!empty($course[$c])){
			$sql .= ",".$course[$c];
		}else{
			$sql .= ",NULL";
		}
    }
    $sql.= ");";

    if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when inserting Courses Data (function addCourseData): ' . $wpdb->print_error() ."\n" . $sql;
	}
    //insert into teacher
    if(!empty($course[8])){
		$teachers_scipers = array();
		$teachers = explode("|", $course[8]);
		foreach($teachers as $teacher) {
			$teacher_data = explode(":", $teacher);	
			
			//check if teacher exist
			$teacher_db = $wpdb->get_results("SELECT * FROM epfl_courses_se_teacher WHERE sciper = ".$teacher_data[0]);
			if($wpdb->last_error)
			{
				echo $wpdb->print_error();
				return 'There was a database error when select teacher Data (function addCourseData): ' . $wpdb->print_error()  ."\n" . $sql;
			}
			if(count($teacher_db)==0){
				$sql = "INSERT INTO epfl_courses_se_teacher (`sciper`,`firstname`,`lastname`) VALUES(".$teacher_data[0].",'".$teacher_data[1]."','".$teacher_data[2]."');";
					if ( $wpdb->query( $sql ) === false )
				{
					return 'There was a database error when inserting teacher Data (function addCourseData): ' . $wpdb->print_error()  ."\n" . $sql;
				}
			}
			array_push($teachers_scipers,$teacher_data[0]);
		}
	        
		//insert into course_teacher
		foreach($teachers_scipers as $sciper) {
			$sql = "INSERT INTO epfl_courses_se_course_teacher (`course_code`,`teacher_sciper`) VALUES('".$course_code."',".$sciper.")";
			if ( $wpdb->query( $sql ) === false )
			{
				
				return 'There was a database error when inserting course_teacher Data (function addCourseData): ' . $wpdb->print_error()  ."\n" . $sql;
			}
		}
	}
    
    //insert into course_keyword
    if(!empty($course[16])){
		$keywords = explode("|", $course[16]);
		foreach($keywords as $keyword) {
			if(!empty($keyword)){
				$sql = "INSERT INTO epfl_courses_se_course_keyword (`course_code`,`keyword_id`) VALUES('".$course_code."',";
				$sql .= "(SELECT id FROM epfl_courses_se_keyword WHERE keyword_en = '".str_replace("'","''",$keyword)."' OR keyword_fr = '".str_replace("'","''",$keyword)."'));";
				
				if ( $wpdb->query( $sql ) === false )
				{
					return 'There was a database error when inserting course_keyword Data (function addCourseData): ' . $wpdb->print_error()  ."\n" . $sql;
				}
			}
		}
	}
    
    //insert into course_polyperspective
    if(!empty($course[17])){
    	$polyperspectives = explode("|", $course[17]);
		foreach($polyperspectives as $polyperspective) {
		
			$polyId=0;
			switch ($polyperspective) {
				case "Interdisciplinaire":
					$polyId=1;
					break;
				case "Globale":
					$polyId=2;
					break;
				case "Citoyenne":
					$polyId=3;
					break;
				case "Créative":
					$polyId=4;
					break;
			}
			
			$sql = "INSERT INTO epfl_courses_se_course_polyperspective (`course_code`,`polyperspective_id`) VALUES('".$course_code."','".$polyId."');";
			
			if ( $wpdb->query( $sql ) === false )
			{
				return 'There was a database error when inserting course_polyperspective Data (function addCourseData): ' . $wpdb->print_error() ."\n" . $sql;
			}
		}
	}
	$wpdb->query('SET foreign_key_checks = 1;');
	return true;
}

function replaceSpecialChars($str) {
  $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή', ':', ',', ' ', '\'', '(', ')');
  $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η', '', '', '-','-','','');
  return str_replace($a, $b, $str);
}

function getTeachers(){
	
	global $wpdb;
	
	$sql = "SELECT * FROM epfl_courses_se_teacher";
	$teachers = $wpdb->get_results($sql);
	
	$teachersArray = array();
	
	foreach($teachers as $teacher){
		array_push($teachersArray,$teacher->firstname." ".$teacher->lastname);
	}
	
	echo json_encode($teachersArray);  
	wp_die(); 
    
}

add_action('wp_ajax_getTeachers','getTeachers');
add_action('wp_ajax_nopriv_getTeachers','getTeachers');

function getCourseTeachers($courseCode){

    global $wpdb;
    
    $sql = "SELECT * FROM epfl_courses_se_teacher t INNER JOIN epfl_courses_se_course_teacher ct ON ct.teacher_sciper = t.sciper"
            . " AND ct.course_code = '" . $courseCode . "'";
    $teachers = $wpdb->get_results($sql);
        
    return($teachers);  
	wp_die(); 
}

add_action('wp_ajax_getCourseTeachers','getCourseTeachers');
add_action('wp_ajax_nopriv_getCourseTeacherss','getCourseTeachers');

function getCourseKeywords($course_code){

	global $wpdb;
    $sql = "SELECT k.keyword_en FROM epfl_courses_se_keyword k INNER JOIN epfl_courses_se_course_keyword ck ON ck.keyword_id = k.id"
            . " AND ck.course_code = '". $course_code . "'";
    $keywords = $wpdb->get_results($sql);    
    return($keywords);  
	wp_die(); 

}

add_action('wp_ajax_getCourseKeywords','getCourseKeywords');
add_action('wp_ajax_nopriv_getCourseKeywords','getCourseKeywords');

function getCoursePolyperspectives($course_code){

	global $wpdb;

    $sql = "SELECT p.name_en FROM epfl_courses_se_polyperspective p INNER JOIN epfl_courses_se_course_polyperspective cp ON cp.polyperspective_id = p.id"
            . " AND cp.course_code = '" . $course_code . "'";
    $polyperspectives = $wpdb->get_results($sql);
        
    return($polyperspectives);  
	wp_die(); 

}

add_action('wp_ajax_getCoursePolyperspectives','getCoursePolyperspectives');
add_action('wp_ajax_nopriv_getCoursePolyperspectives','getCoursePolyperspectives');

function getFilteredCourses($semesters = null,$polyperspectives = null,$keywords = null,$teachers = null,$languages = null){

	global $wpdb;
	    
    $semArray = $_POST['semesters'];
    $polArray = $_POST['polyperspectives'];
    $langArray = $_POST['languages'];
    $teachers = $_POST['teachers'];
    $keywords = $_POST['keywords'];
    
    $sql = "SELECT * FROM epfl_courses_se_course";
    
    $whereOk = false;
    
    if(sizeof($semArray) > 0){
        $sql = $sql. " WHERE";
        $whereOk = true;
        
        $sql .= " semester_code IN ('" . implode("','",$semArray) . "')";        
    }
    if(sizeof($polArray) > 0){
        if(!$whereOk){
            $sql = $sql. " WHERE";
            $whereOk = true;
        }else{
            $sql = $sql . " AND";
        }        
        
        $sql = $sql. " code IN (SELECT course_code FROM epfl_courses_se_course_polyperspective WHERE polyperspective_id IN ("
                . "SELECT p.id FROM epfl_courses_se_polyperspective p WHERE p.name_fr IN('" . implode("','",$polArray) . "') OR "
                . "p.name_en IN('" . implode("','",$polArray) . "') ))";
    }
    
    if(sizeof($langArray) > 0){
        if(!$whereOk){
            $sql = $sql. " WHERE";
            $whereOk = true;
        }else{
            $sql = $sql . " AND";
        }
        
        $i = 1;
        foreach ($langArray as $language){
        
			if($i>1){
				$sql .= " OR ";
			}
			$sql .= " language LIKE '%".$language."%'";
			$i++;
        }
        
    }
    
    if(!empty($teachers)){
        if(!$whereOk){
            $sql = $sql. " WHERE";
            $whereOk = true;
        }else{
            $sql = $sql . " AND";
        }
        
        $sql = $sql. " code IN (SELECT course_code FROM epfl_courses_se_course_teacher WHERE teacher_sciper  = (SELECT t.sciper FROM epfl_courses_se_teacher t WHERE '" .  $teachers . "' LIKE CONCAT(t.firstname,'%') AND '" .  $teachers . "' LIKE CONCAT('%',t.lastname)))";
        
    }
    
    if(!empty($keywords)){
        if(!$whereOk){
            $sql = $sql. " WHERE";
            $whereOk = true;
        }else{
            $sql = $sql . " AND";
        }
        
        $sql = $sql. " code IN (SELECT course_code FROM epfl_courses_se_course_keyword WHERE keyword_id  = (SELECT k.id FROM epfl_courses_se_keyword k WHERE keyword_en = '" . $keywords . "' OR keyword_fr = '" . $keywords . "'))";
        
    }
    
    //echo $sql;wp_die();
    
    $courses = $wpdb->get_results($sql);

    $html = "<table class='table'>
                <thead>
                <tr>
                    <th colspan='8'>".__('Courses', 'epfl-courses-se')." (". count($courses) .")</th>
                </tr>
                <tr>
                    <th colspan='8'>".__('l=Lecture, e=Exercice, p=Project', 'epfl-courses-se')
							."<img src='".plugin_dir_url(__DIR__)."/images/fall24_icon.png' style='margin-left:10px;margin-right:5px;width:16px;height:16px;'>".__('Fall','epfl-courses-se')
							."<img src='".plugin_dir_url(__DIR__)."/images/winter24_icon.png' style='margin-left:10px;margin-right:5px;width:16px;height:16px;'>".__('Winter','epfl-courses-se')
							."<img src='".plugin_dir_url(__DIR__)."/images/spring24_icon.png' style='margin-left:10px;margin-right:5px;width:16px;height:16px;'>".__('Spring','epfl-courses-se')
							."<img src='".plugin_dir_url(__DIR__)."/images/summer24_icon.png' style='margin-left:10px;margin-right:5px;width:16px;height:16px;'>".__('Summer','epfl-courses-se')
					."</th>
                </tr>
                <tr>
                    <th style='min-width:90px;'>".__('Code', 'epfl-courses-se')."</th>
                    <th style='min-width:80px;'>".__('Semester', 'epfl-courses-se')."</th>
                    <th>".__('Lecturers', 'epfl-courses-se')."</th>
                    <th style='min-width:30px;text-align:center;'>".__('l', 'epfl-courses-se')."</th>
                    <th style='min-width:30px;text-align:center;'>".__('e', 'epfl-courses-se')."</th>
                    <th style='min-width:30px;text-align:center;'>".__('p', 'epfl-courses-se')."</th>
                    <th style='text-align:center;'>".__('Exam', 'epfl-courses-se')."</th>
                    <th style='text-align:center;'>".__('Credits/Coefficient', 'epfl-courses-se')."</th>
                </tr></thead>";

	foreach($courses as $course) {
        $teachers = getCourseTeachers($course->code);
        $htmlTeachers = "";
        foreach($teachers as $teacher){
            if(!empty($htmlTeachers)){
                $htmlTeachers = $htmlTeachers . "<br/>";
            }
            $htmlTeachers = $htmlTeachers . $teacher->firstname . " " . $teacher->lastname;
        }
        
        $title = $course->title_fr;
        $resume = $course->resume_fr;
        $language = $course->language;
        
        if($course->language=='EN'){
		    $resume = $course->resume_en;
		    $title = $course->title_en;
        }else if($course->language=='FR-EN'){
			$resume .= "<br/><br/>".$course->resume_fr;
			$language = 'FR';
        }
        
        if(empty($title)) $title = $course->title;
        
        $examPeriod = 'summer';
		$coursePeriod = 'spring';
        if($course->semester_code=='BA3' || $course->semester_code=='BA5' || $course->semester_code=='MA1'){
            $examPeriod = 'winter';
            $coursePeriod = 'fall';
        }
        
        $courseBookHL = "<a target='_blank' href='https://edu.epfl.ch/coursebook/". strtolower($language) ."/";
        $courseBookHL = $courseBookHL . replaceSpecialChars(strtolower($title)) . "-" . strtoupper(str_replace('(','-',str_replace(')','',$course->code)));
        $courseBookHL = $courseBookHL."'>".$title."</a>";
        
        
        $langImgPath = plugin_dir_url(__DIR__)."/images/".strtolower($course->language)."16_icon.png";
        $semesterImgPath = plugin_dir_url(__DIR__)."/images/".$coursePeriod."24_icon.png";
        $examSemesterImgPath = plugin_dir_url(__DIR__)."/images/".$examPeriod."24_icon.png";
        
        $html = $html. "<tbody>
                        <tr>
                            <td colspan='8' style='padding:0px;border-bottom:none;'>
								<button class='collapse-title collapse-title-desktop collapsed'
										type='button'
										data-toggle='collapse'
										data-target='#resume".replaceSpecialChars($course->code)."'
										aria-expanded='false'
										aria-controls='collapse-1'><img src='".$langImgPath."' style='margin-right:5px;'><b>". $courseBookHL . "</b></button>"	
								."<div class='collapse collapse-item collapse-item-desktop' id='resume".replaceSpecialChars($course->code)."'>
									<p>". $resume . "</p></div></td>
                        </tr>
                        <tr>
							<td style='white-space: nowrap;'>" . $course->code . "</td>
                            <td>" . $course->semester_code . " <img src='".$semesterImgPath."' style='margin-right:5px;width:16px;height:16px;'></td>
                            <td>" . $htmlTeachers . "</td>
                            <td style='text-align:center;'>" . $course->lecture_period . "</td>
                            <td style='text-align:center;'>" . $course->exercice_period . "</td>
                            <td style='text-align:center;'>" . $course->project_period . "</td>
                            <td style='text-align:center'><img src='".$examSemesterImgPath."' style='margin-right:5px;;width:16px;height:16px;'>" . $course->exam_info . "</td>
                            <td style='text-align:center;'>" . $course->credits . "</td>
                        </tr>
                    </tbody>";
    }
    $html = $html. "</table>";
    
 	echo $html;  
 	wp_die();
}

add_action('wp_ajax_getFilteredCourses','getFilteredCourses');
add_action('wp_ajax_nopriv_getFilteredCourses','getFilteredCourses');

function getPolyperspectives($language = null){
	
	global $wpdb;
	
	$sql = "SELECT * FROM epfl_courses_se_polyperspective";
	$polyperspectives = $wpdb->get_results($sql);
	
	$polyperspectivesArray = array();
	
	$html = "<table class='table' style='border:none; font-size: 1rem;'>";
     
	$col = 0;
    
	foreach($polyperspectives as $polyperspective){
 		if($col == 0){
 			$html = $html. "<tr>";
 		}
 		
 		$polyperspectiveName = $polyperspective->name_en;
 		if(pll_current_language()=="fr"){
			$polyperspectiveName = $polyperspective->name_fr;
 		}
 		
 		$html = $html. "<td style='border-bottom:none'><div class='custom-control custom-checkbox'><input type='checkbox' class='custom-control-input' id='" . $polyperspectiveName . "' name='" . $polyperspectiveName . "' value='" . $polyperspective->id . "' onclick='handlePolyperspectiveClick(this)'/><label for='" . $polyperspectiveName . "' class='custom-control-label'>" . $polyperspectiveName . "</label></div></td>";
 		
 		$col++;
 		
 		if($col == 2){
 			$html = $html. "</tr>";
 			$col = 0;
 			
 		}
 	}
     
	$html = $html. "</table><br/>";

 	echo json_encode($html);  
 	wp_die();
	
    
}

add_action('wp_ajax_getPolyperspectives','getPolyperspectives');
add_action('wp_ajax_nopriv_getPolyperspectives','getPolyperspectives');

function getSemesters(){

	global $wpdb;
	
	$sql = "SELECT * FROM epfl_courses_se_semester";
	$semesters = $wpdb->get_results($sql);
    
    $html = "<table class='table' style='border:none; font-size: 1rem;'>";
    
    $col = 0;
             
	foreach($semesters as $semester) {
        if($col == 0){
            $html = $html. "<tr>";
        }
        
        $html = $html. "<td style='border-bottom:none'><div class='custom-control custom-checkbox'><input type='checkbox' class='custom-control-input' id='" . $semester->code . "' name='" . $semester->code . "' value='" . $semester->code . "' onclick='handleSemesterClick(this)'/><label for='" . $semester->code . "' class='custom-control-label'>" . $semester->code . "</label></div></td>";
        
        $col++;
        
        if($col == 4){
            $html = $html. "</tr>";
            $col = 0;
            
        }
    }
     
	$html = $html. "</table><br/>";

 	echo json_encode($html);  
 	wp_die();
    
}

add_action('wp_ajax_getSemesters','getSemesters');
add_action('wp_ajax_nopriv_getSemesters','getSemesters');

function getKeywords(){
	
	global $wpdb;
	
	$sql = "SELECT * FROM epfl_courses_se_keyword";
	$keywords = $wpdb->get_results($sql);
	
	$keywordsArray = array();
	
	foreach($keywords as $keyword){
		array_push($keywordsArray,$keyword->keyword_fr);
		array_push($keywordsArray,$keyword->keyword_en);
	}
	
	echo json_encode($keywordsArray);  
	wp_die(); 
}

add_action('wp_ajax_getKeywords','getKeywords');
add_action('wp_ajax_nopriv_getKeywords','getKeywords');

function getCloudKeywords(){
    global $wpdb;
    
    $sql = "SELECT * FROM 
				(SELECT k.keyword_en keyword, count(ck.keyword_id) count
						FROM epfl_courses_se_course_keyword ck
						INNER JOIN epfl_courses_se_keyword k ON k.id = ck.keyword_id
						GROUP BY ck.keyword_id
						ORDER BY count(ck.keyword_id) desc ) as ken
			UNION
			SELECT * FROM 
				(SELECT k.keyword_fr keyword, count(ck.keyword_id) count
						FROM epfl_courses_se_course_keyword ck
						INNER JOIN epfl_courses_se_keyword k ON k.id = ck.keyword_id
						GROUP BY ck.keyword_id
						ORDER BY count(ck.keyword_id) desc ) as kfr
			ORDER BY count desc, keyword asc";
    $cloudKeywords = $wpdb->get_results($sql);
    
    $cloudKeywordsArray = array();
    
    $i = 0;
        
    foreach($cloudKeywords as $cloudKeyword) {
        $i++;
        array_push($cloudKeywordsArray,array('text' => $cloudKeyword->keyword, 'weight' => rand(1,20)));
    }
    
    echo json_encode($cloudKeywordsArray);
    wp_die();
}

add_action('wp_ajax_getCloudKeywords','getCloudKeywords');
add_action('wp_ajax_nopriv_getCloudKeywords','getCloudKeywords');

function initKeywordsPolyperspectivesSemestersData(){
	global $wpdb;
	
	//FIRST DROP DATA
	$sql = "DELETE FROM epfl_courses_se_keyword;";

	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when deleting keywords (function initKeywordsPolyperspectivesSemestersData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	$sql = "DELETE FROM epfl_courses_se_polyperspective;";

	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when deleting polyperspectives (function initKeywordsPolyperspectivesSemestersData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	$sql = "DELETE FROM epfl_courses_se_semester;";

	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when deleting semesters (function initKeywordsPolyperspectivesSemestersData): ' . $wpdb->print_error()  ."\n" . $sql;
	}
	
	//KEYWORDS
	$sql = "INSERT INTO `epfl_courses_se_keyword` VALUES (1,'15TH CENTURY','15E SIÈCLE'),(2,'16TH CENTURY','16E SIÈCLE'),(3,'17TH CENTURY','17E SIÈCLE'),(4,'18TH CENTURY','18E SIÈCLE'),(5,'19TH CENTURY','19E SIÈCLE'),(6,'20TH CENTURY','20E SIÈCLE'),(7,'ACOUSTIC','ACOUSTIQUE'),(8,'ADAPTATION','ADAPTATION'),(9,'ADMINISTRATION','ADMINISTRATION'),(10,'AFFECT','AFFECTER'),(11,'AFRICA','AFRIQUE'),(12,'RANDOM','ALÉATOIRE'),(14,'NORTH AMERICA','AMÉRIQUE DU NORD'),(15,'ANALYSIS','ANALYSE'),(16,'ANALYSIS OF THE SPEECH','ANALYSE DU DISCOURS'),(17,'ANTHROPOCENTRISM','ANTHROPOCENTRISME'),(18,'ANTHROPOLOGY','ANTHROPOLOGIE'),(19,'ANTIQUITY','ANTIQUITÉ'),(20,'MOBILE APPLICATIONS','APPLICATIONS MOBILES'),(21,'ARCHITECTURE','ARCHITECTURE'),(22,'AREA STUDIES','ÉTUDES RÉGIONALES'),(23,'ART','ART'),(24,'ART BRUT','ART BRUT'),(25,'ARTIFICE','ARTIFICE'),(26,'CRAFT','ARTISANAT'),(27,'ARTISTIC CREATION','CREATION ARTISTIQUE'),(28,'LIVE ARTS','ARTS VIVANTS'),(29,'ASIAN STUDIES','ÉTUDES ASIATIQUES'),(30,'ASIA','ASIE'),(31,'EASTERN ASIA','ASIE ORIENTALE'),(32,'ASSEMBLY','ASSEMBLAGE'),(33,'ATONALITY','ATONALITÉ'),(34,'AUSTRALIA','AUSTRALIE'),(35,'AVATARS','AVATARS'),(36,'BAROQUE','BAROQUE'),(37,'BAROQUE MUSIC','MUSIQUE BAROQUE'),(38,'BEHAVIORS','COMPORTEMENTS'),(39,'BEHAVIOURISM','BEHAVIORISME'),(40,'BELIEF','CROYANCE'),(41,'BIG DATA','BIG DATA'),(42,'BIO-DIVERSITY','BIO-DIVERSITÉ'),(43,'BIOLOGICAL FUNCTION','FONCTION BIOLOGIQUE'),(44,'BIOLOGICAL RESOURCES','RESSOURCES BIOLOGIQUES'),(45,'BIOLOGICAL SPECIES','ESPECES BIOLOGIQUES'),(46,'BLUES','BLUES'),(47,'BODY IMAGE','IMAGE CORPORELLE'),(48,'BODY REPRESENTATION','REPRÉSENTATION DU CORPS'),(49,'BLACK BOX','BOÎTE NOIRE'),(50,'BRAIN','CERVEAU'),(51,'BRAINSTORMING','BRAINSTORMING'),(52,'BUSINESS','AFFAIRES'),(53,'BUSINESS PLAN','PLAN D\'AFFAIRES'),(54,'CAREERS','CARRIÈRES'),(55,'MAPPING','CARTOGRAPHIE'),(56,'CASE STUDY','ÉTUDE DE CAS'),(57,'DISASTERS','CATASTROPHES'),(58,'CATEGORIZATION','CATÉGORISATION'),(59,'CAUSALITY','CAUSALITÉ'),(60,'CHALLENGES','DÉFIS'),(61,'SHIISM','CHIISME'),(62,'CHINA','CHINE'),(63,'CIA','CIA'),(64,'CINEMA','CINÉMA'),(65,'CIVIL DISOBEDIENCE','DÉSOBÉISSANCE CIVILE'),(66,'CIVIL LIABILITY','RESPONSABILITÉ CIVILE'),(67,'CIVILIZATION','CIVILISATION'),(68,'CLASS','CLASSE'),(69,'CLASSICAL MUSIC','MUSIQUE CLASSIQUE'),(70,'CLASSICAL STUDIES','ÉTUDES CLASSIQUES'),(71,'CLASSIFICATION','CLASSIFICATION'),(72,'CLASSIC','CLASSIQUE'),(73,'KEYBOARDS','CLAVIERS'),(74,'CLIMATE WARMING','RÉCHAUFFEMENT CLIMATIQUE'),(75,'CODE','CODE'),(76,'COGNITION','COGNITION'),(77,'COGNITIVE RESOURCES','RESSOURCES COGNITIVES'),(78,'COLD WAR','GUERRE FROIDE'),(79,'COLLECTIVE ACTION','ACTION COLLECTIVE'),(80,'COLOUR','COULEUR'),(82,'COMIC BOOKS','BANDES DESSINÉES'),(84,'COMMUNICATION','COMMUNICATION'),(85,'COMMUNICATION THEORIES','THÉORIES DE COMMUNICATION'),(86,'COMPETITION','COMPÉTITION'),(88,'HUMAN BEHAVIOR','COMPORTEMENT HUMAIN'),(89,'INSTANT COMPOSITION','COMPOSITION INSTANTANÉE'),(90,'TRAINING CONCEPT','CONCEPT DE FORMATION'),(91,'CONCEPTUAL IDEAS','IDÉES CONCEPTUELLES'),(92,'CONCEPTUAL PRACTICES','PRATIQUES CONCEPTUELLES'),(93,'CONCERTO','CONCERTO'),(94,'CONFLICT','CONFLIT'),(95,'CONFORMISM','CONFORMISME'),(96,'CONSCIENTIOUS OBJECTION','OBJECTION DE CONSCIENCE'),(97,'CONSCIOUSNESS','CONSCIENCE'),(98,'CONSUMPTION','CONSOMMATION'),(99,'CONSPIRACIES','CONSPIRATIONS'),(100,'CONSTITUTIONAL JUSTICE','JUSTICE CONSTITUTIONNELLE'),(101,'CONSTRAINT','CONTRAINTE'),(102,'CONSTRUCTION','CONSTRUCTION'),(103,'CONTEMPORARY ART','ART CONTEMPORAIN'),(104,'CONTEMPORARY CHINA','CHINE CONTEMPORAINE'),(105,'CONTEMPORARY DRAWING','DESSIN CONTEMPORAIN'),(106,'CONTEMPORARY HISTORY','HISTOIRE CONTEMPORAINE'),(107,'CONTENT ANALYSIS','ANALYSE DE CONTENU'),(108,'CONTRACTS','CONTRATS'),(109,'COUNTERPRODUCTIVITY','CONTREPRODUCTIVITÉ'),(110,'CONTROVERSY','CONTROVERSE'),(112,'CORPUS','CORPUS'),(113,'CREATION','CRÉATION'),(114,'CREATION OF NATION STATE','CRÉATION D\'UN ÉTAT DE NATION'),(115,'CREATIONISM','CRÉATIONNISME'),(116,'CREATIVITY','CRÉATIVITÉ'),(117,'CROWDSOURCING','CROWDSOURCING'),(118,'CULTURE','CULTURE'),(119,'CYBORG','CYBORG'),(120,'DANCE','DANSE'),(121,'DARWINISM','DARWINISME'),(122,'DATA COLLECTION','COLLECTE DE DONNÉES'),(123,'DATA PROTECTION','PROTECTION DES DONNÉES'),(124,'DEFINITION OF LIFE','DÉFINITION DE VIE'),(125,'DEMOCRATIC GOVERNANCE','GOUVERNANCE DEMOCRATIQUE'),(126,'DEMOCRACY','DÉMOCRATIE'),(127,'DEMOGRAPHY','DÉMOGRAPHIE'),(128,'DESIGN','CONCEPTION'),(129,'DESIGN FICTION','FICTION DE DESIGN'),(130,'INDUSTRIAL DESIGN','DESIGN INDUSTRIEL'),(131,'DETERMINISM','DÉTERMINISME'),(132,'DEVELOPMENT','DÉVELOPPEMENT'),(133,'DEVIANCE','DÉVIANCE'),(134,'DIFFUSION','DIFFUSION'),(135,'DIGITAL CULTURE','CULTURE NUMÉRIQUE'),(137,'DIGITALIZATION','NUMÉRISATION'),(138,'DISCRIMINATION','DISCRIMINATION'),(139,'DOCUMENTARY','DOCUMENTAIRE'),(140,'DOCUMENTATION','DOCUMENTATION'),(141,'DODECAPHONISM','DODÉCAPHONISME'),(142,'DODECAPHONY','DODÉCAPHONIE'),(143,'DRAWING','DESSIN'),(144,'DUAL-USE TECHNOLOGIES','TECHNOLOGIES À DOUBLE USAGE'),(145,'DYSFUNCTION','DYSFONCTIONNEMENT'),(146,'DYSTOPIA','DYSTOPIE'),(147,'ECO- OR BIOCENTRISM','ECO -OU BIOCENTRISME'),(148,'ECOLOGICAL TRANSITION','TRANSITION ECOLOGIQUE'),(149,'ECONOMIC CRIMINAL LAW','DROIT PENAL ECONOMIQUE'),(150,'ECONOMIC FREEDOM','LIBERTÉ ÉCONOMIQUE'),(151,'ECONOMIC STRUCTURE','STRUCTURE ECONOMIQUE'),(152,'SOCIAL ECONOMY','ÉCONOMIE SOCIALE'),(153,'ECONOMY','ÉCONOMIE'),(154,'WRITING','ÉCRITURE'),(155,'EDUCATION','ÉDUCATION'),(156,'EGYPT','EGYPTE'),(157,'ELECTRONIC','ÉLECTRONIQUE'),(158,'EMERGENTISM','ÉMERGENTISME'),(159,'EMOTION','ÉMOTION'),(160,'EMPIRICAL STUDIES','ÉTUDES EMPIRIQUES'),(161,'EMPIRICISM','EMPIRISME'),(162,'ENCYCLOPEDISM','ENCYCLOPÉDISME'),(163,'ENERGY','ÉNERGIE'),(164,'ENERGY POLICY','POLITIQUE ENERGETIQUE'),(165,'ENTREPRENEURIAL SPIRIT','ESPRIT D\'ENTREPRISE'),(166,'ENTREPRENEURSHIP','ENTREPRENEURIAT'),(167,'ENVIRONMENTAL IMPACT','IMPACT ENVIRONNEMENTAL'),(168,'ENVIRONMENTAL RESPONSIBILY','RESPONSABILITÉ ENVIRONNEMENTALE'),(169,'ENVIRONMENT','ENVIRONNEMENT'),(170,'EQUALITY','ÉGALITÉ'),(171,'ERGONOMICS','ERGONOMIE'),(172,'AESTHETIC','ESTHÉTIQUE'),(173,'ETHICS','ÉTHIQUE'),(174,'ETHNICITY','ETHNICITÉ'),(175,'GENDER STUDY','ÉTUDE DE GENRE'),(176,'EUROPE','EUROPE'),(177,'EVALUATIVE PRESSURE','PRESSION D\'EVALUATION'),(178,'EVOLUTION','ÉVOLUTION'),(179,'EVOLUTIONARY THEORY','THEORIE EVOLUTIONNAIRE'),(180,'EVOLUTIONISM','ÉVOLUTIONNISME'),(181,'EXHIBITION','EXPOSITION'),(182,'EXPRESSION','EXPRESSION'),(183,'EXPRESSIONISM','EXPRESSIONNISME'),(184,'FEMINISM','FÉMINISME'),(185,'FICTION','FICTION'),(186,'MOVIE','FILM'),(187,'FUNCTIONALITY','FONCTIONNALITÉ'),(188,'FOREIGN POLICY','POLICE ÉTRANGÈRE'),(189,'FORMALISM','FORMALISME'),(190,'FORMAT','FORMAT'),(191,'FORM','FORME'),(192,'FRAUD','FRAUDE'),(193,'FREE WILL','LIBRE ARBITRE'),(194,'FUNCTION','UNE FONCTION'),(196,'FUNDAMENTAL RIGHTS','DROITS FONDAMENTAUX'),(197,'FUTURISM','FUTURISME'),(198,'GENDER','GENRE'),(199,'GEOGRAPHY','GÉOGRAPHIE'),(200,'GEOPOLITICS','GÉOPOLITIQUE'),(201,'MANAGEMENT','GESTION'),(202,'GLASS CEILING','PLAFOND DE VERRE'),(203,'GLOBAL HISTORY','HISTOIRE GLOBALE'),(205,'GRAPHIC DESIGN','GRAPHISME'),(206,'GREECE','GRÈCE'),(207,'GROOVE','RAINURE'),(208,'GROUP','GROUPE'),(209,'GROWTH','CROISSANCE'),(210,'GUERRILLA','GUÉRILLA'),(211,'HACKING','PIRATAGE'),(212,'HEROISM','HÉROÏSME'),(213,'HISTORY','HISTOIRE'),(214,'HUMAN','HUMAIN'),(215,'HUMANITARIAN','HUMANITAIRE'),(216,'DIGITAL HUMANITIES','HUMANITÉS DIGITALES'),(217,'HUMANITY','HUMANITÉ'),(218,'HUMOR','HUMOUR'),(219,'HYPOTHESIS','HYPOTHÈSE'),(220,'IDEOLOGY','IDÉOLOGIE'),(221,'ILLUSTRATION','ILLUSTRATION'),(222,'PICTURE','IMAGE'),(223,'IMITATION','IMITATION'),(224,'IMMATERIAL','IMMATÉRIEL'),(225,'IMPARTIALITY','IMPARTIALITÉ'),(226,'IMPRESSION','IMPRESSION'),(227,'IMPRESSIONNISME','IMPRESSIONNISME'),(228,'IMPROVISATION','IMPROVISATION'),(229,'INDEPENDENCE','INDÉPENDANCE'),(230,'INDETERMINISM','INDÉTERMINISME'),(231,'INDIGENOUS','INDIGÈNE'),(232,'INDUSTRIALIZATION','INDUSTRIALISATION'),(233,'SOCIAL INEQUALITY','INÉGALITÉ SOCIALE'),(234,'INEQUALITY','INÉGALITÉ'),(235,'INFLUENCE','INFLUENCE'),(236,'COMPUTER SCIENCE','INFORMATIQUE'),(237,'ENGINEERING','INGÉNIERIE'),(238,'INNOVATION','INNOVATION'),(239,'INPUT','CONTRIBUTION'),(240,'INSTITUTION','INSTITUTION'),(241,'INSTRUMENT MAKER','FABRICANT D\'INSTRUMENT'),(242,'INSTRUMENTAL','INSTRUMENTAL'),(243,'INTEGRATION','INTÉGRATION'),(244,'INTEGRITY IN RESEARCH','INTÉGRITÉ DE LA RECHERCHE'),(245,'INTELLIGENCE','INTELLIGENCE'),(246,'INTELLIGENT DESIGN','DESIGN INTELLIGENT'),(247,'INTERACTION','INTERACTION'),(248,'INTERCULTURALITY','INTERCULTURALITÉ'),(249,'INTERDISCIPLINARITY','INTERDISCIPLINARITÉ'),(250,'INTERFACE','INTERFACE'),(251,'INTERNATIONAL','INTERNATIONAL'),(252,'INTERNATIONALIZATION','INTERNATIONALISATION'),(253,'INTERNET','INTERNET'),(254,'INTUITION','INTUITION'),(255,'INVENTION','INVENTION'),(256,'INVESTMENTS PROTECTION','PROTECTION DES INVESTISSEMENTS'),(257,'IRAN','IRAN'),(258,'IRONY','IRONIE'),(259,'JAPAN','JAPON'),(260,'JAZZ','JAZZ'),(261,'YOUTH','JEUNESSE'),(262,'JUSTICE','JUSTICE'),(263,'KEYBOARD','CLAVIER'),(264,'LABORATORY LIFE','VIE DE LABORATOIRE'),(265,'LANGUAGE','LANGAGE'),(266,'LATIN AMERICA','AMÉRIQUE LATINE'),(267,'LAYOUT','DISPOSITION'),(268,'LEADERSHIP','DIRECTION'),(269,'LEARNING','APPRENTISSAGE'),(270,'LEGAL FRAMEWORK','CADRE JURIDIQUE'),(271,'LIBERTY','LIBERTÉ'),(272,'LITERATURE','LITTÉRATURE'),(273,'SOFTWARE','LOGICIEL'),(274,'LUTHERIE','LUTHERIE'),(276,'TECHNOLOGY MANAGEMENT','MANAGEMENT DES TECHNOLOGIES'),(277,'HANDLING','MANIPULATION'),(278,'MARKET','MARCHÉ'),(279,'MASS MEDIA','MÉDIAS'),(280,'MATERIALIZATION','MATÉRIALISATION'),(281,'MATERIALS','MATÉRIAUX'),(282,'EQUIPMENT','MATÉRIEL'),(283,'MATHEMATICS','MATHÉMATIQUES'),(284,'MECHANISM','MÉCANISME'),(285,'MEDICINE','MÉDECINE'),(286,'MEDIA','MÉDIA'),(287,'MEDIA CULTURE','CULTURE DES MÉDIAS'),(288,'MEDIA LITERACY','ALPHABÉTISATION DES MÉDIAS'),(289,'MEDIA REPRESENTATIONS','REPRÉSENTATIONS MÉDIATIQUES'),(290,'SOCIAL MEDIA','MÉDIAS SOCIAUX'),(292,'MEDITERRANEAN','MÉDITERRANÉEN'),(293,'MENTAL IMAGERY','IMAGERIE MENTALE'),(294,'MESOPOTAMIA','MESOPOTAMIA'),(295,'METHODS','METHODES'),(296,'MICRO-ECONOMY','MICRO-ÉCONOMIE'),(297,'STAGING','MISE EN SCÈNE'),(298,'MOBILIZATION','MOBILISATION'),(299,'MOBILITY','MOBILITÉ'),(300,'MOCK-UP','MAQUETTE'),(301,'MODALITY','MODALITÉ'),(302,'MODERNITY','MODERNITÉ'),(303,'MODERNIZATION','MODERNISATION'),(304,'MOLDING','MOULAGE'),(305,'GLOBALIZATION','MONDIALISATION'),(306,'MOTIVATION','MOTIVATION'),(307,'MIDDLE EAST','MOYEN-ORIENT'),(308,'MULTIMEDIA','MULTIMÉDIA'),(309,'MUSEUM','MUSÉE'),(310,'MUSIC','MUSIQUE'),(311,'MUSIC ANALYSIS','ANALYSE MUSICALE'),(312,'MUSIC HALL','MUSIC-HALL'),(313,'MUSIC THEORY','THÉORIE DE LA MUSIQUE'),(314,'MUSICAL INSTRUMENTS','INSTRUMENTS DE MUSIQUE'),(316,'AMERICAN MUSIC','MUSIQUE AMÉRICAINE'),(317,'CONTEMPORARY MUSIC','MUSIQUE CONTEMPORAINE'),(318,'ART MUSIC','MUSIQUE SAVANTE'),(319,'VOICE MUSIC','MUSIQUE VOCALE'),(320,'MYTHOLOGY','MYTHOLOGIE'),(321,'NARRATION','NARRATION'),(322,'NARRATOLOGIE','NARRATOLOGIE'),(323,'NATION','NATION'),(324,'NATURAL KINDS','GENRES NATURELS'),(325,'NATURAL SELECTION','SÉLECTION NATURELLE'),(326,'NATURE','NATURE'),(327,'NEOCLASSICISM','NÉOCLASSICISME'),(328,'NEUTRALITY','NEUTRALITÉ'),(329,'NEW EXPERIMENTS','NOUVELLES EXPÉRIENCES'),(330,'NEW FIRM','NOUVELLE FERME'),(331,'STANDARDS','NORMES'),(332,'MUSICAL NOTATION','NOTATION MUSICALE'),(333,'NUCLEAR BOMB','BOMBE NUCLÉAIRE'),(334,'DIGITAL','NUMÉRIQUE'),(335,'NURTURE','NOURRIR'),(336,'OBJECT','OBJET'),(337,'OBLIGATION','OBLIGATION'),(338,'OBSERVATION','OBSERVATION'),(339,'OCCUPATIONAL SEGREGATION','SÉGRÉGATION PROFESSIONNELLE'),(340,'OPERA','OPÉRA'),(341,'OPÉRA BOUFFE','OPÉRA BOUFFE'),(342,'OPERA SERIA','OPÉRA SERIA'),(343,'ORGANIZATION','ORGANISATION'),(344,'ORGANOLOGY','ORGANOLOGIE'),(345,'ORIENTALISM','ORIENTALISME'),(346,'ORIGINALITY','ORIGINALITÉ'),(347,'DIGITAL TOOL','OUTIL NUMÉRIQUE'),(348,'OUTSIDER ART','ART EXTERIEUR'),(349,'PAINTING','PEINTURE'),(350,'KINSHIP','PARENTÉ'),(351,'PARODY','PARODIE'),(352,'PARTICIPATORY DEMOCRACY','DEMOCRATIE PARTICIPATIVE'),(353,'HERITAGE','PATRIMOINE'),(354,'POVERTY','PAUVRETÉ'),(355,'EMERGING COUNTRIES','PAYS ÉMERGENTS'),(356,'DEVELOPING COUNTRIES','PAYS EN VOIE DE DÉVELOPPEMENT'),(357,'LANDSCAPE','PAYSAGE'),(359,'PERCEPTION','PERCÉPTION'),(360,'PERCUSSIONS','PERCUSSIONS'),(361,'PERFORMANCE','PERFORMANCE'),(362,'PERSUASION','PERSUASION'),(363,'PHILOSOPHY','PHILOSOPHIE'),(364,'PHOTOGRAPHY','PHOTOGRAPHIE'),(365,'PHYSICS','PHYSIQUE'),(366,'PHYSICAL','CORPORAL'),(367,'PLAGIARISM','PLAGIAT'),(368,'PLURALISM','PLURALISME'),(369,'POLITICAL INSTITUTIONS','INSTITUTIONS POLITIQUES'),(370,'POLITICAL RIGHTS','DROITS POLITIQUES'),(371,'POLITICS','POLITIQUE'),(372,'CULTURAL POLICIES','POLITIQUES CULTURELLES'),(373,'POPULAR CULTURES','CULTURES POPULAIRES'),(374,'POPULAR MUSIC','MUSIQUE POPULAIRE'),(375,'POST-FEMINISM','POST-FÉMINISME'),(376,'TO POST','POSTER'),(377,'POSTHUMANISM','POSTHUMANISME'),(379,'POWER','PUISSANCE'),(380,'PRACTICES','PRATIQUES'),(381,'PRESS','PRESSE'),(382,'PREVENTION','PRÉVENTION'),(383,'PROBLEMATIC','PROBLÉMATIQUE'),(384,'PROFESSIONAL','PROFESSIONNEL'),(385,'INTELLECTUAL PROPERTY','PROPRIÉTÉ INTELLECTUELLE'),(386,'PROTOTYPE','PROTOTYPE'),(387,'PROTOTYPING','PROTOTYPAGE'),(388,'PSYCHOANALYSIS','PSYCHANALYSE'),(389,'COGNITIVE PSYCHOLOGY','PSYCHOLOGIE COGNITIVE'),(390,'PSYCHOLOGY','PSYCHOLOGIE'),(391,'PSYCHOPATHOLOGY','PSYCHOPATHOLOGIE'),(392,'PUBLIC','PUBLIQUE'),(393,'PUBLIC DEBATE','DÉBAT PUBLIC'),(394,'PUBLIC SPACE','ESPACE PUBLIC'),(395,'PUBLISHING','ÉDITION'),(396,'PURPOSE','OBJECTIF'),(397,'QUANTUM PHYSICS','PHYSIQUE QUANTIQUE'),(398,'QUEER','QUEER'),(399,'RACE','COURSE'),(400,'RAGTIME','RAG-TIME'),(401,'VIRTUAL REALITY','RÉALITÉ VIRTUELLE'),(402,'REASONING','RAISONNEMENT'),(403,'RECIPROCITY','RÉCIPROCITÉ'),(404,'REDUCTIONISM','RÉDUCTIONNISME'),(405,'REFUGEES','RÉFUGIÉS'),(406,'INTERNATIONAL RELATIONSHIPS','RELATIONS INTERNATIONALES'),(407,'RELATIVITY','RELATIVITÉ'),(408,'RELIGION','RELIGION'),(409,'RENAISSANCE','RENAISSANCE'),(410,'REPRESENTATION','REPRÉSENTATION'),(411,'REPURPOSING','RÉAFFECTER'),(412,'RESEARCH','RECHERCHE'),(413,'RESEARCH ETHICS','ETHIQUE DE RECHERCHE'),(414,'RESEARCH PRACTICES','PRATIQUES DE RECHERCHE'),(415,'RESPONSIBILITY','RESPONSABILITÉ'),(416,'WEALTH','RICHESSE'),(417,'RIGHTS','DROITS'),(418,'RISK','RISQUE'),(419,'ROBOTICS','ROBOTIQUE'),(420,'ROMANTIC','ROMANTIQUE'),(421,'ROME','ROME'),(422,'RUSSIA','RUSSIE'),(423,'HEALTH','SANTÉ'),(424,'WORK HEALTH','SANTÉ DU TRAVAIL'),(425,'PUBLIC HEALTH','SANTÉ PUBLIQUE'),(426,'SCANDALS','SCANDALES'),(427,'SCIENCE','SCIENCE'),(428,'SCIENCE-FICTION','SCIENCE-FICTION'),(429,'SCIENCES','SCIENCES'),(430,'ENGINEERING SCIENCES','SCIENCES DE L’INGÉNIEUR'),(431,'BRAIN SCIENCES','SCIENCES DU CERVEAU'),(432,'ECONOMIC SCIENCE','SCIENCES ÉCONOMIQUES'),(433,'SCIENTOMETRICS','SCIENTOMÉTRIE'),(434,'SCULPTURE','SCULPTURE'),(435,'SERIALISM','SÉRIALISME'),(436,'SHINTÔ','SHINTÔ'),(437,'SOCIAL','SOCIAL'),(438,'SOCIETY','SOCIÉTÉ'),(439,'SOCIOLOGY','SOCIOLOGIE'),(440,'SOLIDARITY','SOLIDARITÉ'),(441,'SOUTH-EAST ASIA','ASIE DU SUD EST'),(442,'SPACE','ESPACE'),(443,'SPECTATOR','SPECTATEUR'),(444,'SPECULATION','SPÉCULATION'),(445,'STATISTICS','STATISTIQUES'),(446,'STEREOTYPE','STÉRÉOTYPE'),(447,'DEVELOPMENT STRATEGY','STRATÉGIE DE DÉVELOPPEMENT'),(448,'STRESS','STRESS'),(449,'STRINGS','CORDES'),(450,'SUPER HEROES','SUPERHÉROS'),(451,'SUSTAINABLE DEVELOPMENT','DÉVELOPPEMENT DURABLE'),(452,'SWING','BALANÇOIRE'),(453,'SWITZERLAND','SUISSE'),(454,'SYMPHONY','SYMPHONIE'),(455,'INFORMATION TECHNOLOGY','TECHNOLOGIE DE L\'INFORMATION'),(456,'TECHNOLOGY','TECHNOLOGIE'),(457,'TEMPERAMENT','TEMPÉRAMENT'),(458,'TEXT','TEXTE'),(459,'THEATER','THÉÂTRE'),(460,'MATURAL THEOLOGY','THÉOLOGIE MATURELLE'),(461,'MUSICAL THEORY','THÉORIE MUSICALE'),(462,'THEORY','THÉORIE'),(463,'TIME','TEMPS'),(464,'TONALITY','TONALITÉ'),(465,'TRADITION','TRADITION'),(466,'TRANSPORT','TRANSPORT'),(467,'TYPOGRAPHY','TYPOGRAPHIE'),(468,'UNCONSCIOUS','INCONSCIENT'),(469,'UNESCO','UNESCO'),(470,'UNITED STATES','ÉTATS UNIS'),(471,'URBAN','URBAIN'),(472,'URBAN PLANNING','AMÉNAGEMENT URBAIN'),(473,'URBANIZATION','URBANISATION'),(474,'URBANISM','URBANISME'),(475,'USSR','URSS'),(476,'APPRECIATION','VALORISATION'),(477,'VIDEO GAME','JEU VIDÉO'),(478,'VISIBILITY','VISIBILITÉ'),(479,'VISUAL','VISUEL'),(480,'VISUAL CULTURE','CULTURE VISUELLE'),(481,'VOCAL','VOCAL'),(482,'VOICE','VOIX'),(483,'WAR','GUERRE'),(484,'SOCIAL WEB','WEB SOCIAL'),(485,'WHISTLEBLOWERS','DÉNONCIATEURS'),(486,'WINDS','VENTS'),(487,'WORK','TRAVAIL'),(489,'ACHIEVEMENT MOTIVATION','MOTIVATION D\'ACCOMPLISSEMENT'),(490,'ALERT','ALERTE'),(491,'ARGUMENTATIVE STRATEGY','STRATÉGIE ARGUMENTATIVE'),(492,'BIOLOGY','BIOLOGIE'),(493,'BRANDS','MARQUES'),(494,'CHEATING','TRICHERIE'),(495,'CITY','VILLE'),(496,'COLLECTIVE CREATIVITY','CRÉATIVITÉ COLLECTIVE'),(497,'COMMON GOOD','BIEN COMMUN'),(499,'COMPUTER CODE','CODE INFORMATIQUE'),(500,'CONFLICT OF INTEREST','CONFLIT D\'INTÉRÊT'),(501,'COPERNIC','COPERNIC'),(502,'COPYRIGHT','DROIT D\'AUTEUR'),(503,'DARWIN','DARWIN'),(504,'DEGROWTH','DÉCROISSANCE'),(505,'DENUNCIATIONS','DÉNONCIATIONS'),(506,'DIALOG','DIALOGUE'),(507,'DIGITAL HISTORY','HISTOIRE NUMÉRIQUE'),(509,'DIGITAL TECHNOLOGIES','TECHNOLOGIES NUMÉRIQUES'),(510,'DISCOURSE','DISCOURS'),(511,'DISHONESTY','MALHONNÊTETÉ'),(512,'DIVERSITY','DIVERSITÉ'),(513,'DO','DO'),(514,'DOPING','DOPAGE'),(515,'DYNAMICS','DYNAMIQUE'),(516,'ENGINEERS','INGÉNIEURS'),(517,'ENTERPRISE','ENTREPRISE'),(518,'ETHICAL CONTROVERSY','CONTROVERSE ÉTHIQUE'),(519,'GALILEE','GALILÉE'),(520,'GAME STUDIES','ÉTUDE DU JEU VIDÉO'),(521,'GAMIFICATION','LUDIFICATION'),(522,'GOVERNANCE','GOUVERNANCE'),(523,'GROUND','TERRAIN'),(524,'HISTORY AND PHILOSOPHY OF SCIENCE','HISTOIRE ET PHILOSOPHIE DES SCIENCES'),(525,'HUMAN RIGHTS','DROITS HUMAINS'),(527,'INSTALLATION','INSTALLATION'),(528,'INTERDISCIPLINARITY COLLABORATION','COLLABORATION INTERDISCIPLINAIRE'),(529,'INTERNATIONAL DJIHADISM','DJIHADISME INTERNATIONAL'),(530,'INTERNATIONAL GOVERNANCE','GOUVERNEMENT INTERNATIONAL'),(531,'INVENTION PATENTS','BREVETS D\'INVENTION'),(532,'ITERAION','ITÉRATION'),(533,'KNOWLEDGE','CONNAISSANCE'),(534,'LAW','DROIT'),(535,'LIFESTYLE','MODES DE VIE'),(536,'MANAGERS','MANAGERS'),(537,'MIGRATION','MIGRATION'),(538,'MOBILISATIONS','MOBILITSATIONS'),(539,'MORAL JUSTIFICATIONS','JUSTIFICATIONS MORALES'),(540,'NEW FIRM CREATION','CRÉATION DE NOUVELLE ENTREPRISE'),(541,'NON-VERBAL','NON VERBAL'),(542,'NORTH AFRICA','AFRIQUE DU NORD'),(543,'OTHERNESS','ALTÉRITÉ'),(544,'PARTICIPATIVE DESIGN','DESIGN PARTICIPATIF'),(545,'PATTERN OF RESIDENCE','FORME D’HABITAT'),(546,'PERSIAN CULTURE','CULTURE PERSANE'),(547,'PHILOSOPHIE OF GAME','PHILOSOPHIE DU JEU'),(548,'PHILOSOPHY OF MATHEMATICS','PHILOSOPHIE DES MATHÉMATIQUES'),(549,'PHILOSOPHY OF PHYSICS','PHILOSOPHIE DE LA PHYSIQUE'),(550,'PLANETARY LIMITS','LIMITES PLANÉTAIRES'),(551,'POETRY','POÉSIE'),(552,'POLITICAL ISLAM','ISLAM POLITIQUE'),(553,'POLITICAL MOVEMENTS','MOUVEMENTS POLITIQUES'),(554,'POST-TRUTH','POST-VÉRITÉ'),(555,'PRECAUTION','PRÉCAUTION'),(556,'PROFESSIONAL GROUPS','GROUPES PROFESSIONNELS'),(557,'PSEUDOSCIENCE','PSEUDO-SCIENCE'),(558,'QUALITATIVE METHODS','MÉTHODES QUALITATIVES'),(559,'READING','LECTURE'),(560,'RECRUITMENT','RECRUTEMENT'),(561,'RISK MANAGEMENT','GESTION DES RISQUES'),(562,'RISK SOCIETY','SOCIÉTÉ DU RISQUE'),(563,'ROBOTS','ROBOTS'),(564,'SATISFACTION','SATISFACTION'),(565,'SCIENCE HISTORY','HISTOIRE DES SCIENCES'),(566,'SCIENCES POLITIC','POLITIQUE DES SCIENCES'),(567,'SELECTION','SÉLECTION'),(568,'SKIL','SKIL'),(569,'SOCIAL BEHAVIOURS','COMPORTEMENTS SOCIAUX'),(570,'SOCIAL-ANTHROPOLOGY','ANTHROPOLOGIE SOCIALE'),(571,'SPORT','SPORT'),(572,'SUSTAINABILITY','DURABILITÉ'),(573,'TECHNICS','TECHNIQUES'),(574,'TECHNOLOGY RESEARCH','RECHERCHE TECHNOLOGIQUE'),(575,'TRANSFORMATIONS','TRANSFORMATIONS'),(576,'TRANSHUMANISM','TRANSHUMANISME'),(577,'TRUTH','VÉRITÉ'),(578,'VALUES','VALEURS'),(579,'WEALTH OF NATIONS','RICHESSE DES NATIONS'),(580,'WEB EDITING','RÉDACTION WEB'),(581,'WORKSHOP','ATELIER'),(582,'ZEN','ZEN'),(583,'POLITICAL INSTITUTION','INSTITUTION POLITIQUE'),(584,'RELATIONS','RELATIONS'),(585,'GOOD','BIEN'),(586,'TECHNOLOGIES','TECHNOLOGIES');";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when inserting Keywords Data (function initKeywordsPolyperspectivesSemestersData): ' . $wpdb->print_error() ."\n" . $sql;
	}
	
	//POLYPERSPECTIVES
	$sql = "INSERT INTO `epfl_courses_se_polyperspective` VALUES (1,'Interdisciplinaire','Interdisciplinary'),(2,'Globale','Global'),(3,'Citoyenne','Citizen'),(4,'Créative','Creative');";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when inserting Polyperspectives Data (function initKeywordsPolyperspectivesSemestersData): ' . $wpdb->print_error() ."\n" . $sql;
	}
	
	//SEMESTERS
	$sql = "INSERT INTO `epfl_courses_se_semester` VALUES ('BA2','Bachelor 2'),('BA3','Bachelor 3'),('BA4','Bachelor 4'),('BA5','Bachelor 5'),('BA6','Bachelor 6'),('MA1','Master 1'),('MA2','Master 2');";
	
	if ( $wpdb->query( $sql ) === false )
	{
		return 'There was a database error when inserting Semesters Data (function initKeywordsPolyperspectivesSemestersData): ' . $wpdb->print_error() ."\n" . $sql;
	}
	
	return true;
}

function getAllCourses(){
	
	global $wpdb;
	
	$sql = "SELECT * FROM epfl_courses_se_course";
	return $wpdb->get_results($sql);
	
	wp_die(); 
    
}

?>