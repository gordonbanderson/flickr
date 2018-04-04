<?php

namespace Suilven\Flickr\Task;

use SilverStripe\Dev\BuildTask;
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
        $service->importSet($id);
    }
}
