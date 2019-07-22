<?php

//require_once 'HTTP/Request2.php';

Class IsaRequest {

    protected $baseUrl = null;
    protected $headers = null;
    protected $Http = null;
    protected $options = null;
    protected $config = null;
    protected $type = null;
    protected $args = null;

    public function __construct($type = 'XML') {
        $this->baseUrl = 'https://isatest.epfl.ch/services/';
        $this->type = $type;

        $this->args = array(
                //'method' => 'GET',
                //'ssl_verify_peer' => false,
				//'ssl_verify_host' => false,
				//'connect_timeout' => 5, // timeout on connect 
				//'timeout' => 30, // timeout on response 
				//'follow_redirects' => true,
				//'max_redirects' => 2, // stop after 2 redirects
                //'redirection' => 5,
                //'httpversion' => '1.1',
                //'content-type' => 'application/x-www-form-urlencoded',
                //'body' => array(),
                'headers' => array('Accept' => 'application/xml', 'Accept-Charset' => 'UTF-8'),
                //'blocking' => true,
                //'cookies' => array(),
                //'connection' => 'close',
                );
        
        // For http_request2
        
        //$this->prepareHeaders();
        
        
    }

    protected function prepareHeaders() {
        switch ($this->type) {
            case 'XML':
                $this->headers = Array('Accept' => 'application/xml', 'Accept-Charset' => 'UTF-8');
                break;
            case 'JSON':
            default:
                $this->headers = Array('Accept' => 'application/json', 'Accept-Charset' => 'UTF-8');
                break;
        }
    }

    public function executeRequest($url) {

        $data = null;
        $url = $this->baseUrl . $url;

        $request = wp_remote_get($url,$this->args);//new HTTP_Request2($url);
        
        if( is_wp_error( $request ) ) {
			echo "error ISARequest !";
			return false; // Bail early
		}
        //$r->setMethod(HTTP_Request2::METHOD_GET);
        //$r->setHeader($this->headers);
        //$r->setConfig($this->config);
        //$t = $r->send();
        switch ($this->type) {
            case 'XML':
                $data = wp_remote_retrieve_body( $request );//t->getBody();
                //echo $data;
                break;
            case 'JSON':
            default:
                $data = json_decode($t->getBody(), true);
                break;
        }
        if (empty($data)){
            return null;
		}
        else
            return $data;
    }

    public function findCourseInfo($year, $code) {
        $data = null;
        $code = $this->parseCourseCode($code);
        $year = $this->parseYear($year);
        if (!is_null($code) and ! is_null($year)) {
            //$url = "inscriptions/$year/course/$code";  // => deprecated
            # course/MATH-101(en)/2013-2014
            $url = "course/$code/$year";
            #$url = "instructors/section/SV";
            $data = $this->executeRequest($url);
            #echo '<pre>';
            #print_r($data);
            #echo '</pre>';
            #exit;
        }
        # Prepare default response
        $course = array(
            'found' => false,
            'code' => $code,
            'year' => $year
        );

        if (is_null($data) or array_key_exists('error', $data)) {
            $course['prop_code'] = $this->proposeCourseCode($code);
            $course['prop_year'] = $this->proposeYear($year);
            if (!is_null($data) and array_key_exists('error', $data)) {
                $course['error'] = array_key_exists('error', $data);
                if ($course['error'])
                    $course['error_text'] = $data['error'];
            }
            return $course;
        }

        $course['found'] = true;
        $course['code'] = $data[0]['course']['courseCode'];
        $course['year'] = $year;

        if (array_key_exists('fr', $data[0]['course']['subject']['name'])) {
            $course['subject'] = $data[0]['course']['subject']['name']['fr'];
        } elseif (array_key_exists('en', $data[0]['course']['subject']['name'])) {
            $course['subject'] = $data[0]['course']['subject']['name']['en'];
            // Description in english and not in french... let's assume the course is given in english only.
            $course['lang'] = 'en';
        }

        if (empty($course['lang'])) {
            if (preg_match('/anglais/i', $course['subject']) or preg_match('/english/i', $course['subject'])) {
                $course['lang'] = 'en';
            } elseif (preg_match('/allemand/i', $course['subject']) or preg_match('/german/i', $course['subject'])) {
                $course['lang'] = 'de';
            }
            if (empty($course['lang']))
                $course['lang'] = 'fr';
        }

        if (empty($course['subject'])) {
            $course['subject'] = 'Pas de nom de cours...';
        }
        $course['sections'] = array();
        foreach ($data[0]['course']['gps'] as $gps) {
            if (array_key_exists('fr', $gps['cursus']['name'])) {
                $course['sections'][] = $gps['cursus']['name']['fr'];
            }
            if (array_key_exists('en', $gps['cursus']['name'])) {
                $course['sections'][] = $gps['cursus']['name']['en'];
            }
        }
        if (empty($course['sections']))
            $course['sections'][] = 'n/a';
        $course['professors'] = $data[0]['course']['professors'];
        #$course['noStudents'] = $data[0]['noEffectives'];
        $course['noStudents'] = 0;
        foreach ($data[0]['planInscriptions'] as $student) {
            if ((bool) $student['active'])
                $course['noStudents'] ++;
        }
        //$course['noStudents'] = count($data[0]['planInscriptions']);

        return $course;
    }

}

?>
