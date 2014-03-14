M.availability_date = M.availability_date || {};
M.availability_date.form = Y.Object(M.core_availability.plugin); 

M.availability_date.form.init = function(component, html, defaultTime) {
    this.initBase(component);
    this.html = html;
    this.defaultTime = defaultTime;
};

M.availability_date.form.getNode = function(json) {
    var strings = M.str.availability_date;
    var html = strings.direction_before + ' <span class="availability-group">' +
            '<select name="direction">' +
            '<option value="&gt;=">' + strings.direction_from + '</option>' +
            '<option value="&lt;">' + strings.direction_until + '</option>' +
            '</select></span> ' + this.html;
    var node = Y.Node.create('<span>' + html + '</span>');

    // Set initial value if non-default.
    if (json.t !== undefined) {
        node.setData('time', json.t);
        // Disable everything.
        node.all('select:not([name=direction])').each(function(select) {
            select.set('disabled', true);
        });

        var url = M.cfg.wwwroot + '/availability/condition/date/ajax.php?action=fromtime' +
            '&time=' + json.t;
        Y.io(url, { on : {
            success : function(id, response, args) {
                var fields = Y.JSON.parse(response.responseText);
                for (var field in fields) {
                    var select = node.one('select[name=' + field + ']');
                    select.set('value', fields[field]);
                    select.set('disabled', false);
                }
            },
            failure : function(id, response, args) {
                window.alert(M.str.availability_date.ajaxerror);
            }
        }});
    } else {
        // Set default time that corresponds to the HTML selectors.
        node.setData('time', this.defaultTime);
    }

    // Add event handlers.
    node.one('select[name=direction]').on('valuechange', function() {
        // For the direction, just update the form fields.
        M.core_availability.form.update();
    });
    node.all('select:not([name=direction])').each(function(select) {
        select.on('change', function() {
            M.availability_date.form.updateTime(node);
        })
    });
    return node;
};

M.availability_date.form.updateTime = function(node) {
    // After a change to the date/time we need to recompute the
    // actual time using AJAX because it depends on the user's  
    // time zone and calendar options.
    var url = M.cfg.wwwroot + '/availability/condition/date/ajax.php?action=totime' +
        '&year=' + node.one('select[name=year]').get('value') +
        '&month=' + node.one('select[name=month]').get('value') +
        '&day=' + node.one('select[name=day]').get('value') +
        '&hour=' + node.one('select[name=hour]').get('value') +
        '&minute=' + node.one('select[name=minute]').get('value');
    Y.io(url, { on : {
        success : function(id, response, args) {
            node.setData('time', response.responseText);
            M.core_availability.form.update();
        },
        failure : function(id, response, args) {
            window.alert(M.str.availability_date.ajaxerror);
        }
    }});
}

M.availability_date.form.fillValue = function(value, node) {
    value.d = node.one('select[name=direction]').get('value');
    value.t = parseInt(node.getData('time'));
};
