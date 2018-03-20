<?php

declare(strict_types=1);

require_once('utils.php');

use utils\Utils as Utils;

define("NEWS_API_URL", "https://actu.epfl.ch/api/v1/channels/");
define("NEWS_API_URL_IFRAME", "https://actu.epfl.ch/webservice_iframe/");

function epfl_news_template_text_only($actus, $stickers): string
{
    $actu = '<div>';
	foreach ($actus->results as $item) {
		$actu .= '<div style="height: 103px;">';
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

function epfl_news_template_fac_with_4_news($actus, $stickers): string
{
    $actu = '<div>';
	foreach ($actus->results as $item) {
		$actu .= '<div style="height: 103px;">';
		$actu .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
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

function epfl_news_template_labo_with_5_news($actus, $stickers): string
{
    $actu = '<div>';
	foreach ($actus->results as $item) {
		$actu .= '<div style="height: 103px;">';
		$actu .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
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

function epfl_news_template_labo_with_3_news($actus, $stickers): string
{
    $actu = '<div>';
	foreach ($actus->results as $item) {
		$actu .= '<div style="height: 103px;">';
		$actu .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
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
 * Build HTML. 
 */
function epfl_news_build_html($actus, $template, $stickers): string
{   
    if ($template === "4") {
        $html = epfl_news_template_labo_with_3_news($actus, $stickers);
    } elseif ($template === "8") {
        $html = epfl_news_template_labo_with_5_news($actus, $stickers);
    } elseif ($template === "3") {
        $html = epfl_news_template_fac_with_4_news($actus, $stickers);
    } elseif ($template === "6") {
        $html = epfl_news_template_fac_with_3_news($actus, $stickers);
    } elseif ($template === "2") {
        $html = epfl_news_template_text_only($actus, $stickers);
    } else {
        $html = epfl_news_template_labo_with_3_news($actus, $stickers);
    }
    return $html;
}

/**
 * Build HTML. This template contains all news inside ifram tag
 */
function epfl_news_built_html_pagination_template(string $channel, string $lang): string {

    // call API to get the name of channel
    $url = NEWS_API_URL . $channel;
    $channel = Utils::get_items($url);

    $url = NEWS_API_URL_IFRAME . $channel->name . "/" . $lang . "/nosticker";

    $result = '<IFRAME ';
    $result .= 'src="' . $url . '" ';
    $result .= 'width="700" height="1100" scrolling="no" frameborder="0"></IFRAME>';
    return $result;
}

/**
 * Returns the number of news according to the template
 */
function epfl_news_get_limit(string $template): int
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
function epfl_news_build_api_url(
    string $channel,
    string $template,
    string $lang,
    string $category,
    string $themes
    ): string
{
    // returns the number of news according to the template
    $limit = epfl_news_get_limit($template);

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
function epfl_news_check_parameters(string $channel, string $lang): bool {
    return $channel !== "" && $lang !== "";
}

/**
 * Main function of shortcode
 */
function epfl_news_process_shortcode(
    array $atts, 
    string $content = '', 
    string $tag
    ): string {

        // extract shortcode paramepfl_newseter
        $atts = extract(shortcode_atts(array(
                'channel' => '',
                'lang' => '',
                'template' => '',
                'stickers' => '',
                'category' => '',
                'themes' => '',
        ), $atts, $tag));

        if (epfl_news_check_parameters($channel, $lang) == FALSE) {
            return "";
        }
        
        $stickers = $stickers == 'yes';

        // iframe template
        if ($template === "10") {
            return epfl_news_built_html_pagination_template($channel, $lang);
        }

        $url = epfl_news_build_api_url(
            $channel,
            $template,
            $lang,
            $category,
            $themes
        );

        $actus = Utils::get_items($url);
        return epfl_news_build_html($actus, $template, $stickers);
}

// define the shortcode
add_shortcode('epfl_news', 'epfl_news_process_shortcode');

?>