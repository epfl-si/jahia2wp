<?php

Class InfoscienceRender
{

    public static function pp($arr){
        $retStr = '<ul>';
        if (is_array($arr)){
            foreach ($arr as $key=>$val){
                if (is_array($val)){
                    $retStr .= '<li>' . $key . ' => ' . InfoscienceRender::pp($val) . '</li>';
                }else{
                    $retStr .= '<li>' . $key . ' => ' . $val . '</li>';
                }
            }
        }
        $retStr .= '</ul>';
        return $retStr;
    }

    /**
     * Build HTML.
     *
     * @param $publications: array of data converted from Infoscience
     * @return
     */
    public static function epfl_infoscience_search_build_html($publications) {
        return InfoscienceRender::pp($publications);
    }
}
?>