<?php
namespace Suilven\Flickr\Admin;

use SilverStripe\Admin\ModelAdmin;
use Suilven\Flickr\Model\FlickrSet;

class FlickrSetAdmin extends ModelAdmin {

	private static $managed_models = array(   //since 2.3.2
		FlickrSet::class
	);

	private static $url_segment = 'flickr_sets'; // will be linked as /admin/products
    private static $menu_title = 'Flickr Sets';

    private static $menu_icon = '/flickr/icons/album.png';


}
