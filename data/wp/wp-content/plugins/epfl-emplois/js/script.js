	/*******/
	// TO SET BASED ON PAGE (language and position filter)
	var lang = 2;
	var exceptPositions = ["15050", "15060"];
	/*******/

	var defaultUrl = document.getElementById('EPFLEmploisDefaultUrl').value;
    var searchPositionUrl = document.getElementById('EPFLEmploisSearchPositionUrl').value;

	function onSelectionChanged() {
	 var url = defaultUrl
	 var query = jQuery('input:checkbox:checked').map(function() {
	        var key = this.getAttribute("key");
	        if(key === "searchPosition") {
	            url = searchPositionUrl;
	        }
	     return key+"="+this.value;
	 }).get().join("&");

	var keywords = jQuery("#id_keywords").val().split(" ").join("+");
	   query += "&searchFullPositionsConfExtPubAll="+keywords;

	 if (query) {
	     url = url + "&" + query;
	 }
	 var board = document.getElementById("job-board");
	 board.src = url;
	}

	function reset() {
	jQuery('input:checkbox:checked').removeAttr('checked');
	var board = document.getElementById("job-board");
	 board.src = defaultUrl ;
	}

	function doSearch() {
	var keywords = jQuery("#id_keywords").val().split(" ").join("+");
	console.log(keywords);
	}

	function toggleExpand(elem) {
	 jQuery(elem).next().next("ul").toggle();
	 jQuery(elem).parent("li").toggleClass("expandable");
	 jQuery(elem).parent("li").toggleClass("lastExpandable");
	 jQuery(elem).parent("li").toggleClass("collapsable");
	 jQuery(elem).parent("li").toggleClass("lastCollapsable");
	jQuery(elem).toggleClass("expandable-hitarea");
	jQuery(elem).toggleClass("lastExpandable-hitarea");
	jQuery(elem).toggleClass("collapsable-hitarea");
	jQuery(elem).toggleClass("lastCollapsable-hitarea");
	}

	function toggleSpanExpand(elem) {
	 jQuery(elem).next("ul").toggle();
	 jQuery(elem).parent("li").toggleClass("expandable");
	 jQuery(elem).parent("li").toggleClass("lastExpandable");
	 jQuery(elem).parent("li").toggleClass("collapsable");
	 jQuery(elem).parent("li").toggleClass("lastCollapsable");
	jQuery(elem).prev("div").toggleClass("expandable-hitarea");
	jQuery(elem).prev("div").toggleClass("lastExpandable-hitarea");
	jQuery(elem).prev("div").toggleClass("collapsable-hitarea");
	jQuery(elem).prev("div").toggleClass("lastCollapsable-hitarea");
	}



	var titles = [];
	titles[2] = {};
	titles[3] = {};
	   titles[2]["k15"] = {title: "Function", key: "searchPosition"};
	   titles[2]["k1004"] = {title: "Location", key: "searchSkill1004"};
	   titles[2]["k13"] = {title: "Work rate", key: "searchEmploymentType"};
	titles[2]["k27"] = {title: "Term of employment", key: "searchContractType"};
	/*titles[2]["k10"] = {title: "School / VP", key: "searchFunction"};*/


	   titles[3]["k15"] = {title: "Fonction", key: "searchPosition"};
	   titles[3]["k1004"] = {title: "Lieu de travail", key: "searchSkill1004"};
	titles[3]["k13"] = {title: "Taux d'occupation", key: "searchEmploymentType"};
	titles[3]["k27"] = {title: "Type de contrat", key: "searchContractType"};
	/*titles[3]["k10"] = {title: "Faculté / VP", key: "searchFunction"};*/

	                simpleAJAXLib = {
	               init: function () {
	               this.fetchXML('https://recruitingapp-2863.umantis.com/XMLExport/141?Key=dsl');
	               },

	               fetchXML: function (url) {
	                   let root = 'https://cors-anywhere.herokuapp.com/';
	                   let proxy_url = root + url;
	                   let xhr = new XMLHttpRequest();
	                   xhr.onreadystatechange = function() {
	                       if (this.readyState === 4 && this.status === 200) {
	                              simpleAJAXLib.display(this.responseXML);
	                          }
	                   }
	                   xhr.open("GET", proxy_url);
	                   xhr.setRequestHeader("Accept", 'application/json');
	                   xhr.send();
	               },

	               jsTag: function (url) {
	                 var script = document.createElement('script');
	                 script.setAttribute('type', 'text/javascript');
	                 script.setAttribute('src', url);
	                 return script;
	               },

	               display: function (xml) {
	                 // do the necessary stuff
	                 var array = [];
	                    var jarray = jQuery(xml).find("Value").filter(function () {
	                       return parseInt(jQuery(this).find("lang")[0].textContent,10) == lang && exceptPositions.indexOf(jQuery(this).find("id")[0].textContent) < 0;
	                    })
	                    jarray.each(function (index, value) {
	                               var key = jQuery(value).find("key")[0].textContent
	                     if(!array["k"+key]) {
	                         array["k"+key] = [];
	                     }
	                     array["k"+key].push(value);
	                 });


	                 var root = jQuery("#toggle-pane-0");
	               var html = '';
	                 for(var key in titles[lang]) {
	                    html += '<div class="two-cols clearfix form filter-group"><h3 class="panel-header">'+titles[lang][key].title+'</h3><ul id="id_themes">';

	                               jQuery.each(array[key], function (index, value) {
	                                                 let id = jQuery(value).find("id")[0].textContent;
	                                                 let text = jQuery(value).find("text")[0].textContent;
	                         html += '<li><label><input class="checkbox" key="'+titles[lang][key].key+'" onchange="onSelectionChanged()" type="checkbox" value="'+id+'"><span class="filter-item">'+text+'</span></label></li>';
	                     });

	                     html += '</ul></div>';
	                 }
	               html += '</ul>';
	               root.append(html);
	               }
	               }

	               simpleAJAXLib.init();

