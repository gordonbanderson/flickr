<?php

class FlickrSiteConfig extends \SilverStripe\ORM\DataExtension {

	private static $db = array(
		'ImageFooter' => 'Text',
		'AddLocation' => 'Boolean'
	);


	function updateCMSFields(\SilverStripe\Forms\FieldList $fields) {
		$fields->addFieldToTab("Root.Flickr", new \SilverStripe\Forms\TextareaField("ImageFooter", 'This text will be appended to all image descriptions'));
		$fields->addFieldToTab("Root.Flickr", new \SilverStripe\Forms\CheckboxField("AddLocation", 'Add a textual description of the location to all images'));//, 'Add the location as text to the picture');
		return $fields;
	}

}
