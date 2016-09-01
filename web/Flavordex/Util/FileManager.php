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

namespace Flavordex\Util;

use Flavordex\Config;
use Flavordex\Util\EntryMeta;

/**
 * Helper for managing files.
 *
 * @author Steve Guidetti
 */
class FileManager {

    /**
     * Save a poster image to the file system.
     *
     * @param string $imageBlob The raw image data
     * @param string $hash The hash identifying the image
     * @param EntryMeta $entry The entry metadata
     */
    public static function putPosterImage($imageBlob, $hash, EntryMeta $entry) {
        $path = self::getEntryPath($entry, true);
        $meta = array('hash' => $hash);

        $image = new \Imagick();
        $image->readImageBlob($imageBlob);
        if($image->getImageWidth() > 900) {
            $image->scaleImage(900, 0);
        }
        $meta['width'] = $image->getImageWidth();
        $meta['height'] = $image->getImageHeight();

        $image->writeImage($path . '/poster.jpg');
        $image->destroy();

        file_put_contents($path . '/poster.json', json_encode($meta));
    }

    /**
     * Read the hash file for a poster image.
     *
     * @param EntryMeta $entry The entry metadata
     * @return string|null The hash or NULL if it does not exist
     */
    public static function getHash(EntryMeta $entry) {
        $hashFile = self::getEntryPath($entry) . '/poster.json';
        if(!file_exists($hashFile)) {
            return null;
        }
        $meta = json_decode(file_get_contents($hashFile));
        if($meta) {
            return $meta->hash;
        }
        return null;
    }

    /**
     * Delete the poster image for an entry.
     *
     * @param EntryMeta $entry The entry metadata
     */
    public static function deletePosterImage(EntryMeta $entry) {
        $path = self::getEntryPath($entry);
        array_map('unlink', glob($path . '/poster*.*', GLOB_NOSORT));
        self::rmdirs($path);
    }

    /**
     * Get the path to the directory containing an entry's files.
     *
     * @param EntryMeta $entry The entry metadata
     * @param boolean $create Whether to create the directory if it does not exist
     * @return string The path
     */
    private static function getEntryPath(EntryMeta $entry, $create = false) {
        $path = sprintf('%s/%d/%d/%d', Config::FILES_DIR, $entry->userId, $entry->catId, $entry->id);
        if($create) {
            self::mkdirs($path);
        }
        return $path;
    }

    /**
     * Recursively create a directory.
     *
     * @param string $path The path to the directory to create
     * @return boolean Whether the directories were successfully created
     */
    private static function mkdirs($path) {
        if(!$path) {
            return false;
        }
        if(is_dir($path) || $path == '/') {
            return true;
        }
        if(self::mkdirs(dirname($path))) {
            return mkdir($path);
        }
        return false;
    }

    /**
     * Remove empty directories.
     *
     * @param string $path The path to the directory to check
     */
    private static function rmdirs($path) {
        if(is_dir($path)) {
            if(count(glob($path . '/*', GLOB_NOSORT)) > 0) {
                return;
            }
            rmdir($path);
        }
        self::rmdirs(dirname($path));
    }

}
