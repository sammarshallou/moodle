YUI.add('moodle-backup-handlebigforms', function(Y) {
    // Namespace for the backup.
    M.core_backup = M.core_backup || {};

    M.core_backup.handle_big_forms = function(maxinputvars) {
        // Find the main form.
        var firstsection = Y.one('fieldset#id_coursesettings');
        if (!firstsection) {
            // This is not a relevant page.
            return;
        }
        var form = firstsection.ancestor('form');
        // Add a submit handler.
        form.on("submit", function(e) {
            // Get all checkboxes and hidden fields.
            var checks = form.all('input[type=checkbox]');
            var hiddens = form.all('input[type=hidden]');

            // If there are not very many (safely less than maxinputvars), don't mess.
            if (checks.size() + hiddens.size() < maxinputvars - 100) {
                return;
            }

            // Get the values from all checkboxes and disable checkboxes to
            // prevent them sending the data.
            var value = '';
            checks.each(function(checkbox) {
                if(checkbox.get('checked') && !checkbox.get('disabled')) {
                    value += ',' + encodeURIComponent(checkbox.get('name')) +
                            "=" + encodeURIComponent(checkbox.get('value'));
                }
                checkbox.set('disabled', true);
            });

            // Get values from hidden setting fields and remove them.
            hiddens.each(function(hidden) {
                // Only do backup settings.
                if (!hidden.get('name').match(/^setting/)) {
                    return;
                }
                value += ',' + encodeURIComponent(hidden.get('name')) +
                        "=" + encodeURIComponent(hidden.get('value'));
                hidden.remove();
            });

            // Set value into hidden field.
            var hiddenfield = form.one('input[name=combinedfields]');
            hiddenfield.set('value', value.substr(1));
        });
    }
}, '@VERSION@', {'requires':['base', 'node', 'event']});
