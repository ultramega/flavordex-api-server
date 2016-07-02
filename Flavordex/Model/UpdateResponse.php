<?php

namespace Flavordex\Model;

/**
 * Model containing the results of an update request.
 *
 * @author Steve Guidetti
 */
class UpdateResponse extends Model {

    /**
     * @var boolean
     */
    public $success;

    /**
     * @var int
     */
    public $remoteId;

}
