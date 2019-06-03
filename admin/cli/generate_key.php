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
 * Generates the key for the current server (presuming it does not already exist).
 *
 * @package core_admin
 * @copyright 2020 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \core\encryption;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

$folder = encryption::get_key_folder();
if (encryption::key_exists()) {
    echo 'Key already exists: ' . encryption::get_key_file() . "\n";
    exit;
}

// Creates key pair with default permissions.
echo "Generating key...\n";

encryption::create_key(false);

echo "\nKey created: " . encryption::get_key_file() . "\n\n";
echo "If the key folder is not shared storage, then key files should be copied to all servers.\n";
