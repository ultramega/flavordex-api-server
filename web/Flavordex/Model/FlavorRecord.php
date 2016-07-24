<?php

namespace Flavordex\Model;

/**
 * Model for a flavor record.
 *
 * @author Steve Guidetti
 */
class FlavorRecord extends Model {

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $cat;

    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $value;

    /**
     * @var int
     */
    public $pos;

}
