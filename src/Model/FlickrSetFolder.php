<?php
namespace Suilven\Flickr\Model;

/**
 * Defines the FlickrSetFolder page type
 */
class FlickrSetFolder extends Page implements RenderableAsPortlet {


	 static $allowed_children = array('FlickrSetPage', 'FlickrSetFolder');

	 static $db = array(
	'PromoteToHomePage' => 'Boolean'
	 );


	static $has_one = array('MainFlickrPhoto' => 'FlickrPhoto');



	function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.CoverPhoto', new FlickrPhotoSelectionField('MainFlickrPhotoID', 'Cover Photo', $this->MainFlickrPhoto()));


		$fields->renameField("Content", "Brief Description");
		$fields->addFieldToTab("Root.HomePage", new CheckboxField('PromoteToHomePage', 'Promote to Home Page'));

		return $fields;
	}


	function getPortletTitle() {
		return $this->Title;
	}


	/**
	 * An accessor method for an image for a portlet
	 * @example
	 * <code>
	 *  return $this->NewsItemImage;
	 * </code>
	 *
	 * @return string
	 */
	public function getPortletImage() {
		return $this->MainFlickrPhoto();
	}


	/**
	 * An accessor for text associated with the portlet
	 * @example
	 * <code>
	 * return $this->Summary
	 * </code>
	 *
	 * @return string
	 */
	public function getPortletCaption() {
		return $this->Title;
	}


}

class FlickrSetFolder_Controller extends Page_Controller {
	public function FlickrSetsNewestFirst() {
		return DataList::create('FlickrSetPage')->where('ParentID = '.$this->ID)->sort('FirstPictureTakenAt desc');
	}

	public function FlickrSetFoldersNewestFirst() {
		return DataList::create('FlickrSetFolder')->where('ParentID = '.$this->ID)->sort('Created desc');
	}
}
