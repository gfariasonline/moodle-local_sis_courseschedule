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

/**
 * Fixtures for browser acceptance of course access schedules.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Schedule fixtures using the same repository as the configuration form.
 */
class behat_local_sis_courseschedule extends behat_base {
    /**
     * Capture optional local visual evidence without writing artifacts into the plugin.
     *
     * @AfterStep
     * @param \Behat\Behat\Hook\Scope\AfterStepScope $scope Current step scope.
     */
    public function capture_schedule_page_after_step(\Behat\Behat\Hook\Scope\AfterStepScope $scope): void {
        $directory = getenv('QACS_SCREENSHOT_DIR');
        if (!$directory || !$scope->getTestResult()->isPassed()) {
            return;
        }
        $url = $this->getSession()->getCurrentUrl();
        foreach (['blocked', 'edit'] as $page) {
            if (str_contains($url, '/local/sis_courseschedule/' . $page . '.php')) {
                $this->saveScreenshot($page . '.png', $directory);
            }
        }
    }

    /**
     * Close a course without relying on a fixed day or minute of execution.
     *
     * @Given /^the course "(?P<shortname>[^"]+)" is closed by its access schedule$/
     * @param string $shortname Course fixture short name.
     */
    public function the_course_is_closed_by_its_access_schedule(string $shortname): void {
        global $DB;

        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza'));
        $tomorrow = (int) $now->format('N') % 7 + 1;
        \local_sis_courseschedule\local\schedule_repository::save((int) $courseid, true, 'America/Fortaleza', [
            ['weekday' => $tomorrow, 'startminute' => 480, 'endminute' => 1080],
        ]);
    }
}
