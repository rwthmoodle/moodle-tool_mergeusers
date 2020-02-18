<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @author    Tim Schroeder <t.schroeder@itc.rwth-aachen.de>
 * @copyright 2020 RWTH Aachen University
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The old version of block_quickmail uses some columns to store comma-separated
 * lists of userids. They cannot be merged with the usual
 * UPDATE ... SET ... = ... WHERE ... = ... statements. That's what this class
 * is for.
 */
class OldQuickmailMerger extends GenericTableMerger {

    protected function updateRecords(array $data, array $recordsToModify, string $fieldName, array &$actionLog, array &$errorMessages) {
        global $CFG, $DB;
        if ($fieldName != 'mailto' && $fieldName != 'faileduserids') {
            return parent::updateRecords($data, $recordsToModify, $fieldName, $actionLog, $errorMessages);
        }

        foreach ($recordsToModify as $id) {
            $olduserids = $DB->get_field($data['tableName'], $fieldName, [self::PRIMARY_KEY => $id]);
            if ($olduserids === false) {
                $errorMessages[] = get_string('tableko', 'tool_mergeusers', $data['tableName']) .
                        ': ' . $DB->get_last_error();
                continue;
            }
            $userids = explode(',', $olduserids);
            foreach ($userids as $idx => $userid) {
                if ($userid == $data['fromid']) {
                    $userids[$idx] = $data['toid'];
                }
            }
            $userids = array_unique($userids);
            $newuserids = implode(',', $userids);
            $tableName = $CFG->prefix . $data['tableName'];
            $updateRecords = "UPDATE " . $tableName . ' ' .
                    " SET " . $fieldName . " = '" . $newuserids .
                    "' WHERE " . self::PRIMARY_KEY . " = " . $id;
            if (!$DB->execute($updateRecords)) {
                $errorMessages[] = get_string('tableko', 'tool_mergeusers', $data['tableName']) .
                        ': ' . $DB->get_last_error();
            }
            $actionLog[] = $updateRecords;
        }
    }

    protected function get_records_to_be_updated($data, $fieldName) {
        global $DB;
        return $DB->get_records_sql("SELECT " . self::PRIMARY_KEY .
            " FROM {" . $data['tableName'] . "} WHERE " .
            $fieldName . " LIKE '%" . $data['fromid'] . "%'");
    }
}
