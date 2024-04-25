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

namespace core_files;

/**
 * Allows use of the 'fast file' system for pre-signed file requests.
 *
 * @package core_file
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fast_file {
    /** @var int Links expire after 8 hours (in case of very long videos etc.) */
    const EXPIRY_TIME = 8 * HOURSECS;

    /**
     * Gets a URL to serve a file quickly (assuming it is granted).
     *
     * Depeending on the component and area, the file id and content may be required.
     *
     * @param string $component Component for file
     * @param string $area Area for file
     * @param string $filepath Path of file (must begin with /)
     * @param int $fileid Optional file id (may be required depending on file type)
     * @param string $contenthash Optional contenthash (may be required))
     * @param bool $mustbegranted If true (default) throws exception if grant fails
     * @param bool $forcedownload If true, link has forcedownload=1
     * @return \moodle_url|null URL or null if user was not granted access
     * @throws \coding_exception If user was not granted access and $mustbegranted is true
     */
    public static function get_url(string $component, string $area, string $filepath,
           int $fileid = 0, string $contenthash = '', bool $mustbegranted = true,
           bool $forcedownload = false): ?\moodle_url {
        $hook = new \core_files\hook\grant_fast_file($component, $area, $filepath,
            $fileid, $contenthash);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        if (!$hook->is_granted()) {
            if ($mustbegranted) {
                throw new \coding_exception('Fast file not granted: ' .
                        $component . '/' . $area . '/' . $filepath);
            }
            return null;
        }
        return $hook->get_url($forcedownload);
    }

    /**
     * Gets the signature for a fast file URL.
     *
     * @param string $basepath Most of the URL path (except the signature obviously)
     * @return string SHA-256 signature in hex format
     */
    public static function get_signature(string $basepath): string {
        global $CFG;

        return hash('sha256', $basepath . $CFG->siteidentifier, false);
    }
}
