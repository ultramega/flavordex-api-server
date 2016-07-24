<?php

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
