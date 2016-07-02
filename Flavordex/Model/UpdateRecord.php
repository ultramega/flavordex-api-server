<?php

namespace Flavordex\Model;

/**
 * Model for a data update containing records that have changed.
 *
 * @author Steve Guidetti
 */
class UpdateRecord extends Model {

    /**
     * @var int
     */
    public $timestamp;

    /**
     * @var EntryRecord[]
     */
    public $entries;

    /**
     * @var CatRecord[]
     */
    public $cats;

    protected function parseJson(\stdClass $json) {
        parent::parseJson($json);
        if(is_array($json->entries)) {
            $this->entries = array();
            foreach($json->entries as $entry) {
                $this->entries[] = new EntryRecord($entry);
            }
        }
        if(is_array($json->cats)) {
            $this->cats = array();
            foreach($json->cats as $cat) {
                $this->cats[] = new CatRecord($cat);
            }
        }
    }

}
