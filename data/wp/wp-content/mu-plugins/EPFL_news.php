<?php

require('utils.php');

define("API_URL", "https://actu.epfl.ch/api/v1/channels/");

/*
 * Minimal template (to be used in widget)
 */
function display_widget($actus)
{
    //utils\Utils::debug($actus);
    $actu .= '<div>';

	foreach ($actus->results as $item) {

		$actu .= '<div style="height: 103px;">';
		$actu .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . utils\Utils::get_anchor($item->title) . '">';
		$actu .= '    <img style="width: 169px;" src="' . $item->visual_url . '" title="">';
		$actu .= '  </a>';
		$actu .= '  <div style="display:inline-block;margin-left:5px;">';
		$actu .= '    <h4>';
		$actu .= '      <a href="/news/how-the-tuberculosis-bacterium-tricks-the-immune-5/" target="_blank">';
		$actu .= $item->title;
		$actu .= '      </a>';
		$actu .= '    </h4>';
		$actu .= '    <p>';
		$actu .= '      <span class="date">' . $item->publish_date . ' -</span>';
		$actu .= '      <span class="heading" style="display:inline">' . substr($item->subtitle, 0, 40) . '</span>';
        $actu .= '    </p>';
		$actu .= '  </div>';
		$actu .= '</div>';

    }
    $actu .= '</div>';
    return $actu;
}

function epfl_news_process_shortcode( $atts, $content = '', $tag) {

        // extract shortcode parameter
        $atts = extract(shortcode_atts(array(
                'channel' => '1',
                'lang' => 'fr'
        ), $atts, $tag));

        // define API URL
        $url = API_URL.$channel.'/news/?format=json&lang='.$lang;

        // HTTP GET on REST API actus
        $actus = utils\Utils::get_items($url);

        // Build HTML
        $result = display_widget($actus);
        //utils\Utils::debug($result);

        // return HTML result
        return $result;
}

// define the shortcode
add_shortcode('epfl_news', 'epfl_news_process_shortcode');

?>
