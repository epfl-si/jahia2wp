<?php

Class Render
{

    /**
    * Template text only (template 2)
    *
    * @param $news: response of news API.
    * @return html of template
    */
    public static function epfl_news_template_text_only($news): string
    {
        $html = '<div class="list-articles list-news list-news-textonly clearfix">';
        foreach ($news->results as $item) {

            $publish_date = new DateTime($item->publish_date);
            $publish_date = $publish_date->format('d.m.y');

            $html .= '<article class="post">';
            $html .= '  <header class="entry-header">';
            $html .= '    <h2 class="entry-title">';
            $html .= '      <a href="' . esc_attr($item->news_url) . '">';
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
    public static function epfl_news_template_fac_with_4_news($news, bool $stickers): string
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
            $html .= '    <a href="' . esc_attr($item->news_url) . '">';
            $html .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '">';
            $html .= '    </a>';
            $html .= '  </figure>';

            if ($category_label) {
                $html .= '  <p class="category-label">' . $category_label . ' </p>';
            }

            $html .= '  <div class="entry-content">';
            $html .= '    <header class="entry-header">';
            $html .= '      <h2 class="entry-title">';
            $html .= '        <a href="' . esc_attr($item->news_url) . '">';
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
    public static function epfl_news_template_fac_with_3_news($news, bool $stickers): string
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
            $html .= '    <a href="' . esc_attr($item->news_url) . '">';
            $html .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '">';
            $html .= '    </a>';
            $html .= '  </figure>';
            if ($category_label) {
                $html .= '  <p class="category-label">' . $category_label . ' </p>';
            }
            $html .= '  <div class="entry-content">';
            $html .= '    <header class="entry-header">';
            $html .= '      <h2 class="entry-title">';
            $html .= '        <a href="' . esc_attr($item->news_url) . '">';
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
    public static function epfl_news_template_labo_with_5_news($news, bool $stickers): string
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
            $html .= '      <a href="' . esc_attr($item->news_url) . '">';
            $html .= $item->title;
            $html .= '      </a>';
            $html .= '    </h2>';
            $html .= '  </header>';
            $html .= '  <figure class="post-thumbnail">';
            $html .= '    <a href="' . esc_attr($item->news_url) . '">';
            $html .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '">';
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
    public static function epfl_news_template_labo_with_3_news($news, bool $stickers): string
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
            $html .= '      <a href="' . esc_attr($item->news_url) . '">';
            $html .= $item->title;
            $html .= '      </a>';
            $html .= '    </h2>';
            $html .= '  </header>';
            $html .= '  <figure class="post-thumbnail">';
            $html .= '    <a href="' . esc_attr($item->news_url) . '">';
            $html .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '">';
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
    public static function epfl_news_template_portal_img_top($news, bool $stickers): string
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
            $html .= '    <a href="' . esc_attr($item->news_url) . '">';
            $html .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '">';
            $html .= '    </a>';
            $html .= '  </figure>';

            if ($category_label) {
                $html .= '  <p class="category-label">' . $category_label . ' </p>';
            }

            $html .= '  <div class="entry-content">';
            $html .= '    <header class="entry-header">';
            $html .= '      <h2 class="entry-title">';
            $html .= '        <a href="' . esc_attr($item->news_url) . '">';
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
    public static function epfl_news_template_portal_img_left($news, bool $stickers): string
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
            $html .= '    <a href="' . esc_attr($item->news_url) . '">';
            $html .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '">';
            $html .= '    </a>';
            $html .= '  </figure>';

            if ($category_label) {
                $html .= '  <p class="category-label">' . $category_label . ' </p>';
            }

            $html .= '  <div class="entry-content">';
            $html .= '    <header class="entry-header">';
            $html .= '      <h2 class="entry-title">';
            $html .= '        <a href="' . esc_attr($item->news_url) . '">';
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
        $result .= 'src="' . esc_attr($url) . '" ';
        $result .= 'width="700" height="1100" scrolling="no" frameborder="0"></IFRAME>';
        return $result;
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
            $html = Render::epfl_news_template_labo_with_3_news($news, $stickers);
        } elseif ($template === "8") {
            $html = Render::epfl_news_template_labo_with_5_news($news, $stickers);
        } elseif ($template === "3") {
            $html = Render::epfl_news_template_fac_with_4_news($news, $stickers);
        } elseif ($template === "6") {
            $html = Render::epfl_news_template_fac_with_3_news($news, $stickers);
        } elseif ($template === "2") {
            $html = Render::epfl_news_template_text_only($news);
        } elseif ($template === "1") {
            $html = Render::epfl_news_template_portal_img_top($news, $stickers);
        } elseif ($template === "7") {
            $html = Render::epfl_news_template_portal_img_left($news, $stickers);
        } else {
            $html = Render::epfl_news_template_labo_with_3_news($news, $stickers);
        }
        return '<div class="newsBox">' . $html . '</div>';
    }
    
}

?>