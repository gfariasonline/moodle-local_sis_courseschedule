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
use local_sis_courseschedule\local\access_policy;
use local_sis_courseschedule\local\schedule_evaluator;
use local_sis_courseschedule\local\schedule_repository;

/**
 * Tests for the access policy.
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\local\access_policy
 */
final class access_policy_test extends \advanced_testcase {
    /** @var string Course timezone used in the tests. */
    private const TZ = 'America/Sao_Paulo';

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
     * Create a course with a Monday 08-18 rule.
     *
     * @param bool $enabled
     * @return \stdClass
     */
    private function course_with_rule(bool $enabled = true): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        schedule_repository::save($course->id, $enabled, self::TZ, [
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 1080],
        ]);
        return $course;
    }

    public function test_course_without_rule_is_allowed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $result = access_policy::check($course->id, $user->id, $this->ts('2026-10-19 03:00:00'));
        $this->assertTrue($result->allowed);
        $this->assertSame(schedule_evaluator::REASON_NO_RULE, $result->reason);
    }

    public function test_disabled_rule_is_allowed(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule(false);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assertTrue(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 03:00:00'))->allowed);
    }

    public function test_student_blocked_outside_and_allowed_inside(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assertFalse(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 07:59:59'))->allowed);
        $this->assertTrue(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 08:00:00'))->allowed);
        $this->assertFalse(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 18:00:00'))->allowed);
    }

    public function test_rule_is_per_course(): void {
        $this->resetAfterTest();
        $blocked = $this->course_with_rule();
        $free = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $blocked->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $free->id, 'student');
        $ts = $this->ts('2026-10-19 03:00:00');
        $this->assertFalse(access_policy::check($blocked->id, $user->id, $ts)->allowed);
        $this->assertTrue(access_policy::check($free->id, $user->id, $ts)->allowed);
    }

    public function test_editing_teacher_and_manager_bypass(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $ts = $this->ts('2026-10-19 03:00:00');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $result = access_policy::check($course->id, $teacher->id, $ts);
        $this->assertTrue($result->allowed);
        $this->assertSame(access_policy::REASON_BYPASS, $result->reason);

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_course::instance($course->id)->id);
        $this->assertTrue(access_policy::check($course->id, $manager->id, $ts)->allowed);
    }

    public function test_non_editing_teacher_has_no_bypass_by_default(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->assertFalse(access_policy::check($course->id, $teacher->id, $this->ts('2026-10-19 03:00:00'))->allowed);
    }

    public function test_manage_capability_alone_is_not_bypass(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sis_courseschedule:manage', CAP_ALLOW, $roleid, $context->id, true);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        $this->assertFalse(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 03:00:00'))->allowed);
    }

    public function test_manager_without_bypass_is_flagged_as_restricted(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $context = \context_course::instance($course->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sis_courseschedule:manage', CAP_ALLOW, $roleid, $context->id, true);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(access_policy::is_restricted_without_bypass($course->id, $user->id));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertFalse(access_policy::is_restricted_without_bypass($course->id, $teacher->id));

        // A disabled rule restricts nobody, and neither does a course without a rule.
        schedule_repository::save($course->id, false, self::TZ, [
            ['weekday' => 1, 'startminute' => 480, 'endminute' => 1080],
        ]);
        $this->assertFalse(access_policy::is_restricted_without_bypass($course->id, $user->id));
        $other = $this->getDataGenerator()->create_course();
        $this->assertFalse(access_policy::is_restricted_without_bypass($other->id, $user->id));
    }

    public function test_bypass_granted_in_course_context_only(): void {
        $this->resetAfterTest();
        $a = $this->course_with_rule();
        $b = $this->course_with_rule();
        $user = $this->getDataGenerator()->create_and_enrol($a, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $b->id, 'student');
        $roleid = $this->getDataGenerator()->create_role();
        $contexta = \context_course::instance($a->id);
        assign_capability('local/sis_courseschedule:bypass', CAP_ALLOW, $roleid, $contexta->id, true);
        $this->getDataGenerator()->role_assign($roleid, $user->id, $contexta->id);
        $ts = $this->ts('2026-10-19 03:00:00');
        $this->assertTrue(access_policy::check($a->id, $user->id, $ts)->allowed);
        $this->assertFalse(access_policy::check($b->id, $user->id, $ts)->allowed);
    }

    public function test_unenrolled_user_is_denied_by_schedule_and_never_granted_moodle_access(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 03:00:00'))->allowed);
        // Inside the schedule the policy allows, but that never creates enrolment or capabilities.
        $this->assertTrue(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 10:00:00'))->allowed);
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user->id));
        $this->assertFalse(has_capability('moodle/course:view', \context_course::instance($course->id), $user->id));
    }

    public function test_multiple_enrolments_do_not_bypass(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'self');
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual');
        $this->assertFalse(access_policy::check($course->id, $user->id, $this->ts('2026-10-19 03:00:00'))->allowed);
    }

    public function test_site_admin_default_bypass(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $admin = get_admin();
        $this->assertTrue(access_policy::check($course->id, $admin->id, $this->ts('2026-10-19 03:00:00'))->allowed);
    }

    public function test_rule_change_takes_effect_immediately(): void {
        $this->resetAfterTest();
        $course = $this->course_with_rule();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $ts = $this->ts('2026-10-19 03:00:00');
        $this->assertFalse(access_policy::check($course->id, $user->id, $ts)->allowed);
        schedule_repository::save($course->id, false, self::TZ, []);
        $this->assertTrue(access_policy::check($course->id, $user->id, $ts)->allowed);
    }

    public function test_timestamp_defaults_to_now_when_omitted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(access_policy::check($course->id, $user->id)->allowed);
    }

    public function test_nonexistent_course_is_allowed_without_rule(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue(access_policy::check(987654, $user->id, 1000)->allowed);
    }
}
