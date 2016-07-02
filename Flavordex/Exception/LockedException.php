<?php

namespace Flavordex\Exception;

/**
 * Exception to report a 423 Locked error.
 * 
 * @author Steve Guidetti
 */
class LockedException extends HttpException {

    /**
     * @param string $message The error message
     */
    public function __construct($message = null) {
        parent::__construct('Locked', 423, $message);
    }

}
