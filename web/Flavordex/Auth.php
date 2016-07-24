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

namespace Flavordex;

use Firebase\JWT\JWT;
use Flavordex\Exception\UnauthorizedException;

/**
 * Contains data for an authenticated user.
 *
 * @author Steve Guidetti
 */
class Auth {

    /**
     * @var Auth The singleton instance of Auth
     */
    private static $instance;

    /**
     * @var string The unique ID of the user
     */
    private $uid;

    /**
     * @param string $uid The unique ID of the user
     */
    private function __construct($uid) {
        $this->uid = $uid;
    }

    /**
     * Get the authentication data for the user.
     * 
     * @return Auth An instance of Auth or null if the user is not authenticated
     */
    public static function getAuth() {
        if(!self::$instance && array_key_exists('HTTP_AUTH_TOKEN', $_SERVER)) {
            JWT::$leeway = 8;
            $keys = json_decode(file_get_contents('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'), true);
            $jwt = JWT::decode($_SERVER['HTTP_AUTH_TOKEN'], $keys, array('RS256'));
            if($jwt->aud != Config::FB_PROJECT_ID) {
                throw new UnauthorizedException('Invalid audience');
            }
            if($jwt->iss != 'https://securetoken.google.com/' . Config::FB_PROJECT_ID) {
                throw new UnauthorizedException('Invalid issuer');
            }
            if(empty($jwt->sub)) {
                throw new UnauthorizedException('User not specified');
            }
            self::$instance = new Auth($jwt->sub);
        }
        return self::$instance;
    }

    /**
     * Get the unique ID of the user.
     * 
     * @return string The unique ID of the user
     */
    public function getUid() {
        return $this->uid;
    }

}
