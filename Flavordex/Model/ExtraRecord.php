<?php

namespace Flavordex\Model;

/**
 * Model for an extra record.
 *
 * @author Steve Guidetti
 */
class ExtraRecord extends Model {

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
    public $name;

    /**
     * @var string
     */
    public $value;

    /**
     * @var int
     */
    public $pos;

    /**
     * @var boolean
     */
    public $deleted;

}
