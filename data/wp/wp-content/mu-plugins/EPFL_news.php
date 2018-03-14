<?php

declare(strict_types=1);

require('utils.php');

use utils\Utils as NewsUtils;

define("NEWS_API_URL", "https://actu.epfl.ch/api/v1/channels/");
define("NEWS_API_URL_IFRAME", "https://actu.epfl.ch/webservice_iframe/");

/**
 * Build HTML. This template is waiting for Aline templates.
 */
function build_html($actus): string
{
    $actu = '<div>';
	foreach ($actus->results as $item) {
		$actu .= '<div style="height: 103px;">';
		$actu .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . NewsUtils::get_anchor($item->title) . '">';
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

/**
 * Build HTML. This template contains all news inside ifram tag
 */
function built_html_pagination_template(string $channel, string $lang): string {

    // call API to get the name of channel
    $url = NEWS_API_URL . $channel;
    $channel = NewsUtils::get_items($url);

    $url = NEWS_API_URL_IFRAME . $channel->name . "/" . $lang . "/nosticker";

    $result = '<IFRAME ';
    $result .= 'src="' . $url . '" ';
    $result .= 'width="700" height="1100" scrolling="no" frameborder="0"></IFRAME>';
    return $result;
}

/**
 * Returns the number of news according to the template
 */
function get_limit(string $template): int
{
    switch ($template):
        case "1":
        case "7":
            $limit = 1;
            break;
        case "3":
            $limit = 4;
            break;
        case "2":
        case "4":
        case "6":
            $limit = 3;
            break;
        case "8":
            $limit = 5;
            break;
        default:
            $limit = 3;
    endswitch;
    return $limit;
}

/**
 * Build api URL of news
 */
function build_api_url(
    string $channel,
    string $template,
    string $lang,
    string $category,
    string $themes
    ): string
{

    // returns the number of news according to the template
    $limit = get_limit($template);

    // define API URL
    $url = NEWS_API_URL . $channel . '/news/?format=json&lang=' . $lang . '&limit=' . $limit;

    // filter by category
    if ($category !== '') {
        $url .= '&category=' . $category;
    }

    // filter by themes
    if ($themes !== '') {
        $themes = explode(',', $themes);
        foreach ($themes as $theme) {
            $url .= '&themes=' . $theme;
        }
    }
    return $url;
}

/**
 * Check the required parameters
 */
function check_parameters(string $channel, string $lang): bool {
    return $channel !== "" && $lang !== "";
}

/**
 * Main function of shortcode
 */
function epfl_news_process_shortcode(array $atts, string $content = '', string $tag): string {

        // extract shortcode parameter
        $atts = extract(shortcode_atts(array(
                'channel' => '',
                'lang' => '',
                'template' => '',
                'category' => '',
                'themes' => '',
        ), $atts, $tag));

        if (check_parameters($channel, $lang) == FALSE) {
            return "";
        }

        // iframe template
        if ($template === "10") {
            return built_html_pagination_template($channel, $lang);
        }

        $url = build_api_url(
            $channel,
            $template,
            $lang,
            $category,
            $themes
        );

        $actus = NewsUtils::get_items($url);
        return build_html($actus);
}

// define the shortcode
add_shortcode('epfl_news', 'epfl_news_process_shortcode');

?>