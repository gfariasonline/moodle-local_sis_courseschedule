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

namespace local_sis_courseschedule\local;

/**
 * Persistence of course schedules (one rule per course, with its weekly intervals).
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_repository {
    /** @var string Table with the rule of each course. */
    private const TABLE = 'local_sis_courseschedule';
    /** @var string Table with the allowed intervals. */
    private const SLOTTABLE = 'local_sis_courseschedule_slt';

    /**
     * Get the rule of a course with its intervals.
     *
     * @param int $courseid
     * @return \stdClass|null Rule with a slots array ordered by weekday and start, or null if none exists.
     */
    public static function get_by_course(int $courseid): ?\stdClass {
        global $DB;
        $schedule = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        if (!$schedule) {
            return null;
        }
        $schedule->slots = array_values($DB->get_records(
            self::SLOTTABLE,
            ['scheduleid' => $schedule->id],
            'weekday ASC, startminute ASC, id ASC',
            'id, scheduleid, weekday, startminute, endminute'
        ));
        return $schedule;
    }

    /**
     * Create or replace the rule of a course and all its intervals, atomically.
     *
     * @param int $courseid
     * @param bool $enabled
     * @param string $timezone IANA timezone identifier.
     * @param array $slots List of arrays with weekday (1-7), startminute and endminute (0 <= start < end <= 1440).
     * @throws \invalid_parameter_exception When the input is invalid; nothing is stored.
     */
    public static function save(int $courseid, bool $enabled, string $timezone, array $slots): void {
        global $DB;
        $slots = self::validate($courseid, $enabled, $timezone, $slots);

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $schedule = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        if ($schedule) {
            $schedule->enabled = (int) $enabled;
            $schedule->timezone = $timezone;
            $schedule->timemodified = $now;
            $DB->update_record(self::TABLE, $schedule);
            $DB->delete_records(self::SLOTTABLE, ['scheduleid' => $schedule->id]);
            $scheduleid = $schedule->id;
        } else {
            $scheduleid = $DB->insert_record(self::TABLE, (object) [
                'courseid' => $courseid,
                'enabled' => (int) $enabled,
                'timezone' => $timezone,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        foreach ($slots as $slot) {
            $DB->insert_record(self::SLOTTABLE, (object) (['scheduleid' => $scheduleid] + $slot));
        }
        $transaction->allow_commit();
    }

    /**
     * Delete the rule of a course and its intervals. Does nothing if there is none.
     *
     * @param int $courseid
     */
    public static function delete_by_course(int $courseid): void {
        global $DB;
        $schedule = $DB->get_record(self::TABLE, ['courseid' => $courseid], 'id');
        if (!$schedule) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::SLOTTABLE, ['scheduleid' => $schedule->id]);
        $DB->delete_records(self::TABLE, ['id' => $schedule->id]);
        $transaction->allow_commit();
    }

    /**
     * Validate and normalise the input.
     *
     * @param int $courseid
     * @param bool $enabled
     * @param string $timezone
     * @param array $slots
     * @return array Normalised list of slots (integers only).
     * @throws \invalid_parameter_exception
     */
    private static function validate(int $courseid, bool $enabled, string $timezone, array $slots): array {
        global $DB;
        if ($courseid <= SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
            throw new \invalid_parameter_exception('Invalid course id');
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \invalid_parameter_exception('Invalid timezone');
        }
        if ($enabled && !$slots) {
            throw new \invalid_parameter_exception('An active schedule needs at least one interval');
        }

        $clean = [];
        foreach ($slots as $slot) {
            $slot = (array) $slot;
            foreach (['weekday', 'startminute', 'endminute'] as $key) {
                if (!isset($slot[$key]) || filter_var($slot[$key], FILTER_VALIDATE_INT) === false) {
                    throw new \invalid_parameter_exception("Invalid interval field $key");
                }
            }
            $weekday = (int) $slot['weekday'];
            $start = (int) $slot['startminute'];
            $end = (int) $slot['endminute'];
            if ($weekday < 1 || $weekday > 7 || $start < 0 || $start >= $end || $end > 1440) {
                throw new \invalid_parameter_exception('Interval out of range');
            }
            $clean[] = ['weekday' => $weekday, 'startminute' => $start, 'endminute' => $end];
        }

        // Reject overlaps within the same weekday; adjacent intervals (end == next start) are fine.
        usort($clean, fn($a, $b) => [$a['weekday'], $a['startminute']] <=> [$b['weekday'], $b['startminute']]);
        for ($i = 1; $i < count($clean); $i++) {
            if ($clean[$i]['weekday'] === $clean[$i - 1]['weekday'] && $clean[$i]['startminute'] < $clean[$i - 1]['endminute']) {
                throw new \invalid_parameter_exception('Overlapping intervals');
            }
        }
        return $clean;
    }
}
