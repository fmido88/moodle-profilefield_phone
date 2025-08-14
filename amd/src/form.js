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
 * Handle the ui of the phone field input.
 *
 * @module     profilefield_phone/form
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

/** @type {JQuery<HTMLElement>} */
let groupContainer;
/** @type {JQuery<HTMLElement>} */
let hiddenSelect;
/** @type {JQuery<HTMLElement>} */
let searchInput;
/** @type {String} */
let currentSelectedValue;
/** @type {NodeJS.Timeout} */
let searchTimeout;
/**
 * Register the event listeners.
 */
function register() {
    currentSelectedValue = hiddenSelect.val();
    if (currentSelectedValue) {
        searchInput.attr('placeholder', getPhoneCode(currentSelectedValue));
    }

    hiddenSelect.on('change', function() {
        currentSelectedValue = $(this).val();
        let code = getPhoneCode(currentSelectedValue);
        searchInput.attr('placeholder', code);
    });
}

/**
 * Get the phone code from the country code.
 * @param {String} country
 */
function getPhoneCode(country) {
    let text = hiddenSelect.find('option[value="' + country + '"]').text();
    return text.match(/\+\d+/g).shift();
}

/**
 * Identify the search input.
 * The autocomplete element in MoodleQuickForm take a while
 * to get rendered by js and the search input to be in the DOM.
 */
function identifySearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        searchInput = groupContainer.find('input[type="text"][data-fieldtype="autocomplete"]');
        if (searchInput.length > 0) {
            register();
        } else {
            let freezed = groupContainer.find('span[data-fieldtype="autocomplete"]');
            if (freezed.length > 0 && freezed.find('select').length === 0) {
                freezed.text(freezed.text().match(/\+\d+/g).shift());
                return;
            }

            identifySearchInput();
        }
    }, 100);
}

export const init = function(name, formid) {
    let mform = $('form#' + formid);
    groupContainer = mform.find('.profilefield_phone.phone-input-group.fitem[data-groupname="' + name + '"]');
    hiddenSelect = groupContainer.find('select[name="' + name + '[code]"]');

    // Ugly hack to remove flex-wrap class from the group.
    // This prevent the elements code and number to be wrapped behind each others.
    groupContainer.find('div[data-fieldtype="group"] fieldset div.flex-wrap').removeClass('flex-wrap');
    identifySearchInput();
};
