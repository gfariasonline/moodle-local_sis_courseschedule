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

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Pure evaluation of a weekly schedule at a given instant.
 *
 * No database, no capabilities and no clock access: the timestamp is always injected.
 * Opening is inclusive and closing is exclusive, so [08:00, 18:00) allows 08:00:00 and 17:59:59
 * and blocks 18:00:00. The wall clock of the course timezone decides, never the user's timezone.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_evaluator {
    /** @var string No rule exists for the course. */
    public const REASON_NO_RULE = 'norule';
    /** @var string The rule exists but is disabled. */
    public const REASON_DISABLED = 'disabled';
    /** @var string The instant is inside an allowed interval. */
    public const REASON_WITHIN = 'within';
    /** @var string The instant is outside every allowed interval. */
    public const REASON_OUTSIDE = 'outside';
    /** @var string The rule cannot be interpreted (invalid timezone); access is denied. */
    public const REASON_INVALID = 'invalid';

    /**
     * Evaluate a schedule.
     *
     * @param \stdClass|null $schedule Object with enabled, timezone and slots (weekday, startminute, endminute), or null.
     * @param int $timestamp Unix timestamp of the instant to evaluate.
     * @return \stdClass Object with bool allowed and string reason (one of the REASON_* constants).
     */
    public static function evaluate(?\stdClass $schedule, int $timestamp): \stdClass {
        if ($schedule === null) {
            return self::result(true, self::REASON_NO_RULE);
        }
        if (empty($schedule->enabled)) {
            return self::result(true, self::REASON_DISABLED);
        }
        try {
            $local = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone((string) $schedule->timezone));
        } catch (Exception $e) {
            return self::result(false, self::REASON_INVALID);
        }
        $weekday = (int) $local->format('N');
        $seconds = ((int) $local->format('G')) * 3600 + ((int) $local->format('i')) * 60 + (int) $local->format('s');

        foreach ($schedule->slots ?? [] as $slot) {
            if ((int) $slot->weekday !== $weekday) {
                continue;
            }
            if ($seconds >= (int) $slot->startminute * 60 && $seconds < (int) $slot->endminute * 60) {
                return self::result(true, self::REASON_WITHIN);
            }
        }
        return self::result(false, self::REASON_OUTSIDE);
    }

    /**
     * Build a result object.
     *
     * @param bool $allowed
     * @param string $reason
     * @return \stdClass
     */
    private static function result(bool $allowed, string $reason): \stdClass {
        return (object) ['allowed' => $allowed, 'reason' => $reason];
    }
}
