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
 * Menu profile field.
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 use profilefield_phone\phone;
/**
 * Class profile_field_phone
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_phone extends profile_field_base {

    public $number = null;
    public $code = null;
    public $alpha2 = null;
    /**
     * Constructor method.
     *
     * Pulls out the options for the menu from the database and sets the the corresponding key for the data if it exists.
     *
     * @param int $fieldid
     * @param int $userid
     * @param object $fielddata
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null) {
        // First call parent constructor.
        parent::__construct($fieldid, $userid, $fielddata);

        // Set the phone data.
        if ($this->data !== null) {
            $numbers = explode('-', $this->data);
            if (count($numbers) == 2) {
                $this->number = $numbers[1];
                $this->alpha2 = $numbers[0];
                $this->code = phone::get_phone_code_from_country($this->alpha2);
            } else if (count($numbers) == 1) {
                $this->number = $numbers[0];
                $this->code = '';
                $this->alpha2 = '';
            }
        }
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param \MoodleQuickForm $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        global $CFG;
        phone::add_phone_to_form($mform, $this->inputname, format_string($this->field->name), false, $CFG->country ?? null, true);
    }

    /**
     * Set the default value for this field instance
     * Overwrites the base class method.
     * @param \MoodleQuickForm $mform Moodle form instance
     */
    public function edit_field_set_default($mform) {
        if (isset($this->field->defaultdata)) {
            $key = $this->field->defaultdata;
            $numbers = explode('-', $key);
            if (count($numbers) == 2) {
                $data = [
                    'code' => $numbers[0],
                    'number' => $numbers[1],
                ];
                $mform->setDefault($this->inputname,  $data);
            }
            return;
        }

        if (!empty($this->alpha2) && !empty($this->number)) {
            $mform->setDefault($this->inputname['code'], $this->alpha2);
            $mform->setDefault($this->inputname['number'], $this->number);
        }
    }

    /**
     * The data from the form returns the key.
     *
     * This should be converted to the respective option string to be saved in database
     * Overwrites base class accessor method.
     *
     * @param mixed $data The key returned from the select input in the form
     * @param stdClass $datarecord The object that will be used to save the record
     * @return mixed Data or null
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        return $data['code'] . '-' . $data['number'];
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data
     *
     * Overwrites the base class method.
     *
     * @param stdClass $user User object.
     */
    public function edit_load_user_data($user) {
        if ($this->data !== null) {
            $user->{$this->inputname} = [
                'code' => $this->alpha2,
                'number' => $this->number,
            ];
        }
    }

    /**
     * HardFreeze the field if locked.
     * @param \MoodleQuickForm $mform instance of the moodleform class
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() && !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname . '[number]', $this->number);
            $mform->setConstant($this->inputname . '[code]', $this->alpha2);
        }
    }

    /**
     * Validate the form field from profile page
     *
     * @param stdClass $usernew
     * @return  array  error messages for the form validation
     */
    public function edit_validate_field($usernew) {
        global $DB;

        $errors = phone::validate_phone_from_submitted_data($usernew);
        // Get input value.
        if (isset($usernew->{$this->inputname})) {
            $alpha2 = $usernew->{$this->inputname}['code'];
            $number = $usernew->{$this->inputname}['number'];
            $value = $alpha2 . "-" . $number;
        } else {
            $value = '';
        }

        // Check for uniqueness of data if required.
        if ($this->is_unique() && ($value !== '' || $this->is_required())) {
            $data = $DB->get_records_sql('
                    SELECT id, userid
                      FROM {user_info_data}
                     WHERE fieldid = ?
                       AND ' . $DB->sql_compare_text('data', 255) . ' = ' . $DB->sql_compare_text('?', 255),
                    [$this->field->id, $value]);
            if ($data) {
                $existing = false;
                foreach ($data as $v) {
                    if ($v->userid == $usernew->id) {
                        $existing = true;
                        break;
                    }
                }
                if (!$existing) {
                    $errors[$this->inputname] = get_string('valuealreadyused');
                }
            }
        }
        return $errors;
    }

    /**
     * Display the data for this field
     * @return string
     */
    public function display_data() {
        return "+" . $this->code . $this->number;
    }
    /**
     * Return the field type and null properties.
     * This will be used for validating the data submitted by a user.
     *
     * @return array the param type and null property
     * @since Moodle 3.2
     */
    public function get_field_properties() {
        return array(PARAM_ALPHANUMEXT, NULL_NOT_ALLOWED);
    }
}


