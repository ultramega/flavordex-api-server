<?php

namespace Flavordex\Model;

/**
 * Model for a photo record.
 *
 * @author Steve Guidetti
 */
class PhotoRecord extends Model {

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $entry;

    /**
     * @var string
     */
    public $hash;

    /**
     * @var string
     */
    public $driveId;

    /**
     * @var int
     */
    public $pos;

}
