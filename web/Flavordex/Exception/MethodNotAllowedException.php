<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 405 Method Not Allowed error.
 * 
 * @author Steve Guidetti
 */
class MethodNotAllowedException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Method Not Allowed', 405, $message);
    }

}
