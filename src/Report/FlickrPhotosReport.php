<?php
namespace Suilven\Flickr\Report;


use SilverStripe\Reports\Report;
use Suilven\Flickr\Model\FlickrPhoto;

class CustomSideReport_FlickrPhotosReport extends Report
{
    // the name of the report
    public function title()
    {
        return 'Flickr Photos';
    }

    // what we want the report to return
    public function sourceRecords($params = null)
    {
        return FlickrPhoto::get()->sort('Title');
    }

    // which fields on that object we want to show
    public function columns()
    {
        error_log('SOURCE T1.....' . print_r($this->source, 1));
        $fields = [
            'Title' => 'Title',
            'Description' => 'Description',
            'ISO' => 'ISO',
            'Aperture' => 'Aperture',
            'getThumbnailImage' => array(
                'title' => 'Thumbnail',
                'formatting' => '<img src=\"$ThumbnailURL}\"</img>'
            )
        ];

        return $fields;
    }

}
