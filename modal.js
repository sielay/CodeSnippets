/*

Modal module basing on template.js. TEMPLATE(*) directive was used by JS packager. It was bit before Grunt.

*/

GLX('GLX.ui.modal',(function()
{
    var _template = new GLX.ui.template('/* TEMPLATE(modal.template.htm) */');
    
    var __function = function()
    {
        
    };
    
    var  __addButtons = function(buttons,template)    
    {
         if(buttons && buttons.length)
         {
             var d = document.createElement('DIV');
             for(var i = 0; i < buttons.length; i++)
             {
                 var btn = document.createElement('BUTTON');
                 btn.innerText = buttons[i].label;
                 $(btn).addClass('btn');
                 if(buttons[i].className) {
                     $(btn).addClass(buttons[i].className);
                 }
                 if(buttons[i].click) {
                     $(btn).click(buttons[i].click);
                 }
                 d.appendChild(btn);
             }
             template.feed({
                 buttons: d
             });
         }
    };
    
    /**
     * @param {Object} data
     * @param {Object} data.title
     * @param {Object} data.content
     * @param {Object} data.buttons
     */
    __function.popup = function(data)
    {
        var popup = _template.create();
        var buttons = data.buttons;
        delete data.buttons;
        __addButtons(buttons,popup);
        popup.feed(data);        
        $(document.body).prepend(popup.node);
        $(popup.node).modal('show');
        return popup.node;
    };
    
    /**
     * @param {Object} data
     * @param {Object} data.title
     * @param {Object} data.content
     */
    __function.alert = function(title, body)
    {
        var node = __function.popup({
            title : title,
            content : body,
            buttons : [{
                'label' : 'OK',
                'click' : function() {
                    $(node).modal('hide');
                }
            }] 
        });
    };
    
    /**
     * @param {Object} data
     * @param {Object} data.title
     * @param {Object} data.content
     */
    __function.confirm = function(title, body)
    {
        var _def = $.Deferred();
        var node = __function.popup({
            title : title,
            content : body,
            buttons : [{
                'label' : 'Yes',
                'click' : function() {                    
                    $(node).modal('hide');
                    _def.resolve();
                }
            },{
                'label' : 'No',
                'click' : function() {                    
                    $(node).modal('hide');
                    _def.reject();
                }
            }] 
        });
        return _def;
    };
    
    return __function;
})());
