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

use stdClass;

/**
 * Class helper
 *
 * @package    profilefield_phone
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Get all user's valid phone numbers.
     * @param int|stdClass $user
     * @return string[]
     */
    public static function get_all_user_phones(int|stdClass $user): array {
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

        return array_filter($phones, fn($value): bool => !empty(phone::validate_whole_number($value)));
    }
}
