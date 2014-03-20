M.availability_completion = M.availability_completion || {};
M.availability_completion.form = Y.Object(M.core_availability.plugin);

M.availability_completion.form.init = function(component, cms) {
    this.initBase(component);
    this.cms = cms;
};

M.availability_completion.form.getNode = function(json) {
    // Create HTML structure.
    var strings = M.str.availability_completion;
    var html = strings.title + ' <span class="availability-group">' +
            '<select name="cm">';
    for (var id in this.cms) {
        // Use only the numeric part of the id as value. The string has already
        // been escaped using format_string.
        html += '<option value="' + id.substr(2) + '">' + this.cms[id] + '</option>';
    }
    html += '</select><select name="e">' +
            '<option value="1">' + strings.option_complete + '</option>' +
            '<option value="0">' + strings.option_incomplete + '</option>' +
            '<option value="2">' + strings.option_pass + '</option>' +
            '<option value="3">' + strings.option_fail + '</option>' +
            '</select></span>';
    var node = Y.Node.create('<span>' + html + '</span>');

    // Set initial values.
    if (json.cm !== undefined && this.cms['cm' + json.cm] !== undefined) {
        node.one('select[name=cm]').set('value', json.cm);
    }
    if (json.e !== undefined) {
        node.one('select[name=e]').set('value', json.e);
    }

    // Add event handlers.
    var updateForm = function() {
        // For the direction, just update the form fields.
        M.core_availability.form.update();
    };
    node.one('select[name=cm]').on('change', updateForm);
    node.one('select[name=e]').on('change', updateForm);

    return node;
};

M.availability_completion.form.fillValue = function(value, node) {
    value.cm = parseInt(node.one('select[name=cm]').get('value'));
    value.e = parseInt(node.one('select[name=e]').get('value'));
};
