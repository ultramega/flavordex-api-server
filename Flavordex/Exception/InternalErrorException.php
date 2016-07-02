<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 500 Internal Server Error error.
 * 
 * @author Steve Guidetti
 */
class InternalErrorException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Internal Server Error', 500, $message);
    }

}
