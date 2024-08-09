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
 * Menu profile field definition.
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class profile_define_phone
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_phone extends profile_define_base {
    /**
     * Prints out the form snippet for the part of creating or editing a profile field specific to the current data type.
     * @param MoodleQuickForm $mform instance of the moodleform class
     */
    public function define_form_specific($mform) {
        // Associated phone field.
        $phones = [
            ''       => get_string('none'),
            'phone1' => get_string('phone1'),
            'phone2' => get_string('phone2'),
        ];
        $mform->addElement('select', 'param4', get_string('associatefield', 'profilefield_phone'), $phones);

        $mform->addElement('checkbox', 'param3', get_string('ismobile', 'profilefield_phone'));
        $mform->addElement('checkbox', 'param5', get_string('showuseridentity', 'admin'));
    }

    /**
     * Validate the data from the add/edit profile field form
     * that is specific to the current data type
     * @param array $data
     * @param array $files
     * @return  array    associative array of error messages
     */
    public function define_validate_specific($data, $files) {
        global $DB;
        $data = (array)$data;
        $errors = [];
        if (!empty($data['param4'])) {
            $search = $DB->sql_compare_text('param4');
            $sql = "SELECT *
                    FROM {user_info_field}
                    WHERE $search = :param4
                      AND datatype = :datatype";
            $params = [
                'param4'    => $data['param4'],
                'datatype'  => 'phone',
            ];
            if ($record = $DB->get_record_sql($sql, $params)) {
                $errors['param4'] = get_string('fieldalreadyassociated', 'profilefield_phone', $record->shortname);
            }
        }
        return $errors;
    }
    /**
     * Alter form based on submitted or existing data
     * @param MoodleQuickForm $mform
     */
    public function define_after_data(&$mform) {
        // Do nothing - overwrite if necessary.
    }
    /**
     * Pre-process data from the add/edit profile field form before it is saved.
     *
     * This method is a hook for the child classes to overwrite.
     *
     * @param array|stdClass $data from the add/edit profile field form
     * @return array|stdClass processed data object
     */
    public function define_save_preprocess($data) {
        global $CFG;

        $data = (object)$data;

        $data->param3 ??= 0;
        $data->param5 ??= 0;

        $field = 'profile_field_' . $data->shortname;
        $identities = [];
        $identities = $CFG->showuseridentity ?? '';
        $identities = explode(',', $identities);

        if (!empty($data->param5)) {
            $identities[] = $field;
        } else {
            foreach ($identities as $k => $f) {
                if ($f == $field) {
                    unset($identities[$k]);
                }
            }
        }

        set_config('showuseridentity', implode(',', $identities));

        return $data;
    }
}


