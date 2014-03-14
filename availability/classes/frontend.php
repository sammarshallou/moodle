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
 * Class with front-end (editing form) functionality. This is a base class
 * of a class implemented by each component, and also has static methods.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_availability;

defined('MOODLE_INTERNAL') || die();

/**
 * Class with front-end (editing form) functionality. This is a base class
 * of a class implemented by each plugin, and also has static methods.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class frontend {
    /**
     * Includes JavaScript for the current plugin.
     *
     * Default implementation includes a YUI module called
     * 'moodle-availability_whatever-form' and the 'title' JS string, plus
     * also whatever strings are specified by get_javascript_strings().
     */
    protected function include_javascript() {
        global $PAGE, $CFG;
        $component = $this->get_component();
        $PAGE->requires->yui_module(array('moodle-' . $component . '-form',
                'moodle-core_availability-form'),
                'M.' . $component . '.form.init', array_merge(array($component),
                $this->get_javascript_init_params()));

        $identifiers = $this->get_javascript_strings();
        $identifiers[] = 'title';
        $identifiers[] = 'description';
        $PAGE->requires->strings_for_js($identifiers, $component);
    }
    
    /**
     * Decides whether this plugin should be available in a given course. The
     * plugin can do this depending on course or system settings.
     *
     * Default returns true.
     *
     * @param stdClass $course Course object
     */
    protected function allow_usage($course) {
        return true;
    }

    /**
     * Gets a list of string identifiers (in the plugin's language file) that
     * are required in JavaScript for this plugin. The default returns nothing.
     *
     * You do not need to include the 'title' string (which is used by core) as
     * this is automatically added.
     *
     * @return array Array of required string identifiers
     */
    protected function get_javascript_strings() {
        return array();
    }

    /**
     * Gets parameters for the plugin's init function. Default returns no
     * parameters.
     *
     * @return array Array of parameters for the JavaScript function
     */
    protected function get_javascript_init_params() {
        return array();
    }

    /**
     * Gets the Frankenstyle component name for this plugin.
     *
     * @return string The component name for this plugin
     */
    protected function get_component() {
        return preg_replace('~^(availability_.*?)\\\\frontend$~', '$1', get_class($this));
    }

    /**
     * Includes JavaScript for the main system and all plugins.
     * 
     * @param stdClass $course Moodle course object
     */
    public static function include_all_javascript($course) {
        global $PAGE;

        // Include main JS. This is initialised on DOM ready, i.e. after the
        // plugins.
        $PAGE->requires->yui_module(array('moodle-core_availability-form',
                'base', 'node', 'panel', 'moodle-core-notification-dialogue'),
                'M.core_availability.form.init', array(), null, true);
        $PAGE->requires->strings_for_js(array('none', 'cancel', 'delete'), 'moodle');
        $PAGE->requires->strings_for_js(array('addrestriction',
                'listheader_sign_before', 'listheader_sign_pos',
                'listheader_sign_neg', 'listheader_single',
                'listheader_multi_after', 'listheader_multi_before',
                'listheader_multi_or', 'listheader_multi_and',
                'unknowncondition', 'hide_verb', 'hidden_individual',
                'show_verb', 'shown_individual', 'hidden_all', 'shown_all'),
                'availability');

        // Include JS for all components.
        $pluginmanager = \core_plugin_manager::instance();
        $enabled = $pluginmanager->get_enabled_plugins('availability');
        foreach ($enabled as $plugin => $info) {
            // TODO Remove this
            if ($plugin !== 'date') {
                continue;
            }
            // You can use a custom frontend.php if necessary.
            $class = '\availability_' . $plugin . '\frontend';
            $frontend = new $class();
            if ($frontend->allow_usage($course)) {
                $frontend->include_javascript();
            }
        }
    }
}
