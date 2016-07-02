<?php

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
