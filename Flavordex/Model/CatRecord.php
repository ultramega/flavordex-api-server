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

}
