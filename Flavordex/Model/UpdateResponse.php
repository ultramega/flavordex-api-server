<?php

namespace Flavordex\Model;

/**
 * Model for a response to a push request.
 *
 * @author Steve Guidetti
 */
class UpdateResponse extends Model {

    /**
     * @var array[string]boolean
     */
    public $catStatuses;

    /**
     * @var array[string]boolean
     */
    public $entryStatuses;

    /**
     * @var array[string]int
     */
    public $entryIds;
    
    protected function parseJson(\stdClass $json) {
        parent::parseJson($json);
        if(is_array($json->catStatuses)) {
            $this->catStatuses = $json->catStatuses;
        }
        if(is_array($json->entryStatuses)) {
            $this->entryStatuses = $json->entryStatuses;
        }
        if(is_array($json->entryIds)) {
            $this->entryIds = $json->entryIds;
        }
    }

}
