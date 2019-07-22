<?PHP
class EPFLCOURSESSEDB
{
    /*
     * Returns DB charset
     */
    protected static function epfl_courses_se_get_charset()
    {
        return "DEFAULT CHARACTER SET utf8 COLLATE=utf8_bin";
    }
    
    /*
     * To create DB tables at plugin activation
     */
    public static function init()
    {
        global $wpdb;
        $charset_collate = EPFLCOURSESSEDB::epfl_courses_se_get_charset();
        
        $sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_keyword` (".
				"`id` INTEGER NOT NULL AUTO_INCREMENT UNIQUE, ".
				"`keyword_en` varchar(45) DEFAULT NULL, ".
				"`keyword_fr` varchar(45) DEFAULT NULL, ".
				"PRIMARY KEY (`id`)".
				") ENGINE=InnoDB ".$charset_collate." AUTO_INCREMENT=1;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}

		$sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_polyperspective` (".
				"`id` INTEGER NOT NULL AUTO_INCREMENT UNIQUE, ".
				"`name_fr` varchar ( 30 ) DEFAULT NULL, ".
				"`name_en` varchar ( 30 ) DEFAULT NULL ".
				") ENGINE=InnoDB ".$charset_collate." AUTO_INCREMENT=1;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}

		$sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_semester` (".
				"`code` varchar(5) NOT NULL, ".
				"`name` varchar(45) DEFAULT NULL, ".
				"PRIMARY KEY (`code`) ".
				") ENGINE=InnoDB ".$charset_collate.";";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}

		$sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_teacher` (".
				"`sciper` integer NOT NULL, ".
				"`firstname` varchar(45) DEFAULT NULL, ".
				"`lastname` varchar(45) DEFAULT NULL, ".
				"PRIMARY KEY (`sciper`) ".
				") ENGINE=InnoDB ".$charset_collate.";";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}		
		
        $sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_course` (".
				"`code` varchar ( 10 ) NOT NULL UNIQUE, ".
				"`title` varchar ( 255 ) NOT NULL UNIQUE, ".
				"`title_fr` varchar ( 255 ) DEFAULT NULL, ".
				"`title_en` varchar ( 255 ) DEFAULT NULL, ".
				"`language` varchar ( 10 ) DEFAULT NULL, ".
				"`resume_fr` varchar ( 1000 ) DEFAULT NULL, ".
				"`resume_en` varchar ( 1000 ) DEFAULT NULL, ".
				"`semester_code` varchar ( 5 ) DEFAULT NULL, ".
				"`lecture_period` INTEGER DEFAULT NULL, ".
				"`exercice_period` INTEGER DEFAULT NULL, ".
				"`project_period` INTEGER DEFAULT NULL, ".
				"`tp_period` INTEGER DEFAULT NULL, ".
				"`labo_period` INTEGER DEFAULT NULL, ".
				"`exam_info` varchar ( 255 ) DEFAULT NULL, ".
				"`credits` INTEGER DEFAULT NULL, ".
				"PRIMARY KEY(`code`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_semester` FOREIGN KEY(`semester_code`) REFERENCES `epfl_courses_se_semester`(`code`) ON DELETE NO ACTION ON UPDATE NO ACTION ".
				") ENGINE=InnoDB ".$charset_collate.";";
        
        if ( $wpdb->query( $sql ) === false )
        {
            return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
        }

		$sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_course_keyword` (".
				"`course_code` varchar ( 10 ) NOT NULL, ".
				"`keyword_id` integer NOT NULL, ".
				"PRIMARY KEY(`course_code`,`keyword_id`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_keyword_course_code` FOREIGN KEY(`course_code`) REFERENCES `epfl_courses_se_course`(`code`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_keyword_keyword` FOREIGN KEY(`keyword_id`) REFERENCES `epfl_courses_se_keyword`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION ".
				") ENGINE=InnoDB ".$charset_collate.";";
        
        if ( $wpdb->query( $sql ) === false )
        {
            return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
        }

		$sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_course_polyperspective` (".
				"`course_code` varchar ( 10 ) NOT NULL, ".
				"`polyperspective_id` INTEGER NOT NULL, ".
				"PRIMARY KEY(`course_code`,`polyperspective_id`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_polyperspective_course_code` FOREIGN KEY(`course_code`) REFERENCES `epfl_courses_se_course`(`code`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_polyperspective_polyperspective_id` FOREIGN KEY(`polyperspective_id`) REFERENCES `epfl_courses_se_polyperspective`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION ".
				") ENGINE=InnoDB ".$charset_collate.";";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}

		$sql = "CREATE TABLE IF NOT EXISTS `epfl_courses_se_course_teacher` (".
				"`course_code` varchar ( 10 ) NOT NULL, ".
				"`teacher_sciper` integer NOT NULL, ".
				"PRIMARY KEY(`course_code`,`teacher_sciper`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_teacher_course_code` FOREIGN KEY(`course_code`) REFERENCES `epfl_courses_se_course`(`code`), ".
				"CONSTRAINT `fk_epfl-courses_se_course_teacher_teacher_sciper` FOREIGN KEY(`teacher_sciper`) REFERENCES `epfl_courses_se_teacher`(`sciper`) ON DELETE NO ACTION ON UPDATE NO ACTION ".
				") ENGINE=InnoDB ".$charset_collate.";";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		return true;

		
    }
    
    /*
     * To drop DB tables at plugin uninstall
     */
    public static function dropAll()
    {
        global $wpdb;
                
        $sql = "DROP TABLE IF EXISTS `epfl_courses_se_course_polyperspective`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_course_keyword`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_course_teacher`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_course`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_teacher`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}

		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_semester`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_polyperspective`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
		
		$sql = "DROP TABLE IF EXISTS `epfl_courses_se_keyword`;";
				
		if ( $wpdb->query( $sql ) === false )
		{
			return 'There was a database error installing EPFL-COURSES_SE: ' . $wpdb->print_error();
		}
	
    }
}