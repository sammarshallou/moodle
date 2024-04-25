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

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../config.php');

$forcedownload = optional_param('forecedownload', 0, PARAM_INT);

// Check path matches required pattern.
$path = get_file_argument();
if (preg_match('~^/public/([0-9]+)/([0-9a-f]+)/([^/]+)/([^/]+)(/.*)$~', $path, $matches)) {
    $public = true;
    [1 => $fileid, 2 => $contenthash, 3 => $component, 4 => $area, 5 => $filepath] = $matches;
} else if (preg_match('~^/([^/]+)/(([0-9]+)/([0-9]+)/([0-9a-f]+)/([0-9]+)/([^/]+)/([^/]+)(/.*))$~', $path, $matches)) {
    $public = false;
    [1 => $signature, 2 => $basepath, 3 => $time, 4 => $fileid, 5 => $contenthash,
        6 => $userid, 7 => $component, 8 => $area, 9 => $filepath] = $matches;
} else {
    throw new \moodle_exception('invalidrequest', debuginfo: 'Bad path');
}

if (!$public) {
    // Check link has not expired yet.
    $timesince = time() - $time;
    if ($timesince < 0 || $timesince > fast_file::EXPIRY_TIME) {
        throw new \moodle_exception('expiredkey');
    }

    // Check link signature is valid.
    if ($signature !== fast_file::get_signature($basepath)) {
        throw new \moodle_exception('invalidrequest', debuginfo: 'Signature does not match');
    }
}

// Load file.
$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);
if (!$file) {
    throw new \moodle_exception('filenotfound');
}

// Check file matches expected contenthash.
if ($file->get_contenthash() !== $contenthash) {
    throw new \moodle_exception('filenotfound', debuginfo: 'Content hash does not match');
}

$options = [];
if ($public) {
    $options['cacheability'] = 'public';
}
send_stored_file($file, YEARSECS, 0, $forcedownload, $options);
