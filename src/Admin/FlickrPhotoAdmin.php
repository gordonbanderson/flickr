<?php

namespace Suilven\Flickr\Admin;


use SilverStripe\Admin\ModelAdmin;
use Suilven\Flickr\Model\FlickrAuthor;
use Suilven\Flickr\Model\FlickrPhoto;

class FlickrPhotoAdmin extends ModelAdmin
{

    private static $managed_models = array(   //since 2.3.2
        FlickrPhoto::class,
        FlickrAuthor::class
    );

    private static $url_segment = 'flickr_photos'; // will be linked as /admin/products
    private static $menu_title = 'Flickr Photos';

    private static $menu_icon = '/flickr/icons/photo.png';

}
