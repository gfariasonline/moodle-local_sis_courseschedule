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
 * Configuration of the access schedule of a course.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The page must stay reachable by managers while the course is closed for them.
define('LOCAL_SIS_COURSESCHEDULE_OWNPAGE', true);

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
if ($courseid <= SITEID) {
    throw new moodle_exception('invalidcourseid', 'error');
}
$course = get_course($courseid);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/sis_courseschedule:manage', $context);

$url = new moodle_url('/local/sis_courseschedule/edit.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_show_course_index(false);
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('editschedule', 'local_sis_courseschedule'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$schedule = \local_sis_courseschedule\local\schedule_repository::get_by_course($course->id);
$form = new \local_sis_courseschedule\form\course_schedule_form($url, ['courseid' => $course->id, 'schedule' => $schedule]);
$form->set_data(\local_sis_courseschedule\form\course_schedule_form::to_form_data($schedule));

if ($form->is_cancelled()) {
    redirect(course_get_url($course));
}
if ($data = $form->get_data()) {
    // The course comes from the validated login above; the hidden form field is never trusted.
    $slots = \local_sis_courseschedule\form\course_schedule_form::slots_from_json((string) ($data->slotsjson ?? ''));
    \local_sis_courseschedule\local\schedule_repository::save($course->id, !empty($data->enabled), $data->timezone, $slots);
    $saved = \local_sis_courseschedule\local\schedule_repository::get_by_course($course->id);
    redirect(
        course_get_url($course),
        get_string(
            'schedulesaved',
            'local_sis_courseschedule',
            \local_sis_courseschedule\local\schedule_formatter::format_text($saved)
        ),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editschedule', 'local_sis_courseschedule'));
if (\local_sis_courseschedule\local\access_policy::is_restricted_without_bypass($course->id, (int) $USER->id)) {
    echo $OUTPUT->notification(
        get_string('managerbypassnotice', 'local_sis_courseschedule'),
        \core\output\notification::NOTIFY_INFO
    );
}
$form->display();
echo $OUTPUT->footer();
