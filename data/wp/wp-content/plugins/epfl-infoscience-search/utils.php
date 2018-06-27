<?php

Class InfoscienceSearchUtils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }

    /**
     * Groups an array by a given key.
     *
     * Groups an array into arrays by a given key, or set of keys, shared between all array members.
     *
     * Based on {@author Jake Zatecky}'s {@link https://github.com/jakezatecky/array_group_by array_group_by()} function.
     * This variant allows $key to be closures.
     * Infoscience variant : sanitize the $key before setting it
     *
     * @param array $array   The array to have grouping performed on.
     * @param mixed $key,... The key to group or split by. Can be a _string_,
     *                       an _integer_, a _float_, or a _callable_.
     *
     *                       If the key is a callback, it must return
     *                       a valid key from the array.
     *
     *                       If the key is _NULL_, the iterated element is skipped.
     *
     *                       ```
     *                       string|int callback ( mixed $item )
     *                       ```
     *
     * @return array|null Returns a multidimensional array or `null` if `$key` is invalid.
     */
    public static function array_group_by(array $array, $key)
    {
        $compute_key = function($key) {
            $compute_year = function($value) {
                $d = DateTime::createFromFormat("Y-m-d", $value);
                # is this a full date ?
                if ($d) {
                    return $d->format("Y");
                } else {  
                    # a year only ?
                    $d = DateTime::createFromFormat("Y", $value);
                    if ($d) {
                        return date_format($d, "Y");
                    # no idea what it is, make it key valid and return
                    } else {
                        return;
                    }
                }
            };
        
            $compute_doctype = function($value) {
                return $value;
            };
        
            # check if string is a date, and keep only the year if this is the case
            $year_as_key = $compute_year($key);
            if ($year_as_key) {
                return $year_as_key;
            } else {
                return $key;

                # maybe we are in a doctype
                $doctype_as_key = $compute_doctype($key);
                if ($doctype_as_key) {
                    return $doctype_as_key;
                } else {
                    # no idea, skip
                    return false;
                }
            }
        };

        $_key = $key;

        // Load the new array, splitting by the target key
        $grouped = [];
        foreach ($array as $value) {
            $key = null;

            if (isset($value[$_key])) {
                $label = $compute_key($value[$_key][0]);
    
                # no label ? skip this
                if ($label === null) {
                    continue;
                }

                $grouped['label']= $label;

                if (!isset($grouped['values'])) {
                    $grouped['values'] = [];
                }

                $grouped['values'][] = $value;
            }
        }

        // Recursively build a nested grouping if more parameters are supplied
        // Each grouped array value is grouped according to the next sequential key
        if (func_num_args() > 2) {
            $args = func_get_args();

            foreach ($grouped as $key => $value) {
                $params = array_merge([ $value ], array_slice($args, 2, func_num_args()));
                $grouped[$key] = call_user_func_array('array_group_by', $params);
            }
        }

        return $grouped;
    }
}
?>
