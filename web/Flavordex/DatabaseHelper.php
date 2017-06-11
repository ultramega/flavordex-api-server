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

use Flavordex\Exception\UnauthorizedException;
use Flavordex\Model\CatRecord;
use Flavordex\Model\EntryRecord;
use Flavordex\Model\ExtraRecord;
use Flavordex\Model\FlavorRecord;
use Flavordex\Model\PhotoRecord;
use Flavordex\Model\RegistrationRecord;

/**
 * Database access helper.
 *
 * @author Steve Guidetti
 */
class DatabaseHelper {

    /**
     * @var int The number of seconds to persist a lock with no activity
     */
    private static $lockTimeout = Config::LOCK_TIMEOUT;

    /**
     * @var mysqli The database connection
     */
    private $db;

    /**
     * @var int The user ID to use for performing authorized requests
     */
    private $userId;

    /**
     * @var int The database ID of the client making requests
     */
    private $clientId;

    /**
     * Open a connection to the database.
     */
    public function __construct() {
        $this->db = new \mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_DATABASE);
        $this->db->set_charset('utf8');
    }

    /**
     * Close the connection to the database.
     */
    public function __destruct() {
        $this->db->close();
    }

    /**
     * Get the current user ID.
     *
     * @return int The database ID of the client.
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * Set the user to perform authorized requests.
     *
     * @param Auth $auth The authentication data for the user
     */
    public function setUser(Auth $auth) {
        $this->userId = $this->findUserId($auth->getUid());
    }

    /**
     * Get the current client ID.
     *
     * @return int The database ID of the client
     */
    public function getClientId() {
        return $this->clientId;
    }

    /**
     * Set the client to make requests.
     *
     * @param int $clientId The database ID of the client
     * @throws UnauthorizedException
     */
    public function setClientId($clientId) {
        $this->clientId = (int)$clientId;

        $stmt = $this->db->prepare('SELECT 1 FROM clients WHERE id = ? AND user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('ii', $this->clientId, $this->userId);
                if($stmt->execute() && $stmt->fetch()) {
                    return;
                }
            } finally {
                $stmt->close();
            }
        }

        throw new UnauthorizedException('Client not registered.');
    }

    /**
     * Obtain an exclusive lock for the client of the user.
     *
     * @return boolean Whether the lock was successfully obtained
     */
    public function getLock() {
        $stmt = $this->db->prepare('UPDATE clients SET lock_expire = TIMESTAMPADD(SECOND, ?, NOW(3)) WHERE id = ? AND user = ? AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM clients WHERE user = ? AND lock_expire > NOW(3) LIMIT 1) AS t);');
        if($stmt) {
            try {
                $stmt->bind_param('iiii', self::$lockTimeout, $this->clientId, $this->userId, $this->userId);
                if($stmt->execute()) {
                    return $stmt->affected_rows > 0;
                }
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Release the exclusive lock held by the client of the user.
     */
    public function releaseLock() {
        $stmt = $this->db->prepare('UPDATE clients SET last_sync = NOW(3), lock_expire = NULL, changes_pending = 0 WHERE id = ? AND user = ?');
        if($stmt) {
            try {
                $stmt->bind_param('ii', $this->clientId, $this->userId);
                $stmt->execute();
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the exclusive lock expiration time for the client.
     *
     * @return boolean Whether the client still has a lock
     */
    public function touchLock() {
        $stmt = $this->db->prepare('UPDATE clients SET lock_expire = TIMESTAMPADD(SECOND, ?, NOW(3)) WHERE id = ? AND user = ? AND lock_expire > NOW(3);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', self::$lockTimeout, $this->clientId, $this->userId);
                if($stmt->execute()) {
                    return $stmt->affected_rows > 0;
                }
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Check whether there are pending changes from the client.
     *
     * @return boolean
     */
    public function changesPending() {
        $stmt = $this->db->prepare('SELECT changes_pending FROM clients WHERE id = ? AND user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('ii', $this->clientId, $this->userId);
                if($stmt->execute()) {
                    $stmt->bind_result($changed);
                    return $stmt->fetch() && $changed;
                }
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Flag the client as having pending changes.
     */
    private function changed() {
        $stmt = $this->db->prepare('UPDATE clients SET changes_pending = 1 WHERE id = ? AND user = ?');
        if($stmt) {
            try {
                $stmt->bind_param('ii', $this->clientId, $this->userId);
                $stmt->execute();
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Register a client device with the database.
     *
     * @param string $fcmId The Firebase Cloud Messaging ID
     * @return RegistrationRecord
     * @throws UnauthorizedException
     */
    public function registerClient($fcmId) {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $record = new RegistrationRecord();

        $stmt = $this->db->prepare('DELETE FROM clients WHERE user = ? AND fcm_id = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('is', $this->userId, $fcmId);
                $stmt->execute();
            } finally {
                $stmt->close();
            }
        }

        $stmt = $this->db->prepare('INSERT INTO clients (user, fcm_id) VALUES (?, ?);');
        if($stmt) {
            try {
                $stmt->bind_param('is', $this->userId, $fcmId);
                if($stmt->execute()) {
                    $record->clientId = $stmt->insert_id;
                }
            } finally {
                $stmt->close();
            }
        }

        return $record;
    }

    /**
     * Unregister a client device from the database.
     *
     * @param int $clientId The database ID of the client
     * @return boolean Whether the client device was successfully unregistered
     * @throws UnauthorizedException
     */
    public function unregisterClient($clientId) {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $stmt = $this->db->prepare('DELETE FROM clients WHERE id = ? AND user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('ii', $clientId, $this->userId);
                return $stmt->execute() && $stmt->affected_rows > 0;
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Set the Firebase Cloud Messaging ID for the specified client.
     *
     * @param int $clientId The database ID of the client
     * @param string $fcmId The Firebase Cloud ID
     * @return boolean Whether the Firebase Cloud ID was successfully updated
     */
    public function setFcmId($clientId, $fcmId) {
        $stmt = $this->db->prepare('UPDATE clients SET fcm_id = ? WHERE id = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('si', $fcmId, $clientId);
                return $stmt->execute() && $stmt->affected_rows > 0;
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Get a list of Firebase Cloud Messaging IDs for the current user.
     *
     * @return array[int]string A map of client database IDs to Firebase Cloud Messaging IDs
     * @throws UnauthorizedException
     */
    public function listFcmIds() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $stmt = $this->db->prepare('SELECT id, fcm_id FROM clients WHERE user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $this->userId);
                if($stmt->execute()) {
                    $list = array();

                    $stmt->bind_result($id, $fcmId);
                    while($stmt->fetch()) {
                        $list[$id] = $fcmId;
                    }

                    return $list;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Get all entries deleted by other clients since the client's last sync.
     *
     * @return string[] The UUIDs of entries deleted since the last sync
     * @throws UnauthorizedException
     */
    public function getDeletedEntries() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $records = array();

        $stmt = $this->db->prepare('SELECT uuid, TIMESTAMPDIFF(MICROSECOND, sync_time, NOW(3)) FROM deleted WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($uuid, $age);
                    while($stmt->fetch()) {
                        $records[$uuid] = (int)($age / 1000);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return $records;
    }

    /**
     * Get all entries updated by other clients since the client's last sync.
     *
     * @return array[string]int Map of UUIDs to update timestamps of entries updated since the last sync
     * @throws UnauthorizedException
     */
    public function getUpdatedEntries() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $records = array();

        $stmt = $this->db->prepare('SELECT uuid, TIMESTAMPDIFF(MICROSECOND, sync_time, NOW(3)) FROM entries WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($uuid, $age);
                    while($stmt->fetch()) {
                        $records[$uuid] = (int)($age / 1000);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return $records;
    }

    /**
     * Get a single entry.
     *
     * @param type $entryUuid The UUID of the entry
     * @return EntryRecord
     * @throws UnauthorizedException
     */
    public function getEntry($entryUuid) {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $stmt = $this->db->prepare('SELECT a.id, a.uuid, a.cat, b.uuid, a.title, a.maker, a.origin, a.price, a.location, a.date, a.rating, a.notes, TIMESTAMPDIFF(MICROSECOND, a.sync_time, NOW(3)) FROM entries a LEFT JOIN categories b ON a.cat = b.id WHERE a.uuid = ? AND a.user = ? LIMIT 1;');
        if($stmt) {
            try {
                $stmt->bind_param('si', $entryUuid, $this->userId);
                if($stmt->execute()) {
                    $stmt->store_result();
                    $stmt->bind_result($id, $uuid, $cat, $catUuid, $title, $maker, $origin, $price, $location, $date, $rating, $notes, $age);
                    if($stmt->fetch()) {
                        $record = new EntryRecord();
                        $record->id = $id;
                        $record->uuid = $uuid;
                        $record->cat = $cat;
                        $record->catUuid = $catUuid;
                        $record->title = $title;
                        $record->maker = $maker;
                        $record->origin = $origin;
                        $record->price = $price;
                        $record->location = $location;
                        $record->date = $date;
                        $record->rating = $rating;
                        $record->notes = $notes;
                        $record->age = (int)($age / 1000);

                        $record->extras = $this->getEntryExtras($id);
                        $record->flavors = $this->getEntryFlavors($id);
                        $record->photos = $this->getEntryPhotos($id);

                        return $record;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return null;
    }

    /**
     * Get the extras for an entry.
     *
     * @param int $entryId The database ID of the entry
     * @return ExtraRecord[]
     */
    private function getEntryExtras($entryId) {
        $stmt = $this->db->prepare('SELECT a.uuid, a.name, b.value FROM extras a LEFT JOIN entries_extras b ON a.id = b.extra WHERE b.entry = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $entryId);
                if($stmt->execute()) {
                    $records = array();

                    $stmt->bind_result($uuid, $name, $value);
                    while($stmt->fetch()) {
                        $record = new ExtraRecord();
                        $record->uuid = $uuid;
                        $record->name = $name;
                        $record->value = $value;

                        $records[] = $record;
                    }

                    return $records;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Get the flavors for an entry.
     *
     * @param int $entryId The database ID of the entry
     * @return FlavorRecord[]
     */
    private function getEntryFlavors($entryId) {
        $stmt = $this->db->prepare('SELECT flavor, value, pos FROM entries_flavors WHERE entry = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $entryId);
                if($stmt->execute()) {
                    $records = array();

                    $stmt->bind_result($flavor, $value, $pos);
                    while($stmt->fetch()) {
                        $record = new FlavorRecord();
                        $record->name = $flavor;
                        $record->value = $value;
                        $record->pos = $pos;

                        $records[] = $record;
                    }

                    return $records;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Get the photos for an entry.
     *
     * @param int $entryId The database ID of the entry
     * @return PhotoRecord[]
     */
    private function getEntryPhotos($entryId) {
        $stmt = $this->db->prepare('SELECT hash, drive_id, pos FROM photos WHERE entry = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $entryId);
                if($stmt->execute()) {
                    $records = array();

                    $stmt->bind_result($hash, $driveId, $pos);
                    while($stmt->fetch()) {
                        $record = new PhotoRecord();
                        $record->hash = $hash;
                        $record->driveId = $driveId;
                        $record->pos = $pos;

                        $records[] = $record;
                    }

                    return $records;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the entry, inserting, updating, or deleting as indicated.
     *
     * @param EntryRecord $entry
     * @return boolean Whether the operation was successful
     * @throws UnauthorizedException
     */
    public function pushEntry(EntryRecord $entry) {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $success = false;
        $this->db->autocommit(false);

        $stmt = $this->db->prepare('SELECT id, cat FROM entries WHERE uuid = ? AND user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('si', $entry->uuid, $this->userId);
                if($stmt->execute()) {
                    $stmt->bind_result($id, $cat);
                    if($stmt->fetch()) {
                        $entry->id = $id;
                        $entry->cat = $cat;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        if($entry->deleted) {
            $success = $this->deleteEntry($entry);
        } elseif(!$entry->id) {
            $stmt = $this->db->prepare('SELECT id FROM categories WHERE uuid = ? AND user = ?;');
            if($stmt) {
                try {
                    $stmt->bind_param('si', $entry->catUuid, $this->userId);
                    if($stmt->execute()) {
                        $stmt->store_result();
                        $stmt->bind_result($id);
                        if($stmt->fetch()) {
                            $entry->cat = $id;
                            $success = $entry->cat && $this->insertEntry($entry);
                        }
                    }
                } finally {
                    $stmt->close();
                }
            }
        } else {
            $success = $this->updateEntry($entry);
        }

        $this->db->autocommit(true);

        if($success) {
            $this->changed();
        }
        return $success;
    }

    /**
     * Insert a new entry.
     *
     * @param EntryRecord $entry
     * @return boolean Whether the operation was successful
     */
    private function insertEntry(EntryRecord $entry) {
        $stmt = $this->db->prepare('INSERT INTO entries (uuid, user, cat, title, maker, origin, price, location, date, rating, notes, client) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
        if($stmt) {
            try {
                $stmt->bind_param('siissssssdsi', $entry->uuid, $this->userId, $entry->cat, $entry->title, $entry->maker, $entry->origin, $entry->price, $entry->location, $entry->date, $entry->rating, $entry->notes, $this->clientId);
                if($stmt->execute()) {
                    $entry->id = $stmt->insert_id;
                    if($entry->id) {
                        $this->insertEntryExtras($entry);
                        $this->insertEntryFlavors($entry);
                        $this->insertEntryPhotos($entry);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        $stmt = $this->db->prepare('DELETE FROM deleted WHERE user = ? AND uuid = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('is', $this->userId, $entry->uuid);
                $stmt->execute();
            } finally {
                $stmt->close();
            }
        }

        return $entry->id > 0;
    }

    /**
     * Update an entry.
     *
     * @param EntryRecord $entry
     * @return boolean Whether the operation was successful
     */
    private function updateEntry(EntryRecord $entry) {
        $stmt = $this->db->prepare('UPDATE entries SET title = ?, maker = ?, origin = ?, price = ?, location = ?, date = ?, rating = ?, notes = ?, sync_time = NOW(3), client = ? WHERE user = ? AND id = ? AND sync_time < SUBTIME(NOW(3), ? / 1000);');
        if($stmt) {
            try {
                $stmt->bind_param('ssssssdsiiii', $entry->title, $entry->maker, $entry->origin, $entry->price, $entry->location, $entry->date, $entry->rating, $entry->notes, $this->clientId, $this->userId, $entry->id, $entry->age);
                if($stmt->execute() && $stmt->affected_rows) {
                    $this->updateEntryExtras($entry);
                    $this->updateEntryFlavors($entry);
                    $this->updateEntryPhotos($entry);

                    return true;
                }
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Insert the extras for an entry.
     *
     * @param EntryRecord $entry
     */
    private function insertEntryExtras(EntryRecord $entry) {
        if(!$entry->extras) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO entries_extras (entry, extra, value) VALUES (?, (SELECT id FROM extras WHERE cat = ? AND uuid = ?), ?);');
        if($stmt) {
            try {
                foreach($entry->extras as $extra) {
                    $stmt->bind_param('iiss', $entry->id, $entry->cat, $extra->uuid, $extra->value);
                    $stmt->execute();
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the extras for an entry.
     *
     * @param EntryRecord $entry
     */
    private function updateEntryExtras(EntryRecord $entry) {
        if(!$entry->extras) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM entries_extras WHERE entry = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $entry->id);
                if($stmt->execute()) {
                    $this->insertEntryExtras($entry);
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Insert the flavors for an entry.
     *
     * @param EntryRecord $entry
     */
    private function insertEntryFlavors(EntryRecord $entry) {
        if(!$entry->flavors) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO entries_flavors (entry, flavor, value, pos) VALUES (?, ?, ?, ?);');
        if($stmt) {
            try {
                foreach($entry->flavors as $flavor) {
                    $stmt->bind_param('isii', $entry->id, $flavor->name, $flavor->value, $flavor->pos);
                    $stmt->execute();
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the flavors for an entry.
     *
     * @param EntryRecord $entry
     */
    private function updateEntryFlavors(EntryRecord $entry) {
        if(!$entry->flavors) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM entries_flavors WHERE entry = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $entry->id);
                if($stmt->execute()) {
                    $this->insertEntryFlavors($entry);
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Insert the photos for an entry.
     *
     * @param EntryRecord $entry
     */
    private function insertEntryPhotos(EntryRecord $entry) {
        if(!$entry->photos) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO photos (entry, hash, drive_id, pos) VALUES (?, ?, ?, ?);');
        if($stmt) {
            try {
                foreach($entry->photos as $photo) {
                    $stmt->bind_param('issi', $entry->id, $photo->hash, $photo->driveId, $photo->pos);
                    $stmt->execute();
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the photos for an entry.
     *
     * @param EntryRecord $entry
     */
    private function updateEntryPhotos(EntryRecord $entry) {
        if(!$entry->photos) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM photos WHERE entry = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $entry->id);
                if($stmt->execute()) {
                    $this->insertEntryPhotos($entry);
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Delete an entry. This will delete the entry and add its UUID to the deleted log.
     *
     * @param EntryRecord $entry
     * @return boolean Whether the operation was successful
     */
    private function deleteEntry(EntryRecord $entry) {
        $changed = 0;
        $stmt = $this->db->prepare('DELETE FROM entries WHERE user = ? AND id = ? AND sync_time < SUBTIME(NOW(3), ? / 1000);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $entry->id, $entry->age);
                $stmt->execute();
                $changed = $stmt->affected_rows;
            } finally {
                $stmt->close();
            }
        }

        if($changed) {
            $stmt = $this->db->prepare('INSERT INTO deleted (user, type, cat, uuid, client) VALUES (?, \'entry\', ?, ?, ?);');
            if($stmt) {
                try {
                    $stmt->bind_param('iisi', $this->userId, $entry->cat, $entry->uuid, $this->clientId);
                    return $stmt->execute();
                } finally {
                    $stmt->close();
                }
            }
        }

        return false;
    }

    /**
     * Get all categories deleted by other clients since the client's last sync.
     *
     * @return string[] The UUIDs of categories deleted since the last sync
     * @throws UnauthorizedException
     */
    public function getDeletedCats() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $records = array();

        $stmt = $this->db->prepare('SELECT uuid, TIMESTAMPDIFF(MICROSECOND, sync_time, NOW(3)) FROM deleted WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($uuid, $age);
                    while($stmt->fetch()) {
                        $records[$uuid] = (int)($age / 1000);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return $records;
    }

    /**
     * Get all categories updated by other clients since the client's last sync.
     *
     * @return array[string]int Map of UUIDs to update timestamps of categories updated since the last sync
     * @throws UnauthorizedException
     */
    public function getUpdatedCats() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $records = array();

        $stmt = $this->db->prepare('SELECT uuid, TIMESTAMPDIFF(MICROSECOND, sync_time, NOW(3)) FROM categories WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($uuid, $age);
                    while($stmt->fetch()) {
                        $records[$uuid] = (int)($age / 1000);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return $records;
    }

    /**
     * Get a single category.
     *
     * @param type $catUuid The UUID of the category
     * @return CatRecord
     * @throws UnauthorizedException
     */
    public function getCat($catUuid) {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $stmt = $this->db->prepare('SELECT id, uuid, name, TIMESTAMPDIFF(MICROSECOND, sync_time, NOW(3)) FROM categories WHERE uuid = ? AND user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('si', $catUuid, $this->userId);
                if($stmt->execute()) {
                    $stmt->store_result();
                    $stmt->bind_result($id, $uuid, $name, $age);
                    if($stmt->fetch()) {
                        $record = new CatRecord();
                        $record->id = $id;
                        $record->uuid = $uuid;
                        $record->name = $name;
                        $record->age = (int)($age / 1000);

                        $record->extras = $this->getCatExtras($id);
                        $record->flavors = $this->getCatFlavors($id);

                        return $record;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return null;
    }

    /**
     * Get the extras for a category.
     *
     * @param int $catId The database ID of the category
     * @return ExtraRecord[]
     */
    private function getCatExtras($catId) {
        $stmt = $this->db->prepare('SELECT uuid, name, pos, deleted FROM extras WHERE cat = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $catId);
                if($stmt->execute()) {
                    $records = array();

                    $stmt->bind_result($uuid, $name, $pos, $deleted);
                    while($stmt->fetch()) {
                        $record = new ExtraRecord();
                        $record->uuid = $uuid;
                        $record->name = $name;
                        $record->pos = $pos;
                        $record->deleted = (boolean)$deleted;

                        $records[] = $record;
                    }

                    return $records;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Get the flavors for a category.
     *
     * @param int $catId The database ID of the category
     * @return FlavorRecord[]
     */
    private function getCatFlavors($catId) {
        $stmt = $this->db->prepare('SELECT name, pos FROM flavors WHERE cat = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $catId);
                if($stmt->execute()) {
                    $records = array();

                    $stmt->bind_result($name, $pos);
                    while($stmt->fetch()) {
                        $record = new FlavorRecord();
                        $record->name = $name;
                        $record->pos = $pos;

                        $records[] = $record;
                    }

                    return $records;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the category, inserting, updating, or deleting as indicated.
     *
     * @param CatRecord $cat
     * @return boolean Whether the operation was successful
     * @throws UnauthorizedException
     */
    public function pushCat(CatRecord $cat) {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $success = false;
        $this->db->autocommit(false);

        $stmt = $this->db->prepare('SELECT id FROM categories WHERE uuid = ? AND user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('si', $cat->uuid, $this->userId);
                if($stmt->execute()) {
                    $stmt->store_result();
                    $stmt->bind_result($id);
                    if($stmt->fetch()) {
                        $cat->id = $id;
                    }

                    if($cat->deleted) {
                        $success = $this->deleteCat($cat);
                    } elseif(!$cat->id) {
                        $success = $this->insertCat($cat);
                    } else {
                        $success = $this->updateCat($cat);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        $this->db->autocommit(true);

        if($success) {
            $this->changed();
        }
        return $success;
    }

    /**
     * Insert a new category.
     *
     * @param CatRecord $cat
     * @return boolean Whether the operation was successful
     */
    private function insertCat(CatRecord $cat) {
        $stmt = $this->db->prepare('INSERT INTO categories (uuid, user, name, client) VALUES (?, ?, ?, ?);');
        if($stmt) {
            try {
                $stmt->bind_param('sisi', $cat->uuid, $this->userId, $cat->name, $this->clientId);
                if($stmt->execute()) {
                    $cat->id = $stmt->insert_id;
                    if($cat->id) {
                        $this->insertCatExtras($cat);
                        $this->insertCatFlavors($cat);
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        $stmt = $this->db->prepare('DELETE FROM deleted WHERE user = ? AND uuid = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('is', $this->userId, $entry->uuid);
                $stmt->execute();
            } finally {
                $stmt->close();
            }
        }

        return $cat->id > 0;
    }

    /**
     * Update a category.
     *
     * @param CatRecord $cat
     * @return boolean Whether the operation was successful
     */
    private function updateCat(CatRecord $cat) {
        $stmt = $this->db->prepare('UPDATE categories SET name = ?, sync_time = NOW(3), client = ? WHERE user = ? AND id = ? AND sync_time < SUBTIME(NOW(3), ? / 1000);');
        if($stmt) {
            try {
                $stmt->bind_param('siiii', $cat->name, $this->clientId, $this->userId, $cat->id, $cat->age);
                if($stmt->execute() && $stmt->affected_rows) {
                    $this->updateCatExtras($cat);
                    $this->updateCatFlavors($cat);

                    return true;
                }
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Insert the extras for a category.
     *
     * @param CatRecord $cat
     */
    private function insertCatExtras(CatRecord $cat) {
        if(!$cat->extras) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO extras (uuid, cat, name, pos, deleted) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, pos = ?, deleted = ?;');
        if($stmt) {
            try {
                foreach($cat->extras as $extra) {
                    $stmt->bind_param('sisiisii', $extra->uuid, $cat->id, $extra->name, $extra->pos, $extra->deleted, $extra->name, $extra->pos, $extra->deleted);
                    $stmt->execute();
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the extras for a category.
     *
     * @param CatRecord $cat
     */
    private function updateCatExtras(CatRecord $cat) {
        if(!$cat->extras) {
            return;
        }

        $catUuids = array();
        foreach($cat->extras as $extra) {
            $catUuids[$extra->uuid] = true;
        }

        $stmt = $this->db->prepare('SELECT id, uuid FROM extras WHERE cat = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $cat->id);
                if($stmt->execute()) {
                    $stmt2 = $this->db->prepare('DELETE FROM extras WHERE id = ?;');
                    if($stmt2) {
                        try {
                            $stmt->store_result();
                            $stmt->bind_result($id, $uuid);
                            while($stmt->fetch()) {
                                if(!array_key_exists($uuid, $catUuids)) {
                                    $stmt2->bind_param('i', $id);
                                    $stmt2->execute();
                                }
                            }

                            $this->insertCatExtras($cat);
                        } finally {
                            $stmt2->close();
                        }
                    }
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Insert the flavors for a category.
     *
     * @param CatRecord $cat
     */
    private function insertCatFlavors(CatRecord $cat) {
        if(!$cat->flavors) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO flavors (cat, name, pos) VALUES (?, ?, ?);');
        if($stmt) {
            try {
                foreach($cat->flavors as $flavor) {
                    $stmt->bind_param('isi', $cat->id, $flavor->name, $flavor->pos);
                    $stmt->execute();
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Update the flavors for a category.
     *
     * @param CatRecord $cat
     */
    private function updateCatFlavors(CatRecord $cat) {
        if(!$cat->flavors) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM flavors WHERE cat = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $cat->id);
                if($stmt->execute()) {
                    $this->insertCatFlavors($cat);
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Delete a category. This will delete the category and add its UUID to the deleted log.
     *
     * @param CatRecord $cat
     * @return boolean Whether the operation was successful
     */
    private function deleteCat(CatRecord $cat) {
        $changed = 0;
        $stmt = $this->db->prepare('DELETE FROM categories WHERE user = ? AND id = ? AND sync_time < SUBTIME(NOW(3), ? / 1000);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $cat->id, $cat->age);
                $stmt->execute();
                $changed = $stmt->affected_rows;
            } finally {
                $stmt->close();
            }
        }

        if($changed) {
            $stmt = $this->db->prepare('INSERT INTO deleted (user, type, uuid, client) VALUES (?, \'cat\', ?, ?);');
            if($stmt) {
                try {
                    $stmt->bind_param('isi', $this->userId, $cat->uuid, $this->clientId);
                    return $stmt->execute();
                } finally {
                    $stmt->close();
                }
            }
        }

        return false;
    }

    /**
     * Get the database ID of a user, creating one if it doesn't exist.
     *
     * @param string $authId The authentication ID for the user
     * @return int The user's database ID
     */
    private function findUserId($authId) {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE uid = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('s', $authId);
                if($stmt->execute()) {
                    $stmt->store_result();
                    $stmt->bind_result($id);
                    if($stmt->fetch()) {
                        return $id;
                    } else {
                        $stmt2 = $this->db->prepare('INSERT INTO users (uid) VALUES (?);');
                        if($stmt2) {
                            try {
                                $stmt2->bind_param('s', $authId);
                                if($stmt2->execute()) {
                                    return $stmt2->insert_id;
                                }
                            } finally {
                                $stmt2->close();
                            }
                        }
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return 0;
    }

}
