<?php
/**
 * Plugin Name: EPFL News shortcode
 * Description: provides a shortcode to display news feed
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare(strict_types=1);

require_once('utils.php');

use utils\Utils as Utils;

define("NEWS_API_URL", "https://actu.epfl.ch/api/v1/channels/");
define("NEWS_API_URL_IFRAME", "https://actu.epfl.ch/webservice_iframe/");

/**
 * Template text only
 * 
 * @param $news: response of news API. 
 * @return html of template
 */
function epfl_news_template_text_only($news): string
{
    $html = '<div>';
    $html .= '<p>template_text_only</p>';

	foreach ($news->results as $item) {

        // print fr and en category
        if ($item->lang === "fr") {
            Utils::debug($item->category->fr_label);
        } elseif ($item->lang === "en") {
            Utils::debug($item->category->en_label);
        }

        $publish_date = new DateTime($item->publish_date);
	    $publish_date = $publish_date->format('d.m.Y');

		$html .= '<div style="height: 103px;">';
		$html .= '  <div style="display:inline-block;margin-left:5px;">';
		$html .= '    <h4>';
		$html .= '      <a href="/news/how-the-tuberculosis-bacterium-tricks-the-immune-5/" target="_blank">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h4>';
		$html .= '    <p>';
		$html .= '      <span class="date">' . $publish_date . ' -</span>';
		$html .= '      <span class="heading" style="display:inline">' . substr($item->subtitle, 0, 40) . '</span>';
        $html .= '    </p>';
		$html .= '  </div>';
		$html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template faculty with 4 news
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_fac_with_4_news($news, bool $stickers): string
{
    $html = '<div>';
    $html .= '<p>template_fac_with_4_news</p>';
	foreach ($news->results as $item) {
		$html .= '<div style="height: 103px;">';
		$html .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '    <img style="width: 169px;" src="' . $item->visual_url . '" title="">';
		$html .= '  </a>';
		$html .= '  <div style="display:inline-block;margin-left:5px;">';
		$html .= '    <h4>';
		$html .= '      <a href="/news/how-the-tuberculosis-bacterium-tricks-the-immune-5/" target="_blank">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h4>';
		$html .= '    <p>';
		$html .= '      <span class="date">' . $item->publish_date . ' -</span>';
		$html .= '      <span class="heading" style="display:inline">' . substr($item->subtitle, 0, 40) . '</span>';
        $html .= '    </p>';
		$html .= '  </div>';
		$html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template laboratory with 5 news
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_labo_with_5_news($news, bool $stickers): string
{
    $html = '<div>';
    $html .= '<p>template_labo_with_5_news</p>';
	foreach ($news->results as $item) {
		$html .= '<div style="height: 103px;">';
		$html .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '    <img style="width: 169px;" src="' . $item->visual_url . '" title="">';
		$html .= '  </a>';
		$html .= '  <div style="display:inline-block;margin-left:5px;">';
		$html .= '    <h4>';
		$html .= '      <a href="/news/how-the-tuberculosis-bacterium-tricks-the-immune-5/" target="_blank">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h4>';
		$html .= '    <p>';
		$html .= '      <span class="date">' . $item->publish_date . ' -</span>';
		$html .= '      <span class="heading" style="display:inline">' . substr($item->subtitle, 0, 40) . '</span>';
        $html .= '    </p>';
		$html .= '  </div>';
		$html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template laboratory with 3 news
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_labo_with_3_news($news, bool $stickers): string
{
    $html = '<div>';
    $html .= '<p>template_labo_with_3_news</p>';
	foreach ($news->results as $item) {
		$html .= '<div style="height: 103px;">';
		$html .= '  <a style="float:left;" href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '    <img style="width: 169px;" src="' . $item->visual_url . '" title="">';
		$html .= '  </a>';
		$html .= '  <div style="display:inline-block;margin-left:5px;">';
		$html .= '    <h4>';
		$html .= '      <a href="/news/how-the-tuberculosis-bacterium-tricks-the-immune-5/" target="_blank">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h4>';
		$html .= '    <p>';
		$html .= '      <span class="date">' . $item->publish_date . ' -</span>';
		$html .= '      <span class="heading" style="display:inline">' . substr($item->subtitle, 0, 40) . '</span>';
        $html .= '    </p>';
		$html .= '  </div>';
		$html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Build HTML. 
 * 
 * @param $news: response of news API. 
 * @param $template: id of template
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_build_html($news, string $template, bool $stickers): string
{   
    if ($template === "4") {
        $html = epfl_news_template_labo_with_3_news($news, $stickers);
    } elseif ($template === "8") {
        $html = epfl_news_template_labo_with_5_news($news, $stickers);
    } elseif ($template === "3") {
        $html = epfl_news_template_fac_with_4_news($news, $stickers);
    } elseif ($template === "6") {
        $html = epfl_news_template_fac_with_3_news($news, $stickers);
    } elseif ($template === "2") {
        $html = epfl_news_template_text_only($news);
    } else {
        $html = epfl_news_template_labo_with_3_news($news, $stickers);
    }
    return $html;
}

/**
 * Build HTML. This template contains all news inside ifram tag
 * 
 * @param $channel: id of news channel 
 * @param $lang: lang of news (fr or en)
 * @return html of template
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
 * @param $template: id of template
 * @return the number of news to display
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
 * 
 * @param $channel: id of news channel
 * @param $template: id of template
 * @param $lang: lang of news
 * @param $category: id of news category
 * @param $themes: The list of news themes id. For example: 1,2,5
 * @return the api URL of news
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
 * 
 * @param $channel: id of channel
 * @param $lang: lang of news (fr or en)
 * @return True if the required parameters are right.
 */
function epfl_news_check_required_parameters(string $channel, string $lang): bool {
    
    // check lang
    if ($lang !==  "fr" && $lang !== "en" ) {
        return FALSE;
    }

    // check channel
    if ($channel === "") {
        return FALSE;
    }

    // check that the channel exists
    $url = NEWS_API_URL . $channel;
    $channel_response = Utils::get_items($url);
    if ($channel_response->detail === "Not found.") {
        return FALSE;
    }
    return TRUE;

}

/**
 * Main function of shortcode
 * 
 * @param $atts: attributes of the shortcode
 * @param $content: the content of the shortcode. Always empty in our case.
 * @param $tag: the name of shortcode. epfl_news in our case.
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

        if (epfl_news_check_required_parameters($channel, $lang) == FALSE) {
            return "";
        }
        
        // display stickers on images ?
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