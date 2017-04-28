<?php

namespace Jaxon\Module\Traits;

use Jaxon\Jaxon;
use Jaxon\Module\Controller;
use Jaxon\Utils\Container;
use Jaxon\Utils\Traits\Config;
use Jaxon\Utils\Traits\View;
use Jaxon\Utils\Traits\Session;
use Jaxon\Utils\Traits\Manager;
use Jaxon\Utils\Traits\Event;
use Jaxon\Utils\Traits\Validator;

use stdClass, Exception;

trait Module
{
    use Config, View, Session, Manager, Event, Validator;

    protected $jaxonSetupCalled = false;

    protected $jaxonBeforeCallback = null;
    protected $jaxonAfterCallback = null;
    protected $jaxonInitCallback = null;
    protected $jaxonInvalidCallback = null;
    protected $jaxonErrorCallback = null;

    // Requested class and method
    private $jaxonRequestObject = null;
    private $jaxonRequestMethod = null;

    protected $appConfig = null;
    protected $jaxonResponse = null;

    protected $jaxonBaseClass = '\\Jaxon\\Module\\Controller';

    protected $jaxonViewRenderers = array();
    protected $jaxonViewNamespaces = array();

    // Library options
    protected $jaxonLibraryOptions = null;

    /**
     * Set the module specific options for the Jaxon library.
     *
     * @return void
     */
    abstract protected function jaxonSetup();

    /**
     * Set the module specific options for the Jaxon library.
     *
     * @return void
     */
    abstract protected function jaxonCheck();

    /**
     * Wrap the Jaxon response into an HTTP response and send it back to the browser.
     *
     * @param  $code        The HTTP Response code
     *
     * @return HTTP Response
     */
    abstract public function httpResponse($code = '200');

    /**
     * Get the Jaxon response.
     *
     * @return HTTP Response
     */
    public function ajaxResponse()
    {
        return $this->jaxonResponse;
    }

    /**
     * Add a controller namespace.
     *
     * @param string            $sDirectory             The path to the directory
     * @param string            $sNamespace             The associated namespace
     * @param string            $sSeparator             The character to use as separator in javascript class names
     * @param array             $aProtected             The functions that are not to be exported
     *
     * @return void
     */
    public function addClassNamespace($sDirectory, $sNamespace, $sSeparator = '.', array $aProtected = array())
    {
        // Valid separator values are '.' and '_'. Any other value is considered as '.'.
        $sSeparator = trim($sSeparator);
        if($sSeparator != '_')
        {
            $sSeparator = '.';
        }
        jaxon()->addClassDir(trim($sDirectory), trim($sNamespace), $sSeparator, $aProtected);
    }

    /**
     * Add a view namespace, and set the corresponding renderer.
     *
     * @param string        $sNamespace         The namespace name
     * @param string        $sDirectory         The namespace directory
     * @param string        $sExtension         The extension to append to template names
     * @param string        $sRenderer          The corresponding renderer name
     *
     * @return void
     */
    public function addViewNamespace($sNamespace, $sDirectory, $sExtension, $sRenderer)
    {
        $aNamespace = array(
            'namespace' => $sNamespace,
            'directory' => $sDirectory,
            'extension' => $sExtension,
        );
        if(key_exists($sRenderer, $this->jaxonViewNamespaces))
        {
            $this->jaxonViewNamespaces[$sRenderer][] = $aNamespace;
        }
        else
        {
            $this->jaxonViewNamespaces[$sRenderer] = array($aNamespace);
        }
        $this->jaxonViewRenderers[$sNamespace] = $sRenderer;
    }

    /**
     * Set the Jaxon library default options.
     *
     * @return void
     */
    protected function setLibraryOptions($bExtern, $bMinify, $sJsUri, $sJsDir)
    {
        if(!$this->jaxonLibraryOptions)
        {
            $this->jaxonLibraryOptions = new stdClass();
        }
        $this->jaxonLibraryOptions->bExtern = $bExtern;
        $this->jaxonLibraryOptions->bMinify = $bMinify;
        $this->jaxonLibraryOptions->sJsUri = $sJsUri;
        $this->jaxonLibraryOptions->sJsDir = $sJsDir;
    }

    /**
     * Wraps the module/package/bundle setup method.
     *
     * @return void
     */
    private function _jaxonSetup()
    {
        if(($this->jaxonSetupCalled))
        {
            return;
        }

        $jaxon = jaxon();
        // Use the Composer autoloader. It's important to call this before triggers and callbacks.
        $jaxon->useComposerAutoloader();

        // Set this object as the Module in the DI container.
        // Now it will be returned by a call to jaxon()->module().
        if(get_class($this) != 'Jaxon\\Module\\Module')
        {
            Container::getInstance()->setModule($this);
        }

        // Event before setting up the module
        $this->triggerEvent('pre.setup');

        // Set the module/package/bundle specific specific options
        $this->jaxonSetup();

        // Event before the module has set the config
        $this->triggerEvent('pre.config');

        // Event after the module has read the config
        $this->triggerEvent('post.config');

        // Create the Jaxon response
        $this->jaxonResponse = jaxon()->getResponse();

        if(($this->jaxonLibraryOptions))
        {
            // Jaxon library settings
            if(!$jaxon->hasOption('js.app.extern'))
            {
                $jaxon->setOption('js.app.extern', $this->jaxonLibraryOptions->bExtern);
            }
            if(!$jaxon->hasOption('js.app.minify'))
            {
                $jaxon->setOption('js.app.minify', $this->jaxonLibraryOptions->bMinify);
            }
            if(!$jaxon->hasOption('js.app.uri'))
            {
                $jaxon->setOption('js.app.uri', $this->jaxonLibraryOptions->sJsUri);
            }
            if(!$jaxon->hasOption('js.app.dir'))
            {
                $jaxon->setOption('js.app.dir', $this->jaxonLibraryOptions->sJsDir);
            }
    
            // Set the request URI
            if(!$jaxon->hasOption('core.request.uri'))
            {
                $jaxon->setOption('core.request.uri', 'jaxon');
            }
        }

        // Event before checking the module
        $this->triggerEvent('pre.check');

        $this->jaxonCheck();

        // Event after checking the module
        $this->triggerEvent('post.check');

        // Jaxon application settings
        // The public methods of the base class must not be exported to javascript
        $protected = array();
        $controllerClass = new \ReflectionClass($this->jaxonBaseClass);
        foreach ($controllerClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $xMethod)
        {
            $protected[] = $xMethod->getShortName();
        }
        if($this->appConfig->hasOption('controllers') && is_array($this->appConfig->getOption('controllers')))
        {
            $aNamespaces = $this->appConfig->getOption('controllers');
            foreach($aNamespaces as $aNamespace)
            {
                // Check mandatory options
                if(!key_exists('directory', $aNamespace) || !key_exists('namespace', $aNamespace))
                {
                    continue;
                }
                // Set the default values for optional parameters
                if(!key_exists('separator', $aNamespace))
                {
                    $aNamespace['separator'] = '.';
                }
                if(!key_exists('protected', $aNamespace))
                {
                    $aNamespace['protected'] = [];
                }
                $this->addClassNamespace($aNamespace['directory'], $aNamespace['namespace'],
                    $aNamespace['separator'], array_merge($aNamespace['protected'], $protected));
            }
        }

        // Save the view namespaces
        $sDefaultNamespace = $this->appConfig->getOption('options.views.default', false);
        if(is_array($namespaces = $this->appConfig->getOptionNames('views')))
        {
            foreach($namespaces as $namespace => $option)
            {
                // If no default namespace is defined, use the first one as default.
                if($sDefaultNamespace == false)
                {
                    $sDefaultNamespace = $namespace;
                }
                // Save the namespace
                $directory = $this->appConfig->getOption($option . '.directory');
                $extension = $this->appConfig->getOption($option . '.extension', '');
                $renderer = $this->appConfig->getOption($option . '.renderer', 'jaxon');
                $this->addViewNamespace($namespace, $directory, $extension, $renderer);
            }
        }

        // Save the view renderers and namespaces in the DI container
        $this->initViewRenderers($this->jaxonViewRenderers);
        $this->initViewNamespaces($this->jaxonViewNamespaces, $sDefaultNamespace);

        // Event after setting up the module
        $this->triggerEvent('post.setup');

        $this->jaxonSetupCalled = true;
    }

    /**
     * Register the Jaxon classes.
     *
     * @return void
     */
    public function register()
    {
        $this->_jaxonSetup();
        jaxon()->registerClasses();
    }

    /**
     * Register a specified Jaxon class.
     *
     * @param string            $sClassName             The name of the class to be registered
     * @param array             $aOptions               The options to register the class with
     *
     * @return void
     */
    public function registerClass($sClassName, array $aOptions = array())
    {
        $this->_jaxonSetup();
        jaxon()->registerClass($sClassName, $aOptions);
    }

    /**
     * Get the javascript code to be sent to the browser.
     *
     * @return string  the javascript code
     */
    public function script($bIncludeJs = false, $bIncludeCss = false)
    {
        $this->_jaxonSetup();
        return jaxon()->getScript($bIncludeJs, $bIncludeCss);
    }

    /**
     * Get the HTML tags to include Jaxon javascript files into the page.
     *
     * @return string  the javascript code
     */
    public function js()
    {
        $this->_jaxonSetup();
        return jaxon()->getJs();
    }

    /**
     * Get the HTML tags to include Jaxon CSS code and files into the page.
     *
     * @return string  the javascript code
     */
    public function css()
    {
        $this->_jaxonSetup();
        return jaxon()->getCss();
    }

    /**
     * Set the init callback, used to initialise controllers.
     *
     * @param  callable         $callable               The callback function
     * @return void
     */
    public function onInit($callable)
    {
        $this->jaxonInitCallback = $callable;
    }

    /**
     * Set the pre-request processing callback.
     *
     * @param  callable         $callable               The callback function
     * @return void
     */
    public function onBefore($callable)
    {
        $this->jaxonBeforeCallback = $callable;
    }

    /**
     * Set the post-request processing callback.
     *
     * @param  callable         $callable               The callback function
     * 
     * @return void
     */
    public function onAfter($callable)
    {
        $this->jaxonAfterCallback = $callable;
    }

    /**
     * Set the processing error callback.
     *
     * @param  callable         $callable               The callback function
     * 
     * @return void
     */
    public function onInvalid($callable)
    {
        $this->jaxonInvalidCallback = $callable;
    }

    /**
     * Set the processing exception callback.
     *
     * @param  callable         $callable               The callback function
     * 
     * @return void
     */
    public function onError($callable)
    {
        $this->jaxonErrorCallback = $callable;
    }

    /**
     * Initialise a controller.
     *
     * @return void
     */
    protected function initController(Controller $controller)
    {
        // Return if the controller has already been initialised.
        if(!($controller) || ($controller->response))
        {
            return;
        }
        // Init the controller
        $controller->response = $this->jaxonResponse;
        if(($this->jaxonInitCallback))
        {
            call_user_func_array($this->jaxonInitCallback, array($controller));
        }
        $controller->init();
    }

    /**
     * Get a controller instance.
     *
     * @param  string  $classname the controller class name
     * 
     * @return object  The registered instance of the controller
     */
    public function controller($classname)
    {
        $this->_jaxonSetup();
        // Find the class instance, and register the class if the instance is not found.
        if(!($controller = jaxon()->getPluginManager()->getRegisteredObject($classname)))
        {
            $controller = jaxon()->registerClass($classname, [], true);
        }
        if(($controller))
        {
            $this->initController($controller);
        }
        return $controller;
    }

    /**
     * Get a Jaxon request to a given controller.
     *
     * @param  string  $classname the controller class name
     * 
     * @return object  The request to the controller
     */
    public function request($classname)
    {
        $controller = $this->controller($classname);
        return ($controller != null ? $controller->request() : null);
    }

    /**
     * Get a plugin instance.
     *
     * @param  string  $name the plugin name
     * 
     * @return object  The plugin instance
     */
    public function plugin($name)
    {
        return jaxon()->plugin($name);
    }

    /**
     * This is the pre-request processing callback passed to the Jaxon library.
     *
     * @param  boolean  &$bEndRequest if set to true, the request processing is interrupted.
     * 
     * @return object  the Jaxon response
     */
    public function onEventBefore(&$bEndRequest)
    {
        // Validate the inputs
        $class = $_POST['jxncls'];
        $method = $_POST['jxnmthd'];
        if(!$this->validateClass($class) || !$this->validateMethod($method))
        {
            // End the request processing if the input data are not valid.
            // Todo: write an error message in the response
            $bEndRequest = true;
            return $this->jaxonResponse;
        }
        // Instanciate the controller. This will include the required file.
        $this->jaxonRequestObject = $this->controller($class);
        $this->jaxonRequestMethod = $method;
        if(!$this->jaxonRequestObject)
        {
            // End the request processing if a controller cannot be found.
            // Todo: write an error message in the response
            $bEndRequest = true;
            return $this->jaxonResponse;
        }

        // Call the user defined callback
        if(($this->jaxonBeforeCallback))
        {
            call_user_func_array($this->jaxonBeforeCallback,
                array($this->jaxonResponse, $this->jaxonRequestObject, $this->jaxonRequestMethod, &$bEndRequest));
        }
        return $this->jaxonResponse;
    }

    /**
     * This is the post-request processing callback passed to the Jaxon library.
     *
     * @return object  the Jaxon response
     */
    public function onEventAfter()
    {
        if(($this->jaxonAfterCallback))
        {
            call_user_func_array($this->jaxonAfterCallback,
                array($this->jaxonResponse, $this->jaxonRequestObject, $this->jaxonRequestMethod));
        }
        return $this->jaxonResponse;
    }

    /**
     * This callback is called whenever an invalid request is processed.
     *
     * @return object  the Jaxon response
     */
    public function onEventInvalid($sMessage)
    {
        if(($this->jaxonInvalidCallback))
        {
            call_user_func_array($this->jaxonInvalidCallback, array($this->jaxonResponse, $sMessage));
        }
        return $this->jaxonResponse;
    }

    /**
     * This callback is called whenever an invalid request is processed.
     *
     * @return object  the Jaxon response
     */
    public function onEventError(Exception $e)
    {
        if(($this->jaxonErrorCallback))
        {
            call_user_func_array($this->jaxonErrorCallback, array($this->jaxonResponse, $e));
        }
        else
        {
            throw $e;
        }
        return $this->jaxonResponse;
    }

    /**
     * Check if the current request is a Jaxon request.
     *
     * @return boolean  True if the request is Jaxon, false otherwise.
     */
    public function canProcessRequest()
    {
        $this->_jaxonSetup();
        return jaxon()->canProcessRequest();
    }

    /**
     * Process the current Jaxon request.
     *
     * @return void
     */
    public function processRequest()
    {
        $this->_jaxonSetup();
        // Process Jaxon Request
        $jaxon = jaxon();
        $jaxon->register(Jaxon::PROCESSING_EVENT, Jaxon::PROCESSING_EVENT_BEFORE, array($this, 'onEventBefore'));
        $jaxon->register(Jaxon::PROCESSING_EVENT, Jaxon::PROCESSING_EVENT_AFTER, array($this, 'onEventAfter'));
        $jaxon->register(Jaxon::PROCESSING_EVENT, Jaxon::PROCESSING_EVENT_INVALID, array($this, 'onEventInvalid'));
        $jaxon->register(Jaxon::PROCESSING_EVENT, Jaxon::PROCESSING_EVENT_ERROR, array($this, 'onEventError'));
        if($jaxon->canProcessRequest())
        {
            // Traiter la requete
            $jaxon->processRequest();
        }
    }
}
