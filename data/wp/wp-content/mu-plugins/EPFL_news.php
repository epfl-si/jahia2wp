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
 * Template text only (template 2)
 * 
 * @param $news: response of news API. 
 * @return html of template
 */
function epfl_news_template_text_only($news): string
{
    $html = '<p>template_labo_with_4_news</p>';
    $html .= '<div class="list-articles list-news list-news-textonly clearfix">';
	foreach ($news->results as $item) {
  	
  	$publish_date = new DateTime($item->publish_date);
    $publish_date = $publish_date->format('d.m.y');
	    
		$html .= '<article class="post">';
		$html .= '  <header class="entry-header">';
		$html .= '    <h2 class="entry-title">';
		$html .= '      <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h2>';
		$html .= '  </header>';
		$html .= '  <div class="entry-content">';
		$html .= '    <div class="entry-meta">';
		$html .= '      <time class="entry-date">' . $publish_date . '</time>';
		$html .= '    </div>';
		$html .= '    <div class="teaser">' . substr($item->subtitle, 0, 360) . '</div>';
		$html .= '  </div>';
		$html .= '</article>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template faculty with 4 news (template 3)
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_fac_with_4_news($news, bool $stickers): string
{
    $html = '<p>template_labo_with_4_news</p>';
    $html .= '<div class="list-articles list-news list-news-first-featured clearfix">';
	foreach ($news->results as $item) {

/*
	    // print fr and en category
	    if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                Utils::debug($item->category->fr_label);
            } elseif ($item->lang === "en") {
                Utils::debug($item->category->en_label);
            }
        }
*/
        
    $publish_date = new DateTime($item->publish_date);
    $publish_date = $publish_date->format('d.m.y');
	    
		$html .= '<article class="post">';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
		$html .= '  </figure>';
		$html .= '  <p class="label">' . $item->category->fr_label . ' </p>';
		$html .= '  <div class="entry-content">';
		$html .= '    <header class="entry-header">';
		$html .= '      <h2 class="entry-title">';
		$html .= '        <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= $item->title;
		$html .= '        </a>';
		$html .= '      </h2>';
		$html .= '    </header>';
		$html .= '    <div class="entry-meta">';
		$html .= '      <time class="entry-date">' . $publish_date . '</time>';
		$html .= '    </div>';
		$html .= '    <div class="teaser">' . substr($item->subtitle, 0, 240) . '</div>';
		$html .= '  </div>';
		$html .= '</article>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template faculty with 3 news (template 6 - sidebar)
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_fac_with_3_news($news, bool $stickers): string
{
    $html = '<p>template_labo_with_3_news (sidebar)</p>';
    $html .= '<div class="list-articles list-news list-news-sidebar clearfix">';
	foreach ($news->results as $item) {

	    // print fr and en category
/*
	    if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                Utils::debug($item->category->fr_label);
            } elseif ($item->lang === "en") {
                Utils::debug($item->category->en_label);
            }
        }
*/
        
    $publish_date = new DateTime($item->publish_date);
    $publish_date = $publish_date->format('d.m.y');
	    
		$html .= '<article class="post">';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
		$html .= '  </figure>';
		$html .= '  <p class="label">' . $item->category->fr_label . ' </p>';
		$html .= '  <div class="entry-content">';
		$html .= '    <header class="entry-header">';
		$html .= '      <h2 class="entry-title">';
		$html .= '        <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= $item->title;
		$html .= '        </a>';
		$html .= '      </h2>';
		$html .= '    </header>';
		$html .= '    <div class="entry-meta">';
		$html .= '      <time class="entry-date">' . $publish_date . '</time>';
		$html .= '    </div>';
		$html .= '    <div class="teaser">' . substr($item->subtitle, 0, 240) . '</div>';
		$html .= '  </div>';
		$html .= '</article>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template laboratory with 5 news (template 8)
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_labo_with_5_news($news, bool $stickers): string
{
    $html = '<p>template_labo_with_5_news</p>';
    $html .= '<div class="list-articles list-news clearfix">';
	foreach ($news->results as $item) {
		$html .= '<article class="post">';
		$html .= '  <header class="entry-header">';
		$html .= '    <h2 class="entry-title">';
		$html .= '      <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h2>';
		$html .= '  </header>';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
		$html .= '  </figure>';
		$html .= '  <p class="label">' . $item->category->fr_label . ' </p>';
		$html .= '  <div class="entry-content">';
		$html .= '    <div class="entry-meta">';
		$html .= '      <time class="entry-date">' . $item->publish_date . '</time>';
		$html .= '    </div>';
		$html .= '    <div class="teaser">' . substr($item->subtitle, 0, 240) . '</div>';
		$html .= '  </div>';
		$html .= '</article>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Template laboratory with 3 news (template 4)
 * 
 * @param $news: response of news API. 
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_labo_with_3_news($news, bool $stickers): string
{
    $html = '<p>template_labo_with_3_news</p>';
    $html .= '<div class="list-articles list-news clearfix">';
	foreach ($news->results as $item) {

	    // print fr and en category
/*
	    if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                Utils::debug($item->category->fr_label);
            } elseif ($item->lang === "en") {
                Utils::debug($item->category->en_label);
            }
        }
*/
        
    $publish_date = new DateTime($item->publish_date);
    $publish_date = $publish_date->format('d.m.Y');

		$html .= '<article class="post">';
		$html .= '  <header class="entry-header">';
		$html .= '    <h2 class="entry-title">';
		$html .= '      <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h2>';
		$html .= '  </header>';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="https://actu.epfl.ch/news/' . Utils::get_anchor($item->title) . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
		$html .= '  </figure>';
		$html .= '  <p class="label">' . $item->category->fr_label . ' </p>';
		$html .= '  <div class="entry-content">';
		$html .= '    <div class="entry-meta">';
		$html .= '      <time class="entry-date">' . $publish_date . '</time>';
		$html .= '    </div>';
		$html .= '    <div class="teaser">' . substr($item->subtitle, 0, 240) . '</div>';
		$html .= '  </div>';
		$html .= '</article>';
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