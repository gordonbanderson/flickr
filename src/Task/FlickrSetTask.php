<?php

namespace Suilven\Flickr\Task;

use SilverStripe\Blog\Model\Blog;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Permission;
use Suilven\Flickr\Service\FlickrService;
use Suilven\RealWorldPopulator\Gutenberg\Controller\GutenbergBookExtractBlogPost;

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
