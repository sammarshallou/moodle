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
 * Test encryption.
 *
 * @package core
 * @copyright 2020 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core;

/**
 * Test encryption.
 *
 * @package core
 * @copyright 2020 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class encryption_testcase extends \basic_testcase {

    /**
     * Clear junk created by tests.
     */
    protected function tearDown(): void {
        global $CFG;
        $keyfile = encryption::get_key_file();
        if (file_exists($keyfile)) {
            chmod($keyfile, 0700);
        }
        remove_dir($CFG->dataroot . '/secret');
        unset($CFG->nokeygeneration);
    }

    /**
     * Tests the create_keys and get_public_key functions.
     *
     * @throws \moodle_exception
     */
    public function test_create_key(): void {
        encryption::create_key();
        $key = encryption::get_key();
        $this->assertEquals(32, strlen($key));

        $this->expectExceptionMessage('Key already exists');
        encryption::create_key();
    }

    /**
     * Tests encryption and decryption with empty strings.
     *
     * @throws \moodle_exception
     */
    public function test_encrypt_and_decrypt_empty(): void {
        $this->assertEquals('', encryption::encrypt(''));
        $this->assertEquals('', encryption::decrypt(''));
    }

    /**
     * Tests encryption when the keys weren't created yet.
     */
    public function test_encrypt_nokeys(): void {
        global $CFG;
        // Prevent automatic generation of keys.
        $CFG->nokeygeneration = true;
        $this->expectExceptionMessage('Key not found');
        encryption::encrypt('frogs');
    }

    /**
     * Tests decryption when the data has a different cipher method
     */
    public function test_decrypt_wrongmethod(): void {
        $this->expectExceptionMessage('Data does not match the supported cipher method');
        encryption::decrypt('FAKE-CIPHER-METHOD:xx');
    }

    /**
     * Tests decryption when not enough data is supplied to get the IV and some data.
     */
    public function test_decrypt_tooshort(): void {
        $this->expectExceptionMessage('Insufficient data');
        // It needs min 17 bytes (16 bytes IV + 1 byte data).
        encryption::decrypt(encryption::CIPHER_METHOD . ':' .base64_encode('0123456789abcdef'));
    }

    /**
     * Tests decryption when data is not valid base64.
     */
    public function test_decrypt_notbase64(): void {
        $this->expectExceptionMessage('Invalid base64 data');
        encryption::decrypt(encryption::CIPHER_METHOD . ':' . chr(160));
    }

    /**
     * Tests decryption when the keys weren't created yet.
     */
    public function test_decrypt_nokeys(): void {
        global $CFG;
        // Prevent automatic generation of keys.
        $CFG->nokeygeneration = true;
        $this->expectExceptionMessage('Key not found');
        encryption::decrypt(encryption::CIPHER_METHOD . ':' . base64_encode('0123456789abcdef0'));
    }

    /**
     * Test automatic generation of keys when needed.
     */
    public function test_auto_key_generation(): void {
        // Allow automatic generation (default).
        $encrypted = encryption::encrypt('frogs');
        $this->assertEquals('frogs', encryption::decrypt($encrypted));
    }

    /**
     * Checks that invalid key causes failures.
     */
    public function test_invalid_key(): void {
        global $CFG;

        // Set the key to something bogus.
        $folder = $CFG->dataroot . '/secret/key';
        check_dir_exists($folder);
        file_put_contents(encryption::get_key_file(), 'silly');

        $this->expectExceptionMessage('Invalid key');
        encryption::get_key();
    }

    /**
     * Tests encryption and decryption for real.
     *
     * @throws \moodle_exception
     */
    public function test_encypt_and_decrypt_realdata(): void {
        // Encrypt short string.
        $encrypted = encryption::encrypt('frogs');
        $this->assertNotEquals('frogs', $encrypted);
        $this->assertEquals('frogs', encryption::decrypt($encrypted));

        // Encrypt really long string (1 MB).
        $long = str_repeat('X', 1024 * 1024);
        $this->assertEquals($long, encryption::decrypt(encryption::encrypt($long)));
    }
}
