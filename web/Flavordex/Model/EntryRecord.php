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
 * Model for an entry record.
 *
 * @author Steve Guidetti
 */
class EntryRecord extends Model {

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
    public $catUuid;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $maker;

    /**
     * @var string
     */
    public $origin;

    /**
     * @var string
     */
    public $price;

    /**
     * @var string
     */
    public $location;

    /**
     * @var int
     */
    public $date;

    /**
     * @var float
     */
    public $rating;

    /**
     * @var string
     */
    public $notes;

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

    /**
     * @var PhotoRecord[]
     */
    public $photos;

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
        if(isset($json->photos) && is_array($json->photos)) {
            $this->photos = array();
            foreach($json->photos as $photo) {
                $this->photos[] = new PhotoRecord($photo);
            }
        }
    }

}
