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

namespace Flavordex\Endpoint;

use Flavordex\Auth;
use Flavordex\Exception\MethodNotAllowedException;
use Flavordex\Exception\UnauthorizedException;

/**
 * Base class for all endpoints.
 *
 * @author Steve Guidetti
 */
abstract class Endpoint {

    /**
     * @var string The raw POST data cache
     */
    private static $postData;

    /**
     * Get the user authorization or throw an exception.
     * 
     * @return Auth
     * @throws UnauthorizedException
     */
    protected static function getAuth() {
        $auth = Auth::getAuth();
        if(!$auth) {
            throw new UnauthorizedException('This method requires authorization');
        }
        return $auth;
    }

    /**
     * Get the raw POST data.
     * 
     * @return string
     */
    protected static function getPost() {
        if(!isset(self::$postData)) {
            self::$postData = file_get_contents('php://input');
        }
        return self::$postData;
    }

    /**
     * Require HTTP POST for this request.
     * 
     * @throws MethodNotAllowedException
     */
    protected static function requirePost() {
        if(!self::isPost()) {
            throw new MethodNotAllowedException('This method must be accessed via POST');
        }
    }

    /**
     * Check whether to this request was made via HTTP POST.
     * 
     * @return boolean
     */
    protected static function isPost() {
        return strcasecmp('post', $_SERVER['REQUEST_METHOD']) == 0;
    }

}
