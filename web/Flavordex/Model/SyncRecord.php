<?php

/*
 * The MIT License
 *
 * Copyright 2016 Steve Guidetti.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

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
        if(isset($json->deletedCats) && is_array($json->deletedCats)) {
            $this->deletedCats = $json->deletedCats;
        }
        if(isset($json->updatedCats) && is_array($json->updatedCats)) {
            $this->updatedCats = $json->updatedCats;
        }
        if(isset($json->deletedEntries) && is_array($json->deletedEntries)) {
            $this->deletedEntries = $json->deletedEntries;
        }
        if(isset($json->updatedEntries) && is_array($json->updatedEntries)) {
            $this->updatedEntries = $json->updatedEntries;
        }
    }

}
