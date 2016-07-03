<?php

namespace Flavordex\Model;

/**
 * Model containing lists of deleted and updated categories and entries.
 *
 * @author Steve Guidetti
 */
class SyncRecord extends Model {

    /**
     * @var array[string]int Map of category UUIDs to update ages
     */
    public $deletedCats;

    /**
     * @var array[string]int Map of category UUIDs to update ages
     */
    public $updatedCats;

    /**
     * @var array[string]int Map of entry UUIDs to update ages
     */
    public $deletedEntries;

    /**
     * @var array[string]int Map of entry UUIDs to update ages
     */
    public $updatedEntries;

    protected function parseJson(\stdClass $json) {
        parent::parseJson($json);
        if(is_array($json->deletedCats)) {
            $this->deletedCats = $json->deletedCats;
        }
        if(is_array($json->updatedCats)) {
            $this->updatedCats = $json->updatedCats;
        }
        if(is_array($json->deletedEntries)) {
            $this->deletedEntries = $json->deletedEntries;
        }
        if(is_array($json->updatedEntries)) {
            $this->updatedEntries = $json->updatedEntries;
        }
    }

}
