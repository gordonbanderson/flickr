<?php

namespace Suilven\Flickr\Admin;


use SilverStripe\Admin\ModelAdmin;
use SilverStripe\ORM\DataList;
use SilverStripe\View\Requirements;

class FlickrSetPageFolderAdmin extends ModelAdmin
{
    private static $url_segment = 'flickrsetpagefolders';
    private static $menu_title = 'Flickr Set Folders';

    private static $managed_models = array('FlickrSetPage');

    private static $menu_icon = '/flickr/icons/folders.png';


    /**
     *
     * @var QueuedJobService
     */
    public $jobQueue;

    public function EditForm($request = null)
    {
        $form = parent::EditForm($request);

        Requirements::javascript(FLICKR_EDIT_TOOLS_PATH . '/javascript/flickredit.js');
        Requirements::css(FLICKR_EDIT_TOOLS_PATH . '/css/flickredit.css');


        $flickrSetPages = DataList::create('FlickrSetPage')->where('ParentID = 0');//->sort('Title desc');
        $flickrSetFolders = DataList::create('FlickrSetFolder')->sort('Title');

        $html = '<h2>Flickr Set Folders</h2><div id="flickrFolders">';
        foreach ($flickrSetFolders as $key => $folder) {
            $html .= '<div class="flickrSetFolderDroppable" data-id="' . $folder->ID . '">' . $folder->Title . '</div>';
        }

        $html .= '</div><h2>Flickr Set Pages</h2><div id="flickrSetPages">';
        foreach ($flickrSetPages as $key => $fspage) {
            $html .= '<div class="flickrSetDraggable" data-id="' . $fspage->ID . '">' . $fspage->Title . '</div>';
        }

        $html .= '</div>';


        $lf = new LiteralField('FlickrSetOrganizer', $html);
        //$form->Fields()->push($lf);
        $fl = new FieldList();
        $fl->push($lf);
        $form->setFields($fl);

        return $form;
    }

    public function Tools()
    {
        return '';
    }
}
