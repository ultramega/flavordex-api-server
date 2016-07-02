<?php

namespace Flavordex;

use Flavordex\Exception\UnauthorizedException;
use Flavordex\Model\CatRecord;
use Flavordex\Model\EntryRecord;
use Flavordex\Model\ExtraRecord;
use Flavordex\Model\FlavorRecord;
use Flavordex\Model\PhotoRecord;
use Flavordex\Model\RegistrationRecord;
use Flavordex\Model\RemoteIdsRecord;

/**
 * Database access helper.
 *
 * @author Steve Guidetti
 */
class DatabaseHelper {

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
     * Set the user to perform authorized requests.
     *
     * @param Auth $auth The authentication data for the user
     */
    public function setUser(Auth $auth) {
        $this->userId = $this->getUserId($auth->getUid(), $auth->getEmail());
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
     */
    public function setClientId($clientId) {
        $this->clientId = (int)$clientId;
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
                $stmt->execute();
                return $stmt->affected_rows;
            } finally {
                $stmt->close();
            }
        }

        return false;
    }

    /**
     * Update the last sync time for the client.
     *
     * @param int $time The Unix timestamp
     * @return boolean Whether the sync time was successfully updated
     */
    public function setSyncTime($time) {
        $stmt = $this->db->prepare('UPDATE clients SET last_sync = ? WHERE user = ? AND id = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('sii', $time, $this->userId, $this->clientId);
                $stmt->execute();
                return $stmt->affected_rows;
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
                $stmt->execute();
                $stmt->affected_rows;
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
     * Get the list of entry IDs for the user.
     *
     * @return RemoteIdsRecord
     * @throws UnauthorizedException
     */
    public function getEntryIds() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $stmt = $this->db->prepare('SELECT id, uuid FROM entries WHERE user = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('i', $this->userId);
                if($stmt->execute()) {
                    $list = array();

                    $stmt->bind_result($id, $uuid);
                    while($stmt->fetch()) {
                        $list[$uuid] = $id;
                    }

                    $record = new RemoteIdsRecord();
                    $record->entryIds = $list;
                    return $record;
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Get all entries updated by other clients since the client's last sync.
     *
     * @return EntryRecord[] The list of entries updated since the last sync
     * @throws UnauthorizedException
     */
    public function getUpdatedEntries() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $records = array();

        $stmt = $this->db->prepare('SELECT uuid FROM deleted WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($uuid);
                    while($stmt->fetch()) {
                        $record = new EntryRecord();
                        $record->uuid = $uuid;
                        $record->deleted = true;

                        $records[] = $record;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        $stmt = $this->db->prepare('SELECT a.id, a.uuid, a.cat, b.uuid AS cat_uuid, a.title, a.maker, a.origin, a.price, a.location, a.date, a.rating, a.notes, a.updated, a.shared FROM entries a LEFT JOIN categories b ON a.cat = b.id WHERE a.user = ? AND a.client != ? AND a.sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($id, $uuid, $cat, $catUuid, $title, $maker, $origin, $price, $location, $date, $rating, $notes, $updated, $shared);
                    while($stmt->fetch()) {
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
                        $record->updated = $updated;
                        $record->shared = (boolean)$shared;

                        $record->extras = $this->getEntryExtras($id);
                        $record->flavors = $this->getEntryFlavors($id);
                        $record->photos = $this->getEntryPhotos($id);

                        $records[] = $record;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return $records;
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
        } elseif($entry->id == 0) {
            $stmt = $this->db->prepare('SELECT id FROM categories WHERE uuid = ? AND user = ?;');
            if($stmt) {
                try {
                    $stmt->bind_param('si', $entry->catUuid, $this->userId);
                    if($stmt->execute()) {
                        $stmt->bind_result($id);
                        if($stmt->fetch()) {
                            $entry->cat = $id;
                            $success = $this->insertEntry($entry);
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

        return $success;
    }

    /**
     * Insert a new entry.
     *
     * @param EntryRecord $entry
     * @return boolean Whether the operation was successful
     */
    private function insertEntry(EntryRecord $entry) {
        $stmt = $this->db->prepare('INSERT INTO entries (uuid, user, cat, title, maker, origin, price, location, date, rating, notes, updated, sync_time, client, shared) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
        if($stmt) {
            try {
                $now = self::getTimestamp();
                $stmt->bind_param('siissssssdsssii', $entry->uuid, $this->userId, $entry->cat, $entry->title, $entry->maker, $entry->origin, $entry->price, $entry->location, $entry->date, $entry->rating, $entry->notes, $entry->updated, $now, $this->clientId, $entry->shared);
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
        $stmt = $this->db->prepare('UPDATE entries SET title = ?, maker = ?, origin = ?, price = ?, location = ?, date = ?, rating = ?, notes = ?, updated = ?, sync_time = ?, client = ?, shared = ? WHERE user = ? AND id = ? AND updated < ?;');
        if($stmt) {
            try {
                $now = self::getTimestamp();
                $stmt->bind_param('ssssssdsssiiiis', $entry->title, $entry->maker, $entry->origin, $entry->price, $entry->location, $entry->date, $entry->rating, $entry->notes, $entry->updated, $now, $this->clientId, $entry->shared, $this->userId, $entry->id, $entry->updated);
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
                $stmt->execute();

                $this->insertEntryExtras($entry);
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
                $stmt->execute();

                $this->insertEntryFlavors($entry);
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
                    $stmt->bind_param('isii', $entry->id, $photo->hash, $photo->driveId, $photo->pos);
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
                $stmt->execute();

                $this->insertEntryPhotos($entry);
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
        $stmt = $this->db->prepare('DELETE FROM entries WHERE user = ? AND id = ? AND updated < ?;');
        if($stmt) {
            try {
                $stmt->bind_param('iis', $this->userId, $entry->id, $entry->updated);
                $stmt->execute();
                $changed = $stmt->affected_rows;
            } finally {
                $stmt->close();
            }
        }

        if($changed) {
            $stmt = $this->db->prepare('INSERT INTO deleted (user, type, cat, uuid, sync_time, client) VALUES (?, \'entry\', ?, ?, ?, ?);');
            if($stmt) {
                try {
                    $now = self::getTimestamp();
                    $stmt->bind_param('iissi', $this->userId, $entry->cat, $entry->uuid, $now, $this->clientId);
                    $stmt->execute();

                    return true;
                } finally {
                    $stmt->close();
                }
            }
        }

        return false;
    }

    /**
     * Get all categories updated by other clients since the client's last sync.
     *
     * @return CatRecord[] The list of categories updated since the last sync
     * @throws UnauthorizedException
     */
    public function getUpdatedCats() {
        if(!$this->userId) {
            throw new UnauthorizedException();
        }

        $records = array();

        $stmt = $this->db->prepare('SELECT uuid FROM deleted WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($uuid);
                    while($stmt->fetch()) {
                        $record = new CatRecord();
                        $record->uuid = $uuid;
                        $record->deleted = true;

                        $records[] = $record;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        $stmt = $this->db->prepare('SELECT id, uuid, name, updated FROM categories WHERE user = ? AND client != ? AND sync_time > (SELECT last_sync FROM clients WHERE id = ?);');
        if($stmt) {
            try {
                $stmt->bind_param('iii', $this->userId, $this->clientId, $this->clientId);
                if($stmt->execute()) {
                    $stmt->bind_result($id, $uuid, $name, $updated);
                    while($stmt->fetch()) {
                        $record = new CatRecord();
                        $record->id = $id;
                        $record->uuid = $uuid;
                        $record->name = $name;
                        $record->updated = $updated;

                        $record->extras = $this->getCatExtras($id);
                        $record->flavors = $this->getCatFlavors($id);

                        $records[] = $record;
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return $records;
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

        return $success;
    }

    /**
     * Insert a new category.
     *
     * @param CatRecord $cat
     * @return boolean Whether the operation was successful
     */
    private function insertCat(CatRecord $cat) {
        $stmt = $this->db->prepare('INSERT INTO categories (uuid, user, name, updated, sync_time, client) VALUES (?, ?, ?, ?, ?, ?);');
        if($stmt) {
            try {
                $now = self::getTimestamp();
                $stmt->bind_param('sisssi', $cat->uuid, $this->userId, $cat->name, $cat->updated, $now, $this->clientId);
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
        $stmt = $this->db->prepare('UPDATE categories SET name = ?, updated = ?, sync_time = ?, client = ? WHERE user = ? AND id = ? AND updated < ?;');
        if($stmt) {
            try {
                $now = self::getTimestamp();
                $stmt->bind_param('sssiiis', $cat->name, $cat->updated, $now, $this->clientId, $this->userId, $cat->id, $cat->updated);
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
                $stmt->execute();

                $this->insertCatFlavors($cat);
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
        $stmt = $this->db->prepare('DELETE FROM categories WHERE user = ? AND id = ? AND updated < ?;');
        if($stmt) {
            try {
                $stmt->bind_param('iis', $this->userId, $cat->id, $cat->updated);
                $stmt->execute();
                $changed = $stmt->affected_rows;
            } finally {
                $stmt->close();
            }
        }

        if($changed) {
            $stmt = $this->db->prepare('INSERT INTO deleted (user, type, uuid, sync_time, client) VALUES (?, \'cat\', ?, ?, ?);');
            if($stmt) {
                try {
                    $now = self::getTimestamp();
                    $stmt->bind_param('issi', $this->userId, $cat->uuid, $now, $this->clientId);
                    $stmt->execute();

                    return true;
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
     * @param string $email The user's email address
     * @return int The user's database ID
     */
    private function getUserId($authId, $email) {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE uid = ?;');
        if($stmt) {
            try {
                $stmt->bind_param('s', $authId);
                $stmt->execute();
                $stmt->bind_result($id);
                if($stmt->fetch()) {
                    return $id;
                } else {
                    $stmt2 = $this->db->prepare('INSERT INTO users (email, uid) VALUES (?, ?) ON DUPLICATE KEY UPDATE uid = ?;');
                    if($stmt2) {
                        try {
                            $stmt2->bind_param('sss', $email, $authId, $authId);
                            if($stmt2->execute()) {
                                return $stmt2->insert_id;
                            }
                        } finally {
                            $stmt2->close();
                        }
                    }
                }
            } finally {
                $stmt->close();
            }
        }

        return 0;
    }

    /**
     * Get the current Unix timestamp with milliseconds.
     * 
     * @return int The current Unix timestamp with milliseconds
     */
    private static function getTimestamp() {
        return floor(microtime(true) * 1000);
    }

}
