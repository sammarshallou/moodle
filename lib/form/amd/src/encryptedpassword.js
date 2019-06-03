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
 * Encrypted password functionality.
 *
 * @module core_form/encryptedpassword
 * @package core_form
 * @class encryptedpassword
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Constructor for EncryptedPassword.
 *
 * @param {String} elementId The element to apply the encrypted password JS to
 */
export const EncryptedPassword = function(elementId) {
    const wrapper = document.querySelector('div[data-encryptedpasswordid="' + elementId + '"]');
    this.span = wrapper.querySelector('span');
    this.input = wrapper.querySelector('input');
    this.buttons = wrapper.querySelectorAll('button');

    // Edit button action.
    this.buttons[0].addEventListener('click', (e) => {
        e.stopImmediatePropagation();
        e.preventDefault();
        this.startEditing(true);
    });

    // Cancel button action.
    this.buttons[1].addEventListener('click', (e) => {
        e.stopImmediatePropagation();
        e.preventDefault();
        this.cancelEditing();
    });

    // If the value is not set yet, start editing and remove the cancel option - so that
    // it saves something in the config table and doesn't keep repeat showing it as a new
    // admin setting...
    if (wrapper.dataset.novalue === 'y') {
        this.startEditing(false);
        this.buttons[1].style.display = 'none';
    }
};

/**
 * Starts editing.
 *
 * @param {Boolean} moveFocus If true, sets focus to the edit box
 */
EncryptedPassword.prototype.startEditing = function(moveFocus) {
    this.input.style.display = 'inline';
    this.input.disabled = false;
    this.span.style.display = 'none';
    this.buttons[0].style.display = 'none';
    this.buttons[1].style.display = 'inline';

    // Move the id around, which changes what happens when you click the label.
    const id = this.buttons[0].id;
    this.buttons[0].removeAttribute('id');
    this.input.id = id;

    if (moveFocus) {
        this.input.focus();
    }
};

/**
 * Cancels editing.
 */
EncryptedPassword.prototype.cancelEditing = function() {
    this.input.style.display = 'none';
    this.input.disabled = true;
    this.span.style.display = 'inline';
    this.buttons[0].style.display = 'inline';
    this.buttons[1].style.display = 'none';

    // Move the id around, which changes what happens when you click the label.
    const id = this.input.id;
    this.input.removeAttribute('id');
    this.buttons[0].id = id;
};
