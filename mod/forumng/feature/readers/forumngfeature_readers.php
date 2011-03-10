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
 * Discussion feature: Show people who have read the discussion on the website
 * or who have clicked 'mark read' to mark it as read. Does not include those
 * who read using Atom feed or email subscription.
 * @package forumngfeature
 * @subpackage readers
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forumngfeature_readers extends forumngfeature_discussion {
    public function get_order() {
        return 400;
    }

    public function should_display($discussion) {
        // Check the forum isn't shared (this breaks things because we dunno
        // which groups to use)
        if ($discussion->get_forum()->is_shared()) {
            return false;
        }

        // Check the discussion's within time period
        if (!$discussion->has_unread_data()) {
            return false;
        }

        // Check they have actual permission
        $context = $discussion->get_forum()->get_context();
        if (!has_capability('mod/forumng:viewreadinfo', $context)
            || $discussion->is_deleted()) {
            return false;
        }

        // For group forum, check they have group access
        if ($groupid = $discussion->get_group_id()) {
            // This requires 'write' access i.e. you don't get it just from
            // visible groups
            if (!$discussion->get_forum()->can_access_group($groupid, true)) {
                return false;
            }
        } else {
            // If the forum is NOT grouped, but the course IS, then you must
            // be in a group or have access all groups (because we will only
            // show read data for students in groups you're in)
            $course = $discussion->get_forum()->get_course();
            if ($course->groupmode &&
                    !has_capability('moodle/site:accessallgroups', $context)) {
                // Check they are in at least one group
                global $USER;
                $groups = groups_get_all_groups($course->id, $USER->id,
                        $course->defaultgroupingid);
                if (!$groups || count($groups) == 0) {
                    return false;
                }
            }
        }

        // OK...
        return true;
    }

    public function display($discussion) {
        return parent::get_button($discussion,
            get_string('viewreaders', 'forumngfeature_readers'),
                'feature/readers/readers.php');
    }
}
