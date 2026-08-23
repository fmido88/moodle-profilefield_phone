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

use core\di;
use core\exception\moodle_exception;
use core_text;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use stdClass;

/**
 * Class helper.
 *
 * @package    profilefield_phone
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Return an instance of phone number util.
     * @return PhoneNumberUtil|null
     */
    public static function get_helper(): ?PhoneNumberUtil {
        try {
            return di::get(PhoneNumberUtil::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Parse.
     * @param  string           $number
     * @param  ?string          $countrycode
     * @return PhoneNumber|null
     */
    public static function parse(string $number, ?string $countrycode = null): ?PhoneNumber {
        if ($h = self::get_helper()) {
            try {
                $phone = $h->parse($number, $countrycode, null, true);
            } catch (NumberParseException $e) {
                return null;
            }
        }

        return $phone ?? null;
    }

    /**
     * Get all user's valid phone numbers.
     * @param  int|stdClass $user
     * @return string[]
     */
    public static function get_all_user_phones(int|stdClass $user = 0): array {
        global $USER, $DB, $CFG;
        require_once("$CFG->dirroot/user/profile/lib.php");

        if (empty($user)) {
            $user = clone $USER;
        } else if (\is_int($user)) {
            $user = \core\user::get_user($user, '*', MUST_EXIST);
        }

        $phones = [
            'phone1' => phone::normalize_number($user->phone1),
            'phone2' => phone::normalize_number($user->phone2),
        ];

        $profilefields = \profile_get_user_fields_with_data($user->id);

        foreach ($profilefields as $field) {
            if ($field->field->datatype === 'phone') {
                $phones['profilefield_' . $field->get_shortname()] = $field->display_data();
            }
        }

        return array_filter($phones, fn ($value): bool => !empty(phone::validate_whole_number($value)));
    }

    /**
     * Get user by the submitted phone number.
     * @param string $phone
     * @param string $code
     * @param int $strictness
     *
     * @return stdClass|stdClass[]|null
     */
    public static function get_user_by_phone(string $phone, string $code = '', int $strictness = IGNORE_MISSING) {
        global $DB;
        $phone = phone::normalize_number($phone);
        $phone = ltrim($phone, " \n\r\t\v\x00+0"); // Strip zeros and leading + if existed.

        $params = [
            'phone1'     => "%{$phone}",
            'phone2'     => "%{$phone}",
            'phone3'     => "%{$phone}",
            'phonetype'  => 'phone',
        ];
        $datawhere = $DB->sql_like($DB->sql_compare_text('ui.data'), ':phone1', false, false);
        $phone1where = $DB->sql_like($DB->sql_compare_text('u.phone1'), ':phone2', false, false);
        $phone2where = $DB->sql_like($DB->sql_compare_text('u.phone2'), ':phone3', false, false);

        $uid = $DB->sql_concat('u.id', "'-'", 'u.phone1', "'-'", 'u.phone2');
        $sql = "SELECT $uid as unid, u.*, ui.data as phone3
                FROM {user} u
        LEFT JOIN {user_info_data} ui ON ui.userid = u.id
        LEFT JOIN {user_info_field} uf ON ui.fieldid = uf.id
            WHERE $phone1where
                OR $phone2where
                OR (
                    uf.datatype = :phonetype
                    AND
                        $datawhere
                    -- AND uf.forceunique = 1
                )";

        $records = $DB->get_records_sql($sql, $params);

        $users = [];

        foreach ($records as $record) {
            $valid = false;
            $resultphone = '';

            for ($i = 1; $i <= 3; $i++) {
                $para = $record->{"phone{$i}"};

                if (empty($para) || strpos($para, $phone) === false) {
                    continue;
                }
                $data = self::get_data_from_string($para, $code ?: null);

                if (phone::validate_number($data['alpha2'], $data['number'])) {
                    $valid = true;
                    $resultphone = self::build_internal_format(...$data);
                    break;
                }

                if (empty($code) && ($parseddata = phone::validate_whole_number($para))) {
                    $valid = true;
                    $data = [
                        'alpha2' => $parseddata['alpha2'],
                        'code'   => $parseddata['country_code'],
                        'number' => $parseddata['number'],
                    ];
                    $resultphone = self::build_internal_format(...$data);
                    break;
                }
            }

            if ($valid && !empty($resultphone)) {
                unset($record->unid, $record->phone3);
                $record->phone = $resultphone;

                if ($strictness === IGNORE_MULTIPLE) {
                    return $record;
                }

                $users[$record->id] = $record;
            }
        }

        return match ($strictness) {
            MUST_EXIST      => \count($users) !== 1 ? throw new moodle_exception("Cannot found the user for phone '$phone'") : reset($users),
            IGNORE_MULTIPLE => \count($users) !== 0 ? reset($users) : null,
            IGNORE_MISSING  => match(\count($users)) {
                0       => null,
                1       => reset($users),
                default => (function () use ($users, $phone) {
                    debugging("multiple records found for same phone '$phone'");

                    return reset($users);
                })(),
            }
        };
    }

    /**
     * Determine if we should attempt international number parsing as a fallback.
     *
     * This is needed when get_data_from_string() doesn't properly parse the input,
     * which happens when:
     * - The input is an international format like +41791234501
     * - get_data_from_string() stuffed the whole string into 'number' and filled
     *   alpha2/code from the default country, leading to an invalid combination
     * - No country info was extracted at all
     *
     * @param  string     $rawdata The original raw input string.
     * @param  string|int $number  The number as parsed by get_data_from_string.
     * @param  string|int $code    The code as parsed by get_data_from_string.
     * @return bool
     */
    public static function should_try_international_parse(?string $rawdata, string|int $number, string|int $code): bool {
        $rawdata = trim((string)$rawdata);

        // No country info found at all.
        if (!empty($number) && empty($code)) {
            return true;
        }

        // Input looks like an international number (starts with + or 00).
        if (strpos($rawdata, '+') === 0 || strpos($rawdata, '00') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Parse a string as an international phone number.
     *
     * Handles formats like:
     * - +41791234501
     * - 0041791234501
     * - 41 79 123 45 01
     * - +41 79 123 45 01
     *
     * @param  string     $input    The raw input string.
     * @param  bool       $ismobile Whether to validate as mobile number.
     * @return array|null Parsed data with alpha2, country_code, number keys, or null on failure.
     */
    public static function parse_international_number(string $input, bool $ismobile = false): ?array {
        // Remove all non-digit characters (spaces, dashes, dots, parentheses).
        $normalized = phone::normalize_number($input);

        if (empty($normalized) || core_text::strlen($normalized) < 4) {
            return null;
        }

        $result = phone::validate_whole_number($normalized, $ismobile);

        if ($result !== false && !empty($result['alpha2']) && !empty($result['country_code'])) {
            return $result;
        }

        return null;
    }

    /**
     * Build the internal storage format string from parsed components.
     *
     * @param  string     $alpha2 The alpha2 country code.
     * @param  string|int $code   The numeric country phone code.
     * @param  string|int $number The phone number without country code.
     * @return string     The internal format: (alpha2)-code-number
     */
    public static function build_internal_format(string $alpha2, string|int $code, string|int $number): string {
        return "({$alpha2})-{$code}-{$number}";
    }

    /**
     * Explode the stored data as codes and numbers.
     * @param  string  $string
     * @param  ?string $defcountry The default country code.
     * @return array
     */
    public static function get_data_from_string(string $string, ?string $defcountry = null) {
        $numbers = explode('-', $string);

        $data = [
            'number' => '',
            'alpha2' => '',
            'code'   => '',
        ];

        if ($defcountry !== null && is_number($defcountry)) {
            $guessed = phone::get_country_alpha_from_code($defcountry);
            $defcountry = $guessed && (core_text::strlen($guessed) === 2) ? $guessed : null;
        }

        switch (\count($numbers)) {
            case 1:
                $data['number'] = $numbers[0];
                $data['alpha2'] = $defcountry ?? phone::get_default_country() ?? '';

                if (!empty($data['alpha2'])) {
                    $data['code'] = phone::get_phone_code_from_country($data['alpha2']);
                }
                break;

            case 2:
                $data['number'] = $numbers[1];
                $data['alpha2'] = $numbers[0];
                $data['code'] = phone::get_phone_code_from_country($numbers[0]);
                break;

            default:
                $data['alpha2'] = str_replace(['(', ')'], '', array_shift($numbers));
                $data['code'] = array_shift($numbers);
                $data['number'] = implode('', $numbers);
                break;
        }

        return $data;
    }
}
