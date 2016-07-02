<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 403 Forbidden error.
 * 
 * @author Steve Guidetti
 */
class ForbiddenException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Forbidden', 403, $message);
    }

}
