/**
 * @copyright SIELAY.com
 *
 * Part of wrapper for project frontend logic. Written in times when Angular wasn't there yet.
 * 
 * Main use case is to add some organisation to code in the project. It never had aim to replace jQuery.
 *
 */

(function(){
   
   /**
     * Generates object in given path
     * @param {String} path
     * @param {Object|null} root
     * @return {Object} 
     */
    var provide = function(path, root, assign)
    {
        if(!root)
        {
            root = top;
        }
        var elems = path.split('.');
        
        if(!root[elems[0]])
        {
            root[elems[0]] = {};    
        }
        
        if(elems.length > 1)
        {
            return provide(path.substring(elems[0].length + 1),root[elems[0]], assign);
        } else {
            root[elems[0]] = assign;
        }
    };
    
    /**
     * Assings object to path 
     * @param {String} path
     * @param {Object} assign
     */
    top.GLX = function(path, assign)
    {
        provide(path, top, assign);
    };
    
    var __i18n = {};
    
    GLX.i18n = function(labels)
    {
        var def = $.Deferred();
        var order = [];
        
        for(var i = 0; i < labels.length; i++)
        {
            var hex = hex_sha1(labels[i]);
            if(!__i18n[hex])
            {
                order.push(labels[i]);
            }
        }
        
        if(order.length == 0)
        {
            def.resolve();
        } else {
            GLX.rpc('i18n',order).done(function(matches)
            {
                for(var key in matches)
                {
                    __i18n[key] = matches[key];
                }
                def.resolve();
            });           
        }
        return def;
    };
    
    top.i18n = function(label)
    {
        return __i18n[hex_sha1(label)];
    };
    
    /**
     * @param {String} method
     * @param {Mixed} params
     * @return {$.Deferred}
     */
    GLX.rpc = function(method, params)
    {
        var def = $.Deferred();
        $.ajax({
            url: '/rpc',
            dataType: 'json',
            method: 'post',
            data: {
                method : method,
                params: params,
                id : 1
            },
            success: function(data)
            {
                if(data && data.result)
                {
                    def.resolve(data.result);
                } else {
                    console.error(data.error);
                    def.reject(data.error);
                }
            }
        });
        return def;
    };
    
    /**
     * @param {String} url
     * @param {Mixed} params
     * @return {$.Deferred}
     */
    GLX.form = function(url, params)
    {
        var form = document.createElement('FORM');
        form.method = 'POST';
        form.action = url;
        $.each(params,function(name, value){
            var input = document.createElement('INPUT');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    };
    
    /**
     * Translate data from array of object name, value to hash map
     */
    GLX.flat = function(data)
    {
        var _o = {};
        for(var i = 0; i < data.length; i++)
        {
            _o[data[i].name] = data[i].value;
        }
        return _o;
    };
    
    /**
     * Translate hash map to array name, value 
     */
    GLX.unflat = function(data)
    {
        var _o = [];
        $.each(data, function(key, value) {
           _o.push({
                name: key,
                value : value 
           });
        });
        return _o;
    };
    
    /**
     * Checks if value is empty like in PHP
     * @param {Mixed} value
     * @return {Boolean}
     */
    GLX.empty = function(value)
    {
        if(value === null || value === undefined)
        {
            return true;
        }
        if(typeof value == 'string')
        {
            return value.replace(/^\s+/,'').replace(/\s+$/,'') == '';
        }
    };
    
    /**
     * Parse JSON to format of jquery.fancytree.js
     * @param {JSON} data
     * @return {JSON}
     */
    GLX.treeficate = function(data)
    {
        var res = [];
        for(var key in data)
        {
            var obj = {
                title : key,
                children : [],
                folder: false
            };

            if(data[key] instanceof Array)
            {
                obj.children = GLX.treeficate(data[key]);
                obj.folder = true;
            } else if(data[key] instanceof Object) {
                obj.children = GLX.treeficate(data[key]);
                obj.folder = true;
            } else {
                obj.title = obj.title + " = " + data[key];
            }

            res.push(obj);
        }
        return res;
    };
})();
