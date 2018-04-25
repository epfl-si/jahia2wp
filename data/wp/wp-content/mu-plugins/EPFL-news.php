<?php
/**
 * Plugin Name: EPFL News shortcode
 * Description: provides a shortcode to display news feed
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare(strict_types=1);

define("NEWS_API_URL", "https://actu.epfl.ch/api/v1/channels/");
define("NEWS_API_URL_IFRAME", "https://actu.epfl.ch/webservice_iframe/");

Class NewsUtils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }

    /**
     * This allow to insert anchor before the element
     *   i.e. '<a name="' . $ws->get_anchor($item->title) . '"></a>';
     * and also to get the item link in case it's not provided by the API.
     * e.g. https://actu.epfl.ch/news/a-12-million-franc-donation-to-create-a-center-for/
     */
    public static function get_anchor(string $title): string {

        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                                    'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                                    'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                                    'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                                    'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

        $title = strtr( $title, $unwanted_array );
        $title = str_replace(" ", "-", $title);
        $title = str_replace("'", "-", $title);
        $title = strtolower($title);
        $title = substr($title, 0, 50);

        return $title;
    }

    /**
     * Call API
     * @param url  : the fetchable url
     * @param args : array('timeout' => 10), see https://codex.wordpress.org/Function_Reference/wp_remote_get
     * @return decoded JSON data
     */
    public static function get_items(string $url) {

        $response = wp_remote_get($url);

        if (is_array($response)) {
                $header = $response['headers']; // array of http header lines
                $data = $response['body']; // use the content
                if ( $header["content-type"] === "application/json" ) {
                        $items = json_decode($data);
                        return $items;
                }
        }
    }
}

/**
 * Template text only (template 2)
 *
 * @param $news: response of news API.
 * @return html of template
 */
function epfl_news_template_text_only($news): string
{
    $html = '<div class="list-articles list-news list-news-textonly clearfix">';
	foreach ($news->results as $item) {

  	$publish_date = new DateTime($item->publish_date);
    $publish_date = $publish_date->format('d.m.y');

		$html .= '<article class="post">';
		$html .= '  <header class="entry-header">';
		$html .= '    <h2 class="entry-title">';
		$html .= '      <a href="' . $item->news_url . '">';
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
    $html = '<div class="list-articles list-news list-news-first-featured clearfix">';
	foreach ($news->results as $item) {

        $publish_date = new DateTime($item->publish_date);
        $publish_date = $publish_date->format('d.m.y');

        if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                $category_label = $item->category->fr_label;
            } elseif ($item->lang === "en") {
                $category_label = $item->category->en_label;
            }
        }
		$html .= '<article class="post">';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="' . $item->news_url . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
        $html .= '  </figure>';
        if ($category_label) {
            $html .= '  <p class="category-label">' . $category_label . ' </p>';
        }
		$html .= '  <div class="entry-content">';
		$html .= '    <header class="entry-header">';
		$html .= '      <h2 class="entry-title">';
		$html .= '        <a href="' . $item->news_url . '">';
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
    $html = '<div class="list-articles list-news list-news-sidebar clearfix">';
	foreach ($news->results as $item) {

        if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                $category_label = $item->category->fr_label;
            } elseif ($item->lang === "en") {
                $category_label = $item->category->en_label;
            }
        }

        $publish_date = new DateTime($item->publish_date);
        $publish_date = $publish_date->format('d.m.y');

		$html .= '<article class="post">';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="' . $item->news_url . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
        $html .= '  </figure>';
        if ($category_label) {
            $html .= '  <p class="category-label">' . $category_label . ' </p>';
        }
		$html .= '  <div class="entry-content">';
		$html .= '    <header class="entry-header">';
		$html .= '      <h2 class="entry-title">';
		$html .= '        <a href="' . $item->news_url . '">';
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
    $html = '<div class="list-articles list-news clearfix">';
	foreach ($news->results as $item) {
        if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                $category_label = $item->category->fr_label;
            } elseif ($item->lang === "en") {
                $category_label = $item->category->en_label;
            }
        }
		$html .= '<article class="post">';
		$html .= '  <header class="entry-header">';
		$html .= '    <h2 class="entry-title">';
		$html .= '      <a href="' . $item->news_url . '">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h2>';
		$html .= '  </header>';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="' . $item->news_url . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
        $html .= '  </figure>';
        if ($category_label) {
            $html .= '  <p class="category-label">' . $category_label . ' </p>';
        }
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
    $html = '<div class="list-articles list-news clearfix">';
	foreach ($news->results as $item) {

        $publish_date = new DateTime($item->publish_date);
        $publish_date = $publish_date->format('d.m.Y');

        if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                $category_label = $item->category->fr_label;
            } elseif ($item->lang === "en") {
                $category_label = $item->category->en_label;
            }
        }

		$html .= '<article class="post">';
		$html .= '  <header class="entry-header">';
		$html .= '    <h2 class="entry-title">';
		$html .= '      <a href="' . $item->news_url . '">';
		$html .= $item->title;
		$html .= '      </a>';
		$html .= '    </h2>';
		$html .= '  </header>';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="' . $item->news_url . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
        $html .= '  </figure>';
        if ($category_label) {
            $html .= '  <p class="category-label">' . $category_label . ' </p>';
        }
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
 * Template portal with 1 news – image on top (template 1)
 *
 * @param $news: response of news API.
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_portal_img_top($news, bool $stickers): string
{
    $html = '<div class="list-articles list-news news-portal news-portal-img-top clearfix">';
	foreach ($news->results as $item) {

        if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                $category_label = $item->category->fr_label;
            } elseif ($item->lang === "en") {
                $category_label = $item->category->en_label;
            }
        }

        $publish_date = new DateTime($item->publish_date);
        $publish_date = $publish_date->format('d.m.y');

		$html .= '<article class="post">';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="' . $item->news_url . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
        $html .= '  </figure>';
        if ($category_label) {
            $html .= '  <p class="category-label">' . $category_label . ' </p>';
        }
		$html .= '  <div class="entry-content">';
		$html .= '    <header class="entry-header">';
		$html .= '      <h2 class="entry-title">';
		$html .= '        <a href="' . $item->news_url . '">';
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
 * Template portal with 1 news – image on the left (template 7)
 *
 * @param $news: response of news API.
 * @param $stickers: display stickers on images ?
 * @return html of template
 */
function epfl_news_template_portal_img_left($news, bool $stickers): string
{
    $html = '<div class="list-articles list-news news-portal clearfix">';
	foreach ($news->results as $item) {

        if ($stickers == TRUE) {
            if ($item->lang === "fr") {
                $category_label = $item->category->fr_label;
            } elseif ($item->lang === "en") {
                $category_label = $item->category->en_label;
            }
        }

        $publish_date = new DateTime($item->publish_date);
        $publish_date = $publish_date->format('d.m.y');

		$html .= '<article class="post">';
		$html .= '  <figure class="post-thumbnail">';
		$html .= '    <a href="' . $item->news_url . '">';
		$html .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'">';
		$html .= '    </a>';
        $html .= '  </figure>';
        if ($category_label) {
            $html .= '  <p class="category-label">' . $category_label . ' </p>';
        }
		$html .= '  <div class="entry-content">';
		$html .= '    <header class="entry-header">';
		$html .= '      <h2 class="entry-title">';
		$html .= '        <a href="' . $item->news_url . '">';
		$html .= $item->title;
		$html .= '        </a>';
		$html .= '      </h2>';
		$html .= '    </header>';
		$html .= '    <div class="entry-meta">';
		$html .= '      <time class="entry-date">' . $publish_date . '</time>';
		$html .= '    </div>';
		$html .= '    <div class="teaser">' . substr($item->subtitle, 0, 240) . '</div>';
		$html .= '  </div>';
		$html .= '  <div class="links">';
		$html .= '    <a href="#" class="link-action btn-icon fa-icon next"><span class="label">Toutes les actualités</span></a>';
		$html .= '    <a href="#" class="link-action btn-icon fa-icon feed"><span class="label">Flux RSS</span></a>';
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
    } elseif ($template === "1") {
        $html = epfl_news_template_portal_img_top($news, $stickers);
    } elseif ($template === "7") {
        $html = epfl_news_template_portal_img_left($news, $stickers);
    } else {
        $html = epfl_news_template_labo_with_3_news($news, $stickers);
    }
    return '<div class="newsBox">' . $html . '</div>';
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
    $channel = NewsUtils::get_items($url);

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
    $channel_response = NewsUtils::get_items($url);
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
                'channel'  => '',
                'lang'     => '',
                'template' => '',
                'stickers' => '',
                'category' => '',
                'themes'   => '',
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

        $actus = NewsUtils::get_items($url);
        return epfl_news_build_html($actus, $template, $stickers);
}

add_action( 'init', function() {

    // define the shortcode
    add_shortcode('epfl_news', 'epfl_news_process_shortcode');

    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

        shortcode_ui_register_for_shortcode(

            'epfl_news',

            array(
                'label' => 'Add News shortcode',
                'listItemImage' => 'dashicons-book',
                'attrs'         => array(
                        array(
                            'label'         => 'Channel ID',
                            'attr'          => 'channel',
                            'type'          => 'text',
                            'description'   => "The channel id",
                        ),
                        array(
                            'label'         => 'Template ID',
                            'attr'          => 'template',
                            'type'          => 'text',
                            'description'   => "The template id",
                        ),
                        array(
                            'label'         => 'Language',
                            'attr'          => 'lang',
                            'type'          => 'select',
                            'options'       => array(
                                                    array('value' => 'en', 'label' => esc_html__('English', 'epfl_news_shortcode', 'epfl_news_shortcode')),
                                                    array('value' => 'fr', 'label' => esc_html__('Français', 'epfl_news_shortcode', 'epfl_news_shortcode')),
                            ),
                            'description'   => 'The language used to render news results',
                        ),
                    ),

                'post_type'     => array( 'post', 'page' ),
            )
        );

    endif;

} );


?>