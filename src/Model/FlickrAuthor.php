<?php
namespace Suilven\Flickr\Model;

use SilverStripe\ORM\DataObject;

class FlickrAuthor extends DataObject
{
    private static $db = array(
        'PathAlias' => 'Varchar(255)',
        'Username' => 'Varchar(255)',
        'RealName' => 'Varchar(255)',
        'Location' => 'Varchar(255)',
        'NSID' => 'Varchar(255)'
    );

    private static $has_many = array('FlickrPhotos' => 'Suilven\Flickr\Model\FlickrPhoto');

    private static $table_name = 'FlickrAuthor';

    private static $summary_fields = array(
        'PathAlias' => 'URL',
        'DisplayName' => 'Display Name'
    );

    /**
         * A search is made of the path alias during flickr set import
         */
    private static $indexes = array(
        'PathAlias' => true,
        'RealName' => true,
        'Username' => true
    );
}
