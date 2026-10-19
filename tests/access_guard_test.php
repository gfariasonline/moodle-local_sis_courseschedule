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
use local_sis_courseschedule\local\access_guard;
use local_sis_courseschedule\local\schedule_repository;

/**
 * Tests for the access guard and the after_require_login wiring.
 *
 * Time is frozen with the Moodle test clock. Monday 19 Oct 2026 03:00 in Sao Paulo is outside the
 * Monday 08:00-18:00 rule and 10:00 is inside it.
 *
 * @package    local_sis_courseschedule
 * @category   test
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sis_courseschedule\local\access_guard
 */
final class access_guard_test extends \advanced_testcase {
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
     * Course A closed by a Monday 08-18 rule, course B without rule; a student enrolled in both.
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
        return [$a, $b, $student];
    }

    /**
     * Assert that a callable throws courseaccessclosed and return the exception.
     *
     * @param callable $call
     * @return \moodle_exception
     */
    private function assert_closed(callable $call): \moodle_exception {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->assertSame('courseaccessclosed', $e->errorcode);
            return $e;
        }
        $this->fail('courseaccessclosed was not thrown');
    }

    public function test_closed_course_throws_with_schedule_text_and_other_course_is_free(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $e = $this->assert_closed(fn() => access_guard::after_require_login($a, true));
        $this->assertStringContainsString('It is not possible to access this course at the moment.', $e->getMessage());
        $this->assertSame(1, substr_count($e->getMessage(), 'It is not possible to access this course'));
        $this->assertStringContainsString('Monday: 08:00 - 18:00', $e->getMessage());
        $this->assertStringContainsString(self::TZ, $e->getMessage());
        // Course B keeps working for the same user.
        access_guard::after_require_login($b, true);
        $this->assertTrue(true);
    }

    public function test_real_require_login_runs_the_callback_and_keeps_the_other_course(): void {
        global $COURSE;
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $this->assert_closed(fn() => require_login($a, false, null, false, true));
        require_login($b, false, null, false, true);
        $this->assertSame((int) $b->id, (int) $COURSE->id);
    }

    public function test_activity_login_is_blocked_too(): void {
        [$a, , $student] = $this->scenario();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $a->id]);
        $this->setUser($student);
        $cm = get_fast_modinfo($a)->get_cm($page->cmid);
        $this->assert_closed(fn() => require_login($a, false, $cm, false, true));
    }

    public function test_web_request_redirects_to_blocked_page(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        $url = access_guard::get_blocked_url($a->id);
        $this->assertStringEndsWith('/local/sis_courseschedule/blocked.php', $url->get_path());
        $this->assertSame(['courseid' => (string) $a->id], $url->params());
        // In CLI-based PHPUnit redirect() reports itself with this code: it proves the HTML path was taken
        // instead of courseaccessclosed.
        try {
            access_guard::after_require_login($a, false);
            $this->fail('A redirect was expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
    }

    public function test_file_scripts_get_an_error_not_a_redirect(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        $original = $_SERVER['SCRIPT_NAME'] ?? null;
        foreach (['/pluginfile.php', '/tokenpluginfile.php'] as $script) {
            $_SERVER['SCRIPT_NAME'] = $script;
            $this->assert_closed(fn() => access_guard::after_require_login($a, false));
        }
        if ($original === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $original;
        }
    }

    public function test_bypass_roles_and_admin_are_not_blocked(): void {
        [$a] = $this->scenario();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_and_enrol($a, 'editingteacher');
        $manager = $generator->create_user();
        $generator->role_assign('manager', $manager->id, \context_course::instance($a->id)->id);
        foreach ([$teacher, $manager, get_admin()] as $user) {
            $this->setUser($user);
            access_guard::after_require_login($a, true);
        }
        $this->assertTrue(true);
    }

    public function test_teacher_without_bypass_is_blocked_but_can_still_be_a_manager_of_the_schedule(): void {
        [$a] = $this->scenario();
        $teacher = $this->getDataGenerator()->create_and_enrol($a, 'teacher');
        $context = \context_course::instance($a->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sis_courseschedule:manage', CAP_ALLOW, $roleid, $context->id, true);
        $this->getDataGenerator()->role_assign($roleid, $teacher->id, $context->id);
        $this->setUser($teacher);
        $this->assert_closed(fn() => access_guard::after_require_login($a, true));
        $this->assertTrue(has_capability('local/sis_courseschedule:manage', $context));
    }

    public function test_user_without_enrolment_gets_core_error_not_the_schedule(): void {
        [$a] = $this->scenario();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        try {
            require_login($a, false, null, false, true);
            $this->fail('Core should have refused the user');
        } catch (\require_login_exception $e) {
            $this->assertNotSame('courseaccessclosed', $e->errorcode);
            $this->assertStringNotContainsString('08:00', $e->getMessage());
        }
    }

    public function test_same_session_is_reevaluated_on_every_call(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        $this->assert_closed(fn() => access_guard::after_require_login($a, true));
        $this->clock->set_to($this->ts('2026-10-19 08:00:00'));
        access_guard::after_require_login($a, true);
        $this->clock->set_to($this->ts('2026-10-19 18:00:00'));
        $this->assert_closed(fn() => access_guard::after_require_login($a, true));
        // Editing the rule applies to the very next call.
        schedule_repository::save($a->id, false, self::TZ, []);
        access_guard::after_require_login($a, true);
        $this->assertTrue(true);
    }

    public function test_cli_cron_and_install_are_exempt(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        access_guard::set_test_environment(['cli' => true]);
        $this->assertTrue(access_guard::is_exempt_environment());
        access_guard::after_require_login($a, true);

        access_guard::set_test_environment(['cli' => false]);
        $this->assertFalse(access_guard::is_exempt_environment());
        $this->assert_closed(fn() => access_guard::after_require_login($a, true));

        global $CFG;
        $CFG->upgraderunning = time();
        try {
            $this->assertTrue(access_guard::is_exempt_environment());
            access_guard::after_require_login($a, true);
        } finally {
            unset($CFG->upgraderunning);
        }
    }

    public function test_site_course_and_missing_course_are_ignored(): void {
        [, , $student] = $this->scenario();
        $this->setUser($student);
        access_guard::after_require_login(SITEID, true);
        access_guard::after_require_login(null, true);
        access_guard::after_require_login(0, true);
        access_guard::after_require_login(99999999, true);
        $this->assertTrue(true);
    }

    public function test_course_comes_from_core_argument_never_from_request_id(): void {
        [$a, $b, $student] = $this->scenario();
        $this->setUser($student);
        $_GET['id'] = $_REQUEST['id'] = $a->id;
        try {
            access_guard::after_require_login($b, true);
        } finally {
            unset($_GET['id'], $_REQUEST['id']);
        }
        $this->assertTrue(true);
    }

    public function test_course_may_be_passed_as_id(): void {
        [$a, , $student] = $this->scenario();
        $this->setUser($student);
        $this->assert_closed(fn() => access_guard::after_require_login((int) $a->id, true));
        $this->assert_closed(fn() => access_guard::after_require_login((string) $a->id, true));
    }

    public function test_callbacks_are_registered_in_lib(): void {
        $this->assertTrue(function_exists('local_sis_courseschedule_after_require_login'));
        $this->assertTrue(function_exists('local_sis_courseschedule_override_webservice_execution'));
        $this->assertTrue(function_exists('local_sis_courseschedule_extend_navigation_course'));
        $callbacks = get_plugins_with_function('after_require_login', 'lib.php', false);
        $this->assertArrayHasKey('sis_courseschedule', $callbacks['local'] ?? []);
    }
}
