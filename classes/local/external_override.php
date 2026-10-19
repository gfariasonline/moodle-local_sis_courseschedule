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
 * Adapter for the override_webservice_execution callback (REST, AJAX and external_api wrapper).
 *
 * Covered functions:
 * - core_course_get_contents: core catches any exception from the course context validation and turns
 *   it into errorcoursecontextnotvalid, hiding our code. When the course is closed the adapter first
 *   lets core validate the context (so users without normal access still get core's own error) and
 *   then throws courseaccessclosed outside that catch.
 * - core_calendar_get_calendar_events, core_calendar_get_calendar_monthly_view,
 *   core_calendar_get_calendar_day_view, core_calendar_get_calendar_upcoming_view,
 *   core_calendar_get_action_events_by_timesort, core_calendar_get_action_events_by_course,
 *   core_calendar_get_action_events_by_courses: these validate only the user context and list events
 *   of every enrolled course. The native function runs and events of courses closed for the user are
 *   removed from the result, keeping the other courses, the structure and the ordering. Limits:
 *   page sizes are applied by core before filtering (a page may come back shorter) and counters
 *   other than firstid, lastid and hasevents are not recomputed.
 * - core_calendar_get_calendar_event_by_id: throws courseaccessclosed when the event belongs to a
 *   closed course.
 *
 * Everything else returns false, which makes core run the function normally.
 *
 * Not covered (documented exclusions): calendar iCal export, question toggleflag, functions of
 * third-party plugins that do not validate a course context.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_override {
    /** @var string Function whose course validation error is rewrapped by core. */
    private const CONTENTS = 'core_course_get_contents';

    /** @var string[] Calendar functions whose event lists are filtered. */
    private const CALENDAR_LISTS = [
        'core_calendar_get_calendar_events',
        'core_calendar_get_calendar_monthly_view',
        'core_calendar_get_calendar_day_view',
        'core_calendar_get_calendar_upcoming_view',
        'core_calendar_get_action_events_by_timesort',
        'core_calendar_get_action_events_by_course',
        'core_calendar_get_action_events_by_courses',
    ];

    /** @var string Calendar function returning a single event. */
    private const CALENDAR_SINGLE = 'core_calendar_get_calendar_event_by_id';

    /**
     * Callback body for override_webservice_execution.
     *
     * @param \stdClass $info External function info (name, classname, methodname, ...).
     * @param array $params Validated parameters, in declaration order.
     * @return mixed False to run the native function; anything else replaces its result.
     */
    public static function execute($info, array $params) {
        global $USER;
        $name = $info->name ?? '';
        if ($name === '' || access_guard::is_exempt_environment() || empty($USER->id)) {
            return false;
        }
        if ($name === self::CONTENTS) {
            return self::guard_course_contents((int) ($params[0] ?? 0));
        }
        if (in_array($name, self::CALENDAR_LISTS, true)) {
            $result = call_user_func_array([$info->classname, $info->methodname], $params);
            return self::filter_closed_events($result, (int) $USER->id);
        }
        if ($name === self::CALENDAR_SINGLE) {
            $result = call_user_func_array([$info->classname, $info->methodname], $params);
            $event = (array) (((array) $result)['event'] ?? []);
            $courseid = self::event_courseid($event);
            if ($courseid && access_guard::is_closed_for_user($courseid, (int) $USER->id)) {
                throw access_guard::closed_exception($courseid);
            }
            return $result;
        }
        return false;
    }

    /**
     * Reject core_course_get_contents for a closed course with courseaccessclosed.
     *
     * @param int $courseid
     * @return bool Always false (native execution) when nothing is rejected.
     * @throws \moodle_exception
     */
    private static function guard_course_contents(int $courseid): bool {
        global $USER;
        if (!access_guard::is_closed_for_user($courseid, (int) $USER->id)) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        try {
            // Core's own authorisation first: users without normal access must not learn the schedule.
            \core_external\external_api::validate_context($context);
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'courseaccessclosed') {
                throw $e;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
        throw access_guard::closed_exception($courseid);
    }

    /**
     * Remove events (and per-course groups) of closed courses from a calendar result.
     *
     * @param mixed $result Array or object tree returned by the native function.
     * @param int $userid
     * @return mixed The filtered tree.
     */
    public static function filter_closed_events($result, int $userid) {
        $cache = [];
        $isclosed = function (int $courseid) use (&$cache, $userid): bool {
            if ($courseid <= SITEID) {
                return false;
            }
            return $cache[$courseid] ??= access_guard::is_closed_for_user($courseid, $userid);
        };
        return self::walk($result, $isclosed);
    }

    /**
     * Course id of an exported event (array or object), or 0 when it is not course related.
     *
     * @param array|object $event
     * @return int
     */
    private static function event_courseid($event): int {
        $event = (array) $event;
        if (isset($event['course'])) {
            return (int) (((array) $event['course'])['id'] ?? 0);
        }
        return (int) ($event['courseid'] ?? 0);
    }

    /**
     * Whether a list item is an event, or a group of events, belonging to a closed course.
     *
     * @param mixed $item
     * @param \Closure $isclosed
     * @return bool
     */
    private static function item_is_closed($item, \Closure $isclosed): bool {
        if (!is_array($item) && !is_object($item)) {
            return false;
        }
        $properties = (array) $item;
        $isevent = array_key_exists('eventtype', $properties);
        $isgroup = array_key_exists('events', $properties) && array_key_exists('courseid', $properties);
        return ($isevent || $isgroup) && $isclosed(self::event_courseid($properties));
    }

    /**
     * Recursive filter over arrays and objects.
     *
     * @param mixed $node
     * @param \Closure $isclosed
     * @return mixed
     */
    private static function walk($node, \Closure $isclosed) {
        if (is_array($node)) {
            if (array_is_list($node)) {
                $kept = [];
                foreach ($node as $item) {
                    if (!self::item_is_closed($item, $isclosed)) {
                        $kept[] = self::walk($item, $isclosed);
                    }
                }
                return $kept;
            }
            foreach ($node as $key => $value) {
                // Lists keyed by event id (core_calendar_get_calendar_events) are filtered item by item.
                if (self::item_is_closed($value, $isclosed)) {
                    unset($node[$key]);
                    continue;
                }
                $node[$key] = self::walk($value, $isclosed);
            }
            self::fix_event_counters($node);
            return $node;
        }
        if (is_object($node)) {
            foreach (get_object_vars($node) as $key => $value) {
                $node->$key = self::walk($value, $isclosed);
            }
            self::fix_event_counters($node);
        }
        return $node;
    }

    /**
     * Keep hasevents, firstid and lastid consistent with the remaining events.
     *
     * @param array|object $node Container with an events list; modified in place.
     */
    private static function fix_event_counters(&$node): void {
        $properties = (array) $node;
        if (!isset($properties['events']) || !is_array($properties['events'])) {
            return;
        }
        $events = $properties['events'];
        $set = function (string $key, $value) use (&$node): void {
            if (is_array($node)) {
                $node[$key] = $value;
            } else {
                $node->$key = $value;
            }
        };
        if (array_key_exists('hasevents', $properties)) {
            $set('hasevents', !empty($events));
        }
        foreach (['firstid' => 0, 'lastid' => count($events) - 1] as $key => $index) {
            if (!array_key_exists($key, $properties)) {
                continue;
            }
            // Core exports null for both ids when a page has no events.
            $set($key, $events ? ((array) $events[$index])['id'] : null);
        }
    }
}
