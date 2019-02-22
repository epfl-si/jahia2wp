
/**** Displays search options ****/

/* Parameters */
var lang = jQuery('#EPFLEmploiLang').val();
var exceptPositions = jQuery('#EPFLEmploiExceptPositions').val().split(",").map(function (e) {
    return e.trim();
});

/* URL */
var defaultUrl = jQuery('#EPFLEmploiDefaultUrl').val();
var searchPositionUrl = jQuery('#EPFLEmploiSearchPositionUrl').val();

/* Getting translations */
var transFunction = jQuery('#EPFLEmploiTransFunction').val();
var transLocation = jQuery('#EPFLEmploiTransLocation').val();
var transWorkRate = jQuery('#EPFLEmploiTransWorkRate').val();
var transEmplTerm = jQuery('#EPFLEmploiTransEmplTerm').val();

/***************************/

function onSelectionChanged() {
    var url = defaultUrl
    var query = jQuery('input:checkbox:checked').map(function () {
        var key = this.getAttribute("key");
        if (key === "searchPosition") {
            url = searchPositionUrl;
        }
        return key + "=" + this.value;
    }).get().join("&");

    var keywords = jQuery("#id_keywords").val().split(" ").join("+");
    query += "&searchFullPositionsConfExtPubAll=" + keywords;

    if (query) {
        url = url + "&" + query;
    }
    var board = document.getElementById("job-board");
    board.src = url;
}

function reset() {
    jQuery('input:checkbox:checked').removeAttr('checked');
    var board = document.getElementById("job-board");
    board.src = defaultUrl;
}

function doSearch() {
    var keywords = jQuery("#id_keywords").val().split(" ").join("+");
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


var titles = {};
titles["k15"] = {title: transFunction, key: "searchPosition"};
titles["k1004"] = {title: transLocation, key: "searchSkill1004"};
titles["k13"] = {title: transWorkRate, key: "searchEmploymentType"};
titles["k27"] = {title: transEmplTerm, key: "searchContractType"};

simpleAJAXLib = {
    init: function () {
        this.fetchXML('https://recruitingapp-2863.umantis.com/XMLExport/141?Key=dsl');
    },

    fetchXML: function (url) {
        let root = 'https://cors-anywhere.herokuapp.com/';
        let proxy_url = root + url;
        let xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
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
            return parseInt(jQuery(this).find("lang")[0].textContent, 10) == lang && exceptPositions.indexOf(jQuery(this).find("id")[0].textContent) < 0;
        })
        jarray.each(function (index, value) {
            var key = jQuery(value).find("key")[0].textContent
            if (!array["k" + key]) {
                array["k" + key] = [];
            }
            array["k" + key].push(value);
        });


        var root = jQuery("#toggle-pane-0");
        var html = '';
        for (var key in titles) {
            html += '<div class="two-cols clearfix form filter-group"><h3 class="panel-header">' + titles[key].title + '</h3><ul id="id_themes">';

            jQuery.each(array[key], function (index, value) {
                let id = jQuery(value).find("id")[0].textContent;
                let text = jQuery(value).find("text")[0].textContent;
                html += '<li><label><input class="checkbox" key="' + titles[key].key + '" onchange="onSelectionChanged()" type="checkbox" value="' + id + '"><span class="filter-item">' + text + '</span></label></li>';
            });

            html += '</ul></div>';
        }
        html += '</ul>';
        root.append(html);
    }
}

simpleAJAXLib.init();


/**** Displays Job list ****/
var time = new Date().getTime();
var if_height, src = defaultUrl+'&t='+time,
    /* We dynamically create the iframe and we set current page URL as 'name' attribute to allow JavaScript code inside iframe (hosted on another website) to send information
     * to this page. This information contains height of HTML content displayed in the iframe and will help us to resize the iframe to fit its content. */
emploiFrame = jQuery( '<iframe src="' + src + '" name="' + document.location.href + '" width="652" height="500" frameborder="0" scrolling="no" id="job-board" ><\/iframe>' ).appendTo( '#umantis_iframe' );

/*
* We add a listener to receive messages sent by iframe containing job offer list. Messages tells the iframe's content height
* and has format : if_height=<height_in_pixels>
* This will be used to resize iframe (if size has changed). */
window.addEventListener('message', function(e)
{
    /* Extracting height from received message */
    var h = Number( e.data.replace( /.*if_height=(\d+)(?:&|$)/, '$1' ) );

    if (!isNaN( h ) && h > 0 && h !== if_height)
    {
        /* Height has changed, update the iframe */
        if_height = h;
        emploiFrame.height(h);
    }

} , false);
