<?php

require_once('xml_functions.php');
require_once('db_functions.php');

/*
$csv_file = "../csv/courses_data.csv";

//Update courses from ISA and create csv data file
updateCoursesByYearSection('2018-2019','SHS',$csv_file);
#print_r(getTeachers());
*/

function updateCoursesFromISAByYearSection($year, $section,$csv_path){
	
	/* CREATE CSV FILE WITH COURSES DATA */
	
	$courses_array=array();
	
	//Init csv file with data
// 	$file = fopen($csv_path."courses_data.csv",'w') or die('Could not create csv file --> '.$csv_path."courses_data.csv");
	
	//Create log file
	//$log = fopen($csv_path."update_data.log",'w') or die ('Could not create log file --> '.$csv_path."update_data.log");
	
	
	//Get Course plan by year and section
	$courses = getPlansBamaYearSectionArray($year, $section);
	
// 	$csv_line = array('CODE','SEMESTER','TITLE','LANGUAGE','TITLE_FR','TITLE_EN','RESUME_FR','RESUME_EN','PROFESSORS','CREDITS','LECTURE_PERIOD','EXERCICE_PERIOD','PROJECT_PERIOD','TP_PERIOD','LABO_PERIOD','EXAM_INFO','KEYWORDS','POLYPERSPECTIVES');
// 	fputcsv($file,$csv_line);
	
	//Add courses to csv
// 	fwrite($log,"******** GETTING COURSES FROM ISA -> CSV *********\n\n");
// 	fwrite($log,"Year : ".$year."\n");
// 	fwrite($log,"Section : ".$section."\n");
// 	fwrite($log,"csv_file : ".$csv_path."courses_data.csv\n\n");
	
	foreach($courses as $course){
	
		$course_array = array();
		
// 		fwrite($log,$course[1]." - ".$course[2]."\n");
				
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
			$keywords = array();
			$keyword4csv = "";
// 			fwrite($log,"KEYWORDS FOR ".$course[1]." - ".$course[2]." :");
			foreach($keywordsDB as $keyword){
				$keyword4csv .= $keyword->keyword."|";
				array_push($keywords,$keyword->keyword);
// 				fwrite($log,$keyword->keyword.",");
			}
// 			fwrite($log,"\n");
			//Remove last sep char |
			if(!empty($keyword4csv)){
				$keyword4csv = substr($keyword4csv, 0, -1);
			}	
			$course['keywords'] = $keywords;
			array_push($course_array,$keyword4csv);
			
			//Get course polyperspectives from database if exist
			$polyperspectivesDB = getCoursePolyperspectives($course[1]);
			$polyperspectives = array();
			$polyperspective4csv = "";
			foreach($polyperspectivesDB as $polyperspective){
				$polyperspective4csv .= $polyperspective['name']."|";
			}
			#Remove last sep char |
			if(!empty($polyperspective4csv)){
				$polyperspective4csv = substr($polyperspective4csv, 0, -1);
			}
			$course['polyperspectives'] = $polyperspectives;		
			array_push($course_array,$polyperspective4csv);
			
			//Add course to array
			array_push($courses_array,$course_array);
		}else{
			throw new Exception( 'COURSE BOOK IS MISSING : '.$course[1].' - '.$course[2] );
		}
				
	}
		
	$result = $courses_array($courses_array);
	
// 	if($result=='ok'){
// 		fwrite($log, "\n\n******** END IMPORT FROM ISA --> SUCCESSFULL !! *********\n\n");
// 	}
// 	fclose($log);
		
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

	clearCoursesData();
	initKeywordsPolyperspectivesSemestersData();

}

?>