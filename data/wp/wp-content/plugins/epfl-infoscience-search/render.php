<?php

require_once 'renderers/publications.php';

/*
 * Generic Render
 * $params : $publications is a two level array, in a fixed format. If label is empty, don't show any header
 *                      format :
                            $publications['group_by'] = [
                                        ['label' => null, # or string
                                        'values' => [
                                            'label' => null, # or string
                                            'values' => $array_of_publications,
                                        ],
                                        ];
 */
Class InfoscienceRender {
    protected static function render_url($url) {
        return '<div style="border:2px solid black;padding:8px;word-wrap: break-word;">' . $url . '</div>' ;
     }
    
    protected static function pre_render() {
       return '<div class="infoscience_export">';
    }

    public static function render($publications, $url='', $format="short", $summary=false, $thumbnail=false, $debug=false) {
        $html_rendered = self::pre_render();
        
        $html_rendered .= "";

        $html_rendered .= self::post_render();

        return $html_rendered;
    }

    protected static function post_render() {
        return '</div>';
    }
}

Class ClassesInfoscienceRender extends InfoscienceRender {
    protected static function render_header_1($value) {
        return '<h1 class="infoscience_header1">'. $value . '</h1>';
    }

    protected static function render_header_2($value) {
        return '<h2 class="infoscience_header2">'. $value . '</h2>';
    }

    public static function render($publications, $url='', $format="short", $summary=false, $thumbnail=false, $debug=false) {
        $html_rendered = "";
        if ($debug) {
            $html_rendered .= self::render_url($url);
        }

        $html_rendered .= self::pre_render();

        foreach($publications['group_by'] as $grouped_by_publications) {
            if ($grouped_by_publications['label']) {
                $html_rendered .= self::render_header_1($grouped_by_publications['label']);
            }

            foreach($grouped_by_publications['values'] as $grouped_by2_publications) {
                if ($grouped_by2_publications['label'] && !$grouped_by_publications['label']) {
                    $html_rendered .= self::render_header_1($grouped_by2_publications['label']);
                } else {
                    $html_rendered .= self::render_header_2($grouped_by2_publications['label']);
                }
                
                foreach($grouped_by2_publications['values'] as $index3 => $publication) {
                    $record_renderer_class = get_render_class_for_publication($publication, $format);

                    if ($debug) {
                        $html_rendered .= '<h3>'. $record_renderer_class .'</h3>';
                    }

                    $html_rendered .= $record_renderer_class::render($publication, $summary, $thumbnail);
                }
            }
        }

        $html_rendered .= self::post_render();

        return $html_rendered;
    }
}

Class RawInfoscienceRender extends InfoscienceRender {
    public static function pretty_print($arr){
        $retStr = '<ul>';
        if (is_array($arr)){
            foreach ($arr as $key=>$val){
                if (is_array($val)){
                    $retStr .= '<li>' . $key . ' => ' . RawInfoscienceRender::pretty_print($val) . '</li>';
                }else{
                    $retStr .= '<li>' . $key . ' => ' . $val . '</li>';
                }
            }
        }
        $retStr .= '</ul>';
        return $retStr;
    }

    /**
     * Render for debugging
     *
     * @param $publications: array of data converted from Infoscience
     * @return
     */
    public static function render($publications, $url='', $format="short", $summary=false, $thumbnail=false, $debug=false) {
        return self::render_url($url) . RawInfoscienceRender::pretty_print($publications);
    }    
}
?>  