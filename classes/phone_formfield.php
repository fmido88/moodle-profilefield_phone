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

use core\output\named_templatable;

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->libdir . '/formslib.php');
use MoodleQuickForm as mform;
use HTML_QuickForm_element as element;
use renderer_base;

/**
 * Class phone_formfield
 *
 * @package    profilefield_phone
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phone_formfield extends element implements named_templatable {
    use \templatable_form_element {
        export_for_template as export_for_template_base;
    }
    public static function register() {
        mform::registerElementType('phone', __FILE__, self::class);
    }
    public function toHtml() {

    }
    public function export_for_template(renderer_base $output) {
        $context = $this->export_for_template_base($output);

        return $context;
    }
    public function get_template_name(renderer_base $renderer): string {
        return 'profilefield_phone/phone-element';
    }
}
