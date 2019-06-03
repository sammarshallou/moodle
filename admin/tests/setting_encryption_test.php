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
 * Test setting encryption.
 *
 * @package core_admin
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tests\core_admin;

use core_admin\setting_encryption;

/**
 * Test setting encryption.
 *
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_encryption_testcase extends \basic_testcase {
    /**
     * Clear junk created by tests.
     */
    protected function tearDown() {
        global $CFG;
        $keyfile = $CFG->dataroot . '/secret/keypair/private.key';
        if (file_exists($keyfile)) {
            chmod($keyfile, 0700);
        }
        remove_dir($CFG->dataroot . '/secret');
        unset($CFG->nokeypairgeneration);
    }

    /**
     * Tests the create_keys and get_public_key functions.
     *
     * @throws \moodle_exception
     */
    public function test_create_keys() {
        setting_encryption::create_key_pair();
        $public = setting_encryption::get_public_key();
        $this->assertContains('BEGIN PUBLIC KEY', $public);

        $this->expectExceptionMessageRegExp('~Key pair already exists~');
        setting_encryption::create_key_pair();
    }

    /**
     * Tests encryption and decryption with empty strings.
     *
     * @throws \moodle_exception
     */
    public function test_encrypt_and_decrypt_empty() {
        $this->assertEquals('', setting_encryption::encrypt(''));
        $this->assertEquals('', setting_encryption::decrypt(''));
    }

    /**
     * Tests encryption and decryption when the keys weren't created yet.
     */
    public function test_encrypt_and_decrypt_nokeys() {
        global $CFG;
        // Prevent automatic generation of keys.
        $CFG->nokeypairgeneration = true;
        $this->expectExceptionMessageRegExp('~Key pair not found~');
        setting_encryption::encrypt('frogs');
        $this->expectExceptionMessageRegExp('~Key pair not found~');
        setting_encryption::decrypt('frogs');

        // Allow automatic generation (default).
        unset($CFG->nokeypairgeneration);
        $encrypted = setting_encryption::encrypt('frogs');
        $this->assertEquals('frogs', setting_encryption::decrypt($encrypted));
    }

    /**
     * Checks the errors generated if encryption/decryption fails.
     */
    public function test_encrypt_and_decrypt_failures() {
        global $CFG;

        // Fake encryption to fail by setting the keys to something bogus.
        $folder = $CFG->dataroot . '/secret/keypair';
        check_dir_exists($folder);
        file_put_contents($folder . '/public.key', 'silly');
        file_put_contents($folder . '/private.key', 'sillier');

        $this->expectExceptionMessageRegExp('~Encryption failed~');
        setting_encryption::encrypt('frogs');

        $this->expectExceptionMessageRegExp('~Invalid private key~');
        setting_encryption::decrypt('frogs');
    }

    /**
     * Tests encryption and decryption for real.
     *
     * @throws \moodle_exception
     */
    public function test_encypt_and_decrypt_realdata() {
        setting_encryption::create_key_pair();

        // Encrypt short string that works.
        $encrypted = setting_encryption::encrypt('frogs');
        $this->assertNotEquals('frogs', $encrypted);
        $this->assertEquals('frogs', setting_encryption::decrypt($encrypted));

        // Encrypt maximum length string.
        $long = str_repeat('X', 245);
        $this->assertEquals($long, setting_encryption::decrypt(setting_encryption::encrypt($long)));

        // Encrypt even longer string.
        $long = str_repeat('X', 246);
        $this->expectExceptionMessageRegExp('~Value too long to encrypt~');
        setting_encryption::encrypt($long);
    }
}
