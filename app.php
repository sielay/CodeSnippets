<?php
/**
 * @copyright SIELAY.com
 *
 * This snippet is from own micro framework that allowed it prototype fast. It was created in time composer wasn't yet used
 * so much, neither namespaces. That's why imports are so nasty over here.
 *
 */
include_once(dirname(__FILE__).'/utils.php'); 
include_once(dirname(__FILE__).'/configuration_factory.php');
include_once(dirname(__FILE__).'/nosql.php');
include_once(dirname(__FILE__).'/../model/user.php');
include_once(dirname(__FILE__).'/../model/translation.php');
include_once(dirname(__FILE__).'/../model/label.php');
require dirname(__FILE__).'/../libs/facebook/facebook.php';
include_once(dirname(__FILE__).'/../model/dictionary.php');
include_once(dirname(__FILE__).'/../model/cache.php');
include_once(dirname(__FILE__).'/rpc.php');
include_once(dirname(__FILE__).'/export.php');
include_once(dirname(__FILE__).'/maintainance.php');

/**
 * Main logic class of Galactics Framework
 */
class App
{
    /**
     * Starts application
     */
    public static function run()
    {
        global $leaveMeACopyPlease;
        $leaveMeACopyPlease = $_POST;
        $_POST = safeCrawl($_POST);
        $_GET = safeCrawl($_GET);
        self::setup();
        self::route();
    }
    
    /**
     * Process resource request
     */
    public static function resource()
    {
        self::negotiateHeaders();
        self::setup();
        self::loadResource();
    }   
    
    /**
     * Requires user session
     * @param {String} $perms every next argument represent required permission compared with OR
     * @return {Boolean} on success or if it's runned on localhost
     * On fail redirects
     */
    public static final function requireUser()
    {
        $perms = func_get_args();
        if(!App::$user)
        {            
            header('Location: '.LOGIN_URI); // TODO: need support in new version
            die();
        }
        if(count($perms) > 0)
        {
            foreach($perms as $perm)
            {      
                if(App::$user->hasPerm($perm))
                {
                    return;
                }
            }
            header('Location: /signin'); // TODO: need support in new version
            die();
        }
    }
    
    /**
     * Redirect to local url
     * @param {String} $url to redirect att
     * @param {Boolean} $https to be used
     */
    public static final function redirect($url,$https=false)
    {
        $url = ltrim($url, '/');
        if(defined('URL_PREFIX'))
        {
            $url = constant('URL_PREFIX') . $url; 
        }
        $url = '/'.$url;        
        header('Location: '.$url);
        die();
    }
    
    /**
     * Session set
     * @param {String} $name
     * @param {Mixed} $value
     */
    public static final function sessionSet($name, $value)
    {
         $_SESSION[SESSION_KEY]->{$name} = $value;
    }
    
    /**
     * Session get
     * @param {String} $name
     * @return {Mixed}
     */
    public static final function sessionGet($name)
    {
         return $_SESSION[SESSION_KEY]->{$name};
    }
    
    /**
     * Process template
     * @param {String} $name of template
     * @param {Boolean} $frame if frame should be loaded - default true
     * @param {Boolean} $shared if should use framework template - default false
     */
    public static final function template($name, $frame = true, $shared = false)
    {        
        self::$template = $name;
        self::$sharedTempalte = $shared;
        if($frame !== false)
        {
            self::content('frame');
        } else {
            self::content();
        }
    }
    
    /**
     * Prints JSON response
     * @param {Mixed} $response to print
     * TODO: add header
     */
    public static final function api($response)
    {
        echo json_encode($response);
        die();
    }
    
    /**
     * Updates user in session from DB
     */
    public static final function updateUser()
    {
        self::setUser(new User(App::$user->uid));
    }
    
    /**
     * Sets user to the session
     * @param {User} $user
     * TODO: test and fix conflict handler
     */
    public static final function setUser($user)
    {
        if(self::$user && self::$user->uid != $user->uid)
        {
            /* log out
            App::$args->leftUSER = self::$user;
            App::$args->rightUSER = $user;
            self::sessionSet('conflict_left',self::$user->uid);
            self::sessionSet('conflict_right',$user->uid);
            App::template('conflict');
            die(); */
        }
        self::$user = $user;
        self::sessionSet('user', $user->uid);
    }
    
    /**
     * Loads JS script
     * @param {String} $path to the file
     * Executed without arguments prints load script
     */
    public static function loadJS($path = null)
    {
        if($path == null)
        {
            foreach(self::$jses as $lib)
            {
                ?><script src="<?php echo $lib;?>"></script><?php
            }
        } else {
            self::$jses[] = $path;
        }
    }
    
    /**
     * Loads CSS sheet
     * @param {String} $path to the file
     * Executed without arguments prints load script
     */
    public static function loadCSS($path = null)
    {
        if($path == null)
        {
            foreach(self::$csses as $lib)
            {
                ?><link href="<?php echo $lib;?>" rel="stylesheet" media="screen"><?php
            }
        } else {
            self::$csses[] = $path;
        }
    }
    
    /**
     * Checks if user is logged in and has perm
     * @param {String} $perm
     * @return {Boolean}
     */        
    public static final function userHasPerm($perm)
    {
        if(App::$user)
        {
            return App::$user->hasPerm($perm);
        }
        return false;
    }
    
    /**
     * Overrides path to enable to get fallbacked libraries
     * @param {String} $path
     * @return {String}
     */
    private static function resourcePathOverride($path)
    {
        if(isset(self::$configuration->RESOURCE_MAP))
        {
            foreach(self::$configuration->RESOURCE_MAP as $rewriteRule)
            {
                if(preg_match($rewriteRule[0],$path))
                {
                    $path = preg_replace($rewriteRule[0],$rewriteRule[1],$path);
                    return $path;
                }
            }
        }
        return $path;
    }
    
    /**
     * Loads requested resource
     */
    private static function loadResource()
    {
        
        $path = $_SERVER['REQUEST_URI'];
        $path = preg_replace('|^/\d+|','',$path);
        if(strpos($path,'..') !== false)
        {
            die('hackin\'?');
        }
        $path = self::resourcePathOverride($path);

        if( //TODO: optimize
            substr($_SERVER['REQUEST_URI'], -strlen('.woff')) === '.woff'
            || substr($_SERVER['REQUEST_URI'], -strlen('.ttf')) === '.ttf'
            || substr($_SERVER['REQUEST_URI'], -strlen('.eot')) === '.eot'
            || substr($_SERVER['REQUEST_URI'], -strlen('.svg')) === '.svg'
        )
        {
            $pathParts = explode('/',$path);
            $pathAbsolute = constant('CLIENT_PATH').'/resource/fonts/'.$pathParts[count($pathParts)-1];
            if(!file_exists($pathAbsolute))
            {
                $pathAbsolute = constant('SNVPATH').'/resource/fonts/'.$pathParts[count($pathParts)-1];
            }; 
            echo file_get_contents($pathAbsolute);
        } else {
            $pathAbsolute = constant('CLIENT_PATH').'/resource'.$path;
            if(!file_exists($pathAbsolute))
            {
                $pathAbsolute = constant('SNVPATH').'/resource'.$path;
            };        
            require_once(dirname(__FILE__).'/file_combiner.php');    
            echo FileCombiner::combine($pathAbsolute,fasle,true);
        }
    }

    private static function cacheResourceHeaders($mode = 0)
    {
        session_cache_limiter('private_no_expire');
        if(empty($mode)) {
            $last_modified_time = strtotime("yesterday");
        } else {
            $last_modified_time = round(time(), -3);
        }
        $etag = md5($_SERVER['REQUEST_URI']);
        // always send headers
        header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified_time)." GMT");
        header("Etag: $etag");
        // exit if not modified
        if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $last_modified_time ||
            @trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }
    }
    
    /**
     * Negotiate headers for resources
     */    
    private static function negotiateHeaders()
    {
        //TODO cache    
        if(preg_match('|\.css$|',$_SERVER['REQUEST_URI']))
        {
            self::cacheResourceHeaders(1);
            header('Content-Type: text/css');
        } elseif(preg_match('|\.js|',$_SERVER['REQUEST_URI']))
        {
            self::cacheResourceHeaders(1);
            header('Content-Type: application/javascript');
        } elseif(preg_match('|\.eot|',$_SERVER['REQUEST_URI']))
        {
            header('Content-Type: application/vnd.ms-fontobject');
        } elseif(preg_match('|\.svg|',$_SERVER['REQUEST_URI']))
        {
            header('Content-Type: image/svg+xml svg');
        } elseif(preg_match('|\.ttf|',$_SERVER['REQUEST_URI']))
        {
            header('Content-Type: application/octet-stream');
        } elseif(preg_match('|\.woff|',$_SERVER['REQUEST_URI']))
        {
            header('Content-Type: application/octet-stream');
        } elseif(preg_match('|\.jp(e?)g|',$_SERVER['REQUEST_URI']))
        {
            self::cacheResourceHeaders();
            header('Content-Type: image/jpeg');
        } elseif(preg_match('|\.png|',$_SERVER['REQUEST_URI']))
        {
            self::cacheResourceHeaders();
            header('Content-Type: image/png');
        }
    }
    
    /**
     * Setups application per Request
     */
    private static function setup()
    {
        $configuration = ConfigurationFactory::getConfiguration();   
        require_once(dirname(__FILE__).'/../model/user.php');     
        NOSQL::connect($configuration->MONGO);
        App::$configuration = $configuration;
        self::sessionSetup();
        if(!empty($configuration->FACEBOOK)) {
            self::$facebook = new Facebook(array(
                'appId'  => $configuration->FACEBOOK->appid,
                'secret' => $configuration->FACEBOOK->appsecret,
            ));
        }
        self::checkUser();
        self::setupLocale();
        self::$bucket = new \stdClass();        
    }

    private static function setupLocale()
    {
        if(empty(self::$configuration->LOCALES)) {
            stop('missing LOCALES in configuration');
        }
        $locale = self::sessionGet('locale');
        
        if(empty($locale) || !in_array($locale, self::$configuration->LOCALES)) {
            $locale = self::$configuration->LOCALES[0];
            self::sessionSet('locale', $locale);
        }
        Translation::init($locale, self::$configuration->LOCALES);
    }

    public static function setLocale($locale)
    {
        self::sessionSet('locale', $locale);
        self::setupLocale();
    }
    
    /**
     * Setups session
     */
    private static function sessionSetup()
    {
        if(headers_sent() === FALSE) session_start();
        if(!isset($_SESSION[SESSION_KEY]))
        {
            $_SESSION[SESSION_KEY] = new stdClass();
        }
    }
    
    /**
     * Checks user session
     */
    private static function checkUser()
    {
        $user = self::sessionGet('user');
        if(!is_object($user) && !empty($user))
        {
            App::$user = new User($user);
        }
        if(empty(App::$user)) {
            $fbid = self::$facebook->getUser();
            if($fbid != '0') {
                $user = User::authorise($fbid, null, User::IDENTITY_FACEBOOK);
                if(!empty($user)) {
                    App::$user = $user;
                }
            }
        }
    }
    
    /**
     * Does routing
     */
    private static final function route()
    {
        list($controllerName, $method, $params, $namespace) = self::parseURI();
        if(!empty(APP::$user))
        {
            if(!APP::$user->acceptedTNC() && !in_array($controllerName,array('logout','signin')))
            {
                $controllerName = 'tnc';
                $method = 'accept';       
            }
        }
        
        //self::$router = Maintainance::get();
        if(self::$router != null)
        {
            list($controllerName, $method, $params, $namespace) = self::$router->route($controllerName, $method, $params, $namespace);
        }
        self::doRouting($controllerName, $method, $params, $namespace);
    }
    
    /**
     * Parses URI parameters
     * @return {Array} array($controller,$method,$params,$ns)
     */
    private static final function parseURI()
    {
        $uriParts = explode('?',$_SERVER['REQUEST_URI']);
        $uriParts = preg_replace('/\.html$/','',$uriParts[0]);        
        $uriPath = explode('/',substr($uriParts,1));
        
        if(defined('CONTROLLER_URI_POSITION')) //TODO: merge with framework
        {
            $c = constant('CONTROLLER_URI_POSITION');
            while($c > 0)
            {
                $c--;
                array_shift($uriPath);
            }
        }
        
        if(empty($uriPath[0]))
        {
            array_shift($uriPath);    
        }
        
        $namespace = null;
        
        if(count($uriPath) == 0)
        {
            $uriPath[] = 'index';
        }
        
        if(count($uriPath) == 1)
        {
            $uriPath[] = 'index';
        }
        
        if($uriPath[0] == 'rpc')
        {
            return self::parseRPC();
        }
        
        return array(
            str_replace(array('-','+','.'),'_',array_shift($uriPath)), // controller
            str_replace(array('-','+','.'),'_',array_shift($uriPath)), // method
            $uriPath,
            null
        );
    }

    /**
     * Parses format of JSON-RPC
     */
    private static final function parseRPC()
    {
        $error = null;
        header('Content-Type: application/json');
        if(!empty($_POST['method']))
        {
            $path = explode('.',$_POST['method']);
            if(count($path) == 0)
            {
                $error = 'No method specified';    
            } elseif(count($path) == 1)
            {
                $controller = $path[0];
                $method = 'index';
            } else {
                $controller = $path[0];
                $method = $path[1];
            }
            define('RPCMODE',true);
            
            //RPC::authorise($_POST['params']);
            
            return array(
                $controller,
                $method,
                $_POST['params'],
                'rpc'
            );
        } else {
            $error = 'Syntax error';
        }
        if($error != null)
        {
            App::api(array(
                'error' => $error
            ));
        }
    }
    
    /**
     * Does actual controller load and method invoike
     * @param {String} $controllerName
     * @param {String} $method
     * @param {Array} $params
     * @param {String} $namespace
     */
    private static final function doRouting($controllerName, $method, $params, $namespace = null)
    {
        if($namespace == null)
        {
            $prefix = '';
        } else {
            $prefix = $namespace.'/';
        }
        $path = constant('CLIENT_PATH').'/controller/'.$prefix.$controllerName.'.php';


        //wseredynski todo better fallback handling
        if(!is_readable($path)) {
            $path = dirname(__FILE__).'/../controller/'.$prefix.$controllerName.'.php';
        }        
        //end of workaround

        $controllerName = ucfirst($controllerName).'Controller';

        if(is_readable($path))
        {
            include_once ($path);
            
            if(isset(App::$configuration->NS))
            {
                $controllerNameNS = App::$configuration->NS . '\\' . $controllerName;
            } else {
                $controllerNameNS = $controllerName;
            }

            if(class_exists($controllerNameNS))
            {
                $controller = new $controllerNameNS();
                
                if(empty($method))
                {
                    $method = 'index';
                }           
                if(method_exists($controllerNameNS, $method))
                {
                    $controller->{str_replace(array('.','-'),'_',$method)}($params);
                    return;
                } elseif(method_exists($controllerNameNS, 'byEnum') && $controller->byEnum($method,$params))
                {
                    return;
                }  elseif(method_exists($controllerNameNS, 'on404'))
                {
                    $controller->on404($params);
                    return;
                } elseif($controllerName != 'IndexController')
                {
                    self::doRouting('index','on404',array($controllerName, $method, $params),$namespace);
                    return;
                }
            } elseif($controllerName != 'IndexController')
            {
                self::doRouting('index','on404',array($controllerName, $method, $params),$namespace);
                return;    
            }
        } elseif($controllerName != 'IndexController')
        {
            self::doRouting('index','on404',array($controllerName, $method, $params),$namespace);
            return;
        }
        stop(404,$path,$controllerName,$method,$params);
    }

    /**
     * Print template
     * @param {String} $template
     */
    private static final function content($template = null)
    {
        if($template == null)
        {
            $template = self::$template;
        }
        
        if(self::$sharedTempalte === true && $template != 'frame')
        {
            $path = constant('SNVPATH') . '/view/' .$template . '.php';
        } else {
            $path = constant('CLIENT_PATH') . '/view/' . $template . '.php';
        }
        if(is_readable($path))
        {
            include($path);
        } else {
            stop('template',$path);
        }
    }
    
    public static function select($title, $name, $value, $options)
    {
        $status = self::beforeFormControl($title, $name);
        ?><select class="form-control" id="input_<?=$name;?>" name="<?=$name;?>" >
            <option value="">-- <?l('select');?> --</option>
            <?php self::options($options, $value); ?>
        </select>
        <?php
        self::afterFormControl($status);
    }
    
    public static function field($title, $name, $value, $placeholder = '')
    {
        $status = self::beforeFormControl($title, $name, $value);
        ?>
        <input type="text" class="form-control" id="input_<?=$name;?>" name="<?=$name;?>" value="<?=str_replace('"','\"',$value);?>" placeholder="<?=str_replace('"','\"',rt($placeholder,null,2));?>">
        <?php
        self::afterFormControl($status);
    }
    
    public static function textarea($title, $name, $value, $placeholder = '')
    {
        $status = self::beforeFormControl($title, $name, $value);
        ?>
        <textarea class="form-control" rows="6" id="input_<?=$name;?>" name="<?=$name;?>" placeholder="<?=str_replace('"','\"',rt($placeholder,null,2));?>"><?=strip_tags($value);?></textarea>
        <?php
        self::afterFormControl($status);
    }

    private static function options($options, $value, $indent = 0)
    {
        foreach($options as $k => $object):?>
            <option <?php
            if($object['disabled'])
            {
                echo 'disabled';
            } else {
                echo 'value="'.$object['key'].'"';
            }
            if($object['key'] == $value)
            {
                echo ' selected';
            }
            ?>><?php
                echo str_repeat("&nbsp;", 4*$indent); 
                l($object['key'],null,2);
            ?></option>
            <?php 
            if(isset($object['items']))
            {
                self::options($object['items'], $value, $indent+1);
            }
        endforeach;
    }
        
    public static function beforeFormControl($title, $name)
    {
        if(in_array($name, APP::$bucket->missingFields))
        {
            $status = 'has-error';
        } elseif(!empty($value)) {
            $status = 'has-success';
        } else {
            $status = null;
        }
        ?>
        <div class="form-group <?=$status;?> has-feedback">
            <label class="control-label col-sm-3" for="input_<?=$name;?>"><?t($title,null,2);?></label>
            <div class="col-sm-9">
        <?php
        return $status;
    }
    
    public static function afterFormControl($status)
    {
                if($status == 'has-error'): ?>
                    <span class="glyphicon glyphicon-remove form-control-feedback"></span>
                <?php elseif($status == 'has-success'): ?>
                    <span class="glyphicon glyphicon-ok form-control-feedback"></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public static function handleImageUpload($fieldName, $elem = null)
    {
        $path = self::_handleImageUpload($fieldName,$elem);
        if(is_array($path))
        {
            $extension = '';
            switch($path[1])
            {
                case 'image/gif':
                {
                    $extension = '.gif';
                    break;
                }
                case 'image/png':
                {
                    $extension = '.png';
                    break;
                }
                case 'image/jpeg':
                {
                    $extension = '.jpeg';
                    break;
                }
                case 'text/csv':
                {
                    $extension = '.csv';
                    break;
                }
            }
            $storageDir = '/tmp';
            $name = time() . '_' . rand(1,10000) . $extension;
            $newPath = $storageDir . '/' . $name;
            $success = move_uploaded_file($path[0], $newPath);
            return array($newPath,$name);
        }
        return $path;
    } 
    private static function _handleImageUpload($fieldName,$elem = null)
    {
        if($_FILES[$fieldName])
        {
            if ($elem != null && $_FILES[$fieldName]['error'][$elem] === 0)
            {
                return array($_FILES[$fieldName]['tmp_name'][$elem],$_FILES[$fieldName]['type'][$elem]);
            } elseif($elem === null && $_FILES[$fieldName]['error'] === 0){
                return array($_FILES[$fieldName]['tmp_name'],$_FILES[$fieldName]['type']);
            } else {
                if($elem != null)
                {
                    $err =  $_FILES[$fieldName]['error'][$elem];
                } else {
                    $err = $_FILES[$fieldName]['error'];
                }
                switch($err)
                {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                    {
                        return self::FILE_TOO_BIG;
                    }
                    case UPLOAD_ERR_NO_FILE:
                    {
                        return self::NO_FILE;
                    }
                    default:
                    {
                        return false;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Configuration handler
     */
    public static $configuration = null;
    /**
     * Globally visible data handler 
     */
    public static $bucket = null;

    /**
     * Facebook object
     */
    public static $facebook = null;

    /**
     * Implementation router extension
     */
    public static $router = null;
    /**
     * Current template to be loaded - used while using frame
     */
    public static $template = null;
    /**
     * Shared template info
     */
    public static $sharedTempalte = null;
    /**
     * Current user
     */
    public static $user = null;
    /**
     * Queued JSes
     */
    private static $jses = array();
    /**
     * Queued CSSes
     */
    private static $csses = array();
    
    const FILE_TOO_BIG = -1;
    const NO_FILE = -2;
}
