<?php

namespace Flavordex\Model;

/**
 * Model for a category record.
 *
 * @author Steve Guidetti
 */
class CatRecord extends Model {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $age;

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

    protected function parseJson(\stdClass $json) {
        parent::parseJson($json);
        if(isset($json->extras) && is_array($json->extras)) {
            $this->extras = array();
            foreach($json->extras as $extra) {
                $this->extras[] = new ExtraRecord($extra);
            }
        }
        if(isset($json->flavors) && is_array($json->flavors)) {
            $this->flavors = array();
            foreach($json->flavors as $flavor) {
                $this->flavors[] = new FlavorRecord($flavor);
            }
        }
    }

}
