<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 400 Bad Request error.
 * 
 * @author Steve Guidetti
 */
class BadRequestException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Bad Request', 400, $message);
    }

}
