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
 * Base class for conditional availability information (for a module or
 * section).
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_availability;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for handling conditional availability information (for a module
 * or section).
 */
abstract class info_base {
    /** @var stdClass Course */
    private $course;

    /** @var bool Visibility flag (eye icon) */
    private $visible;

    /** @var string Availability data as JSON string */
    private $availability;

    /** @var tree Availability configuration, decoded from JSON; null if unset */
    private $availabilitytree;

    /**
     * Constructs with item details.
     *
     * @param stdClass $course Course object
     * @param int $visible Value of visible flag (eye icon)
     * @param string $availability Availability definition (JSON format) or null
     * @throws coding_exception If data is not valid JSON format
     */
    public function __construct($course, $visible, $availability) {
        // Set basic values.
        $this->course = $course;
        $this->visible = (bool)$visible;
        $this->availability = $availability;
    }

    /**
     * Gets the availability tree, decoding it if not already done.
     *
     * @return tree Availability tree
     */
    public function get_availability_tree() {
        if (is_null($this->availabilitytree)) {
            if (is_null($this->availability)) {
                throw new \coding_exception(
                        'Cannot call get_availability_tree with null availability');
            }
            $this->availabilitytree = $this->decode_availability($this->availability, true);
        }
        return $this->availabilitytree;
    }

    /**
     * Decodes availability data from JSON format.
     *
     * This function also validates the retrieved data as follows:
     * 1. Data that does not meet the API-defined structure causes a
     *    coding_exception (this should be impossible unless there is
     *    a system bug or somebody manually hacks the database).
     * 2. Data that meets the structure but cannot be implemented (e.g.
     *    reference to missing plugin or to module that doesn't exist) is
     *    either silently discarded (if $lax is true) or causes a
     *    coding_exception (if $lax is false).
     *
     * @param string $availability Availability string in JSON format
     * @param boolean $lax If true, throw exceptions only for invalid structure
     * @return tree Availability tree
     * @throws coding_exception If data is not valid JSON format
     */
    protected function decode_availability($availability, $lax) {
        // Decode JSON data.
        $structure = json_decode($availability);
        if (is_null($structure)) {
            throw new \coding_exception('Invalid availability text', $availability);
        }

        // Recursively decode tree.
        return new tree($structure, $lax);
    }

    /**
     * Determines whether this particular item is currently available
     * according to the availability criteria.
     *
     * - This does not include the 'visible' setting (i.e. this might return
     *   true even if visible is false); visible is handled independently.
     * - This does not take account of the viewhiddenactivities capability.
     *   That should apply later.
     *
     * Depending on options selected, a description of the restrictions which
     * mean the student can't view it (in HTML format) may be stored in
     * $information. If there is nothing in $information and this function
     * returns false, then the activity should not be displayed at all.
     *
     * This function displays debugging() messages if the availability
     * information is invalid.
     *
     * @param string $information String describing restrictions in HTML format
     * @param bool $grabthelot Performance hint: if true, caches information
     *   required for all course-modules, to make the front page and similar
     *   pages work more quickly (works only for current user)
     * @param int $userid If set, specifies a different user ID to check availability for
     * @param course_modinfo|null $modinfo Usually leave as null for default. Specify when
     *   calling recursively from inside get_fast_modinfo()
     * @return bool True if this item is available to the user, false otherwise
     */
    public function is_available(&$information, $grabthelot = false, $userid = 0,
            \course_modinfo $modinfo=null) {
        global $USER;

        // Default to no information.
        $information = '';

        // Do nothing if there are no availability restrictions.
        if (is_null($this->availability)) {
            return true;
        }

        // Resolve optional parameters.
        if (!$userid) {
            $userid = $USER->id;
        }
        if (!$modinfo) {
            $modinfo = get_fast_modinfo($this->course);
        }

        // Get availability from tree.
        try {
            $tree = $this->get_availability_tree();
            $result = $tree->check_available(
                    false, $this->course, $grabthelot, $userid, $modinfo);
        } catch (\coding_exception $e) {
            // We catch the message because it causes fatal problems in most of
            // the GUI if this exception gets thrown (you can't edit the
            // activity to fix it). Obviously it should never happen anyway, but
            // just in case.
            debugging('Error processing availability data for &lsquo;' .
                    $this->get_thing_name() . '&rsquo;: ' . s($e->a));
            return false;
        }

        // See if there are any messages.
        if ($result->is_available()) {
            return true;
        } else {
            // If the item is marked as 'not visible' then we don't change the available
            // flag (visible/available are treated distinctly), but we remove any
            // availability info. If the item is hidden with the eye icon, it doesn't
            // make sense to show 'Available from <date>' or similar, because even
            // when that date arrives it will still not be available unless somebody
            // toggles the eye icon.
            if ($this->visible) {
                $information = $tree->get_result_information(
                        $this->course, $modinfo, $result);
            }

            return false;
        }
    }

    /**
     * Checks whether this activity is going to be available for all users.
     *
     * Normally, if there are any conditions, then it may be hidden depending
     * on the user. However in the case of date conditions there are some
     * conditions which will definitely not result in it being hidden for
     * anyone.
     *
     * @return bool True if activity is available for all
     */
    public function is_available_for_all() {
        if (is_null($this->availability)) {
            return true;
        } else {
            return $this->get_availability_tree()->is_available_for_all();
        }
    }

    /**
     * Obtains a string describing all availability restrictions (even if
     * they do not apply any more). Used to display information for staff
     * editing the website.
     *
     * The modinfo parameter must be specified when it is called from inside
     * get_fast_modinfo, to avoid infinite recursion.
     *
     * This function displays debugging() messages if the availability
     * information is invalid.
     *
     * @param course_modinfo|null $modinfo Usually leave as null for default
     * @return string Information string (for admin) about all restrictions on
     *   this item
     */
    public function get_full_information(course_modinfo $modinfo = null) {
        // Do nothing if there are no availability restrictions.
        if (is_null($this->availability)) {
            return '';
        }

        // Resolve optional parameter.
        if (!$modinfo) {
            $modinfo = get_fast_modinfo($this->course);
        }

        try {
            return $this->get_availability_tree()->get_full_information(
                    $this->course, $modinfo);
        } catch (\coding_exception $e) {
            // Again we catch the message to avoid problems in GUI.
            debugging('Error processing availability data for &lsquo;' .
                    $this->get_thing_name() . '&rsquo;: ' . s($e->a));
            return false;
        }
    }

    /**
     * Called during restore (near end of restore). Updates any necessary ids
     * and writes the updated tree to the database. May output warnings if
     * necessary (e.g. if a course-module cannot be found after restore).
     *
     * @param string $restoreid Restore identifier
     * @param base_logger $logger Logger for any warnings
     */
    public function update_after_restore($restoreid, \base_logger $logger) {
        $tree = $this->get_availability_tree();
        $changed = $tree->update_after_restore($restoreid, $logger, $this->get_thing_name());
        if ($changed) {
            // Save modified data.
            $structure = $tree->save();
            $this->set_in_database(json_encode($structure));
        }
    }

    /**
     * Obtains the name of the item (cm_info or section_info, at present) that
     * this is controlling availability of. Name should be formatted ready
     * for on-screen display.
     *
     * @return string Name of item
     */
    protected abstract function get_thing_name();

    /**
     * Stores an updated availability tree JSON structure into the relevant
     * database table.
     *
     * @param string $availabilty New JSON value
     */
    protected abstract function set_in_database($availabilty);

    /**
     * In rare cases the system may want to change all references to one ID
     * (e.g. one course-module ID) to another one, within a course. This
     * function does that for the conditional availability data for all
     * modules and sections on the course.
     *
     * @param int|stdClass $courseorid Course id or object
     * @param string $table Table name e.g. 'course_modules'
     * @param int $oldid Previous ID
     * @param int $newid New ID
     * @return bool True if anything changed, otherwise false
     */
    public static function update_dependency_id_across_course(
            $courseorid, $table, $oldid, $newid) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $modinfo = get_fast_modinfo($courseorid);
        $anychanged = false;
        foreach ($modinfo->get_cms() as $cm) {
            $info = new info_module($cm);
            $changed = $info->update_dependency_id($table, $oldid, $newid);
            $anychanged = $anychanged || $changed;
        }
        foreach ($modinfo->get_section_info_all() as $section) {
            $info = new info_section($section);
            $changed = $info->update_dependency_id($table, $oldid, $newid);
            $anychanged = $anychanged || $changed;
        }
        $transaction->allow_commit();
        if ($anychanged) {
            get_fast_modinfo($courseorid, 0, true);
        }
        return $anychanged;
    }

    /**
     * Called on a single item. If necessary, updates availability data where
     * it has a dependency on an item with a particular id.
     *
     * @param string $table Table name e.g. 'course_modules'
     * @param int $oldid Previous ID
     * @param int $newid New ID
     * @return bool True if it changed, otherwise false
     */
    protected function update_dependency_id($table, $oldid, $newid) {
        // Do nothing if there are no availability restrictions.
        if (is_null($this->availability)) {
            return false;
        }
        // Pass requirement on to tree object.
        $tree = $this->get_availability_tree();
        $changed = $tree->update_dependency_id($table, $oldid, $newid);
        if ($changed) {
            // Save modified data.
            $structure = $tree->save();
            $this->set_in_database(json_encode($structure));
        }
        return $changed;
    }
}
