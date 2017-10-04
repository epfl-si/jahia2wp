<?PHP
/*
	GOAL: 
	=====
	Allow to extract (by comparison) a WordPress plugin configuration so it can be used
	to configure the plugin after a "fresh" WordPress install.



	README:
	=======
	This script needs to be saved in '../master-wp/container-wp-volumes/plugins/' folder.
		
	Execution can be done with: 
	docker exec wpcli sh -c "php /var/www/html/<wp_folder>/wp-content/plugins/manage-plugin-config.php <wp_folder> <step_no> <config_name>"

	<wp_folder> is the name of the folder in which WP is deployed. You can found it by exectuing the following command:
	docker exec wpcli sh -c "ls -alh"
	
	<config_name> is the name of the configuration where to store information. This will be used to generate the filename
	where we will store the information about configuration. 
	
	
	USE IN DOCKER DEPLOYMENT:
	=========================
	Modify the file '../master-wp/container-wp-cli/bin/docker-entrypoint.sh", after calling "core_install.sh", to add lines like the following:
	php /var/www/html/<wp_folder>/wp-content/plugins/manage-plugin-config.php <wp_folder> 3 <config_name>
	
	For <wp_folder>, you can normally use $WP_PATH environment variable.
	And to execute correctly, the 'config_name' file for the plugin you want to configure has to exists.

	
	HOW TO USE THIS SCRIPT:
	=======================
	0. Deploy WordPress instance with docker
	1. Connect on WordPress admin panel
	2. Activate plugin 
	3. Go on all links on the left menu
	4. Go on all plugin configuration page
	5. Execute STEP 1 of this script
		$ docker exec wpcli sh -c "php /var/www/html/<wp_folder>/wp-content/plugins/manage-plugin-config.php <wp_folder> 1 <config_name>"
	6. Configure plugin AND stay on configuration page !
	7. Execute STEP 2 of this script to extract and save plugin configuration
		$ docker exec wpcli sh -c "php /var/www/html/<wp_folder>/wp-content/plugins/manage-plugin-config.php <wp_folder> 2 <config_name>"
	8. Reinstall WordPress from scratch
	9. Execute STEP 3 of this script to import plugin configuration. (have a look at 'Use in docker deployment')
		$ docker exec wpcli sh -c "php /var/www/html/<wp_folder>/wp-content/plugins/manage-plugin-config.php <wp_folder> 3 <config_name>"





	VERSION HISTORY : 
	=================
	0.1 (13.06.2017)
		- Working version (not flexible)
		- Minimum comments
		- "Dirty code"
		
	0.2 (13.06.2017)
		- More comments
		- Use input parameters
		- Simplify DB connection code	
		
	0.3 (13.06.2017)
		- Now use a class to do things
		- Add "unique" key information for option tables
		- Add new option table (wp_postmeta)
		- Add information about table relation (not real foreign keys but exists) to be able to map correctly 
		  the information we add between them (keep the mapping and avoid conflicts)
		- Handle foreign values already existing in DB (not present in the configuration save file)
		- Handle rows that are deleted and recreated with another ID (bigger) by the plugin. Duplicate entry 
		  error is handled and existing row id is recovered
	
   0.4 (13.06.2017)
		- Handle plugin that store "empty" configuration in DB before user do the manual configuration. 
		  If configuration already exists, it is updated and the mapping with the existing is kept.
		- Check if config files exists before opening them.
		  + Error will be thrown if 'base config' file doesn't exists
		  + Import plugin configuration will be ignored if config file doesn't exists. 
		  
	0.5 (14.06.2017)
		- Config files have been moved in a dedicated directory (defined by CONFIG_FOLDER). If the
		  directory doesn't exists, it is created.
		- Change created config files base names. The plugin name is now at the beginning of the file.
		- Information to access WP database are not hard-coded anymore. They are now directly retrieved
		  in WordPress configuration file (wp-config.php). The recovered information are :
		  DB_NAME
		  DB_HOST
		  DB_USER
		  DB_PASSWORD
		  DB_CHARSET (not handled before)
		  $table_prefix (not handled before)
		- WordPress tables name are now prefixed with the prefix defined in 'wp-config.php'
		- Some code cleaning
		- Single point to trigger errors
 
	0.6 (15.06.2017)
		- Reference/config file names removed. We now only use different extensions for reference and config
		  files. This will be easier to do multiple import
		- Add parameter to import config from all existing files (PARAM_IMPORT_ALL_CONFIG)  
		- Add possibility to import multiple plugin configuration by giving all plugin names for which
		  we want to import configuration.
		- Change classname so it fits bette what the script do

	0.7 (16.06.2017)
		- Add log file (can be activated or not using LOG_ENABLED). The log file have the same name as
		  the script but with ".log" at the end
		- Disable usage of charset (utf8) to access DB because there's problem with encoding.... special
		  characters aren't correctly reinserted when loading plugin config.	
		  
	0.8 (21.06.2017)
		- Add check to see if parameters for WP DB access are found or not.
		- RegEx to look for parameters in wp-config.php file have been modified to allow more "spaces"
		  at a place where it wasn't possible to have spaces...
		
	0.9 (21.06.2017)
		- Either WordPress 4.8 is different of WordPress 4.7.5 or either the plugins are written differently but
		  the way their configuration are stored in the DB is different than before. Now, the configuration is
		  written in the DB but with "empty" values... so the rows already exists in the DB and it wasn't possible
		  to identify "new rows" (containing configuration) anymore... So, now the script store all the content of
		  defined tables in step 1. Then, in step 2, it also list all content of defined tables and compare each
		  row (for each table) to the stored one. If the row has been modified or is new, it is stored in the
		  final config file. The mecanism to import the configuration from the final file hasn't changed because
		  it was already handling existing information in DB so it just modify the necessary things (and add the 
		  others).
	
	0.10 (30.06.2017)
		- Add check to see if WordPress config file exists and trigger error if not.	
		- Change some documentation about how to use this script.
		- Add new parameter to have WordPress install folder as first parameter. This is needed to correctly
		  read WordPress config file ("wp-config.php")
	
	TODO :
	- Add error check (find what) 

	-------------------------------------------------------------------------------------------
*/


	ini_set('display_errors', 1);
	error_reporting(E_ALL^ E_NOTICE);


	define('PARAM_IMPORT_ALL_CONFIG',	'-all');
	
	
	
	
	/*
		GOAL : Display how to use this script 
	*/
	function displayUsage()
	{
		global $argv;
		echo "USAGE : php ".$argv[0]." <wp_folder> <step_no> <plugin_name>\n";
		echo "\t<wp_folder> = Relative folder where WP is located (inside /var/www/html/)\n";
		echo "\tstep_no : 1|2|3\n";
		echo "\t\t1 = Get WP config before plugin configuration\n";
		echo "\t\t2 = Get WP diff config after plugin configuration\n";
		echo "\t\t3 = Load diff config in WP after fresh install\n";
		echo "\n";
	}
	


	/***************************************************************************/
	/**************************** MAIN PROGRAM *********************************/

	/* Check if all arguments are here */
	if(sizeof($argv)<4)
	{
		displayUsage();
		exit;
	}
	
	


	/* Path to WordPress config file */
	// BEFORE FIX BY EB : define('WP_CONFIG_FILE',	'/var/www/html/'.$argv[1].'/wp-config.php');
	define('WP_CONFIG_FILE',	$argv[1].'/wp-config.php');

	/* To store configuration files in another folder */
	define('CONFIG_FOLDER',	'_plugin-config');


	/* Folder where to store config files */
	define('PLUGIN_CONFIG_FOLDER_PATH',	dirname(__FILE__).DIRECTORY_SEPARATOR.CONFIG_FOLDER.DIRECTORY_SEPARATOR);
	
	/* File in which we will store the log */
	define('LOG_FILE', __FILE__.".log");
	define('LOG_ENABLED',	false);
	
	/* File extensions for plugin configuration (reference and final config) */
	define('PLUGIN_CONFIG_FILE_REF_EXT', '.ref');
	define('PLUGIN_CONFIG_FILE_FINAL_EXT', '.config');
	



	/****************************** CLASS **********************************/
	
	
	class PluginConfigManager
	{
		var $db_link;				/* Link to the DB */
		var $config_tables;		/* Configuration about table (auto-gen fields, unique fields) */
		var $tables_relations;	/* Information about "non-official" links/relations between tables */
		var $log_handle;			/* To handle log file */
		
		/*
			GOAL : Class contructor
		*/	  
		function PluginConfigManager()
		{
			
			/**** CONGIF FILES LOCATION ****/
			
			/* If directory to store config files doesn't exists, we create it */
			if(!file_exists(PLUGIN_CONFIG_FOLDER_PATH))
			{
				mkdir(PLUGIN_CONFIG_FOLDER_PATH, 0777);
			}

			
			/**** Get WordPress DATABASE configuration in the wp-config.php file. ****/
			/* To do this, we will :
				1. Read WordPress "wp-config.php" file
				2. Extract the code defining the DB access configuration
				3. Do an 'eval()' on the extracted code to have constants defined in this script */
				
			$define_to_find = array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET');
			
			/* Base RegEx to look for 'define' */		
			$base_wp_param_reg = '/define\([\s]*\'%s\'[\s]*,[\s]*\'[\S]+\'[\s]*\);/i';
	
			/* If config file cannot be found, */
			if(!file_exists(WP_CONFIG_FILE))
			{
				trigger_error(__FILE__.":".__FUNCTION__.": Wordpress config file '".WP_CONFIG_FILE."' not found" , E_USER_ERROR);			
			}
	
			/* Getting 'wp-config.php' file content */
			$config = file_get_contents(WP_CONFIG_FILE);
			
					
			/* Going through 'define' to recover */
			foreach($define_to_find as $define_name)
			{
				$matches = array();
				
				/* Generate RegEx for current 'define' */
				$define_reg = sprintf($base_wp_param_reg, $define_name);

				/* Searching information */
				preg_match($define_reg, $config, $matches);
				
				if(sizeof($matches)==0)
				{
					trigger_error(__FILE__.":".__FUNCTION__.": No value found for '".$define_name."'" , E_USER_ERROR);
				}
				
				/* Defining constant for current script */
				eval($matches[0]);
			}
			
			
			/* Searching for DB table prefix. The line looks like :
				$table_prefix = 'wp_';
			*/
			preg_match('/\$table_prefix[\s]*=[\s]*\'[\S]+\'[\s]*;/i', $config, $matches);
			
			if(sizeof($matches)==0)
			{
				trigger_error(__FILE__.":".__FUNCTION__.": No value found for 'table_prefix'" , E_USER_ERROR);
			}
			
			eval($matches[0]);
			
			
			/**** DB Connection ****/
			/* Open DB connection with previously recovered information */
			$this->db_link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

			if(mysqli_connect_errno() != 0) 
			{
				trigger_error(__FILE__.":".__FUNCTION__.": ".mysqli_connect_error()."\nHost: ".DB_HOST."\nUser: ".DB_USER."\nDB: ".DB_NAME, E_USER_ERROR);

			}
			
			/* Init database charset */
			/* This has been commented because of problems with special characters */
			//mysqli_set_charset($this->db_link, DB_CHARSET);
			
			
			
			
			
		  
			/**** WORDPRESS TABLES DESCRIPTION ****/
			
			/* Tables in which configuration is stored, with 'auto gen id' fields and 'unique field' (others than only auto-gen field). Those tables must be sorted to satisfy foreign keys*/
			$this->config_tables = array($table_prefix.'postmeta'					=> array('meta_id', null),
												  $table_prefix.'options'					=> array('option_id', 'option_name'),
												  $table_prefix.'terms'						=> array('term_id', null),
												  $table_prefix.'termmeta'					=> array('meta_id', null), // include wp_terms.term_id
												  $table_prefix.'term_taxonomy'			=> array('term_taxonomy_id', null), // include wp_terms.term_id
												  $table_prefix.'term_relationships'	=> array(null, array('object_id', 'term_taxonomy_id'))); // include wp_term_taxonomy.term_taxonomy_id
			
			/* Relation between configuration tables. There are no explicit relation between tables in DB but there are relation coded in WP. */									  
			$this->tables_relations = array($table_prefix.'termmeta'					=> array('term_id'			=> $table_prefix.'terms'),
													  $table_prefix.'term_taxonomy'			=> array('term_id'			=> $table_prefix.'terms'),
													  $table_prefix.'term_relationships'	=> array('term_taxonomy_id'=> $table_prefix.'term_taxonomy'));	
													  
													  
			/**** LOG FILE ****/		
			$this->log_handle = fopen(LOG_FILE, 'a');
			
		}
		
		/* ----------------------------------------------------------------------- */
		/*									PROTECTED FUNCTIONS									*/
		/* ----------------------------------------------------------------------- */
		
		
		/*
			GOAL : Return the filename to use to store reference information for a
					 plugin.
			
			$plugin_name	: Plugin name 
		*/
		protected function getReferenceConfigFilename($plugin_name)
		{
			return PLUGIN_CONFIG_FOLDER_PATH.$plugin_name.PLUGIN_CONFIG_FILE_REF_EXT;
		}
		
		/* ----------------------------------------------------------------------- */
		
		/*
			GOAL : Return the filename to use to store configuration for a plugin.
			
			$plugin_name	: Plugin name			 
		*/
		protected function getPluginConfigFilename($plugin_name)
		{
			return PLUGIN_CONFIG_FOLDER_PATH.$plugin_name.PLUGIN_CONFIG_FILE_FINAL_EXT;
		}
		
		
		/* ----------------------------------------------------------------------- */
		
		
		/*
			GOAL : Return information about foreign key information if exists
			
			$for_src_table	 : Source table for which we have to check for a foreign key
			$for_src_fiedl	 : Field in the source table for which we have to check for a foreign key
			
			RET : NULL -> no foreign key
					Target table name
		*/
		protected function getForeignKeyTable($for_src_table, $for_src_field)
		{

			/* If there is foreign key information for the table, */
			if(array_key_exists($for_src_table, $this->tables_relations))
			{
				/* If there is a foreign key defined for the field, */
				if(array_key_exists($for_src_field, $this->tables_relations[$for_src_table]))
				{
					/* We return the exploded string <table>.<field> as result */
					return $this->tables_relations[$for_src_table][$for_src_field];
				}
			
			}/* END IF there is foreign key information for table */
			
			/* If we reach this point, it means no information was found. */
			return null;
		}
		
		/* ----------------------------------------------------------------------- */
		
		/*
			GOAL : Add a line at the end of the log file.
			
			$function	-> Name of the function from which the log add is requested.
			$str			-> Line to add
		*/
		protected function addToLog($function, $str)
		{
			/* If logging is disabled */
			if(!LOG_ENABLED) return;
			
			
			fwrite($this->log_handle, "$function: $str\n");
			fflush($this->log_handle);
		}
		
		/* ----------------------------------------------------------------------- */
		
		
		/*
			GOAL : Triggers an error. This function exists to have a single point to 
					 trigger error with the right information displayed
					 
			$function		: name of the function where the error was triggered
			$error_string  : Error message
		*/
		protected function triggerError($function, $error_string)
		{
			/* Logging error */
			$this->addToLog($function, $error_string);
			/* Triggering error */
			trigger_error(__FILE__.":".$function.": ".$error_string."\n", E_USER_ERROR);
			
		}
		
		
		/* ----------------------------------------------------------------------- */
		/*									PUBLIC FUNCTIONS										*/
		/* ----------------------------------------------------------------------- */
		
		/*
			GOAL  : Go through all table that contains configuration information for 
					  plugins and store everything in a file.
					  
					  The information are saved in a file
					  
			$plugin_name	: Plugin name						
		*/
		function extractAndSaveConfigReference($plugin_name)
		{
		
			$base_config = array();
			
			/* Going throught tables */
			foreach($this->config_tables as $table_name => $fields_infos)
			{
				$request = "SELECT * FROM $table_name";  
				
				$this->addToLog(__FUNCTION__, $request);
				
				
				if(($res = mysqli_query($this->db_link, $request))===false)
				{
					$this->triggerError(__FUNCTION__, mysqli_error($this->db_link));
				}
				
				/* Create array to store table content */
				$base_config[$table_name] = array();
				
				/* Going through table content */
				while($row = mysqli_fetch_assoc($res))
				{
					$base_config[$table_name][] = $row;
				}
				

			}/* END LOOP Going through tables */		
			
			/* Generate output filename */
			$base_config_file = $this->getReferenceConfigFilename($plugin_name);

			$handle = fopen($base_config_file, 'w+');
			fwrite($handle, serialize($base_config));
			fclose($handle);
			

		
		}
		
		/* ----------------------------------------------------------------------- */
		
		/*
			GOAL : Use the base configuration saved before to determine which rows have been
					 added/modified in the DB during plugin configuration.
					 
					 The difference contains the plugin configuration. We save it in a file.
					 
			$plugin_name	: Plugin name					  
		*/
		function extractAndSavePluginConfig($plugin_name)
		{
			$config_diff = array();
			
			/* Generate filename and load base configuration  */
			$base_config_file = $this->getReferenceConfigFilename($plugin_name);
			
			/* If base config file doesn't exists, we skip */
			if(!file_exists($base_config_file))
			{
				$this->triggerError(__FUNCTION__,"Reference config doesn't exists for plugin '".$plugin_name);  
			}
			
			/* Getting base config information  */
			$base_config = unserialize(file_get_contents($base_config_file));			
			
			/* Going throught tables */
			foreach($this->config_tables as $table_name => $fields_infos)
			{
				/* Extract infos */
				list($auto_inc_field, $unique_fields) = $fields_infos;
				
				/* Get diff for table */
				$request = "SELECT * FROM $table_name";
				
				$this->addToLog(__FUNCTION__, $request);
				
				if(($res = mysqli_query($this->db_link, $request))===false)
				{
					$this->triggerError(__FUNCTION__, mysqli_error($this->db_link));
				}
				
				/* To store configuration */
				$config_diff[$table_name] = array();

				//$ref_fields=array();	
				/* If we have a 'unique field' for the current table */
				if($unique_fields !== null)
				{
					$ref_fields = (is_array($unique_fields))?$unique_fields:array($unique_fields);
				}
				/* If we have an autogen field, */
				else if($auto_inc_field!==null)
				{
				   $ref_fields = array($auto_inc_field);
				}

				
				/* Going through differential content */
				while($diff_row = mysqli_fetch_assoc($res))
				{
					
					/* Going through base rows (saved in step 1) */			
					foreach($base_config[$table_name] as $base_row)
					{
						$row_match=true;
					
						/* Going through id/primary/unique fields to see if row match */
						foreach($ref_fields as $ref_field)
						{
							/* If no match between same field in 'base' and 'diff' rows*/
							if($base_row[$ref_field] != $diff_row[$ref_field])
							{	
								$row_match=false;
								break;
							}
							
						}/* END LOOP Going through $match_found=false; fields */
						
						/* If we found the corresponding row, */
						if($row_match)
						{
							
							/* We can exit the loop to continue the process */
							break;
						}
						
						/*** If we arrive here, it means that the current 'base' row doesn't match the current 'diff' row. 
							We continue to search or... have reached the end of the loop and it means we have a new row ***/
					
					}/* END LOOP Going through base rows */
					
					
					/* If we found the corresponding row */
					if($row_match)
					{
						$this->addToLog(__FUNCTION__, "Diff row match base\nDiff=".var_export($diff_row, true));
						
						/* We now have to check if 'base' and 'diff' row are equal or different (for the values not used to identify the row, like id/unique/primary fields). */
						
						$identical_rows = true;
						
						/* Going through fields of row to compare them */
						foreach(array_keys($base_row) as $key)
						{
							/* If key isn't used to identify the row */
							if(!in_array($key, $ref_fields))
							{
								/* If the values are different, */
								if($base_row[$key] != $diff_row[$key])
								{
									$identical_rows = false;
									break;
								}
							} /* END IF the key is not used to identify the row */
							
						}/* END LOOP Going through row fields */
						
						/* If rows are different, */
						if(!$identical_rows)
						{
							$this->addToLog(__FUNCTION__, "Diff row is different than base row");
							/* We store the modified row */
							$config_diff[$table_name][] = $diff_row;
						}
					
					}
					else /* We didn't find a corresponding row. So it means the "diff" row is a new row in the DB */
					{
						/* We store the new row */
						$config_diff[$table_name][] = $diff_row;
					}
					
				}/*END LOOP Going through recovered content */

			}/* END LOOP Going through tables */	
			
			//print_r($config_diff);
			
			$diff_config_file = $this->getPluginConfigFilename($plugin_name);

			 
			/* Save configuration in file */
			$handle = fopen($diff_config_file, 'w+');
			fwrite($handle, serialize($config_diff));
			fclose($handle);
			 

		}


		/* ----------------------------------------------------------------------- */
		
		
		/*
			GOAL : Load the plugin configuration stored in the file and update WP database
			
			$plugin_name	: Plugin name			 
		*/		
		function importPluginConfig($plugin_name)
		{
			$diff_config_file = $this->getPluginConfigFilename($plugin_name);
			
			/* If config file doesn't exists, we skip */
			if(!file_exists($diff_config_file))
			{
				$info = "Config file doesn't exists for plugin '".$plugin_name."'. Skipping.";
				echo "$info\n";
				$this->addToLog(__FUNCTION__, $info);
				return;
			}
			
			$diff_config = unserialize(file_get_contents($diff_config_file));
			
			/* To store ID mapping between configuration stored in files and what is inserted in DB */
			$table_id_mapping = array();
			
			/* To tell if we execute this function in "simulation" mode (meaning that we create a transaction that we rollback right after)*/
			$simulation=false;
			
			/* Start transaction if we are in "simulation" mode */
			if($simulation)mysqli_autocommit($this->db_link, false);
			
			/* Going throught tables */
			foreach($this->config_tables as $table_name => $fields_infos)
			{ 
			
				/* Extract infos */
				list($auto_inc_field, $unique_fields) = $fields_infos;

				/* Array transform if needed */
				if(!is_array($unique_fields)) $unique_fields = array($unique_fields);

				/* Creating mapping array for current table */
				$table_id_mapping[$table_name] = array();



				/* Going through rows to add in table */
				foreach($diff_config[$table_name] as $row)
				{
					/* Values that will be used if we can do an insert */
					$insert_values = array();
					
					/* Values that will be used if row is already existing. This means we have to update it */
					$update_values = array();
					
					/* Goint through fields/values in the row */
					foreach($row as $field => $value)
					{

						/* If current field contains an "auto-generated id", */
						if($auto_inc_field==$field)
						{
							/* Empty value so it will be generated automatically */
							$current_value = '';
						}
						/* If we have information about foreign key, */
						else if(($target_table = $this->getForeignKeyTable($table_name, $field))!==null)
						{ 
							/* If we have a mapping for the current value, */
							if(array_key_exists($value, $table_id_mapping[$target_table]))
							{
								/* Getting mapped id for current value */
								$current_value = $table_id_mapping[$target_table][$value];
								
							}
							else /* We don't have any mapping */
							{
								/* We take the value as it is because it is probably referencing something already existing in the DB 
									(and not present in the saved configuration for the plugin) */
								$current_value = $value;
								
							}
						}
						else /* We can take the value present in the config file (with 'addslashes' to be sure) */
						{
							$current_value = addslashes($value);
						}
						
						/* We store the value to insert */
						$insert_values[] = $current_value;
						
						/* If the field is NOT an "auto-generated" and NOT a part of the primary key, */
						if($auto_inc_field!=$field && !in_array($field, $unique_fields))
						{
							/* We store what we need to update the row if it already exists */
							$update_values[] = $field."='".$current_value."'";
						}
						
						
					}/* END LOOPING through fields/values in the row*/
				
				
					/* Creating request to insert row or to update it if already exists */
					$request = "INSERT INTO $table_name VALUES('".implode("','", $insert_values)."') ".
								  " ON DUPLICATE KEY UPDATE ".implode(",", $update_values);
								  
					$this->addToLog(__FUNCTION__, $request);
					
					if(($res = mysqli_query($this->db_link, $request))===false)
					{
						$this->triggerError(__FUNCTION__, mysqli_error($this->db_link));
					}
					
					/* Getting ID of inserted value */
					$insert_id = mysqli_insert_id($this->db_link);
					
					
					
					/* If row wasn't inserted because already exists, (so it means we must have an 'auto-gen' field) */
					if($insert_id==0 && $auto_inc_field !== null)
					{
						/* To store search conditions to find the existing row ID */
						$search_conditions = array();
						
						/* Going through unique fields */
						foreach($unique_fields as $unique_field_name)
						{
							$search_conditions[] = $unique_field_name."='".$row[$unique_field_name]."'";
						}
						
						/* Creating request to search existing row information */					
						$request = "SELECT * FROM $table_name WHERE ".implode(" AND ", $search_conditions);

						$this->addToLog(__FUNCTION__, $request);

						if(($res = mysqli_query($this->db_link, $request))===false)
						{
							$this->triggerError(__FUNCTION__, mysqli_error($this->db_link));
						}
						
						$res = mysqli_fetch_assoc($res);
						/* Getting ID of existing row */
						$insert_id = $res[$auto_inc_field];
						
					}/* END IF row wasn't inserted */
					
					
					
					/* Save ID mapping from data present in file TO row inserted (or already existing) in DB */
					$table_id_mapping[$table_name][$row[$auto_inc_field]] = $insert_id;
					
				} /* END LOOP Going through table rows */
			
			}/* END LOOP Going through tables */
			
			
			/* If simulation, we rollback the transaction */
			if($simulation)mysqli_rollback($this->db_link);
			

		}
		
		
		/* ----------------------------------------------------------------------- */
		
		/*
			GOAL : Finalize the work
		*/
		function finalize()
		{
			mysqli_close($this->db_link);
			fclose($this->log_handle);
		}
		
	}/* END CLASS */


	


	/***************************************************************************/
	/**************************** MAIN PROGRAM *********************************/


	/* Object creation */
	$pcm = new PluginConfigManager();



	switch($argv[2])
	{
		/** Step 1 : Get "reference" configuration in tables **/
		case 1:
		{
			echo "Getting settings before configuration of plugin $argv[3]... ";

			$pcm->extractAndSaveConfigReference($argv[3]);
			echo "done\n";
			break;
		}

		/** Step 2 : Compare "base" configuration with new configuration after plugin configuration **/
		case 2: 
		{

			echo "Getting configuration for plugin $argv[3]... ";
			
			$pcm->extractAndSavePluginConfig($argv[3]);			
				
			echo "done\n";



			break;
		}

		/** Step 3 : Load configuration for plugin and save it in DB **/
		case 3 :
		{ 
			
			/* if we have to import from all existing configurations */
			if($argv[3] == PARAM_IMPORT_ALL_CONFIG)
			{
				$plugin_to_import = array();
				/* Getting config folder content */
				$config_folder_content = scandir(PLUGIN_CONFIG_FOLDER_PATH);
				
				/* Regex to identify config files */
				$config_file_regex = '/([\S]+)'.PLUGIN_CONFIG_FILE_FINAL_EXT.'/';

				
				/* Going through config folder content */
				foreach($config_folder_content as $filename)
				{
					/* If current file is a configuration file for plugin */
					if(preg_match($config_file_regex, $filename)===1)
					{
						$plugin_to_import[] = preg_replace($config_file_regex, '${1}' , $filename);
					}
				}/* END LOOPING througn config folder content */
				
			}
			else /* There is one (or more) plugin name for which to import configuration. */
			{
				/* Getting plugin names from parameters */
				$plugin_to_import = array_slice($argv, 3);  
			}
			
			/* Going through plugin to import */
			foreach($plugin_to_import as $plugin_name)
			{
				echo "Importing configuration for plugin $plugin_name... ";

				/* Import configuration for current plugin */
				$pcm->importPluginConfig($plugin_name);
			
				echo "done\n";			
			}/* END LOOPING plugin to import */


			break;
		}


	}/* END SWTICH */
	
	/* Finalize things */
	$pcm->finalize();
	
?>
