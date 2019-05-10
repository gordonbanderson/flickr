<?php
/**
 *  // _config.php
 * Director::addRules(10, array(
 *  'emptycache' => 'EmptyCacheController',
 * ));
 */


use Symfony\Component\Yaml\Dumper;


require_once "phpFlickr.php";

class FlickrController extends Page_Controller implements PermissionProvider
{

    static $allowed_actions = array(
        'index',
        'importSet',
        'editprofile',
        'sets',
        'primeBucketsTest',
        'createBucket',
        'fixSetPhotoManyMany',
        'fixSetMainImages',
        'PublishAllFlickrSetPages',
        'batchUpdateSet',
        'ajaxSearchForPhoto',
        'fixArticles',
        'fixDateSetTaken',
        'fixArticleDates',
        'fixPhotoTitles',
        'ajaxSearchForPhoto',
        'updateEditedImagesToFlickr',
        'dumpSetAsJson',
        'primeFlickrSetFolderImages',
        'moveXperiaPics',
        'changeFlickrSetMainImage',
        'fixFocalLength35',
        'fixDescriptions',
        'importFromSearch',
        'importSearchToYML'
    );


    /*

    if ($name === 'C6602') {
                                $this->FocalLength35mm = 28;
                                $fixFocalLength = 28;
                            }

                            if ($name === 'Canon IXUS 220 HS') {
                                $focalConversionFactor = 5.58139534884;
                            }
     */


    public function fixDescriptions()
    {
        $canAccess = (Director::isDev() || Director::is_cli() || Permission::check("ADMIN"));
        if (!$canAccess) return Security::permissionFailure($this);

        $sets = FlickrSet::get()->Filter('Description', 'Array');
        foreach ($sets->getIterator() as $set) {
            echo $set->Title . "\n";
            $setInfo = $this->f->photosets_getInfo($set->FlickrID);

            $setTitle = $setInfo['title']['_content'];
            $setDescription = $setInfo['description']['_content'];
            $set->Title = $setTitle;
            $set->Description = $setDescription;
            $set->write();

            $fsps = FlickrSetPage::get()->filter('FlickrSetForPageID', $set->ID);
            foreach ($fsps->getIterator() as $fsp) {
                echo $fsp;
                $fsp->Title = $setTitle;
                $fsp->Description = $setDescription;
                $fsp->write();
                $fsp->publish("Live", "Stage");
            }
        }
    }

    public function fixFocalLength35()
    {
        $canAccess = (Director::isDev() || Director::is_cli() || Permission::check("ADMIN"));
        if (!$canAccess) return Security::permissionFailure($this);

        $finished = false;

        while (!$finished) {


            $photos = FlickrPhoto::get()->filter(array('FocalLength35mm' => 0, 'IsPublic' => 1))->limit(50);

            if (count($photos) == 0) {
                $finished = true;
            }

            foreach ($photos as $photo) {
                echo $photo->Title . "(" . $photo->FlickrID . ")\n";
                $modelExif = $photo->Exifs()->filter('Tag', 'Model')->first();

                //foreach ($photo->Exifs() as $exif) {
                //	echo ' - '.$exif->Tag.' => '.$exif->Raw."\n";
                //}

                if (isset($modelExif)) {
                    $model = $modelExif->Raw;
                    $focalLengthExif = $photo->Exifs()->filter('Tag', 'FocalLength')->first();
                    $mm = (int)str_replace(' mm', '', $focalLengthExif->Raw);

                    if ($model === 'Canon IXUS 220 HS') {
                        $f35 = round($mm * 5.58139534884);
                        $photo->FocalLength35mm = $f35;
                    } else if ($model === 'C6602') {
                        $photo->FocalLength35mm = 28;
                    } else if ($model === 'Canon EOS 450D') {
                        $f35 = round($mm * 1.61428571429);
                        $photo->FocalLength35mm = $f35;
                    }

                    echo " - Focal length set to " . $photo->FocalLength35mm . "\n";
                    $photo->write();
                } else {
                    echo " - No exif data for image " . $photo->FlickrID . "\n";

                    // mark private for now
                    $photo->IsPublic = false;
                    $photo->write();
                }
            }
        }

        die; // abort rendering
    }

    public function fixArticleDates()
    {
        $articles = DataList::create('Article')->where('StartTime is null');
        foreach ($articles->getIterator() as $article) {
            $article->StartTime = $article->Created;
            $article->write();
        }
    }


    public function providePermissions()
    {
        return array(
            "FLICKR_EDIT" => "Able to import and edit flickr data"
        );
    }


    function primeFlickrSetFolderImages()
    {
        $folders = DataList::create('FlickrSetFolder')->where('MainFlickrPhotoID = 0');

        foreach ($folders as $folder) {
            foreach ($folder->Children() as $folderOrSet) {
                $cname = $folderOrSet->ClassName;
                // we want to find a flickr set page we can use the image from
                if ($cname == 'FlickrSetPage') {
                    $flickrImage = $folderOrSet->getPortletImage();
                    //error_log("FI:".$flickrImage>" ID=".$flickrImage->ID);
                    if ($flickrImage->ID != 0) {
                        $folder->MainFlickrPhotoID = $flickrImage->ID;
                        $folder->write();
                        continue;
                    }
                }
            }
        }
    }


    public function dumpSetAsJson()
    {
        die;
    }


    public function setToJson()
    {
        $flickrSetID = $this->request->param('ID');
        $flickrSet = DataList::create('FlickrSet')->where('FlickrID = ' . $flickrSetID)->first();
        $images = array();
        foreach ($flickrSet->FlickrPhotos() as $fp) {
            $image = array();
            $image['MediumURL'] = $fp->MediumURL;
            $image['BatchTitle'] = $fp->Title;
        }
    }


    public function updateEditedImagesToFlickr()
    {
        $flickrSetID = $this->request->param('ID');
        $flickrSet = FlickrSet::get()->filter(array('FlickrID' => $flickrSetID))->first();

        if ($flickrSet) {
            //DataList::create('FlickrSet')->where('FlickrID = '.$flickrSetID)->first();
            $flickrSet->writeToFlickr();
        } else {
            error_log('Flickr set could not be found');
        }
    }


    public function fixDateSetTaken()
    {
        $fsps = DataList::create('FlickrSetPage')->where('FirstPictureTakenAt is NULL');
        foreach ($fsps as $fsp) {
            $fs = $fsp->FlickrSetForPage();

            if ($fs->ID == 0) {
                continue;
            }
            if ($fs->FirstPictureTakenAt == null) {
                $firstDate = $fs->FlickrPhotos()->sort('TakenAt')->where('TakenAt is not null');
                $firstDate = $firstDate->first();

                if ($firstDate) {
                    $fs->FirstPictureTakenAt = $firstDate->TakenAt;
                    $fs->KeepClean = true;
                    $fs->write();
                }

            }
            $fsp->FirstPictureTakenAt = $fs->FirstPictureTakenAt;
            $fsp->write();

            $fsp->publish("Live", "Stage");
        }
    }


    public function fixArticles()
    {
        $articles = DataList::create('Article')->sort('Title');
//        $articles->where('Article_Live.ID=32469');
        foreach ($articles as $article) {
            $content = $article->Content;
            $sections = explode('FLICKRPHOTO_', $content);
            $alteredContent = '';
            foreach ($sections as $section) {
                //$splits2 = split(' ', $section);
                //$flickrIDwithCrud = array_shift($splits2);
                $flickrID = '';
                for ($i = 0; $i < strlen($section); $i++) {
                    if (is_numeric($section[$i])) {
                        $flickrID .= $section[$i];
                    } else {
                        break;
                    }
                }

//                $restOfCrud = str_replace($flickrID, '', $flickrIDwithCrud);
                $section = str_replace($flickrID, '', $section);
                $section = '[FlickrPhoto id=' . $flickrID . ']' . $section;

                $section = str_replace('<p> </p>', '', $section);
                $alteredContent .= $section;
            }

            $article->Content = $alteredContent;

            try {
                $article->write();
                $article->publish("Live", "Stage");

            } catch (Exception $e) {
                error_log("Unable to write article " . $article->ID);
                error_log($e);
            }

        }
    }


    public function ajaxSearchForPhoto()
    {
        //FIXME authentication

        $flickrPhotoID = Convert::raw2sql($this->request->param('ID'));

        $flickrPhoto = DataList::create('FlickrPhoto')->where('FlickrID=' . $flickrPhotoID)->first();
        $not_found = !$flickrPhoto;

        $result = array(
            'found' => !$not_found
        );

        if ($flickrPhoto) {
            $result['title'] = $flickrPhoto->Title;
            $result['small_url'] = $flickrPhoto->SmallURL;
            $result['medium_url'] = $flickrPhoto->MediumURL;
            $result['id'] = $flickrPhoto->ID;
            $result['description'] = $flickrPhoto->Description;
        }

        return json_encode($result);
    }


    public function batchUpdateSet()
    {
        //FIXME authentication

        $flickrSetID = Convert::raw2sql($this->request->param('ID'));
        $batchTitle = Convert::raw2sql($_POST['BatchTitle']);
        $batchDescription = Convert::raw2sql($_POST['BatchDescription']);
        $batchTags = str_getcsv(Convert::raw2sql($_POST['BatchTags']));

        $flickrSet = DataList::create('FlickrSet')->where('ID = ' . $flickrSetID)->first();
        $flickrPhotos = $flickrSet->FlickrPhotos();

        // $batchDescription = $batchDescription ."\n\n".$flickrSet->ImageFooter;
        // $batchDescription = $batchDescription ."\n\n".$this->SiteConfig()->ImageFooter;

        $tags = array();
        foreach ($batchTags as $batchTag) {
            $batchTag = trim($batchTag);
            $lowerCaseTag = strtolower($batchTag);
            $possibleTags = DataList::create('FlickrTag')->where("Value='" . $lowerCaseTag . "'");

            if ($possibleTags->count() == 0) {
                $tag = new FlickrTag();
                $tag->Value = $lowerCaseTag;
                $tag->RawValue = $batchTag;
                $tag->write();
            } else {
                $tag = $possibleTags->first();
            }

            array_push($tags, $tag->ID);
            //$tag = DataList::create('FlickrExif')->where('WIP');
        }

        foreach ($flickrPhotos as $fp) {
            $fp->Title = $batchTitle;
            $fp->Description = $batchDescription;
            $fp->FlickrTags()->addMany($tags);
            $fp->write();
        }

        $result = array(
            'number_of_images_updated' => $flickrPhotos->count()
        );

        return json_encode($result);

    }


    public function PublishAllFlickrSetPages()
    {
        $pages = DataList::create('FlickrSetPage');
        foreach ($pages as $fsp) {
            $fsp->publish("Stage", "Live");
        }

        $pages = DataList::create('FlickrSetFolder');
        foreach ($pages as $fsp) {
            $fsp->publish("Stage", "Live");
        }
    }


    public function fixPhotoTitles()
    {


        $sets = DataList::create('FlickrSet');
        foreach ($sets as $set) {

            $pageCtr = 1;
            $flickrSetID = $set->FlickrID;

            $mainImageFlickrID = null;
            $allPagesRead = false;

            while (!$allPagesRead) {
                $photos = $this->f->photosets_getPhotos($flickrSetID,
                    'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description',
                    NULL,
                    500,
                    $pageCtr);

                $pageCtr = $pageCtr + 1;


                //print_r($photos);
                $photoset = $photos['photoset'];
                $page = $photoset['page'];
                $pages = $photoset['pages'];
                $allPagesRead = ($page == $pages);


                foreach ($photoset['photo'] as $key => $photo) {
                    $fp = DataList::create('FlickrPhoto')->where('FlickrID = ' . $photo['id'])->first();

                    if ($fp == null) {
                        continue;
                    }

                    $title = $photo['title'];
                    if (strlen($title) > 48) {
                        $fp->Title = $title;
                        $fp->write();
                        //error_log($fp->FlickrID . '==' . $photo['id']. '??');
                    };

                }


            }
        }
    }


    public function fixSetMainImages()
    {
        $sets = DataList::create('FlickrSet')->where('PrimaryFlickrPhotoID = 0');
        foreach ($sets as $set) {
            $pageCtr = 1;
            $flickrSetID = $set->FlickrID;

            $mainImageFlickrID = null;
            $allPagesRead = false;

            while (!$allPagesRead) {

                $photos = $this->f->photosets_getPhotos($flickrSetID,
                    'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description',
                    NULL,
                    500,
                    $pageCtr);

                $pageCtr = $pageCtr + 1;


                //print_r($photos);
                $photoset = $photos['photoset'];
                $page = $photoset['page'];
                $pages = $photoset['pages'];
                $allPagesRead = ($page == $pages);

                foreach ($photoset['photo'] as $key => $photo) {
                    echo '.';
                    if ($photo['isprimary'] == 1) {
                        $fp = DataList::create('FlickrPhoto')->where('FlickrID = ' . $photo['id'])->first();

                        if (isset($fp)) {
                            $set->PrimaryFlickrPhotoID = $fp->ID;
                            $set->write();
                        } else {
                            $firstPicID = $set->FlickrPhotos()->first()->ID;
                            $set->PrimaryFlickrPhotoID = $firstPicID;
                            $set->write();
                        }

                    }
                }


            }
        }
    }


    /* Fix the many many relationships, previously FlickrSetPhoto pages which have now been deleted */
    public function fixSetPhotoManyMany()
    {
        $flickrSetID = Convert::raw2sql($this->request->param('ID'));
        $flickrSets = DataList::create('FlickrSet')->where("FlickrID=" . $flickrSetID);

        $allPagesRead = false;
        $flickrPhotoIDs = array();


        if ($flickrSets->count() == 1) {
            $flickrSet = $flickrSets->first();

            $pageCtr = 1;

            while (!$allPagesRead) {

                $photos = $this->f->photosets_getPhotos($flickrSetID,
                    'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description',
                    NULL,
                    500,
                    $pageCtr);

                $pageCtr = $pageCtr + 1;


                //print_r($photos);
                $photoset = $photos['photoset'];
                $page = $photoset['page'];
                $pages = $photoset['pages'];
                $allPagesRead = ($page == $pages);


                foreach ($photoset['photo'] as $key => $photo) {
                    array_push($flickrPhotoIDs, $photo['id']);
                }


            }

            $flickrPhotos = DataList::create('FlickrPhoto')->where("FlickrID in (" . implode(',', $flickrPhotoIDs) . ")");
            $flickrSet->FlickrPhotos()->removeAll();
            $flickrSet->FlickrPhotos()->addMany($flickrPhotos);
            $flickrSet->write();


        } else {
            // no flickr set found for the given ID
            error_log("Flickr set not found for id " . $flickrSetID);
        }

    }


    function primeBucketsTest()
    {
        $fset = DataList::create('FlickrSet')->last();
        $bucket = new FlickrBucket();
        $bucket->write();// get an ID
        $photos = $fset->FlickrPhotos();

        $bucketPhotos = $bucket->FlickrPhotos();
        $ctr = 0;
        foreach ($photos as $key => $value) {
            $bucketPhotos->add($value);
            $ctr = $ctr + 1;
            if ($ctr > 7) {
                break;
            }
        }
        $bucket->FlickrSetID = $fset->ID;
        $bucket->write();
    }


    public function createBucket()
    {
        $flickrPhotoIDs = $this->request->param('OtherID');
        $flickrSetID = Convert::raw2sql($this->request->param('ID'));
        $ajax_bucket_row = Convert::raw2sql($_GET['bucket_row']);
        $sanitizedIDs = Convert::raw2sql($flickrPhotoIDs);
        $flickrPhotos = FlickrPhoto::get()->where('ID in (' . $sanitizedIDs . ')');
        $flickrSet = FlickrSet::get()->where('ID=' . $flickrSetID)->first();
        $bucket = new FlickrBucket();
        $bucket->write();

        $bucketPhotos = $bucket->FlickrPhotos();
        foreach ($flickrPhotos as $fp) {
            $bucketPhotos->add($fp);
        }
        $bucket->FlickrSetID = $flickrSet->ID;
        $bucket->write();

        $result = array(
            'bucket_id' => $bucket->ID,
            'flickr_set_id' => $flickrSet->ID,
            'ajax_bucket_row' => $ajax_bucket_row

        );

        echo json_encode($result);
        die; // abort render
    }


    public function init()
    {
        parent::init();

        if (!Permission::check("FLICKR_EDIT")) {
            //FIXME - enable in the CMS first, then do
            //Security::permissionFailure();
        }


        // get flickr details from config
        $key = Config::inst()->get($this->class, 'api_key');
        $secret = Config::inst()->get($this->class, 'secret');
        $access_token = Config::inst()->get($this->class, 'access_token');

        $this->f = new phpFlickr($key, $secret);

        //Fleakr.auth_token    = ''
        $this->f->setToken($access_token);


        // Requirements, etc. here
    }

    public function index()
    {
        // Code for the index action here
        return array();
    }

    public function sets()
    {
        $sets = $this->f->photosets_getList('45224965@N04');

        if ($sets) {
            echo "Sets set";
        }


        foreach ($sets['photoset'] as $key => $value) {
            echo '#' . $value['title'];
            echo "\nframework/sake flickr/importSet/" . $value['id'];
            echo "\n\n";

        }
    }


    public function moveXperiaPics()
    {
        $moblogbucketsetid = $this->request->param('ID');
        //  $moblogset = FlickrSet::get()->filter(array('FlickrID' => $moblogbucketsetid))->first();
        $photos = $this->f->photos_search(array("user_id" => "me", "per_page" => 500, 'extras' => 'description, license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_q, url_m, url_n, url_z, url_c, url_l, url_o'));


    }


    public function changeFlickrSetMainImage()
    {
        $flickrsetID = $this->request->param('ID');
        $flickrphotoID = $this->request->param('OtherID');
        $flickrset = FlickrSet::get()->filter('ID', $flickrsetID)->first();
        $flickrset->PrimaryFlickrPhotoID = $flickrphotoID;
        $flickrset->write();
    }


    public function splitMoblog()
    {

        /*
        echo "Created set";
        var_dump($r);
        die;
*/

        $flickrSetID = '72157624403053639';

        // var_dump($photo_response);

        $dateToImages = array();

        $photos = array();


        $page = 1;

        $completed = false;


        while (!$completed) {
            // code...
            echo "GETING PAGE " . $page;
            $photo_response = $this->f->photosets_getPhotos($flickrSetID, 'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description', NULL, NULL, $page);
            $page++;

            $photos = $photo_response['photoset']['photo'];
            $completed = (count($photos) != 500);


            echo "COUNT:" . count($photos);
            echo "COMPLETED?:" . $completed;

            foreach ($photos as $key => $photo) {
                $title = $photo['title'];
                $takenAt = $photo['datetaken'];
                $dateParts = split(' ', $takenAt);
                $date = $dateParts[0];

                if (!isset($dateToImages[$date])) {
                    $dateToImages[$date] = array();
                }

                array_push($dateToImages[$date], $photo);


                echo $date . " :: " . $title;
                echo "\n";
            }
        }


        echo "************ DONE";

        foreach ($dateToImages as $date => $photosForDate) {
            echo "DATE:" . $date . "\n";
            $firstPic = $dateToImages[$date][0]['id'];

            $set = $this->f->photosets_create('Moblog ' . $date, 'Mobile photos for ' . $date, $firstPic);
            var_dump($set);

            $setId = $set['id'];


            //      function photosets_addPhoto ($photoset_id, $photo_id) {


            foreach ($dateToImages[$date] as $key => $image) {
                echo "\t" . $image['title'] . "\n";
                $this->f->photosets_addPhoto($setId, $image['id']);
                //      function photos_addTags ($photo_id, $tags) {

                $this->f->photos_addTags($image['id'], "moblog iphone3g");
            };

            echo "SET COMPLETED\n\n\n\n";
        }

    }





    //FIXME - oreintation missing
    /*
    mysql> update FlickrPhoto set Orientation = 90 where (ThumbnailWidth > ThumbnailHeight);
Query OK, 70 rows affected (0.01 sec)
Rows matched: 70  Changed: 70  Warnings: 0

mysql> update FlickrPhoto set Orientation = 0 where (ThumbnailWidth < ThumbnailHeight);
Query OK, 53 rows affected (0.00 sec)
Rows matched: 53  Changed: 53  Warnings: 0

*/

    public function importSearchToYML()
    {
        $searchParams = array();

        $query = $_GET['q'];
        $searchParams['text'] = $query;
        $searchParams['license'] = 7;
        $searchParams['per_page'] = 100;
        $searchParams['extras'] = 'description, license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_q, url_m, url_n, url_z, url_c, url_l, url_o';
        $searchParams['sort'] = 'relevance'; // 'interestingness-desc'; // also try relevance

        $data = $this->f->photos_search($searchParams);

        $dumper = new Dumper();
        $fixtures = array();
        $ctr = 1;

        $apertures = array('2.8', '5.6', '11', '16', '22');
        $shutterSpeeds = array('1/100', '1/30', '1/15', '1/2', '2', '6', '2/250');
        $isos = array(64, 100, 200, 400, 800, 1600, 2000, 3200);
        $focalLengths = array(24, 50, 80, 90, 120, 150, 200);

        foreach ($data['photo'] as $photo) {
            // the image URL becomes somthing like
            // http://farm{farm-id}.static.flickr.com/{server-id}/{id}_{secret}.jpg
            //
            $photoid = str_pad("$ctr", 4, '0', STR_PAD_LEFT);
            /*
            FlickrPhoto:
              photo0001:
                Title: Bangkok
                Description: Test photograph
                FlickrID: 1234567
                TakenAt: 24/4/2012 18:12
                FirstViewed: 28/4/2012
                Aperture: 8.0
                ShutterSpeed: 1/100
                FocalLength35mm: 140
                ISO: 400
                MediumURL: 'http://www.test.com/test.jpg',
                MediumHeight: 400,
                MediumWidth: 300

                sed s/\{\ \_content\:\ // | sed s/\ \}//
                sudo -u www-data framework/sake flickr/importSearch q='Bangkok Thailand' | sed s/\{\ \_content\:\ // | sed s/\ \}// > /home/gordon/work/git/weboftalent/moduletest/www/elastica/tests/lotsOfPhotos.yml

             */
            $currentFixture = array();
            $currentFixture['FlickrID'] = intval($photo['id']);
            $currentFixture['Title'] = $photo['title'];
            $descBlob = $photo['description']['_content'];
            $description = '';


            $splits = preg_split('/$\R?^/m', $descBlob);

            foreach ($splits as $line) {
                $description .= "<p>";
                $description .= $line;
                $description .= "</p>";
            }

            $currentFixture['Description'] = $description;
            $currentFixture['Aperture'] = $apertures[array_rand($apertures)];
            $currentFixture['ShutterSpeed'] = $shutterSpeeds[array_rand($shutterSpeeds)];
            $currentFixture['FocalLength35mm'] = $focalLengths[array_rand($focalLengths)];
            $currentFixture['ISO'] = $isos[array_rand($isos)];
            $currentFixture['IndexingOff'] = true;


            /*
                static $belongs_many_many = array(
                    'FlickrSets' => 'FlickrSet'
                );

                //1 to many
                static $has_one = array(
                    'Photographer' => 'FlickrAuthor'
                );

                //many to many
                static $many_many = array(
                    'FlickrTags' => 'FlickrTag'
                );
             */

            // get owner photo['owner']

            $fixtures['photo' . $photoid] = $currentFixture;


            $ctr++;
            $url = "https://www.flickr.com/photos/{$photo['owner']}/{$photo["id"]}/";
        }
        $fixtures = array('FlickrPhoto' => $fixtures);
        $yaml = $dumper->dump($fixtures, 3);
        echo $yaml;
        file_put_contents('/tmp/test.yml', $yaml);
        $cmd = 'cat elastica/tests/lotsOfPhotos.yml | sed s/\{\ \_content\:\ // | sed s/\ \}//';//.
        //' > /home/gordon/work/git/weboftalent/moduletest/www/elastica/tests/lotsOfPhotos.yml';
        //exec($cmd);
    }


    public function importFromSearch()
    {
        $searchParams = array();

        //any high number
        $nPages = 1e7;

        $page = 1;
        $ctr = 1;


        while ($page <= $nPages) {
            echo "\n\nLoading $page / $nPages\n";
            $query = $_GET['q'];
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
            echo "n photos returned " . sizeof($data['photo']);


            foreach ($data['photo'] as $photo) {
                print_r($photo);

                echo "Import photo $ctr / $totalImages, page $page / $nPages\n";
                $flickrPhoto = $this->createFromFlickrArray($photo);
                echo "\tLoading exif data\n";
                $flickrPhoto->loadExif();
                $ctr++;
            }
            $page++;
        }
    }


    public function importSet()
    {
        $page = 1;
        static $only_new_photos = false;

        $canAccess = (Director::isDev() || Director::is_cli() || Permission::check("ADMIN"));
        if (!$canAccess) return Security::permissionFailure($this);
        /*
        // For testing
        $flickrPhoto = FlickrPhoto::get()->filter('ID',100)->first();
        $flickrPhoto->loadExif();
        die;
        */

        // Code for the register action here
        $flickrSetID = $this->request->param('ID');
        $path = $_GET['path'];
        $parentNode = SiteTree::get_by_link($path);
        if ($parentNode == null) {
            echo "ERROR: Path " . $path . " cannot be found in this site\n";
            die;
        }

        $this->FlickrSetId = $flickrSetID;

        $photos = $this->f->photosets_getPhotos($flickrSetID,
            'license, date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_o, url_l,description',
            null,
            500);

        $photoset = $photos['photoset'];

        $flickrSet = $this->getFlickrSet($flickrSetID);

        // reload from DB with date - note the use of quotes as flickr set id is a string
        $flickrSet = DataObject::get_one('FlickrSet', 'FlickrID=\'' . $flickrSetID . "'");
        $flickrSet->FirstPictureTakenAt = $photoset['photo'][0]['datetaken'];
        $flickrSet->KeepClean = true;
        $flickrSet->Title = $photoset['title'];
        $flickrSet->write();

        echo "Title set to : " . $flickrSet->Title;

        if ($flickrSet->Title == null) {
            echo("ABORTING DUE TO NULL TITLE FOUND IN SET - ARE YOU AUTHORISED TO READ SET INFO?");
            die;
        }

        $datetime = explode(' ', $flickrSet->FirstPictureTakenAt);
        $datetime = $datetime[0];

        list($year, $month, $day) = explode('-', $datetime);
        echo "Month: $month; Day: $day; Year: $year<br />\n";

        // now try and find a flickr set page
        $flickrSetPage = DataObject::get_one('FlickrSetPage', 'FlickrSetForPageID=' . $flickrSet->ID);
        if (!$flickrSetPage) {
            $flickrSetPage = new FlickrSetPage();
            $flickrSetPage->Title = $photoset['title'];
            $flickrSetPage->Description = $flickrSet->Description;

            //update FlickrSetPage set Description = (select Description from FlickrSet where FlickrSet.ID = FlickrSetPage.FlickrSetForPageID);

            $flickrSetPage->FlickrSetForPageID = $flickrSet->ID;
            $flickrSetPage->write();
            // create a stage version also

        }
        $flickrSetPage->Title = $photoset['title'];

        $flickrSetPage->ParentID = $parentNode->ID;
        $flickrSetPage->write();
        $flickrSetPage->publish("Live", "Stage");

        $flickrSetPageID = $flickrSetPage->ID;
        gc_enable();

        $f1 = Folder::find_or_make("flickr/$year");
        $f1->Title = $year;
        $f1->write();

        $f1 = Folder::find_or_make("flickr/$year/$month");
        $f1->Title = $month;
        $f1->write();

        $f1 = Folder::find_or_make("flickr/$year/$month/$day");
        $f1->Title = $day;
        $f1->write();

        exec("chmod 775 ../assets/flickr/$year");
        exec("chmod 775 ../assets/flickr/$year/$month");
        exec("chmod 775 ../assets/flickr/$year/$month/$day");
        exec("chown gordon:www-data ../assets/flickr/$year");;
        exec("chown gordon:www-data ../assets/flickr/$year/$month");;
        exec("chown gordon:www-data ../assets/flickr/$year/$month/$day");;


        $folder = Folder::find_or_make("flickr/$year/$month/$day/" . $flickrSetID);

        $cmd = "chown gordon:www-data ../assets/flickr";
        exec($cmd);

        exec('chmod 775 ../assets/flickr');


        // new folder case
        if ($flickrSet->AssetFolderID == 0) {
            $flickrSet->AssetFolderID = $folder->ID;
            $folder->Title = $flickrSet->Title;
            $folder->write();

            $cmd = "chown gordon:www-data ../assets/flickr/$year/$month/$day/" . $flickrSetID;
            exec($cmd);

            $cmd = "chmod 775 ../assets/flickr/$year/$month/$day/" . $flickrSetID;
            exec($cmd);
        }

        $flickrSetAssetFolderID = $flickrSet->AssetFolderID;

        $flickrSetPageDatabaseID = $flickrSetPage->ID;


        //$flickrSet = NULL;
        $flickrSetPage = NULL;

        $numberOfPics = count($photoset['photo']);
        $ctr = 1;
        foreach ($photoset['photo'] as $key => $value) {
            echo "Importing photo {$ctr}/${numberOfPics}\n";

            $flickrPhoto = $this->createFromFlickrArray($value);

            if ($value['isprimary'] == 1) {
                $flickrSet->MainImage = $flickrPhoto;
            }


            $flickrPhoto->write();
            $flickrSet->FlickrPhotos()->add($flickrPhoto);
            gc_collect_cycles();

            $flickrPhoto->write();
            gc_collect_cycles();

            if (!$flickrPhoto->LocalCopyOfImage) {


                //mkdir appears to be relative to teh sapphire dir
                $structure = "../assets/flickr/$year/$month/$day/" . $flickrSetID;

                if (!file_exists('../assets/flickr')) {
                    echo "Creating path:" . $structure;

                    /*
                    // To create the nested structure, the $recursive parameter
                    // to mkdir() must be specified.

                    if (!mkdir($structure, 0, true)) {
                     //   die('Failed to create folders...');
                    }

                    $cmd = "chown  gordon:www-data $structure";
                    exec($cmd);

                    $cmd = "chown gordon:www-data ../assets/Uploads/flickr";
                    exec($cmd);

                    exec('chmod 775 ../assets/Uploads/flickr');
                    exec("chmod 775 $structure");


                    error_log("Created dir?");
                } else {
                    echo "Dir already exists";
                }


                */
                    $galleries = Folder::find_or_make('flickr');
                    $galleries->Title = 'Flickr Images';
                    $galleries->write();
                    $galleries = NULL;
                }

                $download_images = Config::inst()->get($this->class, 'download_images');

                if ($download_images && !($flickrPhoto->LocalCopyOfImageID)) {
                    $largeURL = $flickrPhoto->LargeURL;
                    $fpid = $flickrPhoto->FlickrID;

                    $cmd = "wget -O $structure/$fpid.jpg $largeURL";
                    exec($cmd);

                    $cmd = "chown  gordon:www-data $structure/$fpid.jpg";
                    // $cmd = "pwd";
                    echo "EXECCED:" . exec($cmd);

                    $image = new Image();
                    $image->Name = $this->Title;
                    $image->Title = $this->Title;
                    $image->Filename = str_replace('../', '', $structure . '/' . $fpid . ".jpg");
                    $image->Title = $flickrPhoto->Title;
                    //$image->Name = $flickrPhoto->Title;
                    $image->ParentID = $flickrSetAssetFolderID;
                    gc_collect_cycles();

                    $image->write();
                    gc_collect_cycles();

                    $flickrPhoto->LocalCopyOfImageID = $image->ID;
                    $flickrPhoto->write();
                    $image = NULL;
                }

                $result = $flickrPhoto->write();
            }

            $ctr++;

            $flickrPhoto = NULL;
        }

        //update orientation
        $sql = 'update FlickrPhoto set Orientation = 90 where ThumbnailHeight > ThumbnailWidth;';
        DB::query($sql);


        // now download exifs
        $ctr = 0;
        foreach ($photoset['photo'] as $key => $value) {
            echo "IMPORTING EXIF {$ctr}/$numberOfPics\n";
            $flickrPhotoID = $value['id'];
            $flickrPhoto = FlickrPhoto::get()->filter('FlickrID', $flickrPhotoID)->first();
            $flickrPhoto->loadExif();
            $flickrPhoto->write();
            $ctr++;
        }

        $this->fixSetMainImages();
        $this->fixDateSetTaken();

        die(); // abort rendering
    }


    private function createFromFlickrArray($value, $only_new_photos = false)
    {
        gc_collect_cycles();

        $flickrPhotoID = $value['id'];

        // the author, e.g. gordonbanderson
        $pathalias = $value['pathalias'];

        // do we have a set object or not
        $flickrPhoto = DataObject::get_one('FlickrPhoto', 'FlickrID=' . $flickrPhotoID);

        // if a set exists update data, otherwise create
        if (!$flickrPhoto) {
            $flickrPhoto = new FlickrPhoto();
        } // if we are in the mode of only importing new then skip to the next iteration if this pic already exists
        else if ($only_new_photos) {
            //@todo This logic seems incorrect
            //continue;
        }

        $flickrPhoto->Title = $value['title'];

        $flickrPhoto->FlickrID = $flickrPhotoID;
        $flickrPhoto->KeepClean = true;


        $flickrPhoto->MediumURL = $value['url_m'];
        $flickrPhoto->MediumHeight = $value['height_m'];
        $flickrPhoto->MediumWidth = $value['width_m'];

        $flickrPhoto->SquareURL = $value['url_s'];
        $flickrPhoto->SquareHeight = $value['height_s'];
        $flickrPhoto->SquareWidth = $value['width_s'];


        $flickrPhoto->ThumbnailURL = $value['url_t'];
        $flickrPhoto->ThumbnailHeight = $value['height_t'];
        $flickrPhoto->ThumbnailWidth = $value['width_t'];

        $flickrPhoto->SmallURL = $value['url_s'];
        $flickrPhoto->SmallHeight = $value['height_s'];
        $flickrPhoto->SmallWidth = $value['width_s'];

        // If the image is too small, large size will not be set
        if (isset($value['url_l'])) {
            $flickrPhoto->LargeURL = $value['url_l'];
            $flickrPhoto->LargeHeight = $value['height_l'];
            $flickrPhoto->LargeWidth = $value['width_l'];
        }


        $flickrPhoto->OriginalURL = $value['url_o'];
        $flickrPhoto->OriginalHeight = $value['height_o'];
        $flickrPhoto->OriginalWidth = $value['width_o'];

        $flickrPhoto->Description = 'test';// $value['description']['_content'];

        $author = FlickrAuthor::get()->filter('PathAlias', $pathalias)->first();
        if (!$author) {
            $author = new FlickrAuthor();
            $author->PathAlias = $pathalias;
            $author->write();
        }

        $flickrPhoto->PhotographerID = $author->ID;

        $lat = number_format($value['latitude'], 15);
        $lon = number_format($value['longitude'], 15);


        if ($value['latitude']) {
            $flickrPhoto->Lat = $lat;
            $flickrPhoto->ZoomLevel = 15;
        }
        if ($value['longitude']) {
            $flickrPhoto->Lon = $lon;
        }

        if ($value['accuracy']) {
            $flickrPhoto->Accuracy = $value['accuracy'];
        }

        if (isset($value['geo_is_public'])) {
            $flickrPhoto->GeoIsPublic = $value['geo_is_public'];
        }

        if (isset($value['woeid'])) {
            $flickrPhoto->WoeID = $value['woeid'];
        }

        $singlePhotoInfo = $this->f->photos_getInfo($flickrPhotoID);

        $flickrPhoto->Description = $singlePhotoInfo['photo']['description']['_content'];
        $flickrPhoto->TakenAt = $singlePhotoInfo['photo']['dates']['taken'];
        $flickrPhoto->Rotation = $singlePhotoInfo['photo']['rotation'];

        if (isset($singlePhotoInfo['photo']['visibility'])) {
            $flickrPhoto->IsPublic = $singlePhotoInfo['photo']['visibility']['ispublic'];
        }

        $flickrPhoto->write();

        foreach ($singlePhotoInfo['photo']['tags']['tag'] as $key => $taginfo) {
            $tag = DataObject::get_one('FlickrTag', "\"Value\"='" . $taginfo['_content'] . "'");
            if (!$tag) {
                $tag = new FlickrTag();
            }

            $tag->FlickrID = $taginfo['id'];
            $tag->Value = $taginfo['_content'];
            $tag->RawValue = $taginfo['raw'];
            $tag->write();

            $ftags = $flickrPhoto->FlickrTags();
            $ftags->add($tag);

            $flickrPhoto->write();

            $tag = NULL;
            $ftags = NULL;

            gc_collect_cycles();
        }

        return $flickrPhoto;
    }


    /*
    Either get the set from the database, or if it does not exist get the details from flickr and add it to the database
    */
    private function getFlickrSet($flickrSetID)
    {
        // do we have a set object or not
        $flickrSet = DataObject::get_one('FlickrSet', 'FlickrID=\'' . $flickrSetID . "'");

        // if a set exists update data, otherwise create
        if (!$flickrSet) {
            $flickrSet = new FlickrSet();
            $setInfo = $this->f->photosets_getInfo($flickrSetID);
            $setTitle = $setInfo['title']['_content'];
            $setDescription = $setInfo['description']['_content'];
            $flickrSet->Title = $setTitle;
            $flickrSet->Description = $setDescription;
            $flickrSet->FlickrID = $flickrSetID;
            $flickrSet->KeepClean = true;
            $flickrSet->write();
        }

        return $flickrSet;
    }


    public function editprofile()
    {
        // Code for the editprofile action here
        return array();
    }

}
