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
 * Applies the access policy at the point where core has already authorised the user for a course.
 *
 * Called from the after_require_login callback, which core runs after all of its normal checks
 * (login, enrolment, visibility, activity availability). The guard therefore never reveals the
 * schedule to someone who could not enter the course anyway, and it never grants access.
 *
 * The course is taken from the value core passed to require_login(); request parameters such as
 * "id" are never read. CLI (including cron), installation and upgrade are exempt, as are the pages
 * of this plugin that must stay reachable while the course is closed.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_guard {
    /** @var string Constant defined by the plugin pages (blocked.php, edit.php) before require_login(). */
    public const OWNPAGE_CONSTANT = 'LOCAL_SIS_COURSESCHEDULE_OWNPAGE';

    /** @var string[] Scripts that serve files; they must get an error, never an HTML redirect. */
    private const FILE_SCRIPTS = ['pluginfile.php', 'tokenpluginfile.php'];

    /** @var array|null Environment overrides, honoured only while running PHPUnit. */
    private static ?array $testenvironment = null;

    /**
     * Override the environment detection in tests (key "cli" is the only one supported).
     *
     * @param array|null $environment
     */
    public static function set_test_environment(?array $environment): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('Test environment can only be set in PHPUnit');
        }
        self::$testenvironment = $environment;
    }

    /**
     * Whether the guard must stay out of this request (CLI, cron, install, upgrade, own pages).
     *
     * @return bool
     */
    public static function is_exempt_environment(): bool {
        global $CFG;
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return true;
        }
        if (defined(self::OWNPAGE_CONSTANT)) {
            return true;
        }
        if (self::$testenvironment !== null && array_key_exists('cli', self::$testenvironment)) {
            return (bool) self::$testenvironment['cli'];
        }
        // PHPUnit always runs as CLI; there the real request type is what the test wants to exercise.
        return CLI_SCRIPT && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST);
    }

    /**
     * Whether the current request must receive an error instead of an HTML redirect.
     *
     * @param bool $preventredirect The flag core passed to require_login().
     * @return bool
     */
    public static function must_throw(bool $preventredirect): bool {
        if ($preventredirect || AJAX_SCRIPT || WS_SERVER) {
            return true;
        }
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return in_array($script, self::FILE_SCRIPTS, true);
    }

    /**
     * Whether the schedule closes the course for the user right now.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_closed_for_user(int $courseid, int $userid): bool {
        if ($courseid <= SITEID || $userid <= 0) {
            return false;
        }
        return !access_policy::check($courseid, $userid)->allowed;
    }

    /**
     * The exception carrying the stable code courseaccessclosed and the schedule text.
     *
     * @param int $courseid
     * @return \moodle_exception
     */
    public static function closed_exception(int $courseid): \moodle_exception {
        return new \moodle_exception(
            'courseaccessclosed',
            'local_sis_courseschedule',
            '',
            schedule_formatter::format_text(schedule_repository::get_by_course($courseid))
        );
    }

    /**
     * URL of the notice page for a course.
     *
     * @param int $courseid
     * @return \moodle_url
     */
    public static function get_blocked_url(int $courseid): \moodle_url {
        return new \moodle_url('/local/sis_courseschedule/blocked.php', ['courseid' => $courseid]);
    }

    /**
     * Callback body for after_require_login.
     *
     * @param int|string|\stdClass|null $courseorid The value core received in require_login().
     * @param bool $preventredirect
     * @throws \moodle_exception With code courseaccessclosed when the course is closed (API, AJAX, files).
     */
    public static function after_require_login($courseorid, bool $preventredirect): void {
        global $USER;

        if (empty($courseorid) || self::is_exempt_environment()) {
            return;
        }
        $courseid = is_object($courseorid) ? (int) $courseorid->id : (int) $courseorid;
        $userid = (int) ($USER->id ?? 0);
        if (!self::is_closed_for_user($courseid, $userid)) {
            return;
        }
        if (self::must_throw($preventredirect)) {
            throw self::closed_exception($courseid);
        }
        redirect(self::get_blocked_url($courseid));
    }
}
