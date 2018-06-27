<?php

Class InfoscienceRender {
    public static function render($publications){
    }
}

Class HtmlInfoscienceRender extends InfoscienceRender {
    /**
     * Build HTML
     *
     * @param $publications: array of data converted from Infoscience
     * @return
     */
    public static function render($publications, $format="short", $summary=false, $thumbnail=false, $debug=false) {
        $rendered = '<div class="infoscience_export">';
        $template_base_path = plugin_dir_path(__FILE__). 'templates/';
        $links_path = $template_base_path . 'common/' . 'links-bar.php';

        foreach ($publications['group_by'] as $group_by) {
            $group_by_starter = "";
            if ($group_by['label']) {
                ob_start();
                echo '<h1 class="infoscience_header1">'. $group_by['label'] . '</h1>';
                $group_by_starter = ob_get_clean();
                $rendered .= $group_by_starter;
            }

            foreach ($group_by['values'] as $publication) {
                $templated_publication = "";
                # doctype and render type determine template
                $doctype =  strtolower(str_replace(' ', '_', $publication['doctype'][0]));
                $template_path = $template_base_path . $doctype . '_' . $format . '.php';

                if (!$doctype || !file_exists($template_path)) {
                    # show defautl template
                    $template_path = $template_base_path . 'default_' . $format . '.php';;
                }

                ob_start();
                
                if ($debug) {
                    echo 'doctype : ' . $doctype . '<br>';
                    echo 'template path : ' . $template_path;
                }
                
                echo '<div class="infoscience_record">';
                echo '  <div class="infoscience_data">';
                echo '      <div class="record-content">';
                include($template_path);
                #add summary
                if ($summary) {
                echo '          <p class="infoscience_abstract">' . $publication['summary'][0] . '</p>';
                }
                echo '      </div>';
                include($links_path);
                echo '  </div>';
                echo '</div>';


                # TODO: sanitize this ?
                $templated_publication = ob_get_clean();
                $rendered .= $templated_publication;
            }
        }
        $rendered .= '</div>';
        return $rendered;
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
    public static function render($publications, $url='') {
        return '<div style="border:2px solid black;padding:8px;">' . $url . '</div>' . RawInfoscienceRender::pretty_print($publications);
    }    
}
?>  