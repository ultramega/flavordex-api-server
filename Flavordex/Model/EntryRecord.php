<?php

namespace Flavordex\Model;

/**
 * Model for an entry record.
 *
 * @author Steve Guidetti
 */
class EntryRecord extends Model {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $uuid;

    /**
     * @var int
     */
    public $cat;

    /**
     * @var string
     */
    public $catUuid;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $maker;

    /**
     * @var string
     */
    public $origin;

    /**
     * @var string
     */
    public $price;

    /**
     * @var string
     */
    public $location;

    /**
     * @var int
     */
    public $date;

    /**
     * @var float
     */
    public $rating;

    /**
     * @var string
     */
    public $notes;

    /**
     * @var int
     */
    public $age;

    /**
     * @var boolean
     */
    public $shared;

    /**
     * @var boolean
     */
    public $deleted;

    /**
     * @var ExtraRecord[]
     */
    public $extras;

    /**
     * @var FlavorRecord[]
     */
    public $flavors;

    /**
     * @var PhotoRecord[]
     */
    public $photos;

    protected function parseJson(\stdClass $json) {
        parent::parseJson($json);
        if(is_array($json->extras)) {
            $this->extras = array();
            foreach($json->extras as $extra) {
                $this->extras[] = new ExtraRecord($extra);
            }
        }
        if(is_array($json->flavors)) {
            $this->flavors = array();
            foreach($json->flavors as $flavor) {
                $this->flavors[] = new FlavorRecord($flavor);
            }
        }
        if(is_array($json->photos)) {
            $this->photos = array();
            foreach($json->photos as $photo) {
                $this->photos[] = new PhotoRecord($photo);
            }
        }
    }

}
