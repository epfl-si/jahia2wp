<?php
/*
Plugin Name: Courses Search Engine plugin
File : courses_search_engine.php
Description: function to display courses search engine page
Version: 0.1
Author: CAPE - Ludovic Bonivento
*/
$PLUGIN_ROOT_URL = plugin_dir_url(__DIR__);

function display_courses_se() {
?>

	<div class="container-full" style="padding:0px 50px;">
                
        <script type="text/javascript">      
        
        
			var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
            
            setInterval(updateCloud, 10000);
            
            var selectedSemesters = [];
            var selectedPolyperspectives = [];
            var selectedKeyword = '';
            var selectedTeacher = '';
            var selectedLanguages = [];
            var filters = [];
            var keywords = '';
            var teachers = '';
                            
            jQuery(document).ready(function(){

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    dataType: "json",
                    data: {action:'getKeywords'},
                    success: function(data){
						autocomplete(document.getElementById("keywords"), data);
                    }
                });
                
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    dataType: "json",
                    data: {action:'getTeachers'},
                    success: function(data){
						autocomplete(document.getElementById("teachers"), data);
                    }
                });
                
                filterCourses();
                                
                jQuery.ajax({//Make the Ajax Request
                    url: ajaxurl,
                    type: "POST",
                    dataType: "json",
                    data: {action:'getPolyperspectives'},
                    success: function(data){
                        jQuery('#filter-polyperspectives').html(data);    
                    },
                });
                
                jQuery.ajax({//Make the Ajax Request
                    url: ajaxurl,
                    type: "POST",
                    dataType: "json",
                    data: {action:'getSemesters'},
                    success: function(data){
                        jQuery('#filter-semesters').html(data);    
                    },
                });
                
            }); 
            
            //jQCloud
              jQuery(function () {
                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    dataType: "text",
                    data: {action:'getCloudKeywords'},
                    success: function(data){
                        jQuery("#cloud").jQCloud(jQuery.parseJSON(data));
					}
                });
            });
            
            function updateCloud(){
                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    dataType: "text",
                    data: {action:'getCloudKeywords'},
                    success: function(data){
						jQuery("#cloud").jQCloud('update',jQuery.parseJSON(data));
					}
                });
            }
            
            function clickFromCloud(keyword){
                filters = jQuery.grep(filters, function(keyword){
                    return keyword !== selectedKeyword;
                }); 
                selectedKeyword = keyword;
                filters.push(selectedKeyword);
                filterCourses();
            }

            
            function handleSemesterClick(semester) {
                if (semester.checked && selectedSemesters.indexOf(semester.value) === -1){
                    selectedSemesters.push(semester.value);
                    filters.push(semester.value);
                }else if(!semester.checked && selectedSemesters.indexOf(semester.value) > -1){
                    selectedSemesters = jQuery.grep(selectedSemesters, function(value){
                        return value !== semester.value;
                    });                    
                    filters = jQuery.grep(filters, function(value){
                        return value !== semester.value;
                    });                    
                }
                filterCourses();
            }

            
            function handleLanguageClick(language) {
            if (language.checked && selectedLanguages.indexOf(language.value) === -1){
                    selectedLanguages.push(language.value);
                    filters.push(language.value);
                }else if(!language.checked && selectedLanguages.indexOf(language.value) > -1){
                    selectedLanguages = jQuery.grep(selectedLanguages, function(value){
                        return value !== language.value;
                    });                    
                    filters = jQuery.grep(filters, function(value){
                        return value !== language.value;
                    });                    
                }
                filterCourses();
            }
            
            function handlePolyperspectiveClick(polyperspective) {
                if (polyperspective.checked && selectedPolyperspectives.indexOf(polyperspective.name) === -1){
                    selectedPolyperspectives.push(polyperspective.name);
                    filters.push(polyperspective.name);
                }else if(!polyperspective.checked && selectedPolyperspectives.indexOf(polyperspective.name) > -1){
                    selectedPolyperspectives = jQuery.grep(selectedPolyperspectives, function(name){
                        return name !== polyperspective.name;
                    });            
                    filters = jQuery.grep(filters, function(name){
                        return name !== polyperspective.name;
                    });                            
                }
                filterCourses();
            }
            
            function removeFilter(filter){
                
                if(filter === null){
                    selectedKeyword="";
                    selectedTeacher="";
                    selectedPolyperspectives=[];
                    selectedSemesters=[];
                    selectedLanguages=[];
                    filters=[];
                    jQuery("input.custom-control-input").each(function(){
                        if(jQuery(this).prop('disabled')!=true)
                        {
                          jQuery(this).attr('checked',false);
                        }
                    });
                    
                }else{
                    filters = jQuery.grep(filters, function(value){
                        return value !== filter;
                    });

                    if(selectedKeyword === filter){
                        selectedKeyword="";
                    }else if(selectedTeacher === filter){
                        selectedTeacher="";
                    }else {
                        if(selectedPolyperspectives.indexOf(filter) > -1){
                            selectedPolyperspectives = jQuery.grep(selectedPolyperspectives, function(value){
                                return value !== filter;
                            }); 
                            document.getElementById(filter).checked = false;
                        }else if(selectedSemesters.indexOf(filter) > -1){
                            selectedSemesters = jQuery.grep(selectedSemesters, function(value){
                                return value !== filter;
                            }); 
                            document.getElementById(filter).checked = false;
                        }else if(selectedLanguages.indexOf(filter) > -1){
                            selectedLanguages = jQuery.grep(selectedLanguages, function(value){
                                return value !== filter;
                            }); 
                            document.getElementById(filter).checked = false;
                        }
                    }
                }
                
                filterCourses();
                
            }
            
            function updateActiveFilters(){
                var activeFiltersHtml = ""; 
                var col = 0;
                if(filters.length > 0){
                    activeFiltersHtml +=  "<button type='button' class='btn btn-primary' style='margin-right:10px;' onclick='removeFilter(null)'><b>Remove all</b> &ensp; X </button>";
                    jQuery.each(filters, function( key, value ) {
                        activeFiltersHtml +=  "<button type='button' class='btn btn-primary' onclick='removeFilter(&#39;"+value+"&#39;)'><b>" + value + "</b> &ensp; X </button>"
                    });
                }
                jQuery("#activeFilters").html(activeFiltersHtml);
                
            }
            
            function filterCourses(){
				
				jQuery.ajax({//Make the Ajax Request
					url: ajaxurl,
                    type: "POST",
                    dataType: "text",
                    data: {action:'getFilteredCourses',
						   semesters:selectedSemesters,
						   polyperspectives:selectedPolyperspectives, 
						   keywords:selectedKeyword,
						   teachers:selectedTeacher,
						   languages:selectedLanguages},
                    success: function(data){
						jQuery("#courses").html(data);
                        updateActiveFilters();
                    }
                });
            }
            
        </script>
			<div id="activeFilters"></div>
                <button 
					class="collapse-title collapse-title-desktop collapsed"
					type="button"
					data-toggle="collapse"
					data-target="#filters-global"
					aria-expanded="false"
					aria-controls="filters-global"
					style="background-color:lightgrey;padding-left:5px;"
				>
					<?php echo __('Filters', 'epfl-courses-se') ?>
				</button>
                <div class="collapse collapse-item collapse-item-desktop" id="filters-global" style="padding-left : 20px;background-color:lightgrey;">
					<button 
						class="collapse-title collapse-title-desktop collapsed"
						type="button"
						data-toggle="collapse"
						data-target="#filter-semesters"
						aria-expanded="false"
						aria-controls="filter-semesters"
					>
						<?php echo __('Semesters', 'epfl-courses-se') ?>
					</button>
                    <div class="collapse collapse-item collapse-item-desktop"  id="filter-semesters">
                    </div>
                    <button 
						class="collapse-title collapse-title-desktop collapsed"
						type="button"
						data-toggle="collapse"
						data-target="#filter-keywords"
						aria-expanded="false"
						aria-controls="filter-keywords"
					>
						<?php echo __('Keywords', 'epfl-courses-se') ?>
					</button>
					<div class="collapse collapse-item collapse-item-desktop"  id="filter-keywords">
						<form autocomplete="off" action="#" class="border-0 p-0">
						<div class="autocomplete">
							<label><svg class="icon" aria-hidden="true"><use xlink:href="#icon-search"></use></svg></label>
							<input id="keywords" type="text" class="form-control form-control-search">
						</div>
						</form>
						<div id="cloud" style="margin: 0 auto;"></div>
                    </div> 
                    <button 
						class="collapse-title collapse-title-desktop collapsed"
						type="button"
						data-toggle="collapse"
						data-target="#filter-polyperspectives"
						aria-expanded="false"
						aria-controls="filter-polyperspectives"
					>
						<?php echo __('Polyperspectives', 'epfl-courses-se') ?>
					</button>
                    <div class="collapse collapse-item collapse-item-desktop" id="filter-polyperspectives">
                    </div>
                    <button 
						class="collapse-title collapse-title-desktop collapsed"
						type="button"
						data-toggle="collapse"
						data-target="#filter-teachers"
						aria-expanded="false"
						aria-controls="filter-teachers"
					>
						<?php echo __('Teachers', 'epfl-courses-se') ?>
					</button>
					
                    <div class="collapse collapse-item collapse-item-desktop"  id="filter-teachers">
						<form autocomplete="off" action="#" class="border-0 p-0">
						<div class="autocomplete">
							<label><svg class="icon" aria-hidden="true"><use xlink:href="#icon-search"></use></svg></label>
							<input id="teachers" type="text" class="form-control form-control-search">
						</div>
						</form>						
                    </div> 
                    <button 
						class="collapse-title collapse-title-desktop collapsed"
						type="button"
						data-toggle="collapse"
						data-target="#filter-languages"
						aria-expanded="false"
						aria-controls="filter-languages"
					>
						<?php echo __('Languages', 'epfl-courses-se') ?>
					</button>
                    <div class="collapse collapse-item collapse-item-desktop"  id="filter-languages">
						<table class='table' style='border:none; font-size: 1rem;'>
							<tr>
								<td style='border-bottom:none'>
									<div class='custom-control custom-checkbox'>
										<input type='checkbox' class='custom-control-input' id='FR' name='FR' value='FR' onclick='handleLanguageClick(this)'/>
										<label for='FR' class='custom-control-label'>FR</label>
									</div>
								</td>
								<td style='border-bottom:none'>
									<div class='custom-control custom-checkbox'>
										<input type='checkbox' class='custom-control-input' id='EN' name='EN' value='EN' onclick='handleLanguageClick(this)'/>
										<label for='EN' class='custom-control-label'>EN</label>
									</div>
								</td>
							</tr>
						</table><br/>
                    </div>
                </div>
                <div id="courses">                  
		</div>
	</div>
	
<?php ;
}
?>