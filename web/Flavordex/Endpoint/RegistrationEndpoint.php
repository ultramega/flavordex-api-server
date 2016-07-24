<?php

namespace Flavordex\Endpoint;

use Flavordex\DatabaseHelper;
use Flavordex\Exception\UnauthorizedException;

/**
 * The client registration endpoint to register devices with the API.
 *
 * @author Steve Guidetti
 */
class RegistrationEndpoint extends Endpoint {

    /**
     * Register a client device with the backend.
     * 
     * @return RegistrationRecord
     * @throws MethodNotAllowedException
     * @throws UnauthorizedException
     */
    public function register() {
        self::requirePost();
        $auth = self::getAuth();
        
        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        return $helper->registerClient(self::getPost());
    }

    /**
     * Unregister a client device with the backend.
     * 
     * @throws MethodNotAllowedException
     * @throws UnauthorizedException
     */
    public function unregister() {
        self::requirePost();
        $auth = self::getAuth();

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->unregisterClient(self::getPost());
    }

}
