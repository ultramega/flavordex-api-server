<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 401 Unauthorized error.
 * 
 * @author Steve Guidetti
 */
class UnauthorizedException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Unauthorized', 401, $message);
    }

}
