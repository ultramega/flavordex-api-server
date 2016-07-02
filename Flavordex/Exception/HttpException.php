<?php

namespace Flavordex\Exception;

/**
 * Exception for reporting errors as HTTP responses.
 * 
 * @author Steve Guidetti
 */
class HttpException extends \RuntimeException {

    /**
     * @var string The detailed error message
     */
    private $details;

    /**
     * @param string $message The error message
     * @param int $code The error code
     * @param string $details Detailed error message
     */
    public function __construct($message, $code, $details = null) {
        parent::__construct($message, $code, null);
        $this->details = (string)$details;
    }

    /**
     * Get the detailed error message.
     * 
     * @return string
     */
    public function getDetails() {
        return $this->details;
    }

    public function output() {
        $code = $this->getCode();
        $error = $this->getMessage();
        $message = $this->getDetails();
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $error);
        echo json_encode(compact('code', 'error', 'message'));
    }

}
