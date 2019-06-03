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
define(['jquery'], function($) {

    /**
     * Constructor for EncryptedPassword.
     *
     * @param {String} elementId The element to apply the encrypted password JS to
     */
    var EncryptedPassword = function(elementId) {
        var wrapper = $('div[data-encryptedpasswordid="' + elementId + '"]');
        this.span = $('span', wrapper);
        this.input = $('input', wrapper);
        this.buttons = $('button', wrapper);

        // Edit button action.
        $(this.buttons[0]).on('click keypress', $.proxy(function(e) {
            if (e.type === 'keypress' && e.keyCode !== 13) {
                return;
            }
            e.stopImmediatePropagation();
            e.preventDefault();
            this.startEditing(true);
        }, this));

        // Cancel button action.
        $(this.buttons[1]).on('click keypress', $.proxy(function(e) {
            if (e.type === 'keypress' && e.keyCode !== 13) {
                return;
            }
            e.stopImmediatePropagation();
            e.preventDefault();
            this.cancelEditing();
        }, this));

        // If the value is not set yet, start editing and remove the cancel option - so that
        // it saves something in the config table and doesn't keep repeat showing it as a new
        // admin setting...
        if (wrapper.data('novalue') === 'y') {
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
        this.input[0].style.display = 'inline';
        this.input[0].disabled = false;
        this.span[0].style.display = 'none';
        this.buttons[0].style.display = 'none';
        this.buttons[1].style.display = 'inline';

        // Move the id around, which changes what happens when you click the label.
        var id = this.buttons[0].id;
        this.buttons[0].removeAttribute('id');
        this.input[0].id = id;

        if (moveFocus) {
            this.input[0].focus();
        }
    };

    /**
     * Cancels editing.
     */
    EncryptedPassword.prototype.cancelEditing = function() {
        this.input[0].style.display = 'none';
        this.input[0].disabled = true;
        this.span[0].style.display = 'inline';
        this.buttons[0].style.display = 'inline';
        this.buttons[1].style.display = 'none';

        // Move the id around, which changes what happens when you click the label.
        var id = this.input[0].id;
        this.input[0].removeAttribute('id');
        this.buttons[0].id = id;
    };

    return EncryptedPassword;
});
