<?php

namespace Suilven\Flickr\Model;

class FlickrExif extends DataObject
{

    static $db = array(
        'TagSpace' => 'Varchar',
        'Tag' => 'Varchar',
        'Label' => 'Varchar',
        'Raw' => 'Varchar',
        'TagSpaceID' => 'Int'
    );

    static $belongs_many_many = array(
        'FlickrPhotos' => 'FlickrPhoto'
     );

    static $has_one = array(
        'FlickrPhoto' => 'FlickrPhoto'
    );
}
