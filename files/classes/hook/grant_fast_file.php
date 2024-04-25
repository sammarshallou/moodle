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

namespace core_files\hook;

use core_files\fast_file;

/**
 * Handlers can grant access to files for fast serving.
 *
 * @package core_file
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Handlers can grant access to files for fast serving')]
#[\core\attribute\tags('core_file')]
final class grant_fast_file {
    /** @var bool True if access to file has been granted. */
    protected bool $granted = false;

    /** @var bool True if the file is available to public (no user restriction) */
    protected bool $public = false;

    public function __construct(public readonly string $component, public readonly string $area,
            public readonly string $filepath, protected int $fileid = 0,
            protected string $contenthash = '') {
        \core\param::COMPONENT->validate_param($this->component);
        \core\param::ALPHANUMEXT->validate_param($this->area);
        \core\param::PATH->validate_param($this->filepath);
    }

    public function grant(bool $public = false, int $fileid = 0, string $contenthash = ''): void {
        if ($fileid) {
            $this->fileid = $fileid;
        }
        if ($contenthash) {
            $this->contenthash = $contenthash;
        }
        if (!$this->fileid) {
            throw new \coding_exception('File id must be specified');
        }
        if (!$this->contenthash) {
            throw new \coding_exception('Content hash must be specified');
        }

        $this->granted = true;
        $this->public = $public;
    }

    public function grant_file(\stored_file $file, bool $public = false): void {
        $this->grant($public, $file->get_id(), $file->get_contenthash());
    }

    public function is_granted(): bool {
        return $this->granted;
    }

    public function get_url(bool $forcedownload = false): \moodle_url {
        global $USER;

        if (!$this->granted) {
            throw new \coding_exception('Access has not been granted');
        }

        if ($this->public) {
            // Public files have 'public' and only the main required fields.
            $fullpath = '/public/' . $this->fileid . '/' . $this->contenthash .
                '/' . $this->component . '/' . $this->area . $this->filepath;
        } else {
            // File path includes userid and timestamp (valid for 1 hour).
            $basepath =
                time() . '/' . $this->fileid . '/' . $this->contenthash .
                '/' . (int)$USER->id . '/' . $this->component . '/' . $this->area .
                $this->filepath;

            // Signature for security.
            $signature = fast_file::get_signature($basepath);

            $fullpath = '/' . $signature . '/' . $basepath;
        }

        // Force download option.
        $params = [];
        if ($forcedownload) {
            $params['forcedownload'] = 1;
        }

        return new \moodle_url('/files/fastfile.php' . $fullpath, $params);
    }
}
