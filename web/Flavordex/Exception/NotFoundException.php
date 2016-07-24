<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 404 Not Found error.
 * 
 * @author Steve Guidetti
 */
class NotFoundException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Not Found', 404, $message);
    }

}
