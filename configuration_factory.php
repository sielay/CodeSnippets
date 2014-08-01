<?php
/**
 * @copyright SIELAY.com
 *
 * Snippet shows class helping to manage configuration of multi site prototype.
 *
 */
 
/**
 * Class that determines proper configuration
 * TODO: review
 */ 
class ConfigurationFactory
{
    /**
    * server location descriptors
    */
    const SERVER_MASTER = 'server_master'; // main server cloud
    const SERVER_REMOTE = 'server_remote'; // remote system living on client stack
    
    /**
     * Code live cycle descriptors
     */
    const BRANCH_DEVELOPMENT = 'master'; // dirty development
    const BRANCH_TESTING = 'stage'; // testing environment
    const BRANCH_RC = 'rc'; // client approval environment
    const BRANCH_PRODUCTION = 'live'; // production environment
    
    /**
     * Gets configuration hash map
     * @return {Object}
     */
    public static final function getConfiguration()
    {
        if(self::$configuration === null)
        {
            self::computeConfiguration();
        }
        return self::$configuration;
    }
    
    /**
     * Computes configuration
     */
    private static final function computeConfiguration()
    {
        self::verifyEnvironment();
        
        $host = isset($_SERVER['X_FORWARDED_FOR'])?$_SERVER['X_FORWARDED_FOR']:$_SERVER['HTTP_HOST'];
        
        $redirectMap = @parse_ini_file(dirname(__FILE__).'/../../apps.ini',true);
        if($redirectMap)
        {
            if(isset($redirectMap[$host]))
            {
                $host = $redirectMap[$host];
            }
        }
        
        $host = explode('.',$host);        
        $host= array_reverse($host);                
        $host = join('/',$host);
        $clientPath = realpath(dirname(__FILE__).'/../../apps/'.$host);
        if(file_exists($clientPath.'/configuration.php'))
        {
            require_once($clientPath.'/configuration.php');
            self::$configuration = $configuration;
        } else {
            if(strpos($host,'.') !== FALSE)
            {
                $clientPath = realpath(dirname(__FILE__).'/../../apps/'.(substr($host,0,strpos($host,'.')).'_'));
            } else {
                $clientPath = realpath(dirname(__FILE__).'/../');
            }            
            if(file_exists($clientPath.'/configuration.php'))
            {
                require_once($clientPath.'/configuration.php');
                self::$configuration = $configuration;
            } else {
                stop('Configuration is not set');
            }
        }
        if(!defined ('SESSION_KEY'))
        {
            define('SESSION_KEY',$_SERVER['HTTP_HOST']);
        }
        if(!defined('LOGIN_URI'))
        {
            define('LOGIN_URI','/');
        }
        define('CLIENT_PATH',$clientPath);
    }

    /**
     * Verifies if required classes are installed
     */
    private static function verifyEnvironment()
    {
        $requiredClasses = array(
            'MongoCursor' => 'PECL mongo >=0.9.0'
        );
        
        foreach($requiredClasses as $class => $libName)
        {
            if(!class_exists($class))
            {
                stop($libName . ' is not installed');
            }
        }
    }
    
    /**
     * Private client handler
     */
    private static $configuration = null;
}

define('SNVPATH',realpath(dirname(__FILE__).'/../'));
define('ROOTPATH', realpath(dirname(__FILE__).'/../../'));
