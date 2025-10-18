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
 * Helper class to validate phone number data and handle rendering its field
 * in mform.
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
     * Reason of invalid is not matching phone number length.
     * @var string
     */
    public const REASON_NUMBER_LENGTH = 'number_length';

    /**
     * Invalid start number for mobile phones.
     * @var string
     */
    public const REASON_MOBILE_START = 'mobile_start';

    /**
     * Invalid country code and no match for data.
     * @var string
     */
    public const REASON_NO_MATCH = 'no_match';

    /**
     * Adding phone elements to a moodle form.
     * @param  \MoodleQuickForm $mform
     * @param  string           $element        The element name in the form.
     * @param  string           $visiblename    The visible name of the form.
     * @param  bool             $required       Add required rule to the element.
     * @param  null|string|int  $defaultcountry The default country.
     * @param  bool             $fullstring     If to display the country names in full string names.
     * @param  bool             $forcecountry   Force specific country and the user cannot change it.
     * @return void
     */
    public static function add_phone_to_form(
        \MoodleQuickForm &$mform,
        $element,
        $visiblename,
        $required = false,
        $defaultcountry = null,
        $fullstring = false,
        $forcecountry = false,
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

        $phoneinputattr = [
            'size'        => 20,
            'placeholder' => $visiblename,
            'class'       => 'phone-number-input',
        ];
        $phoneinput = $mform->createElement('text', 'number', '', $phoneinputattr);

        $group = [$autocomplete, $phoneinput];

        // Phone numbers always ltr.
        if ($PAGE->theme->get_rtl_mode() || right_to_left()) {
            $group = array_reverse($group);
        }

        $classes = 'profilefield_phone phone-input-group';
        $mform->addGroup($group, $element, $visiblename, null, true, ['class' => $classes]);
        $mform->setType($element . '[number]', PARAM_INT);

        if ($required) {
            $strrequired = get_string('required');
            $rules = [
                'number' => [
                    [$strrequired, 'required', null, 'client'],
                ],
            ];
            if (!$forcecountry) {
                 $rules['code'] = [
                    [$strrequired, 'required', null, 'client'],
                 ];
            }
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
                    $mform->setDefault($element, ['code' => $defaultcountry]);
                    $autocomplete->freeze();
                    $autocomplete->setPersistantFreeze(false);
                    $mform->addElement('hidden', $element . '[code]', $defaultcountry);
                    $mform->setType($element . '[code]', PARAM_ALPHA);
                } else {
                    $mform->setDefault($element . '[code]', $defaultcountry);
                }
            }
        }

        $PAGE->requires->js_call_amd('profilefield_phone/form', 'init', [
            'name'   => $element,
            'formid' => $mform->getAttribute('id'),
        ]);
    }

    /**
     * Set the form default values from the submitted data.
     *
     * @param  \MoodleQuickForm $mform
     * @param  string           $element
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
                $data  = self::validate_whole_number($phone, true);
                $final = [];

                if (false !== $data) {
                    $final['code']   = $data['country_code'];
                    $final['number'] = $data['number'];
                } else {
                    $final['number'] = $phone;
                }
                $phone = $final;
            }
        }

        if (!empty($phone)) {
            $mform->setDefault($element, $phone);
            $mform->setDefault("{$element}[code]", $phone['code'] ?? '');
            $mform->setDefault("{$element}[number]", $phone['number'] ?? '');
        }
    }

    /**
     * To be used in form validation.
     * @param  array|\stdClass $data
     * @param  string          $invalidstring
     * @param  array           $reasons       array of reasons of invalidation.
     * @return string[]
     */
    public static function validate_phone_from_submitted_data($data, $invalidstring = '', &$reasons = []) {
        if (empty($invalidstring)) {
            $invalidstring = get_string('invaliddata', 'profilefield_phone');
        }

        $data   = (array)$data;
        $errors = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['code'], $value['number'])) {
                if (!self::validate_number($value['code'], $value['number'], true, false, true, $reasons[$key])) {
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

        if ($key === 'alpha2' && isset(self::data()[$country])) {
            return (int)(self::data()[$country]['country_code']);
        }

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
            if (isset(self::data()[$country])) {
                return self::data()[$country]['alpha3'];
            }

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
     * @param  bool       $ismobile   if the number is a mobile
     * @param  bool       $returndata returning the phone data after verified
     * @param  bool       $usecountry using the alphabetic code not phone code
     * @param  array      $reasons    array of reasons of invalidation
     * @return array|bool
     */
    public static function validate_number(
        $code,
        $number,
        $ismobile = true,
        $returndata = false,
        $usecountry = false,
        &$reasons = []
    ) {
        $number = self::normalize_number($number);

        $code = trim($code ?? '');

        if (!$usecountry || is_number($code) || strpos($code, '+') === 0) {
            $code    = self::normalize_number($code);
            $codekey = 'country_code';
        } else if (strlen($code) === 2) {
            $codekey = 'alpha2';
        } else if (strlen($code) === 3) {
            $codekey = 'alpha3';
        }

        $code = strtoupper((string)$code);

        if (empty($code) || empty($codekey)) {
            $data = self::validate_whole_number($code . $number, $ismobile);

            if (false === $data) {
                $reasons[] = self::REASON_NO_MATCH;

                return false;
            }

            if ($returndata) {
                return $data;
            }

            return true;
        }

        if ($codekey === 'alpha2' && isset(self::data()[$code])) {
            $data = self::data()[$code];

            if (self::compare_with_single_country_data($code, $codekey, $number, $data, $ismobile, $reasons)) {
                if ($returndata) {
                    $data['number'] = $number;

                    return $data;
                }

                return true;
            }

            return false;
        }

        foreach (self::data() as $data) {
            if (self::compare_with_single_country_data($code, $codekey, $number, $data, $ismobile, $reasons)) {
                if ($returndata) {
                    $data['number'] = $number;

                    return $data;
                }

                return true;
            }
        }

        if (empty($reasons)) {
            $reasons[] = self::REASON_NO_MATCH;
        }

        return false;
    }

    /**
     * Compare a number with its country code with single
     * country data.
     * @param  string|int $code
     * @param  string     $codetype    country_code, alpha2 or alpha3
     * @param  int|string $number      the phone number without the country code
     * @param  array      $counrtydata the country data array
     * @param  bool       $ismobile
     * @param  array      $reasons     return array with reasons of invalidation.
     * @return bool
     */
    protected static function compare_with_single_country_data(
        $code,
        $codetype,
        $number,
        $counrtydata,
        $ismobile = true,
        &$reasons = []
    ) {
        $valid = false;

        if ($code === $counrtydata[$codetype]) {
            $valid = true;

            if (!in_array(strlen($number), $counrtydata['phone_number_lengths'], true)) {
                $valid     = false;
                $reasons[] = self::REASON_NUMBER_LENGTH;
            }

            if ($ismobile) {
                $originalvalid = (bool)$valid;
                $valid = false;

                foreach ($counrtydata['mobile_begin_with'] as $prefix) {
                    if (substr($number, 0, strlen($prefix)) === $prefix) {
                        $valid = true;
                        break;
                    }
                }
                !$valid ? ($reasons[] = self::REASON_MOBILE_START) : null;
                $valid = $valid && $originalvalid;
            }
        }

        return $valid;
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
        if (empty($phone) || strlen($phone) < 4) {
            return false;
        }

        // Possible codes.
        $codes = [
            substr($phone, 0, 1),
            substr($phone, 0, 2),
            substr($phone, 0, 3),
        ];

        foreach ($codes as $code) {
            if ($data = self::validate_number($code, substr($phone, strlen($code)), $ismobile, true)) {
                return $data;
            }
        }

        return false;
    }

    /**
     * Return an array with country data rules from the
     * country code.
     * @param  string|int $code
     * @return array|null
     */
    public static function get_country_rules($code) {
        $counrtycode = self::normalize_number($code);

        if (!empty($counrtycode)) {
            $code = $counrtycode;
            $key  = 'country_code';
        } else if (strlen($code) === 2) {
            $key = 'alpha2';
        } else if (strlen($code) === 3) {
            $key = 'alpha3';
        } else {
            return null;
        }

        $code = strtoupper((string)$code);

        if ($key === 'alpha2' && isset(self::data()[$code])) {
            return self::data()[$code];
        }

        foreach (self::data() as $data) {
            if (isset($data[$key]) && $data[$key] === $code) {
                return $data;
            }
        }

        return null;
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
