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

use local_sis_courseschedule\local\schedule_formatter;
use local_sis_courseschedule\local\schedule_repository;

/**
 * Tests for the schedule formatter.
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\local\schedule_formatter
 */
final class schedule_formatter_test extends \advanced_testcase {
    public function test_minutes_are_formatted_with_24_00_as_end(): void {
        $this->assertSame('00:00', schedule_formatter::format_minute(0));
        $this->assertSame('08:05', schedule_formatter::format_minute(485));
        $this->assertSame('24:00', schedule_formatter::format_minute(1440));
    }

    public function test_text_lists_days_intervals_and_timezone(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, true, 'America/Sao_Paulo', [
            ['weekday' => 3, 'startminute' => 1140, 'endminute' => 1320],
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 720],
            ['weekday' => 1, 'startminute' => 840, 'endminute' => 1080],
        ]);
        $schedule = schedule_repository::get_by_course($course->id);
        $this->assertSame(
            ['Monday: 08:00 - 12:00, 14:00 - 18:00', 'Wednesday: 19:00 - 22:00'],
            schedule_formatter::format_day_lines($schedule)
        );
        $text = schedule_formatter::format_text($schedule);
        $this->assertStringContainsString('Time zone: America/Sao_Paulo', $text);
        $this->assertStringNotContainsStringIgnoringCase('Brasilia', $text);
        $message = schedule_formatter::closed_message($course->id);
        $this->assertStringStartsWith('It is not possible to access this course at the moment.', $message);
        $this->assertStringContainsString('Wednesday: 19:00 - 22:00', $message);
    }

    public function test_portuguese_strings_cover_the_english_ones(): void {
        // The pt_br pack may not be installed in the test site, so compare the plugin files directly.
        $string = [];
        include(__DIR__ . '/../lang/en/local_sis_courseschedule.php');
        $english = array_keys($string);
        $string = [];
        include(__DIR__ . '/../lang/pt_br/local_sis_courseschedule.php');
        $this->assertSame([], array_diff($english, array_keys($string)));
        $this->assertSame([], array_diff(array_keys($string), $english));
        $this->assertStringStartsWith('Não é possível acessar este curso no momento.', $string['courseaccessclosed']);
    }

    public function test_timezone_is_reduced_to_safe_characters(): void {
        $schedule = (object) ['timezone' => 'UTC<script>alert(1)</script>&', 'slots' => []];
        $line = schedule_formatter::format_timezone($schedule);
        $this->assertStringNotContainsString('<', $line);
        $this->assertStringNotContainsString('&', $line);
    }

    public function test_current_time_uses_injected_clock_in_course_timezone(): void {
        // 2026-10-19 is a Monday; 01:30 UTC is still Sunday 22:30 in Sao Paulo (UTC-3).
        $this->mock_clock_with_frozen(gmmktime(1, 30, 0, 10, 19, 2026));
        $this->assertSame(
            'Current time in the course: Sunday, 22:30 (America/Sao_Paulo)',
            schedule_formatter::format_current_time('America/Sao_Paulo')
        );
        $this->assertSame(
            'Current time in the course: Monday, 01:30 (UTC)',
            schedule_formatter::format_current_time('UTC')
        );
        $text = schedule_formatter::format_current_time('UTC<script>');
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsStringIgnoringCase('Brasilia', $text);
    }

    public function test_current_time_string_exists_in_portuguese(): void {
        $string = [];
        include(__DIR__ . '/../lang/pt_br/local_sis_courseschedule.php');
        $this->assertStringStartsWith('Horário atual no curso:', $string['currenttime']);
    }

    public function test_no_rule_gives_empty_text(): void {
        $this->assertSame('', schedule_formatter::format_text(null));
    }
}
