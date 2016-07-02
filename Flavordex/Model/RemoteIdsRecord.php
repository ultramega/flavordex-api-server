<?php

namespace Flavordex\Model;

/**
 * Model containing a map of entry UUIs to remote IDs.
 *
 * @author Steve Guidetti
 */
class RemoteIdsRecord extends Model {

    /**
     * @var array[string]int
     */
    public $entryIds;

    protected function parseJson(\stdClass $json) {
        parent::parseJson($json);
        if(is_array($json->entryIds)) {
            $this->entryIds = $json->entryIds;
        }
    }

}
