<?php

/*
 Group by operations
*/
function sort_group_by_year_desc($a, $b) {
    return strcmp($b["label"], $a["label"]);
}

function sort_group_by_year_asc($a, $b) {
    return strcmp($a["label"], $b["label"]);
}

function sort_group_by_doctype_asc($a, $b) {
    $pos_a = array_search($a['label'], array_keys(InfoscienceGroupBy::doctypes()));
    $pos_b = array_search($b['label'], array_keys(InfoscienceGroupBy::doctypes()));

    return ($pos_a < $pos_b) ? 1 : -1;
}

function sort_group_by_doctype_desc($a, $b) {
    $pos_a = array_search($a['label'], array_keys(InfoscienceGroupBy::doctypes()));
    $pos_b = array_search($b['label'], array_keys(InfoscienceGroupBy::doctypes()));

    return ($pos_b < $pos_a) ? 1 : -1;
}

Class InfoscienceGroupBy {
    private static $doctypes = null;
    
    public static function doctypes() {
        if (self::$doctypes == null) {
            self::$doctypes = [
                'Journal Articles' => __("Journal Articles"),
                'Conference Papers' => __("Conference Papers"),
                'Reviews' => __("Reviews"),
                'Books' => __("Books"),
                'Theses' => __("Theses"),
                'Book Chapters' => __("Book Chapters"),
                'Conference Proceedings' => __("Conference Proceedings"),
                'Working Papers' => __("Working Papers"),
                'Reports' => __("Reports"),
                'Posters' => __("Posters"),
                'Talks' => __("Talks"),
                'Standards' => __("Standards"),
                'Patents' => __("Patents"),
                'Student Projects' => __("Student Projects"),
                'Teaching Resources' => __("Teaching Resources"),
                'Media' => __("Media"),
                'Datasets' => __("Datasets"),
            ];
         }
         return self::$doctypes;
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

            if (isset($value[$_key]) && count($value[$_key]) > 0) {
                $group_data = [];
                $label = $compute_key($value[$_key][0]);
    
                # no label ? skip this
                if ($label === null) {
                    continue;
                }

                # find if the group exist already
                $index_of_current = array_search($label, array_column($grouped, 'label'));

                if ($index_of_current !== false) {
                    $grouped[$index_of_current]['label']= $label;
                    $grouped[$index_of_current]['values'][] =  $value;
                } else {
                    $group_data['label']= $label;
                    $group_data['values'][] = $value;
                    $grouped[] = $group_data;
                }
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
    
    public static function sanitize_group_by($group_by_value) {
        if ($group_by_value) {
            $group_by_value = in_array(strtolower($group_by_value), ['year', 'doctype']) ? strtolower($group_by_value) : null;
            return $group_by_value;
        } else {
            return;
        }
    }

    public static function do_group_by($publications, $group_by=null, $group_by2=null, $sort_order='desc') {
        $grouped_publications = [];
        if ($group_by === 'year') {
            $grouped_publications['group_by'] = InfoscienceGroupBy::array_group_by($publications, 'publication_date');
            #var_dump($grouped_publications);
            #TODO: second level group_by

            if ($sort_order === 'asc') {
                usort($grouped_publications['group_by'], 'sort_group_by_year_asc');
            } else {
                usort($grouped_publications['group_by'], 'sort_group_by_year_desc');
            }
        } elseif (($group_by === 'doctype')) {
            $grouped_publications['group_by'] = InfoscienceGroupBy::array_group_by($publications, 'doctype');
            #var_dump($grouped_publications);
            if ($sort_order === 'asc') {
                usort($grouped_publications['group_by'], 'sort_group_by_doctype_asc');
            } else {
                usort($grouped_publications['group_by'], 'sort_group_by_doctype_desc');
            }

        } else {
            # no group, set the same array level if so, without any label
            $grouped_publications['group_by'] = [
                'title' => null,
                'values' => $publications,
            ];
        }
        
        return $grouped_publications;
    }
}
?>