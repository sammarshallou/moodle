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
 * Search area base class for blocks.
 *
 * Note: Only blocks within courses are supported.
 *
 * @package core_search
 * @copyright 2017 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area base class for blocks.
 *
 * Note: Only blocks within courses are supported.
 *
 * @package core_search
 * @copyright 2017 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_block extends base {

    /**
     * The context levels the search area is working on.
     *
     * This can be overwriten by the search area if it works at multiple
     * levels.
     *
     * @var array
     */
    protected static $levels = [CONTEXT_BLOCK];

    /**
     * Gets the block name only.
     *
     * @return string Block name e.g. 'html'
     */
    public function get_block_name() {
        // Remove 'block_' text.
        return substr($this->get_component_name(), 6);
    }

    /**
     * Returns restrictions on which block_instances rows to return. By default, excludes rows
     * that have empty configdata.
     *
     * @return string SQL restriction (or multiple restrictions joined by AND), empty if none
     */
    protected function get_indexing_restrictions() {
        return "bi.configdata != ''";
    }

    /**
     * Gets recordset of all records modified since given time.
     *
     * See base class for detailed requirements. This implementation includes the key fields
     * from block_instances.
     *
     * This can be overridden to do something totally different if the block's data is stored in
     * other tables.
     *
     * If there are certain instances of the block which should not be included in the search index
     * then you can override get_indexing_restrictions; by default this excludes rows with empty
     * configdata.
     *
     * @param int $modifiedfrom Modified from time (>= this)
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;
        $restrictions = $this->get_indexing_restrictions();
        if ($restrictions) {
            $restrictions = 'AND ' . $restrictions;
        }
        // Query for all entries in block_instances for this type of block, which were modified
        // since the given date. Also find the course or module where the block is located.
        // (Although this query supports both module and course context, currently only two page
        // types are supported, which will both be at course context. The module support is present
        // in case of extension to other page types later.)
        return $DB->get_recordset_sql("
                SELECT bi.id, bi.timemodified, bi.timecreated, bi.configdata,
                       c.id AS courseid, x.id AS contextid
                  FROM {block_instances} bi
                  JOIN {context} x ON x.instanceid = bi.id AND x.contextlevel = ?
                  JOIN {context} parent ON parent.id = bi.parentcontextid
             LEFT JOIN {course_modules} cm ON cm.id = parent.instanceid AND parent.contextlevel = ?
                  JOIN {course} c ON c.id = cm.course
                       OR (c.id = parent.instanceid AND parent.contextlevel = ?)
                 WHERE bi.timemodified >= ?
                       AND bi.blockname = ?
                       AND (parent.contextlevel = ? AND (bi.pagetypepattern LIKE 'course-view-%' 
                           OR bi.pagetypepattern IN ('site-index', 'course-*', '*')))
                       $restrictions
              ORDER BY bi.timemodified ASC",
                [CONTEXT_BLOCK, CONTEXT_MODULE, CONTEXT_COURSE, $modifiedfrom,
                $this->get_block_name(), CONTEXT_COURSE]);
    }

    public function get_doc_url(\core_search\document $doc) {
        global $DB;

        // Load block instance and find cmid if there is one.
        $blockinstanceid = preg_replace('~^.*-~', '', $doc->get('id'));
        $instance = $DB->get_record_sql("
                SELECT bi.id, bi.pagetypepattern, bi.subpagepattern, cm.id AS cmid
                  FROM {block_instances} bi
                  JOIN {context} parent ON parent.id = bi.parentcontextid
             LEFT JOIN {course_modules} cm ON cm.id = parent.instanceid AND parent.contextlevel = ?
                 WHERE bi.id = ?",
                [CONTEXT_MODULE, $blockinstanceid], MUST_EXIST);
        $courseid = $doc->get('courseid');
        $anchor = 'inst' . $blockinstanceid;

        // Check if the block is at course or module level.
        if ($instance->cmid) {
            // No module-level page types are supported at present so the search system won't return
            // them. But let's put some example code here to indicate how it could work.
            debugging('Unexpected module-level page type for block ' . $blockinstanceid . ': ' .
                    $instance->pagetypepattern, DEBUG_DEVELOPER);
            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($instance->cmid);
            return new \moodle_url($cm->url, null, $anchor);
        } else {
            // The block is at course level. Let's check the page type, although in practice we
            // currently only support the course main page.
            if ($instance->pagetypepattern === '*' || $instance->pagetypepattern === 'course-*' ||
                    preg_match('~^course-view-(.*)$~', $instance->pagetypepattern)) {
                return new \moodle_url('/course/view.php', ['id' => $courseid], $anchor);
            } else if ($instance->pagetypepattern === 'site-index') {
                return new \moodle_url('/', [], $anchor);
            } else {
                debugging('Unexpected page type for block ' . $blockinstanceid . ': ' .
                        $instance->pagetypepattern, DEBUG_DEVELOPER);
                return new \moodle_url('/course/view.php', ['id' => $courseid], $anchor);
            }
        }
    }

    public function get_context_url(\core_search\document $doc) {
        return $this->get_doc_url($doc);
    }
}
