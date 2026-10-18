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
 * Notice shown when the course is closed by its access schedule.
 *
 * Shows only the notice and the allowed hours; it loads no course content.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Declared before the login so the plugin guard does not redirect this page to itself.
define('LOCAL_SIS_COURSESCHEDULE_OWNPAGE', true);

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
if ($courseid <= SITEID) {
    redirect(new moodle_url('/my/courses.php'));
}
$course = get_course($courseid);
$context = context_course::instance($course->id);

// Normal Moodle checks: whoever may not enter the course never sees its schedule.
require_login($course);

// Reopened (or never closed for this user): go back to the course.
if (\local_sis_courseschedule\local\access_policy::check($course->id, (int) $USER->id)->allowed) {
    redirect(course_get_url($course));
}

$PAGE->set_url(new moodle_url('/local/sis_courseschedule/blocked.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_show_course_index(false);
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('blockedtitle', 'local_sis_courseschedule'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$schedule = \local_sis_courseschedule\local\schedule_repository::get_by_course($course->id);

echo $OUTPUT->header();
echo $OUTPUT->notification(
    get_string('courseaccessclosedshort', 'local_sis_courseschedule'),
    \core\output\notification::NOTIFY_WARNING
);
if ($schedule) {
    echo $OUTPUT->heading(get_string('allowedhours', 'local_sis_courseschedule'), 3);
    $lines = array_map('s', \local_sis_courseschedule\local\schedule_formatter::format_day_lines($schedule));
    echo html_writer::alist($lines);
    echo html_writer::tag('p', s(get_string('scheduleotherdays', 'local_sis_courseschedule')));
    echo html_writer::tag('p', s(\local_sis_courseschedule\local\schedule_formatter::format_timezone($schedule)));
    echo html_writer::tag('p', s(\local_sis_courseschedule\local\schedule_formatter::format_current_time($schedule->timezone)));
}
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/my/courses.php'),
    get_string('backtomycourses', 'local_sis_courseschedule'),
    ['class' => 'btn btn-primary']
));
if (has_capability('local/sis_courseschedule:manage', $context)) {
    echo html_writer::tag('p', html_writer::link(
        new moodle_url('/local/sis_courseschedule/edit.php', ['courseid' => $course->id]),
        get_string('managescheduleblocked', 'local_sis_courseschedule')
    ));
}
echo $OUTPUT->footer();
