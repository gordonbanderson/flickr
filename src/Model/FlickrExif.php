<?php

namespace Suilven\Flickr\Model;

use SilverStripe\ORM\DataObject;

class FlickrExif extends DataObject
{

    private static $db = array(
        'TagSpace' => 'Varchar',
        'Tag' => 'Varchar',
        'Label' => 'Varchar',
        'Raw' => 'Varchar',
        'TagSpaceID' => 'Int'
    );

    private static $table_name = 'FlickrExif';

    private static $belongs_many_many = array(
        'FlickrPhotos' => 'Suilven\Flickr\Model\FlickrPhoto'
     );

    private static $has_one = array(
        'FlickrPhoto' => 'Suilven\Flickr\Model\FlickrPhoto'
    );
}
