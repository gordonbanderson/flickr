<?php

class FlickrPhotoShortCodeHandler {

	// taken from http://www.ssbits.com/tutorials/2010/2-4-using-short-codes-to-embed-a-youtube-video/ and adapted for SS3
	public static function parse_flickr( $arguments, $caption = null, $parser = null ) {
		// first things first, if we dont have a video ID, then we don't need to
		// go any further
		if ( empty( $arguments['id'] ) ) {
			return;
		}

		$customise = array();
		/*** SET DEFAULTS ***/
		$fp = DataList::create('FlickrPhoto')->where('FlickrID='.$arguments['id'])->first();

		if (!$fp) {
			return '';
		}

		$customise['FlickrImage'] = $fp;
		//set the caption


		if (($caption === null) || ($caption === '')) {
			if (isset($arguments['caption'])) {
			$caption = $arguments['caption'];
			}
		}


		$customise['Caption'] = $caption ? Convert::raw2xml( $caption ) : $fp->Title ;
		$customise['Position'] = !empty($arguments['position']) ? $arguments['position'] : 'center';
		$customise['Small'] = true;
		if ($customise['Position'] == 'center') {
			$customise['Small'] = false;
		}

		$fp = null;

		//overide the defaults with the arguments supplied
		$customise = array_merge( $customise, $arguments );

		//get our YouTube template
		$template = new SSViewer( 'ShortCodeFlickrPhoto' );

		//return the customised template
		return $template->process( new ArrayData( $customise ) );
	}
}
