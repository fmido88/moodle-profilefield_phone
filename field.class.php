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

/*
 * Phone profile field.
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use profilefield_phone\helper;
use profilefield_phone\phone;

/**
 * Class profile_field_phone.
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_phone extends profile_field_base {
    /**
     * The phone number.
     * @var ?int
     */
    public $number = null;

    /**
     * The numeric country phone code.
     * @var ?int
     */
    public $code = null;

    /**
     * Alpha2 country code.
     * @var ?string
     */
    public $alpha2 = null;

    /**
     * Sets user id and user data for the field.
     *
     * @param mixed $data
     * @param int   $dataformat
     */
    public function set_user_data($data, $dataformat = 1) {
        $this->data       = $data;
        $this->dataformat = $dataformat;

        // Try the internal format parser first.
        $numbers      = helper::get_data_from_string($data ?? '');
        $this->number = $numbers['number'];
        $this->code   = $numbers['code'];
        $this->alpha2 = $numbers['alpha2'];

        // If the result looks wrong (e.g. number still contains the country code prefix,
        // or no country info was found), try parsing as an international number.
        if (helper::should_try_international_parse($data, $this->number, $this->code)) {
            $parsed = helper::parse_international_number($data, false);
            if ($parsed !== null) {
                $this->number = $parsed['number'];
                $this->code   = $parsed['country_code'];
                $this->alpha2 = $parsed['alpha2'];
            }
        }

        $this->data = $this->display_data(false);
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method.
     * @param \MoodleQuickForm $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        global $USER;
        // Check if the field is required.
        $required = !$this->is_locked() && $this->is_required() && ($this->userid == $USER->id || isguestuser());
        phone::add_phone_to_form(
            $mform,
            $this->inputname,
            format_string($this->field->name),
            $required,
            phone::get_default_country($this->userid)
        );
    }

    /**
     * Check if the field is locked on the edit profile page
     * Overridden because the locked, required and empty field cause error in edit form.
     *
     * @return bool
     */
    public function is_locked() {
        if (!parent::is_locked()) {
            return false;
        }

        if ($this->is_required() && (empty($this->number) || empty($this->code))) {
            return false;
        }

        return true;
    }

    /**
     * Display the data for this field.
     * @param  bool   $reset Reset the data or no, required for single construction of the class
     *                       for multiple users.
     * @return string
     */
    public function display_data($reset = true) {
        if ($reset && !empty($this->data)) {
            $this->set_user_data($this->data);
        }

        if (empty($this->number) || (strpos($this->data, '+') === 0)) {
            return $this->data ?? '';
        }

        if (!empty($this->code)) {
            return '+' . $this->code . (int)$this->number;
        }

        return $this->number;
    }

    /**
     * Set the default value for this field instance
     * Overwrites the base class method.
     * @param \MoodleQuickForm $mform Moodle form instance
     */
    public function edit_field_set_default($mform) {

        $defcountry = $this->alpha2 ?? phone::get_default_country($this->userid);
        if (!empty($this->data)) {
            $data = [
                'code'   => $defcountry,
                'number' => $this->number,
            ];
        } else if (isset($this->field->defaultdata)) {
            $key     = $this->field->defaultdata;
            $default = helper::get_data_from_string($key, $defcountry);

            if (!empty($default['number'])) {
                $data = [
                    'code'   => $default['alpha2'],
                    'number' => $default['number'],
                ];
            }
        }

        if (!empty($data)) {
            $mform->setDefaults([
                $this->inputname             => $data,
                "{$this->inputname}[code]"   => $data['code'],
                "{$this->inputname}[number]" => $data['number'],
            ]);
        }
    }

    /**
     * The data from the form returns the key.
     *
     * This should be converted to the respective option string to be saved in database
     * Overwrites base class accessor method.
     *
     * @param  mixed    $data       The key returned from the select input in the form
     * @param  stdClass $datarecord The object that will be used to save the record
     * @return mixed    Data or null
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        global $DB;

        if (is_string($data)) {
            return $this->preprocess_string_data($data);
        }

        $number = $data['number'] ?? '';
        $alpha2 = $data['code'] ?? '';

        if (empty($number)) {
            return '';
        }

        if (!phone::validate_number($alpha2, $number, !empty($this->field->param3), false, true)) {
            return '';
        }

        $areacode = phone::get_phone_code_from_country($alpha2);
        if ($areacode === null) {
            return '';
        }

        $this->number = $number;
        $this->code   = $areacode;
        $this->alpha2 = $alpha2;

        return helper::build_internal_format($alpha2, $areacode, $number);
    }

    /**
     * Process string data (e.g. from CSV import) into the internal storage format.
     *
     * Accepts multiple input formats:
     * - Internal format: (DE)-49-1734567890
     * - Display format:  +491734567890 or 00491734567890
     * - With separators: +49 173 456 7890
     * - Alpha2 prefix:   DE-1734567890
     *
     * @param  string $data The raw string data.
     * @return string The data in internal format or empty string.
     */
    protected function preprocess_string_data($data) {
        $data = trim($data);

        if ($data === '') {
            return '';
        }

        $ismobile = !empty($this->field->param3);

        // 1. Try parsing as international number first (most common CSV format).
        //    This handles +41..., 0041..., 41..., etc.
        $international = helper::parse_international_number($data, $ismobile);
        if ($international !== null) {
            $this->number = $international['number'];
            $this->code   = $international['country_code'];
            $this->alpha2 = $international['alpha2'];
            return helper::build_internal_format($international['alpha2'], $international['country_code'], $international['number']);
        }

        // 2. Try the internal format parser for (alpha2)-code-number and alpha2-number.
        $parsed = helper::get_data_from_string($data);
        if (!empty($parsed['number']) && !empty($parsed['alpha2']) && !empty($parsed['code'])) {
            if (phone::validate_number($parsed['alpha2'], $parsed['number'], $ismobile, false, true)) {
                $this->number = $parsed['number'];
                $this->code   = $parsed['code'];
                $this->alpha2 = $parsed['alpha2'];
                return helper::build_internal_format($parsed['alpha2'], $parsed['code'], $parsed['number']);
            }
        }

        // Could not parse - return empty to prevent storing invalid data.
        return '';
    }

    /**
     * Saves the data coming from form.
     * @param stdClass $usernew data coming from the form
     */
    public function edit_save_data($usernew) {
        parent::edit_save_data($usernew);

        // Save associated field.
        if (!empty($this->field->param4) && !empty($usernew->{$this->inputname})) {
            $usernew->{$this->field->param4} = $this->display_data();
            user_update_user($usernew, false, false);
        }
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data.
     *
     * Overwrites the base class method.
     *
     * @param stdClass $user User object.
     */
    public function edit_load_user_data($user) {
        if ($this->data !== null) {
            $user->{$this->inputname} = [
                'number' => $this->number,
                'code'   => $this->alpha2,
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
            $data = [
                'number' => $this->number,
                'code'   => $this->alpha2,
            ];
            $mform->setConstant($this->inputname, $data);
        }
    }

    /**
     * Sets the required flag for the field in the form object.
     *
     * @param MoodleQuickForm $mform instance of the moodleform class
     */
    public function edit_field_set_required($mform) {
        // Do nothing.
    }

    /**
     * Validate the form field from profile page.
     *
     * @param  stdClass $usernew
     * @return array    error messages for the form validation
     */
    public function edit_validate_field($usernew) {
        global $DB;

        $errors = [];

        $alpha2 = '';
        $code   = '';
        $number = '';
        $value  = '';

        $ismobile = !empty($this->field->param3);

        // Get input value.
        if (isset($usernew->{$this->inputname})) {
            if (is_string($usernew->{$this->inputname})) {
                // String input (e.g. from CSV import).
                // Use the same parsing strategy as preprocess_string_data:
                // try international format first, then internal format.
                $rawstring = trim($usernew->{$this->inputname});

                // 1. Try international format (+41..., 0041..., 41..., etc.).
                $international = helper::parse_international_number($rawstring, $ismobile);
                if ($international !== null) {
                    $alpha2 = $international['alpha2'];
                    $code   = $international['country_code'];
                    $number = $international['number'];
                    $value  = helper::build_internal_format($alpha2, $code, $number);
                } else {
                    // 2. Try internal format via get_data_from_string.
                    $data   = helper::get_data_from_string($rawstring);
                    $alpha2 = $data['alpha2'];
                    $number = $data['number'];
                    $code   = $data['code'];

                    if (!empty($alpha2) && !empty($code) && !empty($number)) {
                        $value = helper::build_internal_format($alpha2, $code, $number);
                    }
                }
            } else {
                $number = $usernew->{$this->inputname}['number'] ?? null;

                if (!empty($number)) {
                    $alpha2 = $usernew->{$this->inputname}['code'] ?? '';
                    $code   = phone::get_phone_code_from_country($alpha2) ?? '';

                    if (!empty($code)) {
                        $value = helper::build_internal_format($alpha2, $code, $number);
                    }
                }
            }
        }

        if (empty($value)) {
            $value = '';
        }

        if ($this->is_required() || !empty($number)) {
            $countrycode = !empty($alpha2) ? phone::get_phone_code_from_country($alpha2) : null;
            $valid = $countrycode !== null && phone::validate_number($alpha2, $number, $ismobile, false, true);

            if (!$valid) {
                $value = '';
                $errors[$this->inputname] = get_string('profileinvaliddata', 'admin');
            }
        }

        // Check for uniqueness of data if required.
        if ($this->is_unique() && (($value !== '') || $this->is_required())) {
            $data = $DB->get_records_sql(
                '
                    SELECT id, userid
                      FROM {user_info_data}
                     WHERE fieldid = ?
                       AND ' . $DB->sql_compare_text('data', 255) . ' = ' . $DB->sql_compare_text('?', 255),
                [$this->field->id, $value]
            );

            if (!empty($data)) {
                $existing = false;

                foreach ($data as $v) {
                    if ($v->userid == $usernew->id) {
                        $existing = true;
                        break;
                    }
                }

                if (!$existing) {
                    $errstr = get_string('valuealreadyused');

                    if (isset($errors[$this->inputname])) {
                        $errors[$this->inputname] .= '<br>' . $errstr;
                    } else {
                        $errors[$this->inputname] = $errstr;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Return the field type and null properties.
     * This will be used for validating the data submitted by a user.
     *
     * @return array the param type and null property
     * @since Moodle 3.2
     */
    public function get_field_properties() {
        return [PARAM_TEXT, NULL_NOT_ALLOWED];
    }

    /**
     * Check if the field should convert the raw data into user-friendly data when exporting.
     *
     * @return bool
     */
    public function is_transform_supported(): bool {
        return true;
    }
}
