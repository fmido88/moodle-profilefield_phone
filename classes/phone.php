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

namespace profilefield_phone;

/**
 * Class phone
 *
 * @package     profilefield_phone
 * @copyright   2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phone {
    /**
     * Phones country data.
     * @var array[array]
     */
    protected static $data;

    /**
     * Adding phone elements to a moodle form.
     * @param  \MoodleQuickForm $mform
     * @param  string           $element
     * @param  string           $visiblename
     * @param  mixed            $required
     * @param  null|mixed       $defaultcountry
     * @param  mixed            $fullstring
     * @return void
     */
    public static function add_phone_to_form(
        \MoodleQuickForm &$mform,
        $element,
        $visiblename,
        $required = false,
        $defaultcountry = null,
        $fullstring = false,
        $forcecountry = true,
    ) {
        global $PAGE;

        $options = [
            'multiple'          => false,
            'noselectionstring' => '',
            'casesensitive'     => false,
            'placeholder'       => get_string('code', 'profilefield_phone'),
            'class'             => 'country-select-autocomplete',
        ];
        $autocomplete = $mform->createElement('autocomplete', 'code', '', self::get_country_codes_options($fullstring), $options);
        $phoneinput = $mform->createElement('text', 'number', '', ['size' => 20, 'placeholder' => $visiblename, 'class' => 'phone-number-input']);

        $group = [$autocomplete, $phoneinput];

        if ($PAGE->theme->get_rtl_mode()) {
            $group = array_reverse($group);
        }

        $classes = 'profilefield_phone phone-input-group';
        $mform->addGroup($group, $element, $visiblename, null, true, ['class' => $classes]);
        $mform->setType($element . '[number]', PARAM_INT);

        if ($required) {
            $rules = [
                'code' => [
                    [get_string('required'), 'required', null, 'client'],
                ],
                'number' => [
                    [get_string('required'), 'required', null, 'client'],
                ],
            ];
            $mform->addGroupRule($element, $rules);
        }

        if ($defaultcountry) {
            if (strlen($defaultcountry) === 3) {
                $defaultcountry = self::swap_alpha($defaultcountry);
            } else if (0 !== ($code = self::normalize_number($defaultcountry))) {
                $defaultcountry = self::get_country_alpha_from_code($code);
            }

            if (strlen($defaultcountry) === 2) {
                if ($forcecountry) {
                    $mform->setConstant($element, ['code' => $defaultcountry]);
                    $autocomplete->freeze();
                    $autocomplete->setPersistantFreeze(false);
                } else {
                    $mform->setDefault($element . '[code]', $defaultcountry);
                }
            }
        }

        $PAGE->requires->js_call_amd('profilefield_phone/form', 'init', ['name' => $element]);
    }
    /**
     * Summary of set_default_phone_form
     * @param \MoodleQuickForm $mform
     * @param string $element
     * @return void
     */
    public static function set_default_phone_form(&$mform, $element) {
        if (empty($_REQUEST[$element]) || !$mform->elementExists($element)) {
            return;
        }

        if (is_array($_REQUEST[$element])) {
            $phone = optional_param_array($element, null, PARAM_TEXT);
        } else {
            $phone = optional_param($element, null, PARAM_TEXT);
            if (!empty($phone)) {
                $data = self::validate_whole_number($phone, true);
                $final = [];
                if (false !== $data) {
                    $final['code'] = $data['country_code'];
                    $final['number'] = $data['number'];
                } else {
                    $final['number'] = $phone;
                }
                $phone = $final;
            }
        }

        if (!empty($phone)) {
            $mform->setDefault($element, $phone);
        }
    }
    /**
     * To be used in form validation.
     * @param  array|\stdClass $data
     * @param string $invalidstring
     * @return string[]
     */
    public static function validate_phone_from_submitted_data($data, $invalidstring = '') {
        if (empty($invalidstring)) {
            $invalidstring = get_string('invaliddata', 'profilefield_phone');
        }

        $data   = (array)$data;
        $errors = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['code']) && isset($value['number'])) {
                if (!self::validate_number($value['code'], $value['number'], true, false, true)) {
                    $errors[$key] = $invalidstring;
                }
            }
        }

        return $errors;
    }

    /**
     * Concatenate the code and the number from the submitted data.
     * @param  \stdClass|array $data
     * @return void
     */
    public static function normalize_submitted_phone_data(&$data) {
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $value = (array)$value;
            }

            if (is_array($value) && isset($value['code'], $value['number'])) {
                $code   = self::get_phone_code_from_country($value['code']);
                $number = self::normalize_number($value['number']);

                if (is_object($data)) {
                    $data->$key = (int)($code . $number);
                } else {
                    $data[$key] = (int)($code . $number);
                }
            }
        }
    }

    /**
     * Get an array of country codes to be used in forms.
     * @param  bool     $fullstring
     * @param  bool     $fortemplate
     * @return string[]
     */
    public static function get_country_codes_options($fullstring = false) {
        $options = [];

        $strman = get_string_manager();
        foreach (self::data() as $data) {
            if ($fullstring) {
                if ($strman->string_exists($data['alpha2'], 'countries')) {
                    $country = get_string($data['alpha2'], 'countries');
                } else {
                    // Fallback as the string not exist.
                    $country = $data['country_name'];
                }
            } else {
                $country = $data['alpha3'];
            }

            $options[$data['alpha2']] = $country . ' (+' . $data['country_code'] . ')';
        }

        asort($options);
        return $options;
    }

    /**
     * Get the country phone code from country codes like (US or USA).
     * @param  string   $country
     * @return int|null
     */
    public static function get_phone_code_from_country($country) {
        $country = strtoupper($country);
        $key     = (strlen($country) === 2) ? 'alpha2' : 'alpha3';

        foreach (self::data() as $data) {
            if ($data[$key] === $country) {
                return (int)$data['country_code'];
            }
        }

        return null;
    }

    /**
     * Get the country alphabetic code from phone number code.
     *
     * @param  int|string  $code
     * @param  string      $return
     * @return string|null
     */
    public static function get_country_alpha_from_code($code, $return = 'alpha2') {
        $code = self::normalize_number($code);

        foreach (self::data() as $data) {
            if ($data['country_code'] === $code) {
                return $data[$return];
            }
        }

        return null;
    }

    /**
     * Return alpha2 code from alpha3 code and vice versa.
     * @param  string $country
     * @return string
     */
    public static function swap_alpha($country) {
        $country = strtoupper($country);

        if (strlen($country) === 2) {
            $key    = 'alpha2';
            $return = 'alpha3';
        } else {
            $key    = 'alpha3';
            $return = 'alpha2';
        }

        foreach (self::data() as $data) {
            if ($data[$key] === $country) {
                return $data[$return];
            }
        }

        return null;
    }

    /**
     * Validate a phone number and return the detailed data of the phone.
     * @param  string     $code
     * @param  string     $number
     * @param  bool       $ismobile if the number is a mobile
     * @param  bool       $returndata returning the phone data after verified
     * @param  bool       $usecountry using the alphabetic code not phone code
     * @return array|bool
     */
    public static function validate_number($code, $number, $ismobile = true, $returndata = false, $usecountry = false) {
        $number = self::normalize_number($number);

        if (!$usecountry) {
            $code    = (string) self::normalize_number($code);
            $codekey = 'country_code';
        } else if (strlen($code) === 2) {
            $codekey = 'alpha2';
        } else {
            $codekey = 'alpha3';
        }

        foreach (self::data() as $data) {
            if ($code === $data[$codekey] && in_array(strlen($number), $data['phone_number_lengths'], true)) {
                $valid = true;
                if ($ismobile) {
                    $valid = false;
                    foreach ($data['mobile_begin_with'] as $prefix) {
                        if (substr($number, 0, strlen($prefix)) === $prefix) {
                            if (!$returndata) {
                                return true;
                            }
                            $valid = true;
                            break;
                        }
                    }
                }

                if ($valid) {
                    if ($returndata) {
                        $data['number'] = $number;
                        return $data;
                    }
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize a phone number by removing any thing other than numbers.
     * @param  string $phone
     * @return int
     */
    public static function normalize_number($phone) {
        return (int)preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Validate a phone number starting with the country code.
     * @param  string      $phone
     * @param  bool        $ismobile
     * @return array|false
     */
    public static function validate_whole_number($phone, $ismobile = true) {
        $phone = self::normalize_number($phone);
        // Possible codes.
        $codes = [
            substr($phone, 0, 1),
            substr($phone, 0, 2),
            substr($phone, 0, 3),
        ];

        foreach ($codes as $code) {
            if ($data = self::validate_number($code, substr($phone,strlen($code)), $ismobile, true)) {
                return $data;
            }
        }

        return false;
    }

    /**
     * Array with all possible data.
     * @return array[]
     */
    protected static function data() {
        if (isset(self::$data)) {
            return self::$data;
        }
        self::$data = data::PHONE_DATA;

        return self::$data;
    }
}
