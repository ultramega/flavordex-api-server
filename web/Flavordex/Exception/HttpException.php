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
