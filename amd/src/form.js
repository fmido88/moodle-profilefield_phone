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
 * TODO describe module form
 *
 * @module     profilefield_phone/form
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

const iconifyScript = "https://code.iconify.design/3/3.1.0/iconify.min.js";

/** @type {JQuery<HTMLElement>} */
let selectBox;
/** @type {JQuery<HTMLElement>} */
let searchBox;
/** @type {JQuery<HTMLElement>} */
let inputBox;
/** @type {JQuery<HTMLElement>} */
let selectedOption;
/** @type {JQuery<HTMLElement>} */
let options;

/**
 * Action after select an option.
 */
function selectOption() {
    const icon = this.querySelector('.iconify').cloneNode(true);
    const phoneCode = this.querySelector('strong').cloneNode(true);

    selectedOption.innerHTML = '';
    selectedOption.append(icon, phoneCode);

    inputBox.value = phoneCode.innerText;

    selectBox.removeClass('active');
    selectedOption.removeClass('active');

    searchBox.value = '';
    selectBox.find('.hide').forEach(el => el.classList.remove('hide'));
}

/**
 * Searching for a country.
 */
function searchCountry() {
    let searchQuery = searchBox.value.toLowerCase();
    for (let option of options) {
        let isMatched = option.querySelector('.country-name').innerText.toLowerCase().includes(searchQuery);
        option.classList.toggle('hide', !isMatched);
    }
}

/**
 *
 * @param {JQuery<HTMLElement>} element
 * @param {String} className
 */
function toggleClass(element, className) {
    if (element.hasClass(className)) {
        element.removeClass(className);
    } else {
        element.addClass(className);
    }
}

export const init = function(id) {
    let iconify = document.createElement('script');
    iconify.src = iconifyScript;
    $('head').append(iconify);

    let baseFormElement = $('#' + id);
    if (baseFormElement.length !== 1) {
        return;
    }

    selectBox = baseFormElement.find('.options');
    searchBox = baseFormElement.find('.search-box');
    inputBox = baseFormElement.find('input[type="tel"]');
    selectedOption = baseFormElement.find('.selected-option div');
    options = baseFormElement.find('.option');

    selectedOption.on('click', function() {
        toggleClass(selectedOption, 'active');
        toggleClass(selectBox, 'active');
    });

    options.on('click', selectOption);
    searchBox.on('input', searchCountry);
};
