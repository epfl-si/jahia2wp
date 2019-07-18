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
	
	/************************************
	* CREATE CSV FILE WITH COURSES DATA *
	*************************************/
	
	//Init csv file with data
	$file = fopen($csv_path."courses_data.csv",'w') or die('Could not create csv file --> '.$csv_path."courses_data.csv");
	
	//Create log file
	$log = fopen($csv_path."update_data.log",'w') or die ('Could not create csv file --> '.$csv_path."update_data.log");
	
	
	//Get Course plan by year and section
	$courses = getPlansBamaYearSectionArray($year, $section);
	
	$csv_line = array('CODE','SEMESTER','TITLE','LANGUAGE','TITLE_FR','TITLE_EN','RESUME_FR','RESUME_EN','PROFESSORS','CREDITS','LECTURE_PERIOD','EXERCICE_PERIOD','PROJECT_PERIOD','TP_PERIOD','LABO_PERIOD','EXAM_INFO','KEYWORDS','POLYPERSPECTIVES');
	fputcsv($file,$csv_line);
	
	//Add courses to csv
	fwrite($log,"******** GETTING COURSES FROM ISA -> CSV *********\n\n");
	fwrite($log,"Year : ".$year."\n");
	fwrite($log,"Section : ".$section."\n");
	fwrite($log,"csv_file : ".$csv_path."courses_data.csv\n\n");
	
	foreach($courses as $course){
	
		$csv_line = array();
		
		fwrite($log,$course[1]." - ".$course[2]."\n");
				
		array_push($csv_line,$course[1],$course[0],$course[2]);
		
		//Get course book
		$course_book = getBooksYearCourseArray($year, $course[1]);
		
		if(!empty($course_book)){
		
			//course language
			array_push($csv_line,$course_book['lang']);
			
			$course['titles'] = $course_book['titles'];
			$course['professors'] = $course_book['professors'];
			$course['resumes'] = $course_book['resumes'];
			
			$i=0;
			foreach($course['titles'] as $lang => $title){
				$i++;
				array_push($csv_line,$title);
			}
			if($i<2){
				while($i<2){
					array_push($csv_line,'');
					$i++;
				}
			}
			
			$i=0;
			foreach($course['resumes'] as $lang => $resume){
				$i++;
				#echo "Resume ".$lang." : ".$resume."\n";
				array_push($csv_line,$resume);
			}
			if($i<2){
				while($i<2){
					array_push($csv_line,'');
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
			array_push($csv_line,$teacher4csv);
			
			//credits
			array_push($csv_line,$course[3]);
			
			//class periods and exam info
			array_push($csv_line,$course[4]);
			array_push($csv_line,$course[5]);
			array_push($csv_line,$course[6]);
			array_push($csv_line,$course[7]);
			array_push($csv_line,$course[8]);
			array_push($csv_line,$course[9]);
			
			//Get course keywords from database if exist
			$keywordsDB = getCourseKeywords($course[1]);
			$keywords = array();
			$keyword4csv = "";
			fwrite($log,"KEYWORDS FOR ".$course[1]." - ".$course[2]." :");
			foreach($keywordsDB as $keyword){
				$keyword4csv .= $keyword->keyword."|";
				array_push($keywords,$keyword->keyword);
				fwrite($log,$keyword->keyword.",");
			}
			fwrite($log,"\n");
			//Remove last sep char |
			if(!empty($keyword4csv)){
				$keyword4csv = substr($keyword4csv, 0, -1);
			}	
			$course['keywords'] = $keywords;
			array_push($csv_line,$keyword4csv);
			
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
			array_push($csv_line,$polyperspective4csv);
			
			//Add csv line
			fputcsv($file,$csv_line);	
		}else{
			fwrite($log,"COURSE BOOK IS MISSING : ".$course[1]." - ".$course[2]."\n");
		}
				
	}
		
	//close csv file
	fclose($file);
	
	$result = parseCSV($csv_path."courses_data.csv",$log);
	
	if($result=='ok'){
		fwrite($log, "\n\n******** END IMPORT FROM ISA --> SUCCESSFULL !! *********\n\n");
	}
	fclose($log);
		
	return $result;
	
}

function updateCoursesFromCSV($csv_path){
	
	//Create log file
	$log = fopen($csv_path."update_data.log",'w') or die ('Could not create csv file --> '.$csv_path."update_data.log");
	
	return parseCSV($csv_path."courses_data.csv",$log);
}	

function parseCSV($csv_path,$log){

	fwrite($log, "\n\n*** PARSING CSV AND INSERTING DATA TO DB ***\n\n");

	/****************************************
	* CLEAR DB AND ADD NEW COURSES FROM CSV *
	*****************************************/
	
	//Clear data before import
	$result = clearCoursesData();
	
 	if($result!='ok'){
		fwrite($log,$result);
		fclose($log);
		return $result;
 	}
 	
 	
  	//Parse csv file and insert courses data
  	$row = 1;
 	if (($file = fopen($csv_path, "r")) !== FALSE) {
 		fwrite($log,"... file ok ! \n\n");
 		while (($course = fgetcsv($file, 1000, ",")) !== FALSE) {
 			if($row>1){
				$num = count($course);
				fwrite($log, "inserting course data, csv row $row :" .$course[1]." - ".$course[2]."\n");

				$result = addCourseData($course);
				
				if($result!='ok'){
				    fwrite($log,$result);
				    fclose($log);
					return $result;
				}
			}
			$row++;
		}
	}
	
	fwrite($log, "\n\n*** END PARSING CSV --> SUCCESSFULL !! ***\n\n");
	
	fclose($file);
	
	return 'ok';
}

function initKeywordsPolyperspectivesSemesters(){

	clearCoursesData();
	initKeywordsPolyperspectivesSemestersData();

}

?>