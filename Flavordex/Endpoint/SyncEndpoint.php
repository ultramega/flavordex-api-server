<?php

namespace Flavordex\Endpoint;

use Flavordex\Config;
use Flavordex\DatabaseHelper;
use Flavordex\Exception\UnauthorizedException;
use Flavordex\Model\UpdateRecord;
use Flavordex\Model\UpdateResponse;

/**
 * The sync endpoint for synchronizing journal data between the client and the server.
 *
 * @author Steve Guidetti
 */
class SyncEndpoint extends Endpoint {

    /**
     * Get updated journal data from the server.
     * 
     * @param int $clientId The database ID of the client
     * @return UpdateRecord
     * @throws UnauthorizedException
     */
    public function fetchUpdates($clientId) {
        $auth = self::getAuth();

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->setClientId($clientId);

        $record = new UpdateRecord();
        $record->timestamp = floor(microtime(true) * 1000);
        $record->cats = $helper->getUpdatedCats();
        $record->entries = $helper->getUpdatedEntries();

        return $record;
    }

    /**
     * Send updated journal data to the server.
     * 
     * @param int $clientId The database ID of the client
     * @return UpdateResponse
     * @throws MethodNotAllowedException
     * @throws UnauthorizedException
     */
    public function pushUpdates($clientId) {
        self::requirePost();
        $auth = self::getAuth();

        $record = new UpdateRecord(self::getPost());

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->setClientId($clientId);

        $response = new UpdateResponse();
        $dataChanged = false;

        if($record->cats) {
            $response->catStatuses = array();
            foreach($record->cats as $cat) {
                $status = $helper->pushCat($cat);
                if($status) {
                    $dataChanged = true;
                }
                $response->catStatuses[$cat->uuid] = $status;
            }
        }

        if($record->entries) {
            $response->entryStatuses = array();
            $response->entryIds = array();
            foreach($record->entries as $entry) {
                $status = $helper->pushEntry($entry);
                if($status) {
                    $dataChanged = true;
                }
                $response->entryStatuses[$entry->uuid] = $status;
                $response->entryIds[$entry->uuid] = $entry->id;
            }
        }

        if($dataChanged) {
            $this->notifyClients($helper);
        }

        return $response;
    }

    /**
     * Confirm that the sync was processed by the client.
     * 
     * @param int $clientId The database ID of the client
     * @throws MethodNotAllowedException
     * @throws UnauthorizedException
     */
    public function confirmSync($clientId) {
        self::requirePost();
        $auth = self::getAuth();

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        $helper->setClientId($clientId);
        $helper->setSyncTime(self::getPost());
    }

    /**
     * Get the list of remote entry IDs for the user.
     *
     * @return RemoteIdsRecord
     * @throws UnauthorizedException
     */
    public function getRemoteIds() {
        $auth = self::getAuth();

        $helper = new DatabaseHelper();
        $helper->setUser($auth);
        return $helper->getEntryIds();
    }

    /**
     * Notify all clients belonging to the user the a sync is requested.
     * 
     * @param DatabaseHelper $helper
     */
    private function notifyClients(DatabaseHelper $helper) {
        $opts = array('https' => array(
                'method' => 'POST',
                'header' => array(
                    'Authorization' => Config::FCM_API_KEY,
                    'Content-Type' => 'application/json'
                )
            )
        );
        $content = array(
            'collapse_key' => 'requestKey',
            'registration_ids' => array()
        );

        $fcmIds = array();
        foreach($helper->listFcmIds() as $id => $fcmId) {
            $content['registration_ids'][] = $fcmId;
            $fcmIds[] = $id;
        }

        $opts['https']['content'] = json_encode($content);
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
