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
 * Class used to encrypt or decrypt admin settings.
 *
 * Note that this uses public/private key cryptography. There is no particular advantage to doing
 * this, because the server is doing both the encryption and decryption, so it could use symmetric
 * cryptography. However, if we ever needed to provide the data from another server (or for example
 * by email) then we could provide the public key to the remote server and do it that way.
 *
 * @package core_admin
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Class used to encrypt or decrypt admin settings.
 *
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_encryption {
    /** @var string Name of private key */
    const PRIVATE_KEY_FILE = 'private.key';

    /** @var string Name of public key */
    const PUBLIC_KEY_FILE = 'public.key';

    /**
     * Creates a key pair for the server.
     *
     * @param bool $chmod If true, restricts the file access of the private key
     * @throws \moodle_exception If the server already has a key pair, or there is an error
     */
    public static function create_key_pair(bool $chmod = true) {
        if (self::key_pair_exists()) {
            throw new \moodle_exception('settingencryption_keyalreadyexists', 'admin');
        }

        // Don't make it read-only in Behat or it will fail to clear for future runs.
        if (defined('BEHAT_SITE_RUNNING')) {
            $chmod = false;
        }

        // Create the private and public key.
        $config = [
            'digest_alg' => 'sha512',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ];
        $res = openssl_pkey_new($config);
        if (!$res) {
            throw new \moodle_exception('settingencryption_createfailed', 'admin', '', null,
                    openssl_error_string());
        }

        // Store the private key, making it readable only by server.
        $folder = self::get_key_folder();
        check_dir_exists($folder);
        openssl_pkey_export($res, $privatekey);
        $privatekeyfile = $folder . '/' . self::PRIVATE_KEY_FILE;
        file_put_contents($privatekeyfile, $privatekey);
        if ($chmod) {
            chmod($privatekeyfile, 0400);
        }

        // Store the public key.
        $publickey = openssl_pkey_get_details($res);
        $publickey = $publickey['key'];
        $publickeyfile = $folder . '/' . self::PUBLIC_KEY_FILE;
        file_put_contents($publickeyfile, $publickey);
    }

    /**
     * Gets the folder used to store the private and public keys.
     *
     * @return string Folder path
     */
    public static function get_key_folder(): string {
        global $CFG;
        return ($CFG->secretdataroot ?? $CFG->dataroot . '/secret') . '/keypair';
    }

    /**
     * Checks if there is a key pair.
     *
     * @return bool True if there is a key pair
     */
    public static function key_pair_exists(): bool {
        $folder = self::get_key_folder();
        return file_exists($folder . '/' . self::PRIVATE_KEY_FILE) &&
                file_exists($folder . '/' . self::PUBLIC_KEY_FILE);
    }

    /**
     * Gets the current public key.
     *
     * Unless the config variable $CFG->nokeypairgeneration is set, this will automatically create
     * the key pair if it has not been created yet.
     *
     * @return string The public key (text)
     * @throws \moodle_exception If there isn't one
     */
    public static function get_public_key(): string {
        global $CFG;

        $folder = self::get_key_folder();
        $keyfile = $folder . '/' . self::PUBLIC_KEY_FILE;
        if (!file_exists($keyfile) && empty($CFG->nokeypairgeneration)) {
            self::create_key_pair();
        }
        $result = @file_get_contents($keyfile);
        if ($result === false) {
            throw new \moodle_exception('settingencryption_nokeypair', 'admin');
        }
        return $result;
    }

    /**
     * Gets the current private key.
     *
     * @return string The private key (text)
     * @throws \moodle_exception If there isn't one
     */
    private static function get_private_key(): string {
        $folder = self::get_key_folder();
        $result = @file_get_contents($folder . '/' . self::PRIVATE_KEY_FILE);
        if ($result === false) {
            throw new \moodle_exception('settingencryption_nokeypair', 'admin');
        }
        return $result;
    }

    /**
     * Encrypts data using the server's public key.
     *
     * Note there is a special case - the empty string is not encrypted.
     *
     * The string must be quite small (e.g. a password) as this is encrypted directly with
     * assymetric cryptography, which has limited string length.
     *
     * @param string $data Data to encrypt, or empty string for no data
     * @return string Encrypted data, or empty string for no data
     * @throws \moodle_exception If the key doesn't exist, or the string is too long
     */
    public static function encrypt(string $data): string {
        if ($data === '') {
            return '';
        } else {
            if (strlen($data) > 245) {
                throw new \moodle_exception('settingencryption_toolong', 'admin');
            }
            if (!@openssl_public_encrypt($data, $encrypted, self::get_public_key())) {
                throw new \moodle_exception('settingencryption_encryptfailed', 'admin',
                        '', null, openssl_error_string());
            }
            return base64_encode($encrypted);
        }
    }

    /**
     * Decrypts data using the server's private key.
     *
     * @param string $data Data to decrypt
     * @return string Decrypted data
     */
    public static function decrypt(string $data): string {
        if ($data === '') {
            return '';
        } else {
            $key = @openssl_pkey_get_private(self::get_private_key());
            if (!$key) {
                throw new \moodle_exception('settingencryption_invalidprivatekey', 'admin',
                        '', null, openssl_error_string());
            }

            if (!@openssl_private_decrypt(base64_decode($data), $decrypted, $key)) {
                throw new \moodle_exception('settingencryption_decryptfailed', 'admin',
                        '', null, openssl_error_string());
            }

            return $decrypted;
        }
    }
}
