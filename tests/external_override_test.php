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

use core_external\external_api;
use DateTimeImmutable;
use DateTimeZone;
use local_sis_courseschedule\local\access_guard;
use local_sis_courseschedule\local\external_override;
use local_sis_courseschedule\local\schedule_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/calendar/externallib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Tests for the override_webservice_execution adapter, through external_api::call_external_function().
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\local\external_override
 */
final class external_override_test extends \advanced_testcase {
    /** @var string Course timezone. */
    private const TZ = 'America/Sao_Paulo';

    /** @var \frozen_clock */
    private \frozen_clock $clock;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->clock = $this->mock_clock_with_frozen($this->ts('2026-10-19 03:00:00'));
    }

    protected function tearDown(): void {
        access_guard::set_test_environment(null);
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * Timestamp of a Sao Paulo wall-clock time.
     *
     * @param string $datetime
     * @return int
     */
    private function ts(string $datetime): int {
        return (new DateTimeImmutable($datetime, new DateTimeZone(self::TZ)))->getTimestamp();
    }

    /**
     * Course A closed (Monday 08-18 rule), course B free, student enrolled in both, an assignment due in each.
     *
     * @return array [courseA, courseB, student]
     */
    private function scenario(): array {
        $generator = $this->getDataGenerator();
        $a = $generator->create_course();
        $b = $generator->create_course();
        schedule_repository::save($a->id, true, self::TZ, [['weekday' => 1, 'startminute' => 480, 'endminute' => 1080]]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $a->id, 'student');
        $generator->enrol_user($student->id, $b->id, 'student');
        $assign = $generator->get_plugin_generator('mod_assign');
        $assign->create_instance(['course' => $a->id, 'duedate' => time() + DAYSECS]);
        $assign->create_instance(['course' => $b->id, 'duedate' => time() + 2 * DAYSECS]);
        return [$a, $b, $student];
    }

    /**
     * Call an external function the way AJAX does.
     *
     * @param string $function
     * @param array $args List holding one associative array with the named parameters.
     * @return array The wrapper response.
     */
    private function call(string $function, array $args): array {
        // Outside WS_SERVER the wrapper insists on a session key of the current user.
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function($function, $args[0] ?? [], false);
    }

    /**
     * Course ids present in the exported events of a response.
     *
     * @param array $events
     * @return int[]
     */
    private function event_courseids(array $events): array {
        $ids = array_map(fn($event) => (int) ($event['course']['id'] ?? 0), $events);
        sort($ids);
        return $ids;
    }

    public function test_course_contents_of_closed_course_returns_courseaccessclosed(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);

        $response = $this->call('core_course_get_contents', [['courseid' => $a->id, 'options' => []]]);
        $this->assertTrue($response['error']);
        $this->assertSame('courseaccessclosed', $response['exception']->errorcode);
        $this->assertStringContainsString('Monday: 08:00 - 18:00', $response['exception']->message);

        $ok = $this->call('core_course_get_contents', [['courseid' => $b->id, 'options' => []]]);
        $this->assertFalse($ok['error']);
    }

    public function test_course_contents_without_the_adapter_would_be_rewrapped_by_core(): void {
        // Documents why the adapter exists: without it core answers errorcoursecontextnotvalid.
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        try {
            \core_course_external::get_course_contents($a->id);
            $this->fail('An exception was expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorcoursecontextnotvalid', $e->errorcode);
        }
    }

    public function test_course_contents_does_not_reveal_schedule_to_user_without_access(): void {
        [$a] = $this->scenario();
        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->call('core_course_get_contents', [['courseid' => $a->id, 'options' => []]]);
        $this->assertTrue($response['error']);
        $this->assertNotSame('courseaccessclosed', $response['exception']->errorcode);
        $this->assertStringNotContainsString('08:00', $response['exception']->message);
    }

    public function test_course_contents_reopening_with_same_session(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        $args = [['courseid' => $a->id, 'options' => []]];
        $this->assertTrue($this->call('core_course_get_contents', $args)['error']);
        $this->clock->set_to($this->ts('2026-10-19 09:00:00'));
        $this->assertFalse($this->call('core_course_get_contents', $args)['error']);
        $this->clock->set_to($this->ts('2026-10-19 19:00:00'));
        $this->assertSame('courseaccessclosed', $this->call('core_course_get_contents', $args)['exception']->errorcode);
    }

    public function test_course_contents_bypass_and_cli(): void {
        [$a, , $student] = $this->scenario();
        $teacher = $this->getDataGenerator()->create_and_enrol($a, 'editingteacher');
        $this->setUser($teacher);
        $this->assertFalse($this->call('core_course_get_contents', [['courseid' => $a->id, 'options' => []]])['error']);

        $this->setUser($student);
        access_guard::set_test_environment(['cli' => true]);
        $this->assertFalse($this->call('core_course_get_contents', [['courseid' => $a->id, 'options' => []]])['error']);
    }

    public function test_action_events_by_courses_hides_only_the_closed_course(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $response = $this->call('core_calendar_get_action_events_by_courses', [
            ['courseids' => [$a->id, $b->id], 'timesortfrom' => 1],
        ]);
        $this->assertFalse($response['error']);
        $groups = $response['data']['groupedbycourse'];
        $this->assertSame([(int) $b->id], array_map(fn($g) => (int) $g['courseid'], $groups));
        $this->assertCount(1, $groups[0]['events']);

        // When the course reopens its events come back.
        $this->clock->set_to($this->ts('2026-10-19 09:00:00'));
        $response = $this->call('core_calendar_get_action_events_by_courses', [
            ['courseids' => [$a->id, $b->id], 'timesortfrom' => 1],
        ]);
        $this->assertCount(2, $response['data']['groupedbycourse']);
    }

    public function test_action_events_by_timesort_filters_events_and_keeps_pagination_fields(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $all = $this->call('core_calendar_get_action_events_by_timesort', [['timesortfrom' => 1, 'limitnum' => 20]]);
        $this->assertFalse($all['error']);
        $this->assertSame([(int) $b->id], $this->event_courseids($all['data']['events']));
        $this->assertSame($all['data']['events'][0]['id'], $all['data']['firstid']);
        $this->assertSame($all['data']['events'][0]['id'], $all['data']['lastid']);

        // With the course open both events are returned: the filter is what removed course A.
        $this->clock->set_to($this->ts('2026-10-19 09:00:00'));
        $open = $this->call('core_calendar_get_action_events_by_timesort', [['timesortfrom' => 1, 'limitnum' => 20]]);
        $this->assertSame(
            [(int) $a->id, (int) $b->id],
            $this->event_courseids($open['data']['events'])
        );
    }

    public function test_action_events_by_course_of_closed_course_returns_no_events(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        $response = $this->call('core_calendar_get_action_events_by_course', [['courseid' => $a->id, 'timesortfrom' => 1]]);
        $this->assertFalse($response['error']);
        $this->assertSame([], $response['data']['events']);
    }

    public function test_bypass_user_sees_all_events(): void {
        [$a, $b, $student] = $this->scenario();
        $context = \context_course::instance($a->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sis_courseschedule:bypass', CAP_ALLOW, $roleid, $context->id, true);
        $this->getDataGenerator()->role_assign($roleid, $student->id, $context->id);
        $this->setUser($student);
        $response = $this->call('core_calendar_get_action_events_by_timesort', [['timesortfrom' => 1, 'limitnum' => 20]]);
        $this->assertFalse($response['error']);
        $this->assertSame(
            [(int) $a->id, (int) $b->id],
            $this->event_courseids($response['data']['events'])
        );
    }

    public function test_upcoming_view_filters_closed_course_events(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $response = $this->call('core_calendar_get_calendar_upcoming_view', [['courseid' => SITEID]]);
        $this->assertFalse($response['error']);
        $ids = $this->event_courseids($response['data']['events']);
        $this->assertNotContains((int) $a->id, $ids);
        $this->assertContains((int) $b->id, $ids);
    }

    public function test_calendar_event_by_id_of_closed_course_is_rejected(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $this->clock->set_to($this->ts('2026-10-19 09:00:00'));
        $all = $this->call('core_calendar_get_action_events_by_timesort', [['timesortfrom' => 1, 'limitnum' => 20]]);
        $ids = [];
        foreach ($all['data']['events'] as $event) {
            $ids[(int) $event['course']['id']] = (int) $event['id'];
        }
        $this->clock->set_to($this->ts('2026-10-19 03:00:00'));
        $closed = $this->call('core_calendar_get_calendar_event_by_id', [['eventid' => $ids[(int) $a->id]]]);
        $this->assertTrue($closed['error']);
        $this->assertSame('courseaccessclosed', $closed['exception']->errorcode);
        $free = $this->call('core_calendar_get_calendar_event_by_id', [['eventid' => $ids[(int) $b->id]]]);
        $this->assertFalse($free['error']);
    }

    public function test_events_keyed_by_event_id_are_filtered(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $this->clock->set_to($this->ts('2026-10-19 03:00:00'));
        // Shape returned by core_calendar_get_calendar_events: events keyed by their own id.
        $result = ['events' => [
            101 => ['id' => 101, 'eventtype' => 'course', 'courseid' => (int) $a->id],
            102 => ['id' => 102, 'eventtype' => 'course', 'courseid' => (int) $b->id],
        ], 'warnings' => []];
        $filtered = external_override::filter_closed_events($result, (int) $student->id);
        $this->assertSame([102], array_keys($filtered['events']));
    }

    public function test_other_functions_run_natively(): void {
        [, , $student] = $this->scenario();
        $this->setUser($student);
        $info = (object) ['name' => 'core_webservice_get_site_info', 'classname' => 'x', 'methodname' => 'y'];
        $this->assertFalse(external_override::execute($info, []));
        $this->assertFalse(external_override::execute((object) [], []));
    }

    public function test_filter_handles_objects_and_groups(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $event = fn(int $courseid, int $id) => (object) ['id' => $id, 'eventtype' => 'course',
            'course' => (object) ['id' => $courseid]];
        $tree = (object) [
            'groupedbycourse' => [
                (object) ['courseid' => $a->id, 'events' => [$event($a->id, 1)], 'firstid' => 1, 'lastid' => 1],
                (object) ['courseid' => $b->id, 'events' => [$event($b->id, 2)], 'firstid' => 2, 'lastid' => 2],
            ],
            'weeks' => [['days' => [['hasevents' => true, 'events' => [$event($a->id, 3)]]]]],
            'site' => [$event(SITEID, 4)],
        ];
        $filtered = external_override::filter_closed_events($tree, (int) $student->id);
        $this->assertCount(1, $filtered->groupedbycourse);
        $this->assertEquals($b->id, $filtered->groupedbycourse[0]->courseid);
        $this->assertSame([], $filtered->weeks[0]['days'][0]['events']);
        $this->assertFalse($filtered->weeks[0]['days'][0]['hasevents']);
        $this->assertCount(1, $filtered->site);
    }

    public function test_override_callback_is_registered_and_used_by_the_wrapper(): void {
        $callbacks = get_plugins_with_function('override_webservice_execution', 'lib.php', false);
        $this->assertArrayHasKey('sis_courseschedule', $callbacks['local'] ?? []);
    }
}
