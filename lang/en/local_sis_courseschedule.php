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
 * English language strings.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addslots'] = 'Add more intervals';
$string['allowedhours'] = 'Allowed access hours';
$string['backtomycourses'] = 'Go to My courses';
$string['blockedtitle'] = 'Course unavailable';
$string['courseaccessclosed'] = 'It is not possible to access this course at the moment. {$a}';
$string['courseaccessclosedshort'] = 'It is not possible to access this course at the moment.';
$string['currenttime'] = 'Current time in the course: {$a->day}, {$a->time} ({$a->timezone})';
$string['editschedule'] = 'Course access schedules';
$string['enabled'] = 'Restrict access to the scheduled hours';
$string['enabled_help'] = 'When enabled, the course can only be accessed during the intervals below, in the time zone of the course. Users with the "Access the course outside the scheduled hours" capability are not restricted. Enrolments, grades and progress are not changed.';
$string['endtime'] = 'End (HH:MM)';
$string['errorendbeforestart'] = 'The end must be later than the start.';
$string['errorinvalidtime'] = 'Use the HH:MM format (00:00 to 23:59; 24:00 is accepted only as an end).';
$string['errorinvalidtimezone'] = 'Select a valid time zone.';
$string['errornointerval'] = 'The restriction is active but no interval was filled in. Add at least one weekly interval below (day, start and end), or clear "Restrict access to the scheduled hours" to leave the course unrestricted.';
$string['erroroverlap'] = 'This interval overlaps another interval on the same day.';
$string['errorpartialinterval'] = 'Fill in both the start and the end, or leave both empty.';
$string['intervalcrossmidnight'] = 'An interval cannot cross midnight. To allow, for example, Monday 22:00 to Tuesday 02:00, add Monday 22:00-24:00 and Tuesday 00:00-02:00.';
$string['intervalsheader'] = 'Weekly intervals';
$string['managescheduleblocked'] = 'Manage the access schedule';
$string['managerbypassnotice'] = 'You can configure this schedule, but you do not have the "Access the course outside the scheduled hours" capability. While the restriction is active, the course content stays blocked for you outside the allowed hours; this configuration page remains available.';
$string['navigationlabel'] = 'Access schedules';
$string['pluginname'] = 'Course access schedules';
$string['privacy:metadata'] = 'The Course access schedules plugin only stores course level configuration (weekly intervals and timezone). It does not store personal data.';
$string['scheduleimpact'] = 'Outside the allowed hours the course, its activities and its files cannot be opened online, and answers cannot be sent or synchronised. Quiz autosave and submissions made after closing may be denied. Content already downloaded in the mobile app can still be used offline, but whatever is sent after closing is rejected by the server. Changes take effect on the next request.';
$string['scheduleline'] = '{$a->day}: {$a->intervals}';
$string['schedulenone'] = 'No schedule is configured for this course.';
$string['scheduleotherdays'] = 'On the other days the course is not available.';
$string['schedulesaved'] = 'The access schedule was saved. Current schedule: {$a}';
$string['schedulesummary'] = 'Current saved schedule';
$string['scheduletimezone'] = 'Time zone: {$a}';
$string['sis_courseschedule:bypass'] = 'Access the course outside the scheduled hours';
$string['sis_courseschedule:manage'] = 'Manage the course access schedule';
$string['starttime'] = 'Start (HH:MM)';
$string['timezone'] = 'Course time zone';
$string['timezone_help'] = 'The allowed hours are evaluated in this time zone, regardless of the time zone of each user.';
$string['weekday'] = 'Day of the week';
