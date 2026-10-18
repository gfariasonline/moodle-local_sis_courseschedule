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
 * Turns a schedule into plain text (days, intervals and timezone) for notices and error messages.
 *
 * The text is generated from the same rule used to authorise access. It is plain text: HTML output
 * must still pass it through s() or equivalent. As a defence in depth, the timezone is reduced to the
 * characters allowed in IANA identifiers.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_formatter {
    /** @var string[] Calendar language string identifiers of ISO weekdays 1 (Monday) to 7 (Sunday). */
    private const DAYS = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday',
        6 => 'saturday', 7 => 'sunday'];

    /**
     * Format a minute of the day as HH:MM (1440 becomes 24:00).
     *
     * @param int $minute
     * @return string
     */
    public static function format_minute(int $minute): string {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    /**
     * One line per weekday that has intervals, in ISO order.
     *
     * @param \stdClass $schedule Rule with a slots array.
     * @return string[] Lines such as "Monday: 08:00 - 18:00, 19:00 - 22:00".
     */
    public static function format_day_lines(\stdClass $schedule): array {
        $byday = [];
        foreach ($schedule->slots ?? [] as $slot) {
            $byday[(int) $slot->weekday][] = [(int) $slot->startminute, (int) $slot->endminute];
        }
        $lines = [];
        foreach (self::DAYS as $number => $identifier) {
            if (empty($byday[$number])) {
                continue;
            }
            usort($byday[$number], fn($a, $b) => $a <=> $b);
            $intervals = array_map(
                fn($pair) => self::format_minute($pair[0]) . ' - ' . self::format_minute($pair[1]),
                $byday[$number]
            );
            $lines[] = get_string('scheduleline', 'local_sis_courseschedule', (object) [
                'day' => get_string($identifier, 'calendar'),
                'intervals' => implode(', ', $intervals),
            ]);
        }
        return $lines;
    }

    /**
     * The timezone line, for example "Time zone: America/Sao_Paulo".
     *
     * @param \stdClass $schedule
     * @return string
     */
    public static function format_timezone(\stdClass $schedule): string {
        $timezone = preg_replace('/[^A-Za-z0-9_\/+\-]/', '', (string) $schedule->timezone);
        return get_string('scheduletimezone', 'local_sis_courseschedule', $timezone);
    }

    /**
     * The current time in the course timezone, for example "Current time in the course: Monday, 09:30 (America/Sao_Paulo)".
     *
     * Uses the injectable clock, so the value matches what the access policy evaluates.
     *
     * @param string $timezone IANA identifier of the course; an invalid one falls back to the server timezone.
     * @param int|null $timestamp Instant to show; defaults to the clock time.
     * @return string Plain text; HTML output must still escape it.
     */
    public static function format_current_time(string $timezone, ?int $timestamp = null): string {
        $timestamp ??= \core\di::get(\core\clock::class)->time();
        $timezone = preg_replace('/[^A-Za-z0-9_\/+\-]/', '', $timezone);
        try {
            $zone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $timezone = \core_date::get_server_timezone();
            $zone = new \DateTimeZone($timezone);
        }
        $moment = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($zone);
        return get_string('currenttime', 'local_sis_courseschedule', (object) [
            'day' => get_string(self::DAYS[(int) $moment->format('N')], 'calendar'),
            'time' => $moment->format('H:i'),
            'timezone' => $timezone,
        ]);
    }

    /**
     * Single-line plain text with the days, intervals and timezone.
     *
     * @param \stdClass|null $schedule
     * @return string Empty string when there is no rule.
     */
    public static function format_text(?\stdClass $schedule): string {
        if ($schedule === null) {
            return '';
        }
        $days = self::format_day_lines($schedule);
        $text = $days ? implode('; ', $days) . '. ' : '';
        return $text . get_string('scheduleotherdays', 'local_sis_courseschedule') . ' ' .
            self::format_timezone($schedule) . '.';
    }

    /**
     * Message used when access to a course is denied: the fixed notice followed by the schedule.
     *
     * @param int $courseid
     * @return string
     */
    public static function closed_message(int $courseid): string {
        return get_string(
            'courseaccessclosed',
            'local_sis_courseschedule',
            self::format_text(schedule_repository::get_by_course($courseid))
        );
    }
}
