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

use Flavordex\Config;
use Flavordex\DatabaseHelper;
use Flavordex\Exception\LockedException;
use Flavordex\Model\CatRecord;
use Flavordex\Model\EntryRecord;
use Flavordex\Model\SyncRecord;
use Flavordex\Model\UpdateResponse;

/**
 * The sync endpoint for synchronizing journal data between the client and the server.
 *
 * @author Steve Guidetti
 */
class SyncEndpoint extends Endpoint {

    /**
     * Start a synchronization session.
     * 
     * @param int $clientId The database ID of the client
     * @throws LockedException
     */
    public function startSync($clientId) {
        self::requirePost();
        $auth = self::getAuth();

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->setClientId($clientId);
        if(!$helper->getLock()) {
            throw new LockedException('Unable to obtain an exclusive lock');
        }
    }

    /**
     * End the synchronization session.
     * 
     * @param int $clientId The database ID of the client
     */
    public function endSync($clientId) {
        self::requirePost();
        $auth = self::getAuth();

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->setClientId($clientId);

        $notify = $helper->changesPending();

        $helper->releaseLock();

        if($notify) {
            self::notifyClients($helper);
        }
    }

    /**
     * Get a list of deleted and updated categories and entries.
     * 
     * @param int $clientId The database ID of the client
     * @return SyncRecord
     */
    public function getUpdates($clientId) {
        $helper = self::getLockedHelper($clientId);

        $record = new SyncRecord();
        $record->deletedCats = $helper->getDeletedCats();
        $record->updatedCats = $helper->getUpdatedCats();
        $record->deletedEntries = $helper->getDeletedEntries();
        $record->updatedEntries = $helper->getUpdatedEntries();

        return $record;
    }

    /**
     * Get a single category.
     * 
     * @param int $clientId The database ID of the client
     * @param string $catUuid The UUID of the category
     * @return CatRecord
     */
    public function getCat($clientId, $catUuid) {
        $helper = self::getLockedHelper($clientId);
        return $helper->getCat($catUuid);
    }

    /**
     * Send a single category.
     * 
     * @param int $clientId The database ID of the client
     * @return UpdateResponse
     */
    public function putCat($clientId) {
        self::requirePost();
        $helper = self::getLockedHelper($clientId);
        $cat = new CatRecord(self::getPost());

        $response = new UpdateResponse();
        $response->success = $helper->pushCat($cat);
        $response->remoteId = $cat->id;

        return $response;
    }

    /**
     * Get a single entry.
     * 
     * @param int $clientId The database ID of the client
     * @param string $entryUuid The UUID of the entry
     * @return EntryRecord
     */
    public function getEntry($clientId, $entryUuid) {
        $helper = self::getLockedHelper($clientId);
        return $helper->getEntry($entryUuid);
    }

    /**
     * Send a single entry.
     * 
     * @param int $clientId The database ID of the client
     * @return UpdateResponse
     */
    public function putEntry($clientId) {
        self::requirePost();
        $helper = self::getLockedHelper($clientId);
        $entry = new EntryRecord(self::getPost());

        $response = new UpdateResponse();
        $response->success = $helper->pushEntry($entry);
        $response->remoteId = $entry->id;

        return $response;
    }

    /**
     * Get an authenticated database helper and refresh the exclusive lock.
     * 
     * @param int $clientId The database ID of the client
     * @return DatabaseHelper
     * @throws LockedException
     */
    private static function getLockedHelper($clientId) {
        $auth = self::getAuth();
        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->setClientId($clientId);
        if(!$helper->touchLock()) {
            throw new LockedException('Client does not have an exclusive lock');
        }

        return $helper;
    }

    /**
     * Notify all clients belonging to the user the a sync is requested.
     * 
     * @param DatabaseHelper $helper
     */
    private static function notifyClients(DatabaseHelper $helper) {
        $clients = $helper->listFcmIds();
        if(count($clients) < 2) {
            return;
        }

        $opts = array('http' => array(
                'method' => 'POST',
                'header' => array(
                    'Authorization: key=' . Config::FCM_API_KEY,
                    'Content-Type: application/json'
                )
            )
        );
        $content = array(
            'collapse_key' => 'requestKey',
            'delay_while_idle' => true,
            'registration_ids' => array()
        );

        $fcmIds = array();
        foreach($clients as $id => $fcmId) {
            if($id != $helper->getClientId()) {
                $content['registration_ids'][] = $fcmId;
                $fcmIds[] = $id;
            }
        }

        $opts['http']['content'] = json_encode($content);
        $context = stream_context_create($opts);
        $response = json_decode(file_get_contents('https://fcm.googleapis.com/fcm/send', false, $context));

        if($response && ($response->failure || $response->canonical_ids)) {
            for($i = 0; $i < count($response->results); $i++) {
                if(isset($response->results[$i]->message_id)) {
                    if(isset($response->results[$i]->registration_ids)) {
                        $helper->setFcmId($fcmIds[$i], $response->results[$i]->registration_ids);
                    }
                } elseif(isset($response->results[$i]->error)) {
                    if($response->results[$i]->error == 'NotRegistered') {
                        $helper->unregisterClient($fcmIds[$i]);
                    }
                }
            }
        }
    }

}
