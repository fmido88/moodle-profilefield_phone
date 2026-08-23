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

use advanced_testcase;
use profile_field_phone;
use stdClass;

/**
 * Tests for the phone profile field save and validation paths.
 *
 * @package     profilefield_phone
 * @covers      \profile_field_phone
 * @copyright   2026 Saeed Ya
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_field_phone_test extends advanced_testcase {
    /**
     * Load the profile field APIs and legacy field class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/user/profile/lib.php");
        require_once("{$CFG->dirroot}/user/profile/field/phone/field.class.php");
        parent::setUpBeforeClass();
    }

    /**
     * Create a phone profile field instance for testing.
     *
     * @param array $overrides Field configuration overrides.
     * @param int $userid User id for the field instance.
     * @return profile_field_phone
     */
    private function create_field(array $overrides = [], int $userid = 0): profile_field_phone {
        $field = $this->getDataGenerator()->create_custom_profile_field(array_merge([
            'datatype' => 'phone',
            'name' => 'Phone',
            'shortname' => 'phone',
            'required' => 0,
            'forceunique' => 0,
            'param3' => 0,
        ], $overrides));

        return new profile_field_phone($field->id, $userid);
    }

    /**
     * Valid submitted values are formatted for storage.
     */
    public function test_preprocess_valid_country_and_number(): void {
        $this->resetAfterTest();
        $field = $this->create_field();

        $actual = $field->edit_save_data_preprocess([
            'code' => 'AU',
            'number' => '412345678',
        ], new stdClass());

        $this->assertSame('(AU)-61-412345678', $actual);
    }

    /**
     * A libphonenumber-valid region missing from the plugin country table must not cause a TypeError.
     */
    public function test_unresolvable_country_code_fails_safely(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['param3' => 1]);

        $this->assertTrue(phone::validate_number('GG', '7781123456', true, false, true));
        $this->assertNull(phone::get_phone_code_from_country('GG'));

        $data = [
            'code' => 'GG',
            'number' => '7781123456',
        ];

        $this->assertSame('', $field->edit_save_data_preprocess($data, new stdClass()));

        $usernew = (object)[
            'id' => 123,
            $field->inputname => $data,
        ];
        $errors = $field->edit_validate_field($usernew);
        $this->assertArrayHasKey($field->inputname, $errors);
    }

    /**
     * Unknown and missing country identifiers fail validation without throwing.
     */
    public function test_malformed_country_identifiers_fail_safely(): void {
        $this->resetAfterTest();
        $field = $this->create_field();

        $cases = [
            ['code' => 'ZZ', 'number' => '412345678'],
            ['number' => '412345678'],
        ];

        foreach ($cases as $data) {
            $this->assertSame('', $field->edit_save_data_preprocess($data, new stdClass()));

            $usernew = (object)[
                'id' => 123,
                $field->inputname => $data,
            ];
            $errors = $field->edit_validate_field($usernew);
            $this->assertArrayHasKey($field->inputname, $errors);
        }
    }

    /**
     * Blank optional phone values remain valid.
     */
    public function test_blank_optional_phone_is_valid(): void {
        $this->resetAfterTest();
        $field = $this->create_field();
        $data = ['code' => '', 'number' => ''];

        $this->assertSame('', $field->edit_save_data_preprocess($data, new stdClass()));

        $usernew = (object)[
            'id' => 123,
            $field->inputname => $data,
        ];
        $this->assertSame([], $field->edit_validate_field($usernew));
    }

    /**
     * Existing valid stored values round-trip through load and preprocess.
     */
    public function test_existing_value_round_trip(): void {
        $this->resetAfterTest();
        $field = $this->create_field();
        $field->set_user_data('(AU)-61-412345678');
        $user = new stdClass();

        $field->edit_load_user_data($user);

        $this->assertSame([
            'number' => '412345678',
            'code' => 'AU',
        ], $user->{$field->inputname});
        $this->assertSame(
            '(AU)-61-412345678',
            $field->edit_save_data_preprocess($user->{$field->inputname}, new stdClass())
        );
    }

    /**
     * A malformed phone field must not throw from profile_save_data after an unrelated core update.
     */
    public function test_profile_save_with_malformed_phone_does_not_throw_after_core_update(): void {
        global $DB;

        $this->resetAfterTest();
        $field = $this->create_field(['param3' => 1]);
        $user = $this->getDataGenerator()->create_user(['timezone' => 'Australia/Sydney']);

        $user->timezone = 'Europe/London';
        user_update_user($user, false, false);

        $user->{$field->inputname} = [
            'code' => 'GG',
            'number' => '7781123456',
        ];
        profile_save_data($user);

        $this->assertSame('Europe/London', $DB->get_field('user', 'timezone', ['id' => $user->id]));
    }

    /**
     * A required phone field rejects a blank submission without throwing.
     */
    public function test_required_blank_phone_is_invalid(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['required' => 1]);
        $data = ['code' => '', 'number' => ''];

        $this->assertSame('', $field->edit_save_data_preprocess($data, new stdClass()));

        $usernew = (object)[
            'id' => 123,
            $field->inputname => $data,
        ];
        $errors = $field->edit_validate_field($usernew);
        $this->assertArrayHasKey($field->inputname, $errors);
        $this->assertSame(get_string('profileinvaliddata', 'admin'), $errors[$field->inputname]);
    }

    /**
     * Mobile-only validation (param3) still applies for a country present in the plugin table.
     *
     * AU is used because it exists in the plugin's country table and libphonenumber. A genuine
     * AU mobile national number (9 digits, e.g. 412345678) is valid for the MOBILE type. The same
     * digits with the last digit dropped (41234567, 8 digits) are verified, via the actual
     * libphonenumber util in this codebase, to be impossible for MOBILE while still possible for
     * FIXED_LINE_OR_MOBILE - so it is only rejected because the field is mobile-only.
     */
    public function test_mobile_only_semantics(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['param3' => 1]);

        $validmobile = ['code' => 'AU', 'number' => '412345678'];
        $this->assertSame('(AU)-61-412345678', $field->edit_save_data_preprocess($validmobile, new stdClass()));

        $usernewvalid = (object)[
            'id' => 123,
            $field->inputname => $validmobile,
        ];
        $this->assertSame([], $field->edit_validate_field($usernewvalid));

        $invalidformobile = ['code' => 'AU', 'number' => '41234567'];
        $this->assertSame('', $field->edit_save_data_preprocess($invalidformobile, new stdClass()));

        $usernewinvalid = (object)[
            'id' => 123,
            $field->inputname => $invalidformobile,
        ];
        $errors = $field->edit_validate_field($usernewinvalid);
        $this->assertArrayHasKey($field->inputname, $errors);
    }

    /**
     * Force-unique validation allows the owning user to keep their value and rejects a duplicate
     * submitted by a different user, exercised through the real profile-save lifecycle.
     */
    public function test_forceunique_semantics(): void {
        global $DB;

        $this->resetAfterTest();

        $fielddef = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'phone',
            'name' => 'Phone',
            'shortname' => 'phone',
            'required' => 0,
            'forceunique' => 1,
            'param3' => 0,
        ]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $inputname = 'profile_field_' . $fielddef->shortname;
        $phonedata = ['code' => 'AU', 'number' => '412345678'];

        // Persist a valid phone value for user1 through the real profile-save lifecycle.
        $user1->$inputname = $phonedata;
        profile_save_data($user1);

        $this->assertSame(
            '(AU)-61-412345678',
            $DB->get_field('user_info_data', 'data', ['fieldid' => $fielddef->id, 'userid' => $user1->id])
        );

        // The owning user resubmitting their existing value must not trigger a uniqueness error.
        $field1 = new profile_field_phone($fielddef->id, $user1->id);
        $usernew1 = (object)[
            'id' => $user1->id,
            $field1->inputname => $phonedata,
        ];
        $errors1 = $field1->edit_validate_field($usernew1);
        $this->assertArrayNotHasKey($field1->inputname, $errors1);

        // A different user submitting the same value must be rejected as a duplicate.
        $field2 = new profile_field_phone($fielddef->id, $user2->id);
        $usernew2 = (object)[
            'id' => $user2->id,
            $field2->inputname => $phonedata,
        ];
        $errors2 = $field2->edit_validate_field($usernew2);
        $this->assertArrayHasKey($field2->inputname, $errors2);
        $this->assertStringContainsString(get_string('valuealreadyused'), $errors2[$field2->inputname]);
    }
}
