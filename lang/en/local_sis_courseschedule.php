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

$string['allowedhours'] = 'Allowed access hours';
$string['backtomycourses'] = 'Go to My courses';
$string['blockedtitle'] = 'Course unavailable';
$string['courseaccessclosed'] = 'It is not possible to access this course at the moment. {$a}';
$string['courseaccessclosedshort'] = 'It is not possible to access this course at the moment.';
$string['currenttime'] = 'Current time in the course: {$a->day}, {$a->time} ({$a->timezone})';
$string['editschedule'] = 'Course access schedules';
$string['enabled'] = 'Restrict access to the scheduled hours';
$string['enabled_help'] = 'When enabled, the course can only be accessed during the intervals below, in the time zone of the course. Users with the "Access the course outside the scheduled hours" capability are not restricted. Enrolments, grades and progress are not changed.';
$string['errorinvalidtimezone'] = 'Select a valid time zone.';
$string['errornointerval'] = 'The restriction is active but no period is selected. Drag over the grid to select the allowed hours, or clear "Restrict access to the scheduled hours" to leave the course unrestricted.';
$string['errorinvalidslots'] = 'The selected periods are invalid. Reload the page and select them again.';
$string['erroroverlap'] = 'Two periods overlap on the same day.';
$string['intervalcrossmidnight'] = 'A period cannot cross midnight. To allow, for example, Monday 22:00 to Tuesday 02:00, select Monday 22:00-24:00 and Tuesday 00:00-02:00.';
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
$string['timezone'] = 'Course time zone';
$string['timezone_help'] = 'The allowed hours are evaluated in this time zone, regardless of the time zone of each user.';
$string['grid_addperiod'] = 'Add period (08:00-12:00)';
$string['grid_clearweek'] = 'Clear week';
$string['grid_copyweekdays'] = 'Copy Monday to Tuesday-Friday';
$string['grid_empty'] = 'No period selected: the course is closed all week.';
$string['grid_endtime'] = 'End (HH:MM)';
$string['grid_help'] = 'Click and drag over the grid to allow the hours of a day (blue). Start dragging on a blue cell to remove hours. Each row is 30 minutes. For exact minutes or keyboard use, edit the list below the grid.';
$string['grid_listtitle'] = 'Allowed periods';
$string['grid_remove'] = 'Remove';
$string['grid_starttime'] = 'Start (HH:MM)';
$string['grid_weekday'] = 'Day of the week';
$string['weeklygrid'] = 'Allowed hours';
