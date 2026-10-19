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
 * @author     Thiago Serrão
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
        $data += ['enabled' => 1, 'timezone' => 'America/Sao_Paulo', 'slotsjson' => '[]'];
        return $form->validation($data, []);
    }

    /**
     * Encode intervals as the grid does.
     *
     * @param array[] $slots Lists of [weekday, start minute, end minute].
     * @return string
     */
    private static function json(array $slots): string {
        return json_encode(array_map(
            fn($slot) => ['weekday' => $slot[0], 'startminute' => $slot[1], 'endminute' => $slot[2]],
            $slots
        ));
    }

    public function test_valid_slots(): void {
        $json = self::json([[1, 480, 720], [1, 1140, 1440], [2, 0, 60]]);
        $this->assertSame([], $this->validate(['slotsjson' => $json]));
        $this->assertSame([
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 720],
            ['weekday' => 1, 'startminute' => 1140, 'endminute' => 1440],
            ['weekday' => 2, 'startminute' => 0, 'endminute' => 60],
        ], course_schedule_form::slots_from_json($json));
    }

    public function test_validation_errors(): void {
        // The same hours on different days do not overlap.
        $this->assertSame([], $this->validate(['slotsjson' => self::json([[1, 480, 660], [2, 480, 660]])]));

        $overlap = $this->validate(['slotsjson' => self::json([[1, 480, 660], [1, 600, 720]])]);
        $this->assertSame(['schedulegrid'], array_keys($overlap));
        $this->assertStringContainsString('overlap', $overlap['schedulegrid']);

        $invalid = [
            self::json([[1, 600, 540]]),
            self::json([[1, 600, 600]]),
            self::json([[0, 60, 120]]),
            self::json([[8, 60, 120]]),
            self::json([[1, -1, 120]]),
            self::json([[1, 60, 1441]]),
            'not json',
            '{"weekday":1}',
            '[{"weekday":"1","startminute":0,"endminute":60}]',
            '[{"weekday":1}]',
        ];
        foreach ($invalid as $json) {
            $this->assertArrayHasKey('schedulegrid', $this->validate(['slotsjson' => $json]), $json);
            $this->assertSame([], course_schedule_form::slots_from_json($json), $json);
        }
    }

    public function test_active_rule_needs_an_interval_and_inactive_does_not(): void {
        $errors = $this->validate(['enabled' => 1]);
        $this->assertSame(['schedulegrid'], array_keys($errors));
        $this->assertStringContainsString('no period is selected', $errors['schedulegrid']);
        $this->assertSame([], $this->validate(['enabled' => 0]));
        $this->assertSame([], $this->validate(['enabled' => 0, 'slotsjson' => '']));
    }

    public function test_timezone_must_be_iana(): void {
        $this->assertArrayHasKey('timezone', $this->validate(['enabled' => 0, 'timezone' => 'Mars/Olympus']));
    }

    public function test_round_trip_with_stored_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $slots = course_schedule_form::slots_from_json(self::json([[5, 1080, 1440], [1, 480, 750]]));
        schedule_repository::save($course->id, true, 'Europe/Lisbon', $slots);
        $form = course_schedule_form::to_form_data(schedule_repository::get_by_course($course->id));
        $this->assertSame(1, $form['enabled']);
        $this->assertSame('Europe/Lisbon', $form['timezone']);
        $this->assertEquals([
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 750],
            ['weekday' => 5, 'startminute' => 1080, 'endminute' => 1440],
        ], json_decode($form['slotsjson'], true));
        $this->assertSame([], course_schedule_form::to_form_data(null));

        // The form with a stored rule renders the grid mount point and the preloaded intervals.
        $instance = new course_schedule_form(new \moodle_url('/local/sis_courseschedule/edit.php'), [
            'courseid' => $course->id, 'schedule' => schedule_repository::get_by_course($course->id),
        ]);
        $instance->set_data($form);
        $html = $instance->render();
        $this->assertStringContainsString('id="local-sis-courseschedule-grid"', $html);
        $this->assertStringContainsString('name="slotsjson"', $html);
        $this->assertStringContainsString('Monday: 08:00 - 12:30', $html);
    }
}
