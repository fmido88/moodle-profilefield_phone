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

const RETRY_DELAY = 500;
const MAX_ATTEMPTS = 20;

class PhoneForm {
    /** @type {JQuery<HTMLElement>} */
    groupContainer;
    /** @type {JQuery<HTMLElement>} */
    hiddenSelect;
    /** @type {JQuery<HTMLElement>} */
    searchInput;
    /** @type {String} */
    currentSelectedValue;
    /** @type {String} */
    name;
    /** @type {String} */
    formid;

    /**
     * Create a phone form element class to handle phone element.
     * @param {String} name The phone element name.
     * @param {String} formid The mform id contains the element.
     */
    constructor(name, formid) {
        this.name = name;
        this.formid = formid;
        timeoutFunction(this.identifyGroupCotainer.bind(this));
    }

    /**
     * Identify the group container and hidden select element.
     */
    identifyGroupCotainer() {
        let mform = $('form#' + this.formid);
        this.groupContainer = mform.find('.profilefield_phone.phone-input-group.fitem[data-groupname="' + this.name + '"]');
        if (this.groupContainer.length === 0) {
            return false;
        }

        this.hiddenSelect = this.groupContainer.find('select[name="' + this.name + '[code]"]');

        // Ugly hack to remove flex-wrap class from the group.
        // This prevent the elements code and number to be wrapped under each others.
        this.groupContainer.find('div[data-fieldtype="group"] fieldset div.flex-wrap').removeClass('flex-wrap');
        this.groupContainer.find('div.d-flex.align-items-center').removeClass('align-items-center');

        timeoutFunction(this.identifySearchInput.bind(this));
        return true;
    }
    /**
     * Register the event listeners.
     */
    register() {
        this.currentSelectedValue = this.hiddenSelect.val();
        if (this.currentSelectedValue) {
            this.searchInput.attr('placeholder', this.getPhoneCode(this.currentSelectedValue));
        }

        let self = this;
        this.hiddenSelect.on('change', function() {
            self.currentSelectedValue = $(this).val();
            let code = self.getPhoneCode(self.currentSelectedValue);
            self.searchInput.attr('placeholder', code);
        });
    }

    /**
     * Get the phone code from the country code.
     * @param {String} country
     */
    getPhoneCode(country) {
        let text = this.hiddenSelect.find('option[value="' + country + '"]').text();
        let matches = text.match(/\+\d+/g);
        return matches ? matches[0] : '';
    }

    /**
     * Identify the search input.
     * The autocomplete element in MoodleQuickForm take a while
     * to get rendered by js and the search input to be in the DOM.
     */
    identifySearchInput() {
        this.searchInput = this.groupContainer.find('input[type="text"][data-fieldtype="autocomplete"]');
        if (this.searchInput.length > 0) {
            this.register();
            return true;
        } else {
            let freezed = this.groupContainer.find('span[data-fieldtype="autocomplete"]');
            if (freezed.length > 0 && freezed.find('select').length === 0) {
                let matches = freezed.text().match(/\+\d+/g);
                if (matches) {
                    freezed.text(matches[0]);
                }
                return true;
            }
        }
        return false;
    }
}

/**
 * @param {Function} fun
 * @param {Number} attempts Number of attempts remaining.
 * @param {Number} delay Delay in milliseconds before this attempt.
 */
function timeoutFunction(fun, attempts = MAX_ATTEMPTS, delay = 0) {
    setTimeout(function() {
        let done = fun();
        if (!done && attempts > 1) {
            timeoutFunction(fun, attempts - 1, RETRY_DELAY);
        }
    }, delay);
}

export const init = function(name, formid) {
    new PhoneForm(name, formid);
};
