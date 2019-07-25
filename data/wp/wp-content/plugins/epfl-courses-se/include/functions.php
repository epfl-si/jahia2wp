<?php

require_once('xml_functions.php');
require_once('db_functions.php');

function updateCoursesFromISAByYearSection($year, $section,$csv_path){
	
	/* CREATE CSV FILE WITH COURSES DATA */
	
	$courses_array=array();
	
	//Get Course plan by year and section
	$courses = getPlansBamaYearSectionArray($year, $section);
	
	foreach($courses as $course){
	
		$course_array = array();
				
		array_push($course_array,$course[1],$course[0],$course[2]);
		
		//Get course book
		$course_book = getBooksYearCourseArray($year, $course[1]);
		
		if(!empty($course_book)){
		
			//course language
			array_push($course_array,$course_book['lang']);
			
			$course['titles'] = $course_book['titles'];
			$course['professors'] = $course_book['professors'];
			$course['resumes'] = $course_book['resumes'];
			
			$i=0;
			foreach($course['titles'] as $lang => $title){
				$i++;
				array_push($course_array,$title);
			}
			if($i<2){
				while($i<2){
					array_push($course_array,'');
					$i++;
				}
			}
			
			$i=0;
			foreach($course['resumes'] as $lang => $resume){
				$i++;
				#echo "Resume ".$lang." : ".$resume."\n";
				array_push($course_array,$resume);
			}
			if($i<2){
				while($i<2){
					array_push($course_array,'');
					$i++;
				}
			}
			
			$teacher4csv = "";
			#echo "Teachers : ";
			foreach($course['professors'] as $teacher){
				#echo $teacher['sciper'].",";
				$teacher4csv .= $teacher['sciper'].":".$teacher['first-name'].":".$teacher['last-name']."|";
			}	
			#Remove last sep char |
			$teacher4csv = substr($teacher4csv, 0, -1);
			array_push($course_array,$teacher4csv);
			
			//credits
			array_push($course_array,$course[3]);
			
			//class periods and exam info
			array_push($course_array,$course[4]);
			array_push($course_array,$course[5]);
			array_push($course_array,$course[6]);
			array_push($course_array,$course[7]);
			array_push($course_array,$course[8]);
			array_push($course_array,$course[9]);
			
			//Get course keywords from database if exist
			$keywordsDB = getCourseKeywords($course[1]);
 			$keywords = "";
			foreach($keywordsDB as $keyword){
				$keywords .= $keyword->keyword_en."|";
			}
			//Remove last sep char |
			if(!empty($keyword4csv)){
				$keywords = substr($keywords, 0, -1);
			}	
			array_push($course_array,$keywords);
			
			//Get course polyperspectives from database if exist
			$polyperspectivesDB = getCoursePolyperspectives($course[1]);
			$polyperspectives = "";
			foreach($polyperspectivesDB as $polyperspective){
				$polyperspectives .= $polyperspective->name_en."|";
			}
			#Remove last sep char |
			if(!empty($polyperspective4csv)){
				$polyperspectives = substr($polyperspectives, 0, -1);
			}		
			array_push($course_array,$polyperspectives);
			
			//Add course to array
			array_push($courses_array,$course_array);
		}else{
			echo 'COURSE BOOK IS MISSING : '.$course[1].' - '.$course[2];
		}
				
	}
		
	$result = parseCoursesArray($courses_array);
		
	return $result;
	
}

function parseCoursesArray($courses_array){

	/* CLEAR DB AND ADD NEW COURSES FROM CSV */
	
	//Clear data before import
	$result = clearCoursesData();
	
 	if($result!==true){
		return $result;
 	}
 	
  	//Parse csv file and insert courses data
  	$rowCount = 0;
  	foreach($courses_array as $course){
		$rowCount++;
		if($rowCount>1){
			$result = addCourseData($course);
			
			if($result!==true){
				return $result;
			}
		}
  	}
	return true;
}

function getCoursesDataCSVArray(){

	$courses = getAllCourses();
	
	$csv_arr = array();
	$csv_header=array('CODE','SEMESTER','TITLE','LANGUAGE','TITLE_FR','TITLE_EN','RESUME_FR','RESUME_EN','PROFESSORS','CREDITS','LECTURE_PERIOD','EXERCICE_PERIOD','PROJECT_PERIOD','TP_PERIOD','LABO_PERIOD','EXAM_INFO','KEYWORDS','POLYPERSPECTIVES');
	
	array_push($csv_arr,$csv_header);
		
	foreach($courses as $course){
	
		$csv_line = array();
		
		array_push($csv_line,$course->code);
		array_push($csv_line,$course->semester_code);
		array_push($csv_line,$course->title);
		array_push($csv_line,$course->language);
		array_push($csv_line,$course->title_fr);
		array_push($csv_line,$course->title_en);
		array_push($csv_line,$course->resume_fr);
		array_push($csv_line,$course->resume_en);
		
		//get teachers 
		$teachersString='';
		$teachers = getCourseTeachers($course->code);
		foreach($teachers as $teacher){
			$teachersString=$teacher->sciper.":".$teacher->firstname.":".$teacher->lastname."|";
		}
		#Remove last sep char |
		if(!empty($teachersString)){
			$teachersString = substr($teachersString, 0, -1);
		}
		array_push($csv_line,$teachersString);
		
		array_push($csv_line,$course->credits);
		array_push($csv_line,$course->lecture_period);
		array_push($csv_line,$course->exercice_period);
		array_push($csv_line,$course->project_period);
		array_push($csv_line,$course->tp_period);
		array_push($csv_line,$course->labo_period);
		array_push($csv_line,$course->exam_info);
		
		//get keywords
		$keywordsString='';
		$keywords = getCourseKeywords($course->code);
		foreach($keywords as $keyword){
			$keywordsString=$keyword->keyword_en."|";
		}
		#Remove last sep char |
		if(!empty($keywordsString)){
			$keywordsString = substr($keywordsString, 0, -1);
		}
		array_push($csv_line,$keywordsString);
		
		//get polyperspectives
		$polyperspectivesString='';
		$polyperspectives = getCoursePolyperspectives($course->code);
		foreach($polyperspectives as $polyperspective){
			$polyperspectivesString=$polyperspective->name_en."|";
		}
		#Remove last sep char |
		if(!empty($polyperspectivesString)){
			$polyperspectivesString = substr($polyperspectivesString, 0, -1);
		}
		array_push($csv_line,$polyperspectivesString);
		
		array_push($csv_arr,$csv_line);
	}
	
	return $csv_arr;
}

function initKeywordsPolyperspectivesSemesters(){

	$result = clearCoursesData();
	if($result!=true){
		return $result;
	}	
	
	$result = initKeywordsPolyperspectivesSemestersData();
	
	return $result;

}

?>