<?php
/**
 * GridFieldExifData displays read only exif data for a Flickr photograph
 *
 * @see GridField
 *
 * @package weboftalent-flickr
 * @subpackage fields-relational
 */
class GridFieldFlickrImage implements GridField_HTMLProvider {
	public function getHTMLFragments($gridField) {
		$forTemplate = new ArrayData(array());

		return array(
			'header' => $forTemplate->renderWith('GridFieldFlickrImage'),
		);
	}
}
