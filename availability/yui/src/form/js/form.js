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
        this.field = Y.one('#id_availability');
        //this.field.setAttribute('aria-hidden', 'true');
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
    getNode : function(json) {
        throw 'getNode not implemented';
    },
    fillValue : function(value, node) {
        throw 'fillValue not implemented';
    },
    fillErrors : function(errors, node) {
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

    // If it's not the root, add a delete button to the 'none' option.
    if (!root) {
        // TODO.
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
            var newItem;
            if (child.type !== undefined) {
                // Plugin type.
                newItem = new M.core_availability.Item(child);
            } else {
                // List type.
                newItem = new M.core_availability.List(child);
            }
            this.children.push(newItem);
            this.node.one('> .availability_children').appendChild(
                newItem.node);
        }

        if (this.root) {
            // TODO Do something about show/showc
            console.log('TODO');
        }
    }

    // Add update listeners to the dropdowns.
    this.node.one('.availability_neg').on('valuechange', function() {
        // TODO
        // Update hidden field.
        M.core_availability.form.update();
    }, this);
    this.node.one('.availability_op').on('valuechange', function() {
        // TODO
        // Update hidden field.
        M.core_availability.form.update();
    }, this);

    // Update HTML to hide unnecessary parts.
    this.updateHtml();
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

    // For root list,
    // TODO
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
        newItem = new M.core_availability.Item({ type: type, creating: true });
        this.children.push(newItem);
        this.node.one('> .availability_children').appendChild(
            newItem.node);
        M.core_availability.form.update();
        this.updateHtml();
        dialogRef.dialog.hide();
    };
};

M.core_availability.List.prototype.getValue = function() {
    // Work out JSON structure from current options.
    var value = {
        op: this.node.one('.availability_neg').get('value') +
            this.node.one('.availability_op').get('value')
    };
    // TODO Handle show/showc.
    value.c = [];
    for (var i=0; i<this.children.length; i++) {
        value.c.push(this.children[i].getValue());
    }
    return value;
};

M.core_availability.List.prototype.fillErrors = function(errors) {
    // List with no items is an error.
    if (this.children.length === 0) {
        errors.push('list_nochildren');
    }
    // Pass to children.
    for (var i=0; i<this.children.length; i++) {
        this.children[i].fillErrors(errors);
    }
};

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
M.core_availability.Item = function(json) {
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
    this.node.appendChild(this.pluginNode);

    // TODO Add a delete button to the node (prob some outer structure).
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
