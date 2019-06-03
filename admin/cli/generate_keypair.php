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
 * Generates the keypair for the current server (presuming it does not already exist).
 *
 * @package core_admin
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \core_admin\setting_encryption;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');

$folder = setting_encryption::get_key_folder();
if (setting_encryption::key_pair_exists()) {
    echo "Keypair already exists in $folder\n";
    exit;
}

// Creates key pair with default permissions.
echo "Generating keypair in $folder...\n";

setting_encryption::create_key_pair(false);

echo "Keypair created.\n\n";
echo "If the key folder is not shared storage, then both key files should be copied to all servers.\n";
