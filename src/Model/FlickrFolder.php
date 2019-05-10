<?php
namespace Suilven\Flickr\Model;

/**
 * Defines the GalleryFolder page type
 */
class FlickrFolder extends Page {


	 static $allowed_children = array('FlickrSetPage', 'FlickrFolder');

	 static $has_one = array(
		'CoverPhoto' => 'Image',
	 );


	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Content.CoverPhoto", new UploadField('CoverPhoto'));


		$fields->renameField("Content", "Brief Description");


		/*
		$fields->addFieldToTab('Root.Content.Main', new CalendarDateField('Date'), 'Content');
		$fields->addFieldToTab('Root.Content.Main', new TextField('Author'), 'Content');
		*/
		return $fields;
	}

}

class FlickrFolder_Controller extends Page_Controller {

}
