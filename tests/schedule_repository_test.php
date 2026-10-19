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

use local_sis_courseschedule\local\schedule_repository;

/**
 * Tests for the schedule repository.
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\local\schedule_repository
 */
final class schedule_repository_test extends \advanced_testcase {
    public function test_get_returns_null_without_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertNull(schedule_repository::get_by_course($course->id));
    }

    public function test_save_and_get_roundtrip(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, true, 'America/Sao_Paulo', [
            ['weekday' => 3, 'startminute' => 840, 'endminute' => 1080],
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 720],
            ['weekday' => 1, 'startminute' => 780, 'endminute' => 1440],
        ]);
        $schedule = schedule_repository::get_by_course($course->id);
        $this->assertEquals($course->id, $schedule->courseid);
        $this->assertEquals(1, $schedule->enabled);
        $this->assertSame('America/Sao_Paulo', $schedule->timezone);
        $this->assertCount(3, $schedule->slots);
        // Ordered by weekday then start.
        $this->assertEquals([1, 480, 720], [$schedule->slots[0]->weekday, $schedule->slots[0]->startminute,
            $schedule->slots[0]->endminute]);
        $this->assertEquals([1, 780, 1440], [$schedule->slots[1]->weekday, $schedule->slots[1]->startminute,
            $schedule->slots[1]->endminute]);
        $this->assertEquals(3, $schedule->slots[2]->weekday);
        $this->assertGreaterThan(0, $schedule->timecreated);
    }

    public function test_save_twice_updates_single_row_and_replaces_slots(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        $first = schedule_repository::get_by_course($course->id);
        schedule_repository::save($course->id, false, 'Europe/Lisbon', [
            ['weekday' => 2, 'startminute' => 10, 'endminute' => 20],
            ['weekday' => 4, 'startminute' => 10, 'endminute' => 20],
        ]);
        $second = schedule_repository::get_by_course($course->id);
        $this->assertSame(1, $DB->count_records('local_sis_courseschedule'));
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals($first->timecreated, $second->timecreated);
        $this->assertEquals(0, $second->enabled);
        $this->assertSame('Europe/Lisbon', $second->timezone);
        $this->assertCount(2, $second->slots);
        $this->assertSame(2, $DB->count_records('local_sis_courseschedule_slt'));
    }

    public function test_duplicate_courseid_rejected_by_database(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, false, 'UTC', []);
        $this->expectException(\dml_exception::class);
        $DB->insert_record('local_sis_courseschedule', (object) [
            'courseid' => $course->id, 'enabled' => 0, 'timezone' => 'UTC', 'timecreated' => 1, 'timemodified' => 1,
        ]);
    }

    public function test_isolation_between_courses(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_course();
        $b = $this->getDataGenerator()->create_course();
        schedule_repository::save($a->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        schedule_repository::save($b->id, true, 'UTC', [['weekday' => 5, 'startminute' => 100, 'endminute' => 200]]);
        $sa = schedule_repository::get_by_course($a->id);
        $sb = schedule_repository::get_by_course($b->id);
        $this->assertCount(1, $sa->slots);
        $this->assertEquals(1, $sa->slots[0]->weekday);
        $this->assertCount(1, $sb->slots);
        $this->assertEquals(5, $sb->slots[0]->weekday);
        schedule_repository::save($a->id, true, 'UTC', [['weekday' => 2, 'startminute' => 0, 'endminute' => 60]]);
        $this->assertEquals(5, schedule_repository::get_by_course($b->id)->slots[0]->weekday);
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        $ok = ['weekday' => 1, 'startminute' => 480, 'endminute' => 1080];
        return [
            'weekday 0' => ['UTC', [['weekday' => 0] + $ok]],
            'weekday 8' => ['UTC', [['weekday' => 8] + $ok]],
            'start negative' => ['UTC', [['startminute' => -1] + $ok]],
            'start equals end' => ['UTC', [['startminute' => 600, 'endminute' => 600] + $ok]],
            'start after end' => ['UTC', [['startminute' => 700, 'endminute' => 600] + $ok]],
            'end above 1440' => ['UTC', [['endminute' => 1441] + $ok]],
            'bad timezone' => ['Mars/Olympus', [$ok]],
            'empty timezone' => ['', [$ok]],
            'overlap' => ['UTC', [$ok, ['startminute' => 1000, 'endminute' => 1200] + $ok]],
            'enabled without slots' => ['UTC', []],
            'missing key' => ['UTC', [['weekday' => 1, 'startminute' => 1]]],
            'non integer' => ['UTC', [['weekday' => 'x'] + $ok]],
        ];
    }

    /**
     * Invalid input is rejected and nothing is stored.
     *
     * @dataProvider invalid_provider
     * @param string $timezone
     * @param array $slots
     */
    public function test_invalid_input_rejected_and_nothing_persisted(string $timezone, array $slots): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        try {
            schedule_repository::save($course->id, true, $timezone, $slots);
            $this->fail('Expected invalid_parameter_exception');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame(0, $DB->count_records('local_sis_courseschedule'));
            $this->assertSame(0, $DB->count_records('local_sis_courseschedule_slt'));
        }
    }

    public function test_adjacent_slots_are_allowed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, true, 'UTC', [
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 720],
            ['weekday' => 1, 'startminute' => 720, 'endminute' => 1080],
        ]);
        $this->assertCount(2, schedule_repository::get_by_course($course->id)->slots);
    }

    public function test_disabled_may_have_no_slots(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, false, 'UTC', []);
        $this->assertEquals(0, schedule_repository::get_by_course($course->id)->enabled);
    }

    public function test_failed_save_keeps_previous_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        try {
            schedule_repository::save($course->id, true, 'UTC', [['weekday' => 9, 'startminute' => 0, 'endminute' => 60]]);
            $this->fail('Expected exception');
        } catch (\invalid_parameter_exception $e) {
            $schedule = schedule_repository::get_by_course($course->id);
            $this->assertCount(1, $schedule->slots);
            $this->assertEquals(1, $schedule->slots[0]->weekday);
        }
    }

    public function test_unknown_or_site_course_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        schedule_repository::save(SITEID, false, 'UTC', []);
    }

    public function test_unknown_course_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        schedule_repository::save(999999, false, 'UTC', []);
    }

    public function test_delete_by_course(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_course();
        $b = $this->getDataGenerator()->create_course();
        schedule_repository::save($a->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        schedule_repository::save($b->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        schedule_repository::delete_by_course($a->id);
        $this->assertNull(schedule_repository::get_by_course($a->id));
        $this->assertNotNull(schedule_repository::get_by_course($b->id));
        $this->assertSame(1, $DB->count_records('local_sis_courseschedule_slt'));
        // Deleting a course without a rule is a no-op.
        schedule_repository::delete_by_course($a->id);
    }

    public function test_course_deletion_removes_rule(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_course();
        $b = $this->getDataGenerator()->create_course();
        schedule_repository::save($a->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        schedule_repository::save($b->id, true, 'UTC', [['weekday' => 1, 'startminute' => 0, 'endminute' => 60]]);
        delete_course($a->id, false);
        $this->assertFalse($DB->record_exists('local_sis_courseschedule', ['courseid' => $a->id]));
        $this->assertSame(1, $DB->count_records('local_sis_courseschedule_slt'));
        $this->assertNotNull(schedule_repository::get_by_course($b->id));
    }
}
