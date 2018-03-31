<?php
namespace Suilven\Flickr\Service;

use Rezzza\Flickr\ApiFactory;
use Rezzza\Flickr\Http\GuzzleAdapter;
use Rezzza\Flickr\Metadata;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Suilven\Flickr\Model\FlickrAuthor;
use Suilven\Flickr\Model\FlickrPhoto;
use Suilven\Flickr\Model\FlickrSet;
use Suilven\Flickr\Model\FlickrTag;

class FlickrService
{

    /*
    Cache the tags in memory to try and avoid DB calls
     */
    private static $tagCache = array();

    private $factory;

    public function __construct()
    {
        // get flickr details from config
        $consumerKey = Config::inst()->get('Suilven\Flickr\Service\FlickrService', 'consumer_key');
        $consumerSecret = Config::inst()->get('Suilven\Flickr\Service\FlickrService', 'consumer_secret');
        $token = Config::inst()->get('Suilven\Flickr\Service\FlickrService', 'token');
        $tokenSecret = Config::inst()->get('Suilven\Flickr\Service\FlickrService', 'token_secret');

        $metadata = new Metadata($consumerKey, $consumerSecret);
        $metadata->setOauthAccess($token, $tokenSecret);

        $this->factory  = new ApiFactory($metadata, new GuzzleAdapter());

        error_log('CK=' . $consumerKey);




        if (!$consumerKey) {
            echo "In order to import photographs Flickr key, secret and access token must be provided";
            die;
        }
    }


    public function index()
    {
        // Code for the index action here
        return array();
    }


    public function importFromSearch()
    {
        $canAccess = ( Director::isDev() || Director::is_cli() || Permission::check("ADMIN") );
        if (!$canAccess) {
            return Security::permissionFailure($this);
        }

        $searchParams = array();

        //any high number
        $nPages = 1e7;

        $page = 1;
        $ctr = 1;

        $start=time();


        while ($page <= $nPages) {
            echo "\n\n\n\n----------- LOADING PICS -----------\n";
            $query = $_GET['q'];

            if ($nPages == 1e7) {
                echo "Starting search for $query\n";
            } else {
                echo "\n\nLoading $page / $nPages\n";
            }

            $searchParams['text'] = $query;
            $searchParams['license'] = 7;
            $searchParams['per_page'] = 500;
            $searchParams['page'] = $page;
            $searchParams['extras'] = 'description, license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_q, url_m, url_n, url_z, url_c, url_l, url_o';
            $searchParams['sort'] = 'relevance'; // 'interestingness-desc'; // also try relevance

            $data = $this->f->photos_search($searchParams);
            $nPages = $data['pages'];
            $totalImages = $data['total'];

            echo "Found $nPages pages\n";
            echo "n photos returned ".sizeof($data['photo']);

            foreach ($data['photo'] as $photo) {
                echo "[Import photo $ctr / $totalImages, page $page / $nPages]\n";
                echo "Loading photo:".$photo['title']."\n";

                $elapsed = time()-$start;
                $perPhoto = $elapsed/$ctr;
                $remaining = ($totalImages-$ctr)*$perPhoto;
                $hours = floor($remaining / 3600);
                $minutes = floor(($remaining / 60) % 60);
                $seconds = $remaining % 60;
                echo "Estimated time remaning - $hours:$minutes:$seconds\n";

                $flickrPhoto = $this->createOrUpdateFromFlickrArray($photo);
                echo "\tLoading exif data\n";
                if (!$flickrPhoto->Processed) {
                    if ($flickrPhoto->checkExifRequired()) {
                        $flickrPhoto->loadExif();
                    }
                    $flickrPhoto->Processed = true;
                    $flickrPhoto->write();
                }
                $ctr++;
            }
            $page++;
        }
    }


    public function importSet($flickrSetID)
    {
        $page= 1;
        static $only_new_photos = false;
/*
        $path = $_GET['path'];
        $parentNode = SiteTree::get_by_link($path);
        if ($parentNode == null) {
            echo "ERROR: Path ".$path." cannot be found in this site\n";
            die;
        }
*/


        /**
         *  $xmlPhotoList = $apiFactory->call('flickr.photosets.getPhotos', [
        'photoset_id' => $photosetId,
        'page' => $page,
        ]);

         */
        $xml = $this->factory->call('flickr.photosets.getPhotos', [
            'photoset_id' => $flickrSetID,
            'extras' => 'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description',

        ]);



        foreach ($xml->photoset->photo as $photo) {
            error_log('photo!');
            //error_log(print_r($photo, 1));
        }


        $photoset = $xml->photoset;

        $flickrSet = $this->getFlickrSet($flickrSetID);

        // reload from DB with date - note the use of quotes as flickr set id is a string
        //$flickrSet = DataObject::get_one( 'Suilven\Flickr\Model\FlickrSet', 'FlickrID=\''.$flickrSetID."'" );
        $flickrSet->FirstPictureTakenAt = (string) $photoset->photo[0]->attributes()->datetaken;
        error_log('TAKEN AT:' . $flickrSet->FirstPictureTakenAt);
        $flickrSet->KeepClean = true;


        $flickrSet->Title = (string) $photoset->attributes()->title;
        error_log('TITLE  ' . $flickrSet->Title);
        $flickrSet->write();

        echo "Title set to : ".$flickrSet->Title;

        if ($flickrSet->Title == null) {
            echo( "ABORTING DUE TO NULL TITLE FOUND IN SET - ARE YOU AUTHORISED TO READ SET INFO?" );
            die;
        }

        $datetime = explode(' ', $flickrSet->FirstPictureTakenAt);
        $datetime = $datetime[0];

        list( $year, $month, $day ) = explode('-', $datetime);
        echo "Month: $month; Day: $day; Year: $year<br />\n";



        $numberOfPics = count($photoset->photo);
        $ctr = 1;
        foreach ($photoset->photo as $photo) {
            echo "Importing photo {$ctr}/${numberOfPics}\n";

            $flickrPhoto = $this->createOrUpdateFromFlickrArray($photo);

            if ((int) $photo->isprimary == 1) {
                $flickrSet->MainImage = $flickrPhoto;
            }

            $flickrPhoto->write();
            $flickrSet->FlickrPhotos()->add($flickrPhoto);

            error_log('ID: ' . $flickrPhoto->ID);


            $ctr++;

            $flickrPhoto = null;
        }

         //update orientation
        $sql = 'update FlickrPhoto set Orientation = 90 where ThumbnailHeight > ThumbnailWidth;';
        DB::query($sql);


        // now download exifs
        $ctr = 0;
        foreach ($photoset->photo as $photo) {
            echo "IMPORTING EXIF {$ctr}/$numberOfPics\n";
            $flickrPhotoID =  (int) $photo->attributes()->id;
            error_log('FPID: ' . $flickrPhotoID);
            error_log($photo->asXml());
            $flickrPhoto = FlickrPhoto::get()->filter('FlickrID', $flickrPhotoID)->first();
            $flickrPhoto->loadExif();
            $flickrPhoto->write();
            $ctr++;
        }

        // @todo Fix $this->fixSetMainImages();
        // @todo ditto $this->fixDateSetTaken();

        return $flickrSet;
    }


    private function createOrUpdateFromFlickrArray($photo, $only_new_photos = false)
    {
        gc_collect_cycles();

        $attributes = $photo->attributes();

        $flickrPhotoID = (int) $attributes->id;

        // the author, e.g. gordonbanderson
        $pathalias = $attributes['pathalias'];

        // do we have a set object or not
        $flickrPhoto = DataObject::get_one('Suilven\Flickr\Model\FlickrPhoto', 'FlickrID='.$flickrPhotoID);

        // if a set exists update data, otherwise create
        if (!$flickrPhoto) {
            $flickrPhoto = new FlickrPhoto();
        }

        $flickrPhoto->Title = (string) $attributes->title;

        $flickrPhoto->FlickrID = $flickrPhotoID;
        $flickrPhoto->KeepClean = true;

        $flickrPhoto->TakenAt = (string) $attributes->datetaken;
        error_log('GRANUL:' . $attributes->datetakengranularity);

        $flickrPhoto->DateGranularity = (int) $attributes->datetakengranularity;


        $flickrPhoto->MediumURL = (string) $attributes->url_m;
        $flickrPhoto->MediumHeight = (int) $attributes->height_m;
        $flickrPhoto->MediumWidth = (int) $attributes->width_m;

        $flickrPhoto->SquareURL = (string)  $attributes->url_s;
        $flickrPhoto->SquareHeight = (int) $attributes->height_s;
        $flickrPhoto->SquareWidth = (int) $attributes->width_s;


        $flickrPhoto->ThumbnailURL =(string) $attributes->url_t;
        $flickrPhoto->ThumbnailHeight = (int) $attributes->height_t;
        $flickrPhoto->ThumbnailWidth = (int) $attributes->width_t;

        $flickrPhoto->SmallURL = (string) $attributes->url_s;
        $flickrPhoto->SmallHeight = (int) $attributes->height_s;
        $flickrPhoto->SmallWidth = (int) $attributes->width_s;

        // If the image is too small, large size will not be set
        if (!empty($attributes->url_l)) {
            $flickrPhoto->LargeURL = (string) $attributes->url_l;
            $flickrPhoto->LargeHeight = (int) $attributes->height_l;
            $flickrPhoto->LargeWidth = (int) $attributes->width_l;
        }


        $flickrPhoto->OriginalURL = (string) $attributes->url_o;
        $flickrPhoto->OriginalHeight = (int) $attributes->height_o;
        $flickrPhoto->OriginalWidth = (int) $attributes->width_o;

        $flickrPhoto->Description = (string) $attributes->description; //'test';// $value['description']['_content'];




        $lat = number_format((float)$attributes->latitude, 15);
        $lon = number_format((float)$attributes->longitude, 15);

        if (isset($attributes['place_id'])) {
            $flickrPhoto->FlickrPlaceID = (string) $attributes->place_id;
        }

        if (isset($attributes->woeid)) {
            $flickrPhoto->WoeID = (int)$attributes->woeid;
        }

        // @todo Make this an enum
        $flickrPhoto->Media = (string) $attributes->media;


        if (!empty($lat)) {
            $flickrPhoto->Lat = (float) $lat;
            $flickrPhoto->ZoomLevel = 15;
        }
        if (!empty($lon)) {
            $flickrPhoto->Lon = (float) $lon;
        }

        if ($attributes->accuracy) {
            $flickrPhoto->Accuracy = (int) $attributes['accuracy'];
        }

        if (isset($attribute->geo_is_public)) {
            $flickrPhoto->GeoIsPublic = (boolean) $attributes['geo_is_public'];
        }

        if (isset($attrbutes->woeid)) {
            $flickrPhoto->WoeID = (int) $attributes['woeid'];
        }


        $singlePhotoInfo = $this->getPhotoDetail($flickrPhoto);

        // need an ID
        $flickrPhoto->write();

        error_log($singlePhotoInfo->asXml());
        foreach ($singlePhotoInfo->tags as $tags) {
            foreach ($tags as $tag) {
                error_log($tag->asXml());
                $tagNormalised = (string) $tag;
                $tags = FlickrTag::get()->filter('Value', $tagNormalised);
                if ($tags->count() > 1) {
                    throw new Exception("The tag {$tagNormalised} has multiple instances");
                }
                $tagDO = $tags->first();

                if (!$tagDO) {
                    $tagDO = new FlickrTag();
                    $tagDO->FlickrID = (int) $tag->id;
                    $tagDO->Value = (string) $tag;
                    $tagDO->RawValue = (string) $tag->raw;
                    $tagDO->write();
                }

                $ftags= $flickrPhoto->FlickrTags();
                $ftags->add($tagDO);
            }
        }





        return $flickrPhoto;
    }


    /*
    Either get the set from the database, or if it does not exist get the details from flickr and add it to the database
    */
    private function getFlickrSet($flickrSetID)
    {
        // do we have a set object or not
        $flickrSet = DataObject::get_one('Suilven\Flickr\Model\FlickrSet', 'FlickrID=\''.$flickrSetID."'");

        // if a set exists update data, otherwise create
        if (!$flickrSet) {
            $flickrSet = new FlickrSet();

            $setInfo = $this->factory->call('flickr.photosets.getInfo', [
                'photoset_id' => $flickrSetID,
            ]);

            $setTitle = $setInfo->title;
            $setDescription = $setInfo->description;
            $flickrSet->Title = (string) $setTitle;
            $flickrSet->Description = (string) $setDescription;
            $flickrSet->FlickrID = $flickrSetID;
            $flickrSet->KeepClean = true;

            // @todo - add username
            $flickrSet->write();
        }

        return $flickrSet;
    }

    /**
     * @todo Refactor, bit messy
     *
     * @param $flickrPhotoID
     * @param $flickrPhoto
     * @return mixed
     */
    private function getPhotoDetail(&$flickrPhoto)
    {
        $xml = $this->factory->call('flickr.photos.getinfo', [
            'photo_id' => $flickrPhoto->FlickrID,
        ]);

        $singlePhotoInfo = $xml->photo;

        if (isset($photo->license)) {
            $flickrPhoto->FlickrLicenseID = (int) $singlePhotoInfo->license;
        }

        $owner = $singlePhotoInfo->owner;
        $pathalias = (string) $singlePhotoInfo->path_alias;


        $author = FlickrAuthor::get()->filter('PathAlias', $pathalias)->first();
        if (!$author) {
            $author = new FlickrAuthor();
            $author->PathAlias = $pathalias;
            $author->RealName = (string)$owner->realname;
            $author->Username = (string) $owner->username;
            $author->NSID = (string) $owner->nsid;
            $author->write();
        } elseif (!$author->RealName) {
            // Fix missing values
            $author->PathAlias = $pathalias;
            $author->RealName = (string)$owner->realname;
            $author->Username = (string) $owner->username;
            $author->NSID = (string) $owner->nsid;
            $author->write();
        }

        $flickrPhoto->PhotographerID = $author->ID;


        $flickrPhoto->Description = (string) $singlePhotoInfo->description;
        $flickrPhoto->Rotation = (int) $singlePhotoInfo->rotation; // @todo check this is correct

        error_log('ROTATION: ' . $flickrPhoto->Rotation);

        if (isset($singlePhotoInfo->visibility)) {
            $flickrPhoto->IsPublic = (boolean) $singlePhotoInfo->visibility->ispublic;
        }
        return $singlePhotoInfo;
    }
}
