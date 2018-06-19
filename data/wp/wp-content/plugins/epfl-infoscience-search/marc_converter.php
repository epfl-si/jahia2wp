<?php

require_once 'File/MARCXML.php';

Class InfoscienceMarcConverter
{
    public static function parse_text($record, $field, $ind1, $ind2, $subfield) {
        # TODO: manage subfield & indicators
        $value = $record->getField($field)->getData();
        return $value; 
    }

    public static function parse_authors($record, $field, $ind1, $ind2, $subfield) {
        $authors = [];
        $people = $record->getFields($field);

        if ($people) {
            foreach ($people as $person) {
                if (!$person->isEmpty()) {
                    # if we have an indicator, verify that the person in the right one
                    if ($ind1) {
                        $indicator = $person->getIndicator(1);

                        if ($indicator == $ind1) {
                            $authors[] = $person->getSubfield($subfield)->getData();
                        }
                    } elseif ($ind2) {
                        $indicator = $person->getIndicator(2);

                        if ($indicator == $ind2) {
                            $authors[] = $person->getSubfield($subfield)->getData();
                        }                        
                    } else {
                        $authors[] = $person->getSubfield($subfield)->getData();
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

        $record_array['control_number'] = InfoscienceMarcConverter::parse_text($record, '001', '', '', '');

        #$record_array['patent_control_information'] = $record->getField('013');#->getSubfield('a')->getData();
        #InfoscienceMarcConverter::parse_text($record, '013', '', '', 'a');

        $record_array['authors'] = InfoscienceMarcConverter::parse_authors($record, '700', '', '', 'a');
        $record_array['authors_1'] = InfoscienceMarcConverter::parse_authors($record, '720', '', '1', 'a');
        $record_array['directors'] = InfoscienceMarcConverter::parse_authors($record, '720', '', '2', 'a');
        $record_array['authors_3'] = InfoscienceMarcConverter::parse_authors($record, '720', '', '3', 'a');

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
            array_push($publications, InfoscienceMarcConverter::parse_record($marc_record));
        }
        return $publications;
    }
}
?>