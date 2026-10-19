<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Moodle callbacks of the Course access schedules plugin.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called by require_login() after all of the normal access checks have passed.
 *
 * The signature is the one require_login() uses in Moodle 4.5 to 5.3: the arguments are passed through
 * unchanged, so $courseorid is the course (object or id) core resolved the login for.
 *
 * @param int|stdClass|null $courseorid
 * @param bool $autologinguest
 * @param cm_info|stdClass|null $cm
 * @param bool $setwantsurltome
 * @param bool $preventredirect
 */
function local_sis_courseschedule_after_require_login(
    $courseorid = null,
    $autologinguest = true,
    $cm = null,
    $setwantsurltome = true,
    $preventredirect = false
) {
    \local_sis_courseschedule\local\access_guard::after_require_login($courseorid, (bool) $preventredirect);
}

/**
 * Called before an external function runs (REST, AJAX and external_api::call_external_function()).
 *
 * @param stdClass $externalfunctioninfo
 * @param array $params
 * @return mixed False to let core run the function; any other value replaces its result.
 */
function local_sis_courseschedule_override_webservice_execution($externalfunctioninfo, $params) {
    return \local_sis_courseschedule\local\external_override::execute($externalfunctioninfo, (array) $params);
}

/**
 * Add the schedule page to the course settings navigation.
 *
 * @param navigation_node $navigation Course node.
 * @param stdClass $course
 * @param context $context Course context.
 */
function local_sis_courseschedule_extend_navigation_course($navigation, $course, $context) {
    if ($course->id == SITEID || !has_capability('local/sis_courseschedule:manage', $context)) {
        return;
    }
    $navigation->add(
        get_string('navigationlabel', 'local_sis_courseschedule'),
        new moodle_url('/local/sis_courseschedule/edit.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_sis_courseschedule',
        new pix_icon('i/calendar', '')
    );
}
