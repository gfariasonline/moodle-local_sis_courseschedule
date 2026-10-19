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
 * Custom data: courseid (int) and schedule (stdClass|null, the stored rule). The intervals are edited in a
 * weekly grid (AMD module schedule_grid) that serialises them as JSON in the hidden field slotsjson:
 * a list of objects with weekday (1-7), startminute and endminute.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_schedule_form extends \moodleform {
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

        $mform->addElement('hidden', 'slotsjson');
        $mform->setType('slotsjson', PARAM_RAW);
        $mform->setDefault('slotsjson', '[]');
        $mform->addElement('static', 'schedulegrid', get_string('weeklygrid', 'local_sis_courseschedule'),
            \html_writer::div('', '', ['id' => 'local-sis-courseschedule-grid']));

        $days = [];
        foreach ([1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday',
                7 => 'sunday'] as $number => $identifier) {
            $days[$number] = get_string($identifier, 'calendar');
        }
        $strings = [];
        foreach (['help', 'copyweekdays', 'clearweek', 'listtitle', 'addperiod', 'remove', 'empty', 'weekday', 'starttime',
                'endtime'] as $identifier) {
            $strings[$identifier] = get_string('grid_' . $identifier, 'local_sis_courseschedule');
        }
        global $PAGE;
        $PAGE->requires->js_call_amd('local_sis_courseschedule/schedule_grid', 'init', [[
            'selector' => '#local-sis-courseschedule-grid',
            'field' => 'slotsjson',
            'days' => $days,
            'strings' => $strings,
        ]]);

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
        $slots = [];
        foreach ($schedule->slots as $slot) {
            $slots[] = ['weekday' => (int) $slot->weekday, 'startminute' => (int) $slot->startminute,
                'endminute' => (int) $slot->endminute];
        }
        return ['enabled' => (int) $schedule->enabled, 'timezone' => $schedule->timezone,
            'slotsjson' => json_encode($slots)];
    }

    /**
     * Decode and check the submitted intervals.
     *
     * @param string $json Value of the slotsjson field.
     * @return array [slots, error]: valid slots (weekday, startminute, endminute) and an error string identifier or null.
     */
    private static function decode_slots(string $json): array {
        $decoded = json_decode($json === '' ? '[]' : $json, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [[], 'errorinvalidslots'];
        }
        $slots = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || !is_int($item['weekday'] ?? null) || !is_int($item['startminute'] ?? null)
                    || !is_int($item['endminute'] ?? null)) {
                return [[], 'errorinvalidslots'];
            }
            [$day, $start, $end] = [$item['weekday'], $item['startminute'], $item['endminute']];
            if ($day < 1 || $day > 7 || $start < 0 || $end > 1440 || $end <= $start) {
                return [[], 'errorinvalidslots'];
            }
            foreach ($slots as $other) {
                if ($other['weekday'] === $day && $start < $other['endminute'] && $end > $other['startminute']) {
                    return [[], 'erroroverlap'];
                }
            }
            $slots[] = ['weekday' => $day, 'startminute' => $start, 'endminute' => $end];
        }
        return [$slots, null];
    }

    /**
     * Convert the submitted JSON into slots. Invalid input yields no slots; validation() has already rejected it.
     *
     * @param string $json Value of the slotsjson field.
     * @return array[] Slots with weekday, startminute, endminute.
     */
    public static function slots_from_json(string $json): array {
        return self::decode_slots($json)[0];
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
        [$slots, $error] = self::decode_slots((string) ($data['slotsjson'] ?? ''));
        if ($error) {
            $errors['schedulegrid'] = get_string($error, 'local_sis_courseschedule');
        } else if (!empty($data['enabled']) && !$slots) {
            $errors['schedulegrid'] = get_string('errornointerval', 'local_sis_courseschedule');
        }
        if (!in_array((string) ($data['timezone'] ?? ''), \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = get_string('errorinvalidtimezone', 'local_sis_courseschedule');
        }
        return $errors;
    }
}
