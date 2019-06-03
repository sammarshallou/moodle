<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class used to encrypt or decrypt data.
 *
 * @package core
 * @copyright 2020 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core;

/**
 * Class used to encrypt or decrypt data.
 *
 * @package core
 * @copyright 2020 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class encryption {
    /** @var string Encryption method used */
    const CIPHER_METHOD = 'AES-256-CTR';

    /** @var int Length of required key in bits (must match CIPHER_METHOD). */
    const KEY_BITS = 256;

    /** @var string Name of encryption key */
    const KEY_FILE = 'encryption.key';

    /**
     * Creates a key for the server.
     *
     * @param bool $chmod If true, restricts the file access of the key
     * @throws \moodle_exception If the server already has a key, or there is an error
     */
    public static function create_key(bool $chmod = true): void {
        if (self::key_exists()) {
            throw new \moodle_exception('encryption_keyalreadyexists', 'error');
        }

        // Don't make it read-only in Behat or it will fail to clear for future runs.
        if (defined('BEHAT_SITE_RUNNING')) {
            $chmod = false;
        }

        // Generate the key.
        $key = openssl_random_pseudo_bytes(self::KEY_BITS / 8);

        // Store the key, making it readable only by server.
        $folder = self::get_key_folder();
        check_dir_exists($folder);
        $keyfile = self::get_key_file();
        file_put_contents($keyfile, $key);
        if ($chmod) {
            chmod($keyfile, 0400);
        }
    }

    /**
     * Gets the folder used to store the secret key.
     *
     * @return string Folder path
     */
    public static function get_key_folder(): string {
        global $CFG;
        return ($CFG->secretdataroot ?? $CFG->dataroot . '/secret') . '/key';
    }

    /**
     * Gets the file path used to store the secret key. The filename contains the cipher method,
     * so that if necessary to transition in future it would be possible to have multiple.
     *
     * @return string Full path to file
     */
    public static function get_key_file(): string {
        return self::get_key_folder() . '/' . self::CIPHER_METHOD . '.key';
    }

    /**
     * Checks if there is a key file.
     *
     * @return bool True if there is a key file
     */
    public static function key_exists(): bool {
        return file_exists(self::get_key_file());
    }

    /**
     * Gets the current key, automatically creating it if there isn't one yet.
     *
     * You should not need to use this function - just call encrypt or decrypt.
     *
     * @return string The key (binary)
     * @throws \moodle_exception If there isn't one already (and creation is disabled)
     */
    public static function get_key(): string {
        global $CFG;

        $keyfile = self::get_key_file();
        if (!file_exists($keyfile) && empty($CFG->nokeygeneration)) {
            self::create_key();
        }
        $result = @file_get_contents($keyfile);
        if ($result === false) {
            throw new \moodle_exception('encryption_nokey', 'error');
        }
        if (strlen($result) !== self::KEY_BITS / 8) {
            throw new \moodle_exception('encryption_invalidkey', 'error');
        }
        return $result;
    }

    /**
     * Encrypts data using the server's key.
     *
     * Note there is a special case - the empty string is not encrypted.
     *
     * @param string $data Data to encrypt, or empty string for no data
     * @return string Encrypted data, or empty string for no data
     * @throws \moodle_exception If the key doesn't exist, or the string is too long
     */
    public static function encrypt(string $data): string {
        if ($data === '') {
            return '';
        } else {
            // Create IV.
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));

            // Encrypt data.
            $encrypted = @openssl_encrypt($data, self::CIPHER_METHOD, self::get_key(), OPENSSL_RAW_DATA, $iv);
            if ($encrypted === false) {
                throw new \moodle_exception('encryption_encryptfailed', 'error',
                        '', null, openssl_error_string());
            }

            // Encrypted data is cipher method plus IV plus encrypted data.
            return self::CIPHER_METHOD . ':' . base64_encode($iv . $encrypted);
        }
    }

    /**
     * Decrypts data using the server's key.
     *
     * @param string $data Data to decrypt
     * @return string Decrypted data
     */
    public static function decrypt(string $data): string {
        if ($data === '') {
            return '';
        } else {
            if (strpos($data, self::CIPHER_METHOD . ':') !== 0) {
                throw new \moodle_exception('encryption_wrongcipher', 'error');
            }
            $realdata = base64_decode(substr($data, strlen(self::CIPHER_METHOD) + 1), true);
            if ($realdata === false) {
                throw new \moodle_exception('encryption_decryptfailed', 'error',
                        '', null, 'Invalid base64 data');
            }

            $ivlength = openssl_cipher_iv_length(self::CIPHER_METHOD);
            if (strlen($realdata) < $ivlength + 1) {
                throw new \moodle_exception('encryption_decryptfailed', 'error',
                        '', null, 'Insufficient data');
            }
            $iv = substr($realdata, 0, $ivlength);
            $encrypted = substr($realdata, $ivlength);

            $decrypted = @openssl_decrypt($encrypted, self::CIPHER_METHOD, self::get_key(), OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) {
                throw new \moodle_exception('encryption_decryptfailed', 'error',
                        '', null, openssl_error_string());
            }

            return $decrypted;
        }
    }
}
