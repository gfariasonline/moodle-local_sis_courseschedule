# Course access schedules (Horários de acesso ao curso) #

Local plugin `local_sis_courseschedule` for Moodle.

Temporarily blocks online access to selected courses outside the schedule configured for each course. Enrolments, grades and progress are not changed, and other courses remain available.

Per course, teachers or managers with the right capability can:

* enable or disable the restriction;
* choose the course time zone (IANA identifier);
* define allowed weekdays and one or more time intervals per day.

Outside the allowed hours, the browser shows a notice with the allowed days, intervals and time zone, and web service functions covered by the plugin return the error `courseaccessclosed`. Users with the `local/sis_courseschedule:bypass` capability are not restricted. Each new online request is evaluated again.

Covered: the course page, activities and protected files of modules that check course login, web services and AJAX functions that validate the course context, `core_course_get_contents`, and calendar events of closed courses (filtered out of the calendar web services).

Not covered in this version: iCalendar export by token, question flagging in an already open attempt (`toggleflag`) and third-party components that do not use the covered entry points. Content already loaded in the browser or downloaded in the Moodle app may remain visible. The official Moodle App may discard offline submissions made while a course is closed.

The plugin does not store a history of access attempts or the identity of the person who edited a rule.

## Requirements ##

* Moodle 4.5 to 5.3.
* PHP version required by your Moodle version.

## Capabilities ##

* `local/sis_courseschedule:manage` - configure the schedule of a course (granted by default to managers and editing teachers).
* `local/sis_courseschedule:bypass` - ignore the schedule restriction (granted by default to managers and editing teachers).

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/sis_courseschedule

On Moodle versions that use the `public` directory, the destination is

    {your/moodle/dirroot}/public/local/sis_courseschedule

Afterwards, log in to your Moodle site as an admin and go to _Site administration > Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Usage ##

Open the course, then _Course settings > Access schedules_ (or `/local/sis_courseschedule/edit.php?courseid=COURSEID`). Enable the rule, choose the time zone and add the allowed intervals. The start of an interval is inclusive and the end is exclusive. Days without intervals are closed.

A step-by-step tutorial (in Portuguese) is available in the project wiki:
https://github.com/gfariasonline/moodle-local_sis_courseschedule/wiki/Tutorial-de-uso

## Maturity ##

Alpha (release 0.2.0).

## Author ##

Thiago Serrão

## License ##

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
