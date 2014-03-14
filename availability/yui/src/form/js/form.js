/**
 * Provides interface for users to edit availability settings on the
 * module/section editing form.
 *
 * @module moodle-core_availability-form
 */

M.core_availability = M.core_availability || {};
M.core_availability.form = {
    /**
     * Object containing installed plugins. They are indexed by plugin name.
     *
     * @property plugins
     * @type {Object}
     */
    plugins : {},

    /**
     * Availability field (textarea).
     *
     * @property field
     * @type Node
     */
    field : null,

    /**
     * Main div that replaces the availability field.
     *
     * @property mainDiv
     * @type Node
     */
    mainDiv : null,

    /**
     * Object that represents the root of the tree.
     *
     * @property rootList
     * @type M.core_availability.List
     */
    rootList : null,

    /**
     * Counter used when creating anything that needs an id.
     *
     * @property idCounter
     * @type {Number}
     */
    idCounter : 0,

    /**
     * Called to initialise the system when the page loads. This is called
     * after plugins have been added.
     *
     * @method init
     */
    init : function() {
        // Get the availability field, hide it, and replace with the main div.
        this.field = Y.one('#id_availabilityconditionsjson');
        this.field.setAttribute('aria-hidden', 'true');
        this.mainDiv = Y.Node.create('<div class="availability_field"></div>');
        this.field.insert(this.mainDiv, 'after');

        // Get top-level tree as JSON.
        var value = this.field.get('value');
        if (value === '') {
            this.rootList = new M.core_availability.List(null, true);
        } else {
            var data = Y.JSON.parse(value);
            this.rootList = new M.core_availability.List(data, true);
        }
        this.mainDiv.appendChild(this.rootList.node);

        // Mark main area as dynamically updated.
        this.mainDiv.setAttribute('aria-live', 'polite');
    },

    /**
     * Called at any time to update the hidden field value.
     *
     * This should be called whenever any value changes in the form settings.
     *
     *  @method update
     */
    update : function() {
        // Convert tree to value.
        var jsValue = this.rootList.getValue();

        // Store any errors (for form reporting) in 'errors' value if present.
        var errors = [];
        this.rootList.fillErrors(errors);
        if (errors.length !== 0) {
            jsValue.errors = errors;
        }

        // Set into hidden form field, JS-encoded.
        this.field.set('value', Y.JSON.stringify(jsValue));
    }
};

M.core_availability.plugin = {
    init : function(component) {
        this.initBase(component);
    },
    initBase : function(component) {
        var name = component.replace(/^availability_/, '');
        M.core_availability.form.plugins[name] = this;
    },
    getNode : function() {
        // Parameters: json.
        throw 'getNode not implemented';
    },
    fillValue : function() {
        // Parameters: value, node.
        throw 'fillValue not implemented';
    },
    fillErrors : function() {
        // Parameters: errors, node.
        // Default does nothing.
    }
};


/**
 * Maintains a list of children and settings for how they are combined.
 *
 * @class M.core_availability.List
 * @constructor
 * @param {Object} json Decoded JSON value
 * @param {Boolean} [false] root True if this is root level list
 */
M.core_availability.List = function(json, root) {
    if (root !== undefined) {
        this.root = root;
    }
    // Create DIV structure (without kids).
    this.node = Y.Node.create('<div class="availability_list">' +
        '<div class="availability_header">' +
        M.str.availability.listheader_sign_before +
        ' <select class="availability_neg"><option value="">' +
        M.str.availability.listheader_sign_pos + '</option>' +
        '<option value="!">' + M.str.availability.listheader_sign_neg + '</option></select> ' +
        '<span class="availability_single">' + M.str.availability.listheader_single + '</span>' +
        '<span class="availability_multi">' + M.str.availability.listheader_multi_before +
        ' <select class="availability_op"><option value="&">' +
        M.str.availability.listheader_multi_and + '</option>' +
        '<option value="|">' + M.str.availability.listheader_multi_or + '</option></select> ' +
        M.str.availability.listheader_multi_after + '</span></div>' +
        '<div class="availability_children"></div>' +
        '<div class="availability_none">' + M.str.moodle.none + '</div>' +
        '<div class="availability_button"></div></div>');

    if (root) {
        // If it's the root, add an eye icon before the text.
        var shown = true;
        if (json && json.show !== undefined) {
            shown = json.show;
        }
        this.eyeIcon = new M.core_availability.EyeIcon(false, shown);
        this.node.one('.availability_header').get('firstChild').insert(
            this.eyeIcon.span, 'before');
    } else {
        // If it's not the root, add a delete button to the 'none' option.
        console.log('TODO');
    }

    // Create the button and add it.
    var button = Y.Node.create('<button type="button" class="btn btn-default">' +
        M.str.availability.addrestriction + '</button>');
    button.on("click", function() { this.clickAdd(); }, this);
    this.node.one('div.availability_button').appendChild(button);

    if (json) {
        // Set operator from JSON data.
        switch (json.op) {
            case '&' :
            case '|' :
                this.node.one('.availability_neg').set('value', '');
                break;
            case '!&' :
            case '!|' :
                this.node.one('.availability_neg').set('value', '!');
                break;
        }
        switch (json.op) {
            case '&' :
            case '!&' :
                this.node.one('.availability_op').set('value', '&');
                break;
            case '|' :
            case '!|' :
                this.node.one('.availability_op').set('value', '|');
                break;
        }

        // Construct children.
        for(var i=0; i<json.c.length; i++) {
            var child = json.c[i];
            if (this.root && json && json.showc !== undefined) {
                child.showc = json.showc[i];
            }
            var newItem;
            if (child.type !== undefined) {
                // Plugin type.
                newItem = new M.core_availability.Item(child, this.root);
            } else {
                // List type.
                newItem = new M.core_availability.List(child);
            }
            this.children.push(newItem);
            this.node.one('> .availability_children').appendChild(
                newItem.node);
        }

        if (this.root) {
        }
    }

    // Add update listeners to the dropdowns.
    this.node.one('.availability_neg').on('change', function() {
        // Update hidden field and HTML.
        M.core_availability.form.update();
        this.updateHtml();
    }, this);
    this.node.one('.availability_op').on('change', function() {
        // Update hidden field.
        M.core_availability.form.update();
        this.updateHtml();
    }, this);

    // Update HTML to hide unnecessary parts.
    this.updateHtml();
};

M.core_availability.List.prototype.isIndividualShowIcons = function() {
    if (!this.root) {
        throw 'Can only call this on root list';
    }
    var neg = this.node.one('.availability_neg').get('value') === '!';
    var isor = this.node.one('.availability_op').get('value') === '|';
    return (!neg && !isor) || (neg && isor);
};

M.core_availability.List.prototype.updateHtml = function() {
    // Control children appearing or not appearing.
    if (this.children.length > 0) {
        this.node.one('> .availability_children').removeAttribute('aria-hidden');
        this.node.one('> .availability_none').setAttribute('aria-hidden', 'true');
        this.node.one('> .availability_header').removeAttribute('aria-hidden');
        if (this.children.length > 1) {
            this.node.one('.availability_single').setAttribute('aria-hidden', 'true');
            this.node.one('.availability_multi').removeAttribute('aria-hidden');
        } else {
            this.node.one('.availability_single').removeAttribute('aria-hidden');
            this.node.one('.availability_multi').setAttribute('aria-hidden', 'true');
        }
    } else {
        this.node.one('> .availability_children').setAttribute('aria-hidden', 'true');
        this.node.one('> .availability_none').removeAttribute('aria-hidden');
        this.node.one('> .availability_header').setAttribute('aria-hidden', 'true');
    }

    // For root list, control eye icons.
    if (this.root) {
        var showEyes = this.isIndividualShowIcons();

        // Individual icons.
        for (var i = 0; i < this.children.length; i++) {
            var child = this.children[i];
            if (showEyes) {
                child.eyeIcon.span.removeAttribute('aria-hidden');
            } else {
                child.eyeIcon.span.setAttribute('aria-hidden', 'true');
            }
        }

        // Single icon is the inverse.
        if (showEyes) {
            this.eyeIcon.span.setAttribute('aria-hidden', 'true');
        } else {
            this.eyeIcon.span.removeAttribute('aria-hidden');
        }
    }

    // TODO
};

M.core_availability.List.prototype.deleteDescendant = function(descendant) {
    // Loop through children.
    for (var i = 0; i < this.children.length; i++) {
        var child = this.children[i];
        if (child === descendant) {
            // Remove child and stop.
            this.children.splice(i, 1);
            this.node.one('> .availability_children').removeChild(
                child.node);
            M.core_availability.form.update();
            this.updateHtml();
            return true;
        } else if (child instanceof M.core_availability.List) {
            // Recursive call.
            child.deleteDescendant(descendant);
        }
    }
    return false;
};

M.core_availability.List.prototype.clickAdd = function() {
    var content = Y.Node.create('<div>' +
            '<ul class="list-unstyled"></ul>' +
            '<div class="availability-buttons">' +
            '<button type="button" class="btn btn-default">' + M.str.moodle.cancel +
            '</button></div></div>');
    var cancel = content.one('button');

    var dialogRef = { dialog: null };
    var ul = content.one('ul');
    for (var type in M.core_availability.form.plugins) {
        // Add entry for plugin.
        var li = Y.Node.create('<li></li>');
        var id = 'availability_addrestriction_' + type;
        var pluginStrings = M.str['availability_' + type];
        var button = Y.Node.create('<button type="button" class="btn btn-default"' +
                'id="' + id + '">' + pluginStrings.title + '</button>');
        button.on('click', this.getAddHandler(type, dialogRef), this);
        li.appendChild(button);
        var label = Y.Node.create('<label for="' + id + '">' +
                pluginStrings.description + '</label>');
        li.appendChild(label);
        ul.appendChild(li);
    }

    var config = {
        headerContent : M.str.availability.addrestriction,
        bodyContent : content,
        additionalBaseClass : 'availability-dialogue',
        draggable : true,
        modal : true
    };
    dialogRef.dialog = new M.core.dialogue(config);
    dialogRef.dialog.show();
    cancel.on('click', function() { dialogRef.dialog.hide(); });
};

M.core_availability.List.prototype.getAddHandler = function(type, dialogRef) {
    return function() {
        // Add child to end of this list (default JSON).
        newItem = new M.core_availability.Item({ type: type, creating: true }, this.root);
        this.children.push(newItem);
        this.node.one('> .availability_children').appendChild(
            newItem.node);
        M.core_availability.form.update();
        this.updateHtml();
        dialogRef.dialog.hide();
    };
};

M.core_availability.List.prototype.getValue = function() {
    // Work out operator from selects.
    var value = {};
    value.op = this.node.one('.availability_neg').get('value') +
            this.node.one('.availability_op').get('value')
    
    // Work out children from list.
    value.c = [];
    for (var i=0; i<this.children.length; i++) {
        value.c.push(this.children[i].getValue());
    }

    // Work out show/showc for root level.
    if (this.root) {
        if (this.isIndividualShowIcons()) {
            value.showc = [];
            for (var i=0; i<this.children.length; i++) {
                value.showc.push(!this.children[i].eyeIcon.isHidden());
            }
        } else {
            value.show = !this.eyeIcon.isHidden();
        }
    }
    return value;
};

M.core_availability.List.prototype.fillErrors = function(errors) {
    // List with no items is an error (except root).
    if (this.children.length === 0 && !this.root) {
        errors.push('list_nochildren');
    }
    // Pass to children.
    for (var i=0; i<this.children.length; i++) {
        this.children[i].fillErrors(errors);
    }
};

M.core_availability.List.prototype.eyeIcon = null;

/**
 * True if list is special root level list.
 *
 * @property root
 * @type {Boolean}
 */
M.core_availability.List.prototype.root = false;

/**
 * Child Lists or Items
 *
 * @property children
 * @type {mixed[]}
 */
M.core_availability.List.prototype.children = [];

/**
 * HTML node for list.
 *
 * @property node
 * @type Node
 */
M.core_availability.List.prototype.node = null;

/**
 * Represents a single condition.
 */
M.core_availability.Item = function(json, root) {
    this.pluginType = json.type;
    if (M.core_availability.form.plugins[json.type] === undefined) {
        // Handle undefined plugins.
        this.plugin = null;
        this.pluginNode = Y.Node.create('<div class="availability_warning">' +
                M.str.availability.addrestriction + '</div>');
    } else {
        // Plugin is known.
        this.plugin = M.core_availability.form.plugins[json.type];
        this.pluginNode = this.plugin.getNode(json);
    }

    this.node = Y.Node.create('<div class="availability-item">');

    // Add eye icon if required. This icon is added for root items, but may be
    // hidden depending on the selected list operator.
    if (root) {
        var shown = true;
        if(json.showc !== undefined) {
            shown = json.showc;
        }
        this.eyeIcon = new M.core_availability.EyeIcon(true, shown);
        this.node.appendChild(this.eyeIcon.span);
    }

    // Add plugin controls.
    this.pluginNode.addClass('availability-plugincontrols');
    this.node.appendChild(this.pluginNode);

    // Add delete button for node.
    var span = Y.Node.create('<span class="availability-delete">');
    this.node.appendChild(span);
    var deleteButton = Y.Node.create('<img src="' +
            M.cfg.wwwroot + '/theme/image.php/' + M.cfg.theme + '/core/' + M.cfg.themerev +
            '/t/delete" alt="' +
            M.str.moodle['delete'] + '" title="' + M.str.moodle['delete'] +
            '" tabindex="0" role="button"/>');
    span.appendChild(deleteButton);
    deleteButton.on("click", function() {
        M.core_availability.form.rootList.deleteDescendant(this);
    }, this);
};

M.core_availability.Item.prototype.getValue = function() {
    value = { 'type' : this.pluginType };
    if (this.plugin) {
        this.plugin.fillValue(value, this.pluginNode);
    }
    return value;
};

M.core_availability.Item.prototype.fillErrors = function(errors) {
    if (this.plugin) {
        // Pass to plugin.
        this.plugin.fillErrors(errors, this.pluginNode);
    } else {
        // Unknown plugin is an error
        errors.push('item_unknowntype');
    }
};

/**
 * Name of plugin.
 *
 * @property pluginType
 * @type {String}
 */
M.core_availability.Item.prototype.pluginType = null;

/**
 * Object representing plugin form controls.
 *
 * @property plugin
 * @type {Object}
 */
M.core_availability.Item.prototype.plugin = null;

/**
 * Eye icon for item.
 *
 * @property eyeIcon
 * @type M.core_availability.EyeIcon
 */
M.core_availability.Item.prototype.eyeIcon = null;

/**
 * HTML node for item.
 *
 * @property node
 * @type Node
 */
M.core_availability.Item.prototype.node = null;

/**
 * Inner part of node that is owned by plugin.
 *
 * @property pluginNode
 * @type Node
 */
M.core_availability.Item.prototype.pluginNode = null;

M.core_availability.EyeIcon = function(individual, shown) {
    this.individual = individual;
    this.span = Y.Node.create('<span class="availability-eye">');
    var iconBase = M.cfg.wwwroot + '/theme/image.php/' + M.cfg.theme + '/core/' + M.cfg.themerev;
    var hideButton = Y.Node.create('<img tabindex="0" role="button"/>');

    // Set up button text and icon.
    var suffix = individual ? '_individual' : '_all';
    var setHidden = function() {
        hideButton.set('src', iconBase + '/t/show');
        hideButton.set('alt', M.str.availability['hidden' + suffix]);
        hideButton.set('title', M.str.availability['hidden' + suffix] + ' \u2022 ' +
                M.str.availability.show_verb);
    };
    var setShown = function() {
        hideButton.set('src', iconBase + '/t/hide');
        hideButton.set('alt', M.str.availability['shown' + suffix]);
        hideButton.set('title', M.str.availability['shown' + suffix] + ' \u2022 ' +
                M.str.availability.hide_verb);
    };
    if(shown) {
        setShown();
    } else {
        setHidden();
    }

    // Update when button is clicked.
    this.span.appendChild(hideButton);
    hideButton.on('click', function() {
        if (this.isHidden()) {
            setShown();
        } else {
            setHidden();
        }
        M.core_availability.form.update();
    }, this);
};

M.core_availability.EyeIcon.prototype.individual = false;

M.core_availability.EyeIcon.prototype.span = null;

M.core_availability.EyeIcon.prototype.isHidden = function() {
    var suffix = this.individual ? '_individual' : '_all';
    var compare = M.str.availability['hidden' + suffix];
    return this.span.one('img').get('alt') === compare;
};
