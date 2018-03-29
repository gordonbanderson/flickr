<?php
namespace Suilven\Flickr\Model;
use SilverStripe\ORM\DataObject;

class FlickrTag extends DataObject
{
    private static $table_name = 'FlickrTag';

    private static $db = array(
        'Value' => 'Varchar(255)',
        'FlickrID' => 'Varchar(255)',
        'RawValue' => 'HTMLText'
    );

    private static $display_fields = array(
        'RawValue'
    );


    private static $searchable_fields = array(
        'RawValue'
    );

    private static $summary_fields = array(
        'Value',
        'RawValue',
        'FlickrID'
    );

    private static $belongs_many_many = array(
        'FlickrPhotos' => 'Suilven\Flickr\Model\FlickrPhoto'
    );


    public function NormaliseCount($c)
    {
        return log(doubleval($c), 2);
    }


    // this is required so the grid field autocompleter returns readable entries after searching
    function Title()
    {
        return $this->RawValue;
    }


    /*
    Static helper
    */
    public static function CreateOrFindTags($csv)
    {
        $result = new ArrayList();

        if (trim($csv) == '') {
            return $result; // ie empty array
        }

        $tags = explode(',', $csv);
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (!$tagName) {
                continue;
            }
            $ftag = DataList::create('FlickrTag')->where("Value='".strtolower($tagName)."'")->first();
            if (!$ftag) {
                $ftag = FlickrTag::create();
                $ftag->RawValue = $tagName;
                $ftag->Value  = strtolower($tagName);
                $ftag->write();
            }

            $result->add($ftag);
        }

        return $result;
    }
}
