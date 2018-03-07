/**
 * @namespace M.mod_hsuforum
 * @author Mark Nielsen
 */
M.mod_hsuforum = M.mod_hsuforum || {};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_flags = function(Y) {
    if (Y.all('.mod_hsuforum_posts_container').isEmpty()) {
        return;
    }
    // We bind to document otherwise screen readers read everything as clickable.
    Y.delegate('click', function(e) {
        var link = e.currentTarget;
        e.preventDefault();
        e.stopPropagation();

        M.mod_hsuforum.io(Y, link.get('href'), function() {
            link.toggleClass('hsuforum_flag_active');

            var span = link.one('span');
            var oldtitle = link.getAttribute('title');
            var newTitle = link.getData('title');
            span.set('text', newTitle);
            link.setAttribute('title', newTitle)
                .setData('title', oldtitle);

        });
    }, document, 'a.hsuforum_flag');
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_treeview = function(Y, id, url, nodes) {
    var tree = new Y.YUI2.widget.TreeView(id, nodes);

    // This allows links to be clicked
    tree.subscribe('clickEvent', function() {
        return false;
    });
    tree.setDynamicLoad(function(node, fnLoadComplete) {
        var dicussionid = node.data.id;
        if (!dicussionid) {
            fnLoadComplete();
            return;
        }
        M.mod_hsuforum.io(Y, url + '&discussionid=' + encodeURI(dicussionid), function(data) {
            var addNodes = function(nodeList, nodeListParent) {
                var children;
                for (var i = 0, len = nodeList.length; i < len; i++) {
                    children = nodeList[i].children;
                    nodeList[i].children = [];
                    var parent = new Y.YUI2.widget.HTMLNode(nodeList[i], nodeListParent, false);
                    addNodes(children, parent);
                }
            };
            addNodes(data, node);
            fnLoadComplete();
        });
    });
    tree.render();

    var wrapper = Y.one('#'+id).ancestor('.hsuforum_treeview_wrapper');
    wrapper.one('.hsuforum_expandall').on('click', function(e) {
        e.preventDefault();
        var expandAll = function(node) {
            if (!node.isRoot() && !node.data.doExpandAll) {
                return;
            }
            for (var i = 0; i < node.children.length; i++) {
                var child = node.children[i];
                child.data.doExpandAll = true;
                if (child.expanded) {
                    child.collapse();
                }
                child.expand();
            }
            node.data.doExpandAll = false;
        };
        tree.subscribe('expandComplete', expandAll);
        tree.collapseAll();
        expandAll(tree.getRoot());
    });

    wrapper.one('.hsuforum_collapseall').on('click', function(e) {
        e.preventDefault();
        tree.collapseAll();
    });
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_subscribe = function(Y) {
    if (Y.all('.mod_hsuforum_posts_container').isEmpty()) {
        return;
    }
    // We bind to document otherwise screen readers read everything as clickable.
    Y.delegate('click', function(e) {
        var link = e.currentTarget;
        e.preventDefault();
        e.stopPropagation();

        M.mod_hsuforum.io(Y, link.get('href'), function() {
            link.toggleClass('subscribed');
            var label, pix,
                name = link.getData('name'),
                span = link.one('span'),
                img = link.one('img');
            if (link.hasClass('subscribed')) {
                label = M.util.get_string('subscribedtodiscussionx', 'hsuforum', name);
                pix = 'check-yes';
            } else {
                label = M.util.get_string('notsubscribedtodiscussionx', 'hsuforum', name);
                pix = 'check-no';
            }
            span.set('text', label);
            link.setAttribute('title', label);
            img.setAttribute('src', M.util.image_url(pix, 'hsuforum'));
        });
    }, document, 'a.hsuforum_discussion_subscribe');
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_post_in_context = function(Y) {
    var nodes = Y.all('.mod_hsuforum_posts_container');
    if (nodes) {
        nodes.each(function(node) {
            node.delegate('click', function(e) {
                var link = e.target;

                e.preventDefault();
                e.stopPropagation();

                M.mod_hsuforum.io(Y, link.get('href'), function(data) {
                    var panelNode = Y.Node.create('<div class="hsuforum_postincontext_dialog" />');
                    var panel = new Y.Panel({
                        contentBox: panelNode,
                        bodyContent: data.html,
                        centered: true,
                        height: Math.round(node.get('winHeight') * .75),
                        width: Math.round(node.get('winWidth') * .75),
                        zIndex: 1000,
                        modal: true,
                        visible: true,
                        render: true,
                        plugins: [Y.Plugin.Drag],
                        hideOn: [ { eventName: 'clickoutside' } ]
                    });
                    panel.dd.addHandle('.hsuforum_postincontext_dialog .yui3-widget-hd');
                    panel.after('visibleChange', function (e) {
                        if (!e.newVal && e.prevVal) {
                            panel.destroy();
                            panelNode.remove(true);
                        }
                    });
                });
            }, 'a.hsuforum_viewincontext');
        });
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_nested = function(Y) {
    var nodes = Y.all('.mod_hsuforum_posts_container');
    if (nodes) {
        nodes.each(function(node) {
            node.delegate('click', function(e) {
                // Ignore when images or links are clicked
                if (e.target.test('img') || e.target.test('a')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();

                var parent = e.target.ancestor('.hsuforum_nested_wrapper');
                var body = parent.one('.hsuforum_nested_body');
                var header = parent.one('.hsuforum_nested_header');

                M.mod_hsuforum.toggle_expanded(Y, body, function() {
                    parent.toggleClass('expanded');
                    if (!parent.hasClass('expanded')) {
                        header.set('title', M.str.hsuforum.clicktoexpand);
                    } else {
                        header.set('title', M.str.hsuforum.clicktocollapse);
                    }
                    M.mod_hsuforum.markread(Y, header);

                    var posts = body.one('.hsuforum_nested_posts');
                    if (posts) {
                        M.mod_hsuforum.load_posts(Y, body, posts);
                    }
                });
            }, '.hsuforum_nested_header');

            node.delegate('click', function(e) {
                var a;
                if (e.target.test('a')) {
                    a = e.target;
                } else {
                    a = e.target.ancestor('a');
                }
                if (a) {
                    e.preventDefault();
                    openpopup(e, {
                        url: a.get('href') + '&popup=1',
                        name: 'ratings',
                        options: "height=400,width=600,top=0,left=0,menubar=0,location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent"
                    });
                }
            }, '.forum-post-rating');
        });
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.markread = function(Y, node) {
    if (node.hasClass('unread') && node.hasAttribute('unreadurl')) {
        M.mod_hsuforum.io(Y, node.getAttribute('unreadurl'),
            function() {     // Success
                node.replaceClass('unread', 'read');

                var unread = node.ancestor('.hsuforum_nested_discussion').one('.unreadposts .unread');
                if (unread) {
                    var count = parseInt(unread.getContent()) - 1;
                    unread.setContent(count);

                    if (count == 0) {
                        unread.replaceClass('unread', 'read');
                    }
                }
            }, function() {  // Failure
                node.removeAttribute('unreadurl');
            }
        );
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.toggle_expanded = function(Y, node, callback) {
    var anim = new Y.Anim({
        node: node,
        duration: .3,
        from: { height: 0 },
        to: {
            height: function(node) {
                return node.get('scrollHeight');
            }
        },
        easing: Y.Easing.easeOut
    });

    anim.set('reverse', node.hasClass('expanded'));
    anim.on('end', function() {
        node.toggleClass('expanded');

        // Allow it to grow as children are expanded
        if (node.hasClass('expanded')) {
            node.setStyle('height', 'auto');
        }
        if (callback) {
            callback()
        }
    });
    anim.run();
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.load_posts = function(Y, body, node) {
    if (!node.hasClass('postsloaded')) {
        node.addClass('postsloaded');

        M.mod_hsuforum.io(Y, node.getAttribute('postsurl'), function(data) {
            // Freeze the height so we can animate it
            body.setStyle('height', body.getComputedStyle('height'));

            node.setContent(data.html);
            M.mod_hsuforum.init_rating(Y, node);

            var anim = new Y.Anim({
                node: body,
                duration: .3,
                from: { height: body.get('clientHeight') },
                to: { height: body.get('scrollHeight') },
                easing: Y.Easing.easeOut
            });
            anim.on('end', function() {
                body.setStyle('height', 'auto');
            });
            anim.run();
        });
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_rating = function(Y, node) {
    M.core_rating.Y = Y;
    node.all('select.postratingmenu').each(M.core_rating.attach_rating_events, M.core_rating);
    node.all('input.postratingmenusubmit').setStyle('display', 'none');
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.io = function(Y, url, successCallback, failureCallback) {
    Y.io(url, {
        on: {
            success: function(id, o) {
                M.mod_hsuforum.io_success_handler(Y, o, successCallback);
            },
            failure: function() {
                M.mod_hsuforum.io_failure_handler(Y, failureCallback);
            }
        }
    });
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.io_success_handler = function(Y, response, callback) {
    var data = {};
    if (response.responseText) {
        try {
            data = Y.JSON.parse(response.responseText);
            if (data.error) {
                alert(data.error);
                if (window.console !== undefined && console.error !== undefined) {
                    console.error(data.error);
                    console.error(data.stacktrace);
                    console.error(data.debuginfo);
                }
                return;
            }
        } catch (ex) {
            alert(M.str.hsuforum.jsondecodeerror);
            return;
        }
    }
    if (callback) {
        callback(data);
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.io_failure_handler = function(Y, callback) {
    alert(M.str.hsuforum.ajaxrequesterror);

    if (callback) {
        callback();
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_modform = function(Y, HSUFORUM_GRADETYPE_MANUAL) {
    var gradetype = Y.one('.path-mod-hsuforum select[name="gradetype"]');

    if (gradetype) {
        var warning = Y.Node.create('<span id="gradetype_warning" class="hidden">' + M.str.hsuforum.manualwarning + '</span>');
        gradetype.get('parentNode').appendChild(warning);

        var updateMessage = function() {
            if (gradetype.get('value') == HSUFORUM_GRADETYPE_MANUAL) {
                warning.removeClass('hidden');
            } else {
                warning.addClass('hidden');
            }
        };

        // Init the view
        updateMessage();

        // Update view on change
        gradetype.on('change', function() {
            updateMessage();
        });
    }
};