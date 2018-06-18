<?php

require_once 'File/MARCXML.php';

Class InfoscienceMarcConverter
{
    /**
     * Transform Marc record to a flat key value array
     */
    public static function parse_authors($record) {
        $authors = [];
        $people = $record->getFields('700');

        if ($people) {
            foreach ($people as $person) {
                if (!$person->isEmpty()) {
                    $authors[] = $person->getSubfield('a')->getData();
                }
            }
        }

        return $authors;
    }

    public static function parse_record($record) {

        $record_array = [];
        $record_array['authors'] = InfoscienceMarcConverter::parse_authors($record);
        

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