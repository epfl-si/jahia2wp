<?php

Class InfoscienceSearchUtils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }

    public static function convert_keys($array_to_convert, $map) {
        $converted_array = array();

        foreach ($array_to_convert as $key => $value) {
            if (array_key_exists($key, $map)) {
                $converted_array[$map[$key]] = $value;
            } else {
                $converted_array[$key] = $value;
            }
        }
        return $converted_array;
    }
}
?>
