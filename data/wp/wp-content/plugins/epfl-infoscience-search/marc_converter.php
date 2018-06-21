<?php

require_once 'File/MARCXML.php';

Class InfoscienceMarcConverter
{

    /**
    * Parse all entries and drop them 'as is'
    */
    public static function parse_all($record, $field) {
        $list_fields = [];

        foreach ($record->getFields($field) as $tag => $subfields) {
            if (method_exists($subfields, 'getSubfields')) {
                foreach ($subfields->getSubfields() as $code => $value) {
                    $list_fields[$tag] = $value;
                }
            }
        }

        return $list_fields;
    }

    /**
    * Parse all urls and dispatch them into
    * urls => icon, fulltext
    */
    public static function parse_files($record, $field) {
        $file_urls  = InfoscienceMarcConverter::parse_text($record, '856', '4', '', ['u']);

        $sorted_urls = [];

        foreach($file_urls as $url){
            if (preg_match('/\.pdf$/', strtolower($url))) {
                $sorted_urls['fulltext'][] = $url;
            } else {
                $matches = [];
                preg_match('/(\.png|\.jpg|\.jpeg|\.gif)$/', $url, $matches);
                if ($matches) {
                    $sorted_urls['icon'][] = $url;
                }
            }
        }
        return $sorted_urls;
    }

    /**
    * Parse external ids and filter to get only DOIs
    */
    public static function parse_doi($record) {
        $dois = [];
        $extern_type  = InfoscienceMarcConverter::parse_text($record, '024', '7', '', ['2'])[0];

        if (strtolower($extern_type) === 'doi') {
            $id  = InfoscienceMarcConverter::parse_text($record, '024', '7', '', ['a'])[0];
            $dois[] = $id;
        }

        return $dois;
    }
    
    /**
    * Parse a specified entry. Provide multiple subfields with name to have a key value return
    */
    public static function parse_text($record, $field, $ind1='', $ind2='', $subfields=[''], $subfields_name = []) {
         if (!$subfields[0]){
            return [$record->getField($field)->getData()];
         }
        
         $fields = $record->getFields($field);
         $value = [];
         $sub_values_mode = false;

         if (count($subfields) > 1) {
            $sub_values_mode = true;
         }

        foreach ($fields as $field) {
            $sub_value = [];
            foreach($subfields as $index=>$subfield) {
                if ($subfield && subfield !== '') {
                    if ($field->getSubfield($subfield)) {
                        if ($subfields_name && array_key_exists($index, $subfields_name)) {
                            $sub_value[$subfields_name[$index]] = $field->getSubfield($subfield)->getData();
                        } else {
                            $sub_value[] = $field->getSubfield($subfield)->getData();
                        }
                    }
                }
            }
            if ($sub_value) {
                if ($sub_values_mode) {
                    $value[] = $sub_value;
                } else {
                    $value = $sub_value;
                }
            }
        }
        return $value; 
    }
    
    public static function parse_authors($record, $field, $ind1, $ind2, $subfields) {
        $compute_name = function ($full_name) {
            $names = explode(',', $full_name);
            $family = count(names) > 0 ? trim($names[0]) : '';
            $fnames = count($names) > 1 ? explode(' ', $names[1]) : '';

            $initname = "";

            foreach($fnames as $fname) {
                if (!$fname || empty($fname)) {
                    continue;
                }
                

                $fname = trim($fname);
                
                if (strpos($fname, '-') !== false) {
                    $sname = explode('-', $fname);

                    if (count($sname[0]) > 1) {
                        $initname .= $sname[0][0];
                    }

                    if (count($sname[0]) > 1 || count($sname[1]) > 1) {
                        $initname .= "-";
                    } 
                    
                    if (count($sname[1]) > 1) {
                        $initname .= $sname[1][0] . ". ";
                    }
                }
                else {
                    $fname = trim($fname);
                    if (count($fname) > 0) {
                        $initname .= trim($fname[0]) . ". ";
                    }
                }
            }

            if ($family && !empty($family)) {
                $initname .= $family;
            }

            return $initname;

        };


        $authors = [];
        $people = $record->getFields($field);
        $subfield = $subfields[0];

        if ($people) {
            foreach ($people as $person) {
                if (!$person->isEmpty()) {
                    # if we have an indicator, verify that the person in the right one
                    if ($ind1) {
                        $indicator = $person->getIndicator(1);

                        if ($indicator == $ind1) {
                            $authors[] = $compute_name($person->getSubfield($subfield)->getData());
                        }
                    } elseif ($ind2) {
                        $indicator = $person->getIndicator(2);

                        if ($indicator == $ind2) {
                            $authors[] = $compute_name($person->getSubfield($subfield)->getData());
                        }                        
                    } else {
                        $authors[] = $compute_name($person->getSubfield($subfield)->getData());
                    }
                }
            }
        }
        
        return $authors;
    }

    /**
     * Transform Marc record to a flat key value array
     */
    public static function parse_record($record, $filter_empty=false) {
        $record_array = [];

        $record_array['record_id'] = InfoscienceMarcConverter::parse_text($record, '001', '', '', ['']);
        
        # SPEC: how we show it ?
        $record_array['patent'] = InfoscienceMarcConverter::parse_text($record, '013', '', '',['a', 'c'], ['number', 'state']);

        $record_array['isbn'] = InfoscienceMarcConverter::parse_text($record, '020', '', '', ['a']);
        
        # SPEC: don't get doi if patents, as 0247_a has the TTO id too
        $record_array['doi'] = InfoscienceMarcConverter::parse_doi($record);

        $record_array['title'] = InfoscienceMarcConverter::parse_text($record, '245', '', '', ['a']);
        
        $record_array['publication_location'] = InfoscienceMarcConverter::parse_text($record, '260', '', '', ['a']);
        $record_array['publication_institution'] = InfoscienceMarcConverter::parse_text($record, '260', '', '', ['b']);
        $record_array['publication_year'] = InfoscienceMarcConverter::parse_text($record, '269', '', '', ['a']);
        $record_array['publication_page'] = InfoscienceMarcConverter::parse_text($record, '300', '', '', ['a']);

        /* if needed, uncomment this generic datas
        $record_array['description'] = InfoscienceMarcConverter::parse_all($record, '300');
        */
        
        /* if needed, uncomment this generic datas
        $record_array['subjects'] = InfoscienceMarcConverter::parse_all($record, ['600', '610', '611', '630', '648', '650',
            '651', '653', '654', '655', '656', '657', '658', '662', '690',
            '691', '696', '697', '698', '699']);
        */

        $record_array['doctype'] = InfoscienceMarcConverter::parse_text($record, '336', '', '', ['a']);
        
        $record_array['summary'] = InfoscienceMarcConverter::parse_text($record, '520', '', '', ['a']);
        
        $record_array['author'] = InfoscienceMarcConverter::parse_authors($record, '700', '', '', ['a']);

        $record_array['corporate_name'] = InfoscienceMarcConverter::parse_text($record, '710', '', '', ['a']);
        
        $record_array['conference'] = InfoscienceMarcConverter::parse_text($record, '711', '', '', ['a', 'c', 'd'], ['name', 'location', 'date']);
        if (empty($record_array['conference'])) {
            $record_array['conference'] = InfoscienceMarcConverter::parse_text($record, '711', '2', '', ['a', 'c', 'd'], ['name', 'location', 'date']);
        }

        $record_array['author_1'] = InfoscienceMarcConverter::parse_authors($record, '720', '', '1', ['a']);
        $record_array['director'] = InfoscienceMarcConverter::parse_authors($record, '720', '', '2', ['a']);
        $record_array['author_3'] = InfoscienceMarcConverter::parse_authors($record, '720', '', '3', ['a']);

        $record_array['company_name'] = InfoscienceMarcConverter::parse_text($record, '720', '', '5', ['a']);

        $record_array['journal'] = InfoscienceMarcConverter::parse_text($record, '773', '', '5', ['j', 'k', 'q', 't'], ['volume', 'number', 'page', 'publisher']);

        $record_array['report_url'] = InfoscienceMarcConverter::parse_text($record, '790', '', '', ['w']);

        # TODO: url has special rules, set url if fulltexts / icons to print
        $record_array['url'] = InfoscienceMarcConverter::parse_files($record, '856', '4', '', ['u']);

        $record_array['approved'] = InfoscienceMarcConverter::parse_text($record, '909', 'C', '0', ['p']);
        $record_array['pending'] = InfoscienceMarcConverter::parse_text($record, '999', 'C', '0', ['p']);

        if ($filter_empty) {
            $record_array = array_filter($record_array);
        }

        return $record_array;
    }

    /**
     * Transform Marc from Infoscience into an multiple dimension array
     *
     * @param $marc_xml: response of infoscience search in of=xm format
     * @return the built array
     */
    public static function convert_marc_to_array($marc_xml) {
        $publications = [];
        
        $marc_source = new File_MARCXML($marc_xml, File_MARC::SOURCE_STRING);

        while ($marc_record = $marc_source->next()) {
            array_push($publications, InfoscienceMarcConverter::parse_record($marc_record, false));
        }
        return $publications;
    }
}
?>