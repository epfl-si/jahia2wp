<?php

require_once('isa.php');

function getPlansBamaYearSectionArray($year, $section){
	
	$isaRequest = new IsaRequest('XML');

	# Build url
	$service_url = "/plans/bama/".$year."/section/".$section;
	
	#Fetch data
	$data = $isaRequest->executeRequest($service_url);
	
	return parseXMLPlansBamaYearSection($data);
}

function parseXMLPlansBamaYearSection($data) {

	$courses = array();
	
	$dom = new DOMDocument();
	$dom->loadXML($data);
		
	foreach ($dom->getElementsByTagName('study-plan') as $study_plan){
				
		$semester = $study_plan->getElementsByTagName('academic-session')->item(0)->getElementsByTagName('semester')->item(0)->getAttribute('code');
		
		foreach ($study_plan->getElementsByTagName('plan')->item(0)->getElementsByTagName('course') as $course){
			
			$course_code = $course->getAttribute('code');
			$course_title = $course->getElementsByTagName('name')->item(0)->nodeValue;
			$course_credit = $course->getElementsByTagName('credit')->item(0)->nodeValue;
			if (empty($course_credit)){
				$course_credit = $course->getElementsByTagName('coefficient')->item(0)->nodeValue;
			}
			
			if (!empty($course_code)){
						
				$course_lecture = '';
				$course_exercice = '';
				$course_project = '';
				$course_tp = '';
				$course_labo = '';
				$course_exam = '';

				# parse classes (lecture, exercice, project and exam)
				foreach($course->getElementsByTagName('classes')->item(0)->getElementsByTagName('class') as $class){
				
					$study_type = $class->getElementsByTagName('studyType')->item(0)->getElementsByTagName('code')->item(0)->nodeValue;
					$nb_period = $class->getElementsByTagName('noPeriods')->item(0)->nodeValue;
					
					switch ($study_type){
						case 'LIP_COURS':
							$course_lecture = $nb_period;
							break;
						case 'LIP_EXERCICE':
							$course_exercice = $nb_period;
							break;
						case 'LIP_CC':
							$course_exam = 'semester';
							break;
						case 'LIP_PROJET':
							$course_project = $nb_period;
							break;
						case 'LIP_ECRIT':
							$course_exam = 'written';
							break;
						case 'LIP_ORAL':
							$course_exam = 'oral';
							break;
						case 'LIP_LABO':
							$course_labo = $nb_period;
							break;
						case 'LIP_TP':
							$course_tp = $nb_period;
							break;
					}
			
				}
				
				array_push($courses, array($semester, $course_code, $course_title,$course_credit,$course_lecture,$course_exercice,$course_project,$course_tp,$course_labo,$course_exam));
			}
		}
	}

    return $courses;
	
}

function getBooksYearCourseArray($year, $code) {

	$isaRequest = new IsaRequest('XML');

    # Build URL 
    $service_url = "/books/".$year."/course/".$code;

    # Fetch data
    $data = $isaRequest->executeRequest($service_url);

    return parseXMLCourseBook($data);
}

function parseXMLCourseBook($data) {
	
	$course = array();

	if(!empty($data)){
		$dom = new DOMDocument();
		$dom->loadXML($data);

		# Course code
		if(!empty($dom->getElementsByTagName('code')->item(0))){
			$course['code'] = $dom->getElementsByTagName('code')->item(0)->nodeValue;

			# course title
			$course['titles'] = array();
			$title = $dom->getElementsByTagName('title')->item(0);
			if(!empty($title)){
				foreach (array('fr', 'en') as $lang) {
					if ($title->getElementsByTagName($lang)->length > 0)
						$course['titles'][$lang] = $title->getElementsByTagName($lang)->item(0)->nodeValue;
				}
			
			
				# course language    
				$course['lang'] = $dom->getElementsByTagName('lang')->item(0)->getElementsByTagName('fr')->item(0)->nodeValue;
				
				if($course['lang']=='français / anglais'){
					$course['lang'] = 'FR-EN';
				}else if($course['lang']=='anglais'){
					$course['lang'] = 'EN';
				}else{
					$course['lang'] = 'FR';
				}
				
				# course resumes
				$course['resumes'] = array();
				$paragraphs = $dom->getElementsByTagName('paragraphs')->item(0);
				$resume_fr = "";
				$resume_en = "";
				foreach ($paragraphs->getElementsByTagName('paragraph') as $paragraph) {
					if ($paragraph->getElementsByTagName('type')->item(0)->getAttribute('code') == 'RUBRIQUE_RESUME') {
						$lang = $paragraph->getElementsByTagName('lang')->item(0)->nodeValue;
						if($lang == 'fr'){
							$content_fr = $paragraph->getElementsByTagName('content')->item(0)->nodeValue;
						}else{
							$content_en = $paragraph->getElementsByTagName('content')->item(0)->nodeValue;
						}
							
						
						
					}
				}
				
				$course['resumes']['fr'] = $content_fr;
				$course['resumes']['en'] = $content_en;
				
				# course professors
				$course['professors'] = array();
				$professors = $dom->getElementsByTagName('professors')->item(0);
				foreach ($professors->getElementsByTagName('professor') as $professor) {
					$professor_data = array();
					foreach (array('sciper', 'first-name', 'last-name') as $professor_field) {
						if ($professor->getElementsByTagName($professor_field)->length)
							$professor_data[$professor_field] = $professor->getElementsByTagName($professor_field)->item(0)->nodeValue;
					}
					$course['professors'][] = $professor_data;
				}
			
			}else{
				$course['lang'] = '';
			}
		}
	}
    
    return $course;
}

?>