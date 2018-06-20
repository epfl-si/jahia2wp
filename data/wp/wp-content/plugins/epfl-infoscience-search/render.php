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
    public static function render($publications, $format="short", $has_summary=false) {
        $rendered = '';
        foreach ($publications as $publication) {
            $templated_publication = "<br/><br/>";
            # doctype and render type determine template
            $doctype =  strtolower(str_replace(' ', '_', $publication['doctype'][0]));

            $template_base_path = plugin_dir_path(__FILE__). 'templates/';
            $template_path = $template_base_path . $doctype . '_' . $format . '.php';

            if (file_exists($template_path)) {
                ob_start();
                include($template_path);
                $templated_publication .= ob_get_clean();
    
                $rendered .= $templated_publication;

                if ($has_summary){
                    $rendered .= " with summary please";
                } else {
                    $rendered .= " without summary please";
                }
            } else {
                $rendered .= $templated_publication;
                $rendered .= "Untemplated doctype error - " . $template_path;
            }
        }
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