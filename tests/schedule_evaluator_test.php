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

use DateTimeImmutable;
use DateTimeZone;
use local_sis_courseschedule\local\schedule_evaluator;

/**
 * Tests for the pure schedule evaluator.
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\local\schedule_evaluator
 */
final class schedule_evaluator_test extends \basic_testcase {
    /**
     * Build a schedule object.
     *
     * @param array $slots list of [weekday, startminute, endminute]
     * @param bool $enabled
     * @param string $timezone
     * @return \stdClass
     */
    private function schedule(array $slots, bool $enabled = true, string $timezone = 'America/Sao_Paulo'): \stdClass {
        $schedule = new \stdClass();
        $schedule->enabled = $enabled ? 1 : 0;
        $schedule->timezone = $timezone;
        $schedule->slots = [];
        foreach ($slots as [$weekday, $start, $end]) {
            $schedule->slots[] = (object) ['weekday' => $weekday, 'startminute' => $start, 'endminute' => $end];
        }
        return $schedule;
    }

    /**
     * Timestamp of a wall-clock time in a timezone.
     *
     * @param string $datetime e.g. 2026-10-19 08:00:00
     * @param string $timezone IANA identifier
     * @return int
     */
    private function ts(string $datetime, string $timezone): int {
        return (new DateTimeImmutable($datetime, new DateTimeZone($timezone)))->getTimestamp();
    }

    public function test_no_rule_is_allowed(): void {
        $result = schedule_evaluator::evaluate(null, 1000);
        $this->assertTrue($result->allowed);
        $this->assertSame(schedule_evaluator::REASON_NO_RULE, $result->reason);
    }

    public function test_disabled_rule_is_allowed(): void {
        $schedule = $this->schedule([[1, 480, 1080]], false);
        $result = schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 03:00:00', 'America/Sao_Paulo'));
        $this->assertTrue($result->allowed);
        $this->assertSame(schedule_evaluator::REASON_DISABLED, $result->reason);
    }

    /**
     * Boundaries for Monday 08:00-18:00.
     *
     * @return array
     */
    public static function boundary_provider(): array {
        return [
            '07:59:59 blocked' => ['2026-10-19 07:59:59', false],
            '08:00:00 allowed' => ['2026-10-19 08:00:00', true],
            '12:00:00 allowed' => ['2026-10-19 12:00:00', true],
            '17:59:59 allowed' => ['2026-10-19 17:59:59', true],
            '18:00:00 blocked' => ['2026-10-19 18:00:00', false],
            'tuesday blocked (no slot)' => ['2026-10-20 12:00:00', false],
        ];
    }

    /**
     * Opening is inclusive and closing is exclusive.
     *
     * @dataProvider boundary_provider
     * @param string $datetime
     * @param bool $expected
     */
    public function test_open_inclusive_close_exclusive(string $datetime, bool $expected): void {
        $schedule = $this->schedule([[1, 480, 1080]]);
        $result = schedule_evaluator::evaluate($schedule, $this->ts($datetime, 'America/Sao_Paulo'));
        $this->assertSame($expected, $result->allowed);
        $this->assertSame(
            $expected ? schedule_evaluator::REASON_WITHIN : schedule_evaluator::REASON_OUTSIDE,
            $result->reason
        );
    }

    public function test_two_slots_in_same_day(): void {
        $schedule = $this->schedule([[1, 480, 720], [1, 840, 1080]]);
        $tz = 'America/Sao_Paulo';
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 09:00:00', $tz))->allowed);
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 12:00:00', $tz))->allowed);
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 13:59:59', $tz))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 14:00:00', $tz))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 17:59:59', $tz))->allowed);
    }

    public function test_end_of_day_1440_and_day_change(): void {
        // Monday 22:00-24:00 and Tuesday 00:00-01:00 (period crossing midnight stored as two days).
        $schedule = $this->schedule([[1, 1320, 1440], [2, 0, 60]]);
        $tz = 'America/Sao_Paulo';
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 21:59:59', $tz))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 23:59:59', $tz))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-20 00:00:00', $tz))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-20 00:59:59', $tz))->allowed);
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-20 01:00:00', $tz))->allowed);
    }

    public function test_sunday_to_monday_week_change(): void {
        // Only Monday 00:00-01:00 (ISO weekday 1); Sunday (7) has no slot.
        $schedule = $this->schedule([[1, 0, 60]]);
        $tz = 'America/Sao_Paulo';
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-18 23:59:59', $tz))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 00:00:00', $tz))->allowed);
        // Sunday is ISO weekday 7.
        $sunday = $this->schedule([[7, 0, 1440]]);
        $this->assertTrue(schedule_evaluator::evaluate($sunday, $this->ts('2026-10-18 12:00:00', $tz))->allowed);
    }

    public function test_course_timezone_decides_not_utc(): void {
        $schedule = $this->schedule([[1, 480, 1080]], true, 'America/Sao_Paulo');
        // 20:30 UTC is 17:30 in Sao Paulo (UTC-3, no DST in October 2026): allowed.
        $ts = $this->ts('2026-10-19 20:30:00', 'UTC');
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $ts)->allowed);
        // Same rule in UTC would be blocked at that instant.
        $utc = $this->schedule([[1, 480, 1080]], true, 'UTC');
        $this->assertFalse(schedule_evaluator::evaluate($utc, $ts)->allowed);
    }

    public function test_user_timezone_does_not_change_decision(): void {
        $schedule = $this->schedule([[1, 480, 1080]], true, 'America/Sao_Paulo');
        $ts = $this->ts('2026-10-19 07:59:59', 'America/Sao_Paulo');
        $before = schedule_evaluator::evaluate($schedule, $ts);
        $olddefault = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
        try {
            $after = schedule_evaluator::evaluate($schedule, $ts);
        } finally {
            date_default_timezone_set($olddefault);
        }
        $this->assertFalse($before->allowed);
        $this->assertEquals($before, $after);
    }

    public function test_dst_spring_forward_new_york(): void {
        $tz = 'America/New_York';
        // Weekday 08:00-18:00 slot on Mondays; DST began Sunday 2026-03-08.
        $schedule = $this->schedule([[1, 480, 1080]], true, $tz);
        // Monday 2026-03-02 (EST, UTC-5): 12:00 UTC is 07:00 local.
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-03-02 12:00:00', 'UTC'))->allowed);
        // Monday 2026-03-09 (EDT, UTC-4): 12:00 UTC is 08:00 local.
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-03-09 12:00:00', 'UTC'))->allowed);
        // Day of the change: Sunday 02:30-03:30 wall clock. 01:59:59 EST is before; 03:00 EDT is inside.
        $sunday = $this->schedule([[7, 150, 210]], true, $tz);
        $this->assertFalse(schedule_evaluator::evaluate($sunday, $this->ts('2026-03-08 06:59:59', 'UTC'))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($sunday, $this->ts('2026-03-08 07:00:00', 'UTC'))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($sunday, $this->ts('2026-03-08 07:29:59', 'UTC'))->allowed);
        $this->assertFalse(schedule_evaluator::evaluate($sunday, $this->ts('2026-03-08 07:30:00', 'UTC'))->allowed);
    }

    public function test_dst_fall_back_new_york_repeated_hour(): void {
        $tz = 'America/New_York';
        // 2026-11-01: 01:00-02:00 occurs twice. Slot 01:00-02:00 wall clock is allowed in both occurrences.
        $schedule = $this->schedule([[7, 60, 120]], true, $tz);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-11-01 05:30:00', 'UTC'))->allowed);
        $this->assertTrue(schedule_evaluator::evaluate($schedule, $this->ts('2026-11-01 06:30:00', 'UTC'))->allowed);
        $this->assertFalse(schedule_evaluator::evaluate($schedule, $this->ts('2026-11-01 07:00:00', 'UTC'))->allowed);
    }

    public function test_enabled_without_slots_is_blocked(): void {
        // Defensive: the repository rejects this state, but the evaluator must fail closed.
        $schedule = $this->schedule([]);
        $result = schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 10:00:00', 'America/Sao_Paulo'));
        $this->assertFalse($result->allowed);
    }

    public function test_invalid_timezone_fails_closed(): void {
        $schedule = $this->schedule([[1, 0, 1440]], true, 'Not/AZone');
        $result = schedule_evaluator::evaluate($schedule, $this->ts('2026-10-19 10:00:00', 'UTC'));
        $this->assertFalse($result->allowed);
    }
}
