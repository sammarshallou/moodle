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
 * Script for generating the printable version of the discussion or selected posts.
 * This uses the post selector infrastructure to handle the situation when posts
 * are being selected.
 * @package forumngfeature
 * @subpackage print
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../post_selector.php');

class print_post_selector extends post_selector {
    function get_button_name() {
        return get_string('print', 'forumngfeature_print');
    }
    function get_page_name() {
        return get_string('print_pagename', 'forumngfeature_print');
    }
    function apply($discussion, $all, $selected, $formdata) {
        global $COURSE, $USER, $CFG;
        $d = $discussion->get_id();
        $forum = $discussion->get_forum();
        print_header($this->get_page_name());
        $printablebacklink = $CFG->wwwroot . '/mod/forumng/discuss.php?' . $discussion->get_link_params(mod_forumng::PARAM_HTML) ;
        print '
<div class="forumng-printable-header">
<div class="forumng-printable-backlink">' . link_arrow_left($discussion->get_subject(), $printablebacklink) . '</div>
<div class="forumng-printable-date">' . get_string('printedat','forumngfeature_print', userdate(time())) . '</div>
<div class="clearer"></div></div>' . "\n" . '<div class="forumng-showprintable">';
        if ($all) {
            print $forum->get_type()->display_discussion($discussion, array(
                mod_forumng_post::OPTION_NO_COMMANDS => true,
                mod_forumng_post::OPTION_CHILDREN_EXPANDED => true,
                mod_forumng_post::OPTION_PRINTABLE_VERSION => true));
        } else {
            $allhtml = '';
            $alltext = '';
            $discussion->build_selected_posts_email($selected, $alltext, $allhtml, true, true);
            print $allhtml;
        }

        print "</div>";
        $forum->print_js(0, true);
        print "\n</body>\n<html>";
    }
}

post_selector::go(new print_post_selector());
