/*

Quite fast template engine for frontend. It use benefits of Element creation out of the DOM tree and further cloning. See modal.js for use case.

*/

GLX('GLX.ui.template',(function() {
    /**
     * Picks references recursively
     * @param {HTMLElement} node
     * @param {Object} refs
     */
    var __getRefs = function(node,refs,that)
    {
        if(node.nodeType == 1)
        {
            var _a = [];
            for(var i = 0; i < node.attributes.length; i++)
            {
                var attr = node.attributes[i];
                if(/^t-/.test(attr.name))
                {
                    _a.push(attr.name);
                    if(attr.name == 't-e')
                    {
                        var _en = attr.value;
                        $(node).on('click', function(){
                            that.trigger(_en);
                        });
                    } else {
                        if(refs[attr.value])
                        {
                            console.error('Attribute already used ' + attr.value);                      
                        }                               
                        refs[attr.value] = {
                            n : node,
                            e : attr.name.replace(/^t-/,'')
                        };
                    }
                }
            }
            var _o;
            while(_o = _a.pop())
            {                   
                node.removeAttribute(_o);
            }
            for(var _c = node.firstChild; _c != null; _c = _c.nextSibling)
            {
                __getRefs(_c,refs,that);
            }
        }
        return refs;
    };
    
    /**
     * Feeds template
     * @param {Object} object - hash to be applied
     */
    var _f = function(object)
    {
        for(var i in object)
        {
            if(this.refs[i])
            {
                var _e = this.refs[i].e;
                if(_e == 'html') _e = 'innerHTML';
                if(_e == 'text') _e = 'innerText';
                if(_e == 'value') _e = 'value';
                if(_e == 'ref' || _e == 'e') continue;
                if(_e == 'if')
                {
                    this.refs[i].n.parentNode.removeChild(this.refs[i].n);
                    continue;
                }
                if(object[i] instanceof HTMLElement)
                {
                    $(this.refs[i].n).append(object[i]);   
                } else {
                    if(_e == 'value')
                    {
                        $(this.refs[i].n).val(object[i]);
                    } else {
                        this.refs[i].n[_e] = object[i];
                    }
                }
            }
        }
    };
    
    /**
     * Creates template product
     */
    var __create = function()
    {
        var o = $({});
        o.node = this._n.cloneNode(true);
        o.refs = __getRefs(o.node,{},o);
        o.feed = _f;
        return o;
    };
    
    /**
     * Return template object
     * @param {String} __template - text template
     */
    return function(__template)
    {
        var _n;
        if(/\<tr/i.test(__template))
        {
            _n = document.createElement('tbody');               
        } else {
            _n = document.createElement('div');
        }        
        
        _n.innerHTML =__template;
        this._n = _n.firstChild;
        this.create = __create;
    };
})());
