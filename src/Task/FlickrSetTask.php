<?php

namespace Suilven\Flickr\Task;

use SilverStripe\Dev\BuildTask;
use Suilven\Flickr\Model\FlickrSet;
use Suilven\Flickr\Service\FlickrService;

/**
 * Defines and refreshes the elastic search index.
 */
class FlickrSetTask extends BuildTask
{

    protected $title = 'Flickr Set Importer';

    protected $description = 'Import a Flickr Set';

    private static $segment = 'flickrset';

    protected $enabled = true;


    public function run($request)
    {

        $id = $_GET['id'];
        $service = new FlickrService();
        $set = $service->importSet($id);

        $photos = $set->FlickrPhotos();

        error_log('Photo');
        foreach($photos as $photo) {
            error_log('  photo_' . $photo->ID . ':');
            error_log('    Title: ' . $photo->Title);
            error_log('    Description: ' . $photo->Description);
            error_log('    Latitude: ' . $photo->Lat);
            error_log('    Longitude: ' . $photo->Lon);
            error_log('    Aperture: ' . $photo->Aperture);
            error_log('    TakenAt: ' . $photo->TakenAt);
            error_log('    FlickrPlaceID: ' . $photo->FlickrPlaceID);
            error_log('    ShutterSpeed: ' . $photo->ShutterSpeed);
            error_log('    FocalLength35mm: ' . $photo->FocalLength35mm);
            error_log('    ISO: ' . $photo->ISO);
        }

    }
}
