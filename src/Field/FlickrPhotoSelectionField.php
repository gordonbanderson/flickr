<?php
/**
 * Text input field.
 *
 * @package forms
 * @subpackage fields-basic
 */
class FlickrPhotoSelectionField extends HiddenField {

	/**
	 * @var int
	 */
	protected $maxLength;

	protected $flickrTitle;
	protected $flickrID;

	/**
	 * Returns an input field, class="text" and type="text" with an optional maxlength
	 */
	function __construct($name, $title = null, $flickrPhoto = '', $maxLength = null, $form = null) {
		$this->maxLength = $maxLength;

		parent::setTemplate('FLickrPhotoSelectionField');

		$value = '';
		if ($flickrPhoto) {
			$value = $flickrPhoto->ID;
			$this->flickrTitle = $flickrPhoto->Title;
			$this->flickrID = $flickrPhoto->FlickrID;
			$this->mediumURL = $flickrPhoto->MediumURL;
		}

		$this->addExtraClass('flickrPhotoSelectionField');


		parent::__construct($name, $title, $value, $form);
	}

	function getFlickrTitle() {
		return $this->flickrTitle;
	}

	function getFlickrID() {
		return $this->flickrID;
	}

	function getMediumURL() {
		return $this->mediumURL;
	}

	/**
	 * @param int $length
	 */
	function setMaxLength($length) {
		$this->maxLength = $length;

		return $this;
	}

	/**
	 * @return int
	 */
	function getMaxLength() {
		return $this->maxLength;
	}

	function getAttributes() {
		return array_merge(
			parent::getAttributes(),
			array(
				'maxlength' => $this->getMaxLength(),
				'size' => ($this->getMaxLength()) ? min($this->getMaxLength(), 30) : null
			)
		);
	}

	function InternallyLabelledField() {
		if(!$this->value) $this->value = $this->Title();
		return $this->Field();
	}


	public function FieldHolder( $properties = array() ) {
		Requirements::javascript( 'weboftalent-flickr/javascript/flickredit.js' );


		return parent::FieldHolder();
	}

}

