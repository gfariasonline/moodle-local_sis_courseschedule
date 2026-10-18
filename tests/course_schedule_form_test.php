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

namespace local_sis_courseschedule;

use local_sis_courseschedule\form\course_schedule_form;
use local_sis_courseschedule\local\schedule_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests for the schedule form helpers and validation.
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\form\course_schedule_form
 */
final class course_schedule_form_test extends \advanced_testcase {
    /**
     * Build the form without a submission and run its validation on the given data.
     *
     * @param array $data
     * @return array Errors.
     */
    private function validate(array $data): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $form = new course_schedule_form(new \moodle_url('/local/sis_courseschedule/edit.php'), [
            'courseid' => $course->id, 'schedule' => null,
        ]);
        $data += ['enabled' => 1, 'timezone' => 'America/Sao_Paulo', 'weekday' => [], 'starttime' => [], 'endtime' => []];
        return $form->validation($data, []);
    }

    public function test_parse_time(): void {
        $this->assertSame(0, course_schedule_form::parse_time('00:00', false));
        $this->assertSame(485, course_schedule_form::parse_time('8:05', false));
        $this->assertSame(1439, course_schedule_form::parse_time('23:59', true));
        $this->assertSame(1440, course_schedule_form::parse_time('24:00', true));
        $this->assertNull(course_schedule_form::parse_time('24:00', false));
        $this->assertNull(course_schedule_form::parse_time('24:01', true));
        $this->assertNull(course_schedule_form::parse_time('25:00', true));
        $this->assertNull(course_schedule_form::parse_time('08:60', true));
        $this->assertNull(course_schedule_form::parse_time('abc', true));
        $this->assertNull(course_schedule_form::parse_time('', true));
    }

    public function test_valid_rows_and_blank_rows(): void {
        $data = ['weekday' => [1, 1, 2], 'starttime' => ['08:00', '19:00', ''], 'endtime' => ['12:00', '24:00', '']];
        $this->assertSame([], $this->validate($data));
        $this->assertSame([
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 720],
            ['weekday' => 1, 'startminute' => 1140, 'endminute' => 1440],
        ], course_schedule_form::slots_from_data($data));
    }

    public function test_validation_errors(): void {
        $errors = $this->validate(['weekday' => [1, 1], 'starttime' => ['10:00', '09:00'], 'endtime' => ['09:00', '10:30']]);
        $this->assertArrayHasKey('endtime[0]', $errors);
        $this->assertArrayNotHasKey('endtime[1]', $errors);

        $errors = $this->validate(['weekday' => [1, 1], 'starttime' => ['08:00', '10:00'], 'endtime' => ['11:00', '12:00']]);
        $this->assertArrayHasKey('endtime[1]', $errors);

        // The same hours on different days do not overlap.
        $samehours = ['weekday' => [1, 2], 'starttime' => ['08:00', '08:00'], 'endtime' => ['11:00', '11:00']];
        $this->assertSame([], $this->validate($samehours));

        $errors = $this->validate(['weekday' => [1], 'starttime' => ['08:00'], 'endtime' => ['']]);
        $this->assertArrayHasKey('endtime[0]', $errors);

        $errors = $this->validate(['weekday' => [1], 'starttime' => ['xx'], 'endtime' => ['10:00']]);
        $this->assertArrayHasKey('starttime[0]', $errors);
    }

    public function test_active_rule_needs_an_interval_and_inactive_does_not(): void {
        $errors = $this->validate(['enabled' => 1]);
        // The error is anchored on the first interval row, next to where the intervals are typed.
        $this->assertSame(['starttime[0]'], array_keys($errors));
        $this->assertStringContainsString('no interval was filled in', $errors['starttime[0]']);
        $this->assertStringContainsString('Add at least one weekly interval', $errors['starttime[0]']);
        $blank = $this->validate(['enabled' => 1, 'weekday' => [1, 1], 'starttime' => ['', ''], 'endtime' => ['', '']]);
        $this->assertArrayHasKey('starttime[0]', $blank);
        $this->assertSame([], $this->validate(['enabled' => 0]));
    }

    public function test_timezone_must_be_iana(): void {
        $this->assertArrayHasKey('timezone', $this->validate(['enabled' => 0, 'timezone' => 'Mars/Olympus']));
    }

    public function test_round_trip_with_stored_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $slots = course_schedule_form::slots_from_data([
            'weekday' => [5, 1], 'starttime' => ['18:00', '08:00'], 'endtime' => ['24:00', '12:30'],
        ]);
        schedule_repository::save($course->id, true, 'Europe/Lisbon', $slots);
        $form = course_schedule_form::to_form_data(schedule_repository::get_by_course($course->id));
        $this->assertSame(1, $form['enabled']);
        $this->assertSame('Europe/Lisbon', $form['timezone']);
        $this->assertSame([1, 5], $form['weekday']);
        $this->assertSame(['08:00', '18:00'], $form['starttime']);
        $this->assertSame(['12:30', '24:00'], $form['endtime']);
        $this->assertSame([], course_schedule_form::to_form_data(null));

        // The form with a stored rule renders with its rows preloaded.
        $instance = new course_schedule_form(new \moodle_url('/local/sis_courseschedule/edit.php'), [
            'courseid' => $course->id, 'schedule' => schedule_repository::get_by_course($course->id),
        ]);
        $instance->set_data($form);
        $html = $instance->render();
        $this->assertStringContainsString('value="12:30"', $html);
        $this->assertStringContainsString('name="starttime[1]"', $html);
        $this->assertStringContainsString('Monday: 08:00 - 12:30', $html);
    }
}
