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

namespace local_sis_courseschedule\form;

use local_sis_courseschedule\local\schedule_formatter;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to configure the access schedule of one course.
 *
 * Custom data: courseid (int) and schedule (stdClass|null, the stored rule). Intervals are repeated
 * elements: weekday, start and end as HH:MM; a row with both times empty is ignored.
 *
 * @package    local_sis_courseschedule
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_schedule_form extends \moodleform {
    /** @var int Rows shown when there are no stored intervals. */
    private const MINROWS = 3;

    /**
     * Parse HH:MM into a minute of the day.
     *
     * @param string $value
     * @param bool $isend Whether 24:00 is accepted.
     * @return int|null Null when invalid.
     */
    public static function parse_time(string $value, bool $isend): ?int {
        if (!preg_match('/^([01]?\d|2[0-4]):([0-5]\d)$/', trim($value), $matches)) {
            return null;
        }
        $minute = (int) $matches[1] * 60 + (int) $matches[2];
        if ($minute > 1440 || ($minute === 1440 && !$isend)) {
            return null;
        }
        return $minute;
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $schedule = $this->_customdata['schedule'] ?? null;

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('static', 'impact', '', get_string('scheduleimpact', 'local_sis_courseschedule'));
        $summary = $schedule ? schedule_formatter::format_text($schedule) : get_string('schedulenone', 'local_sis_courseschedule');
        $mform->addElement('static', 'currentsummary', get_string('schedulesummary', 'local_sis_courseschedule'), s($summary));

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_sis_courseschedule'));
        $mform->addHelpButton('enabled', 'enabled', 'local_sis_courseschedule');

        $zones = array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers());
        $mform->addElement('autocomplete', 'timezone', get_string('timezone', 'local_sis_courseschedule'), $zones);
        $mform->addHelpButton('timezone', 'timezone', 'local_sis_courseschedule');
        $mform->setDefault('timezone', \core_date::get_server_timezone());
        $mform->addElement('static', 'currenttime', '', s(schedule_formatter::format_current_time(
            $schedule->timezone ?? \core_date::get_server_timezone()
        )));

        $mform->addElement('header', 'intervalshdr', get_string('intervalsheader', 'local_sis_courseschedule'));
        $mform->addElement('static', 'midnightnote', '', get_string('intervalcrossmidnight', 'local_sis_courseschedule'));

        $days = [];
        foreach ([1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday',
                7 => 'sunday'] as $number => $identifier) {
            $days[$number] = get_string($identifier, 'calendar');
        }
        $row = [
            $mform->createElement('select', 'weekday', get_string('weekday', 'local_sis_courseschedule'), $days),
            $mform->createElement('text', 'starttime', get_string('starttime', 'local_sis_courseschedule'), ['size' => 6]),
            $mform->createElement('text', 'endtime', get_string('endtime', 'local_sis_courseschedule'), ['size' => 6]),
        ];
        $count = max(self::MINROWS, count($schedule->slots ?? []) + 1);
        $options = [
            'weekday' => ['type' => PARAM_INT],
            'starttime' => ['type' => PARAM_TEXT],
            'endtime' => ['type' => PARAM_TEXT],
        ];
        $this->repeat_elements($row, $count, $options, 'slotcount', 'addslots', 1,
            get_string('addslots', 'local_sis_courseschedule'), true);

        $this->add_action_buttons();
    }

    /**
     * Values to preload from a stored rule.
     *
     * @param \stdClass|null $schedule
     * @return array
     */
    public static function to_form_data(?\stdClass $schedule): array {
        if (!$schedule) {
            return [];
        }
        $data = ['enabled' => (int) $schedule->enabled, 'timezone' => $schedule->timezone,
            'weekday' => [], 'starttime' => [], 'endtime' => []];
        foreach ($schedule->slots as $slot) {
            $data['weekday'][] = (int) $slot->weekday;
            $data['starttime'][] = schedule_formatter::format_minute((int) $slot->startminute);
            $data['endtime'][] = schedule_formatter::format_minute((int) $slot->endminute);
        }
        return $data;
    }

    /**
     * Convert submitted rows into slots; rows with both times empty are skipped.
     *
     * Invalid rows are skipped here; validation() has already rejected them.
     *
     * @param array $data Submitted data with weekday, starttime, endtime lists.
     * @return array[] Slots with weekday, startminute, endminute.
     */
    public static function slots_from_data(array $data): array {
        $slots = [];
        foreach ((array) ($data['starttime'] ?? []) as $i => $start) {
            $end = ($data['endtime'][$i] ?? '');
            if (trim((string) $start) === '' && trim((string) $end) === '') {
                continue;
            }
            $startminute = self::parse_time((string) $start, false);
            $endminute = self::parse_time((string) $end, true);
            if ($startminute === null || $endminute === null) {
                continue;
            }
            $slots[] = ['weekday' => (int) ($data['weekday'][$i] ?? 1), 'startminute' => $startminute,
                'endminute' => $endminute];
        }
        return $slots;
    }

    /**
     * Server side validation.
     *
     * @param array $data
     * @param array $files
     * @return array Errors indexed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $valid = [];
        foreach ((array) ($data['starttime'] ?? []) as $i => $start) {
            $end = (string) ($data['endtime'][$i] ?? '');
            $start = (string) $start;
            if (trim($start) === '' && trim($end) === '') {
                continue;
            }
            if (trim($start) === '' || trim($end) === '') {
                $errors["endtime[$i]"] = get_string('errorpartialinterval', 'local_sis_courseschedule');
                continue;
            }
            $startminute = self::parse_time($start, false);
            $endminute = self::parse_time($end, true);
            if ($startminute === null) {
                $errors["starttime[$i]"] = get_string('errorinvalidtime', 'local_sis_courseschedule');
            }
            if ($endminute === null) {
                $errors["endtime[$i]"] = get_string('errorinvalidtime', 'local_sis_courseschedule');
            }
            if ($startminute === null || $endminute === null) {
                continue;
            }
            if ($endminute <= $startminute) {
                $errors["endtime[$i]"] = get_string('errorendbeforestart', 'local_sis_courseschedule');
                continue;
            }
            $weekday = (int) ($data['weekday'][$i] ?? 1);
            foreach ($valid as $other) {
                if ($other['weekday'] === $weekday && $startminute < $other['end'] && $endminute > $other['start']) {
                    $errors["endtime[$i]"] = get_string('erroroverlap', 'local_sis_courseschedule');
                    break;
                }
            }
            $valid[] = ['weekday' => $weekday, 'start' => $startminute, 'end' => $endminute];
        }
        if (!empty($data['enabled']) && !$valid && !$errors) {
            $errors['starttime[0]'] = get_string('errornointerval', 'local_sis_courseschedule');
        }
        if (!in_array((string) ($data['timezone'] ?? ''), \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = get_string('errorinvalidtimezone', 'local_sis_courseschedule');
        }
        return $errors;
    }
}
