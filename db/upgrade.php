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
 * Database upgrades for the course access schedule.
 *
 * @package    local_sis_courseschedule
 * @author     Thiago Serrão
 * @copyright  2026 SIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Preserve existing intervals while giving their table the full component prefix.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_local_sis_courseschedule_upgrade($oldversion): bool {
    global $DB;

    if ($oldversion < 2026101801) {
        $dbman = $DB->get_manager();
        $legacytable = new xmldb_table('local_sis_coursesched_slot');
        if ($dbman->table_exists($legacytable)) {
            $dbman->rename_table($legacytable, 'local_sis_courseschedule_slt');
        }
        upgrade_plugin_savepoint(true, 2026101801, 'local', 'sis_courseschedule');
    }

    return true;
}
