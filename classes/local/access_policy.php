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
 * Decides whether a user may use a course at a given instant, according to its schedule.
 *
 * The result only says whether the schedule lets the user through. It never grants Moodle access:
 * enrolment, capabilities and the other normal checks remain the responsibility of core. Decisions
 * are not cached; every call reads the current rule.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_policy {
    /** @var string The user holds the bypass capability in the course context. */
    public const REASON_BYPASS = 'bypass';

    /**
     * Whether the rule is active but the user cannot bypass it (for example a manager without bypass).
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_restricted_without_bypass(int $courseid, int $userid): bool {
        $schedule = schedule_repository::get_by_course($courseid);
        if ($schedule === null || empty($schedule->enabled)) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && !has_capability('local/sis_courseschedule:bypass', $context, $userid);
    }

    /**
     * Check whether the schedule allows the user in the course at the given time.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $timestamp Instant to evaluate; defaults to the current server time.
     * @return \stdClass Object with bool allowed and string reason.
     */
    public static function check(int $courseid, int $userid, ?int $timestamp = null): \stdClass {
        $timestamp ??= \core\di::get(\core\clock::class)->time();
        $schedule = schedule_repository::get_by_course($courseid);

        // Nothing to restrict: no need to resolve capabilities.
        if ($schedule === null || empty($schedule->enabled)) {
            return schedule_evaluator::evaluate($schedule, $timestamp);
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context && has_capability('local/sis_courseschedule:bypass', $context, $userid)) {
            return (object) ['allowed' => true, 'reason' => self::REASON_BYPASS];
        }
        return schedule_evaluator::evaluate($schedule, $timestamp);
    }
}
