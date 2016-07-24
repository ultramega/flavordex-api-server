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
 * Base class for all Models.
 *
 * @author Steve Guidetti
 */
abstract class Model {

    /**
     * @param string|object $fromJson A JSON string or object from which to parse data
     */
    public function __construct($fromJson = null) {
        if($fromJson) {
            $json = is_object($fromJson) ? $fromJson : json_decode($fromJson);
            if($json) {
                $this->parseJson($json);
            }
        }
    }

    /**
     * Parse data from a JSON object.
     * 
     * @param \stdClass $json
     */
    protected function parseJson(\stdClass $json) {
        foreach(array_keys(get_object_vars($json)) as $var) {
            if(is_scalar($json->$var)) {
                $this->$var = $json->$var;
            }
        }
    }

}
