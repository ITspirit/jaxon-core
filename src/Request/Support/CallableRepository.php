<?php

/**
 * CallableRepository.php - Jaxon callable object repository
 *
 * This class stores all the callable object already created.
 *
 * @package jaxon-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2019 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Request\Support;

use Jaxon\Request\Request;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CallableRepository
{
    use \Jaxon\Utils\Traits\Config;
    use \Jaxon\Utils\Traits\Template;

    /**
     * The registered namespaces
     *
     * These are the namespaces specified when registering directories.
     *
     * @var array
     */
    protected $aNamespaceOptions = [];

    /**
     * The registered classes
     *
     * These are registered classes, and classes in directories registered without a namespace.
     *
     * @var array
     */
    protected $aClassOptions = [];

    /**
     * The namespaces
     *
     * These are all the namespaces found in registered directories
     *
     * @var array
     */
    protected $aNamespaces = [];

    /**
     * The created callable objects
     *
     * @var array
     */
    protected $aCallableObjects = [];

    /**
     * The options to be applied to callable objects
     *
     * @var array
     */
    protected $aCallableOptions = [];

    /**
     *
     * @param string        $sClassName     The name of the class being registered
     * @param array|string  $aOptions       The associated options
     *
     * @return void
     */
    public function addClass($sClassName, $aOptions)
    {
        // Todo: if there's a namespace, register with '_' as separator
        $sClassName = trim($sClassName, '\\');
        $this->aClassOptions[$sClassName] = $aOptions;
    }

    /**
     * Get a given class options from specified directory options
     *
     * @param string        $sClassName         The name of the class
     * @param array         $aDirectoryOptions  The directory options
     * @param array         $aDefaultOptions    The default options
     *
     * @return array
     */
    private function getClassOptions($sClassName, array $aDirectoryOptions, array $aDefaultOptions = [])
    {
        $aOptions = $aDefaultOptions;
        if(key_exists('separator', $aDirectoryOptions))
        {
            $aOptions['separator'] = $aDirectoryOptions['separator'];
        }
        if(key_exists('protected', $aDirectoryOptions))
        {
            $aOptions['protected'] = $aDirectoryOptions['protected'];
        }
        if(key_exists('*', $aDirectoryOptions))
        {
            $aOptions = array_merge($aOptions, $aDirectoryOptions['*']);
        }
        if(key_exists($sClassName, $aDirectoryOptions))
        {
            $aOptions = array_merge($aOptions, $aDirectoryOptions[$sClassName]);
        }

        return $aOptions;
    }

    /**
     *
     * @param string        $sDirectory     The directory being registered
     * @param array         $aOptions       The associated options
     *
     * @return void
     */
    public function addDirectory($sDirectory, $aOptions)
    {
        $itDir = new RecursiveDirectoryIterator($sDirectory);
        $itFile = new RecursiveIteratorIterator($itDir);
        // Iterate on dir content
        foreach($itFile as $xFile)
        {
            // skip everything except PHP files
            if(!$xFile->isFile() || $xFile->getExtension() != 'php')
            {
                continue;
            }

            $aClassOptions = [];
            // No more classmap autoloading. The file will be included when needed.
            if(($aOptions['autoload']))
            {
                $aClassOptions['include'] = $xFile->getPathname();
            }

            $sClassName = $xFile->getBasename('.php');
            $aClassOptions = $this->getClassOptions($sClassName, $aOptions, $aClassOptions);
            $this->addClass($sClassName, $aClassOptions);
        }
    }

    /**
     *
     * @param string        $sNamespace     The namespace of the directory being registered
     * @param array         $aOptions       The associated options
     *
     * @return void
     */
    public function addNamespace($sNamespace, array $aOptions)
    {
        // Separator default value
        if(!key_exists('separator', $aOptions))
        {
            $aOptions['separator'] = '.';
        }
        $this->aNamespaceOptions[$sNamespace] = $aOptions;
    }

    /**
     * Find a class name is register with Jaxon::CALLABLE_CLASS type
     *
     * @param string        $sClassName            The class name of the callable object
     *
     * @return array|null
     */
    private function getOptionsFromClass($sClassName)
    {
        if(!key_exists($sClassName, $this->aClassOptions))
        {
            return null; // Class not registered
        }
        return $this->aClassOptions[$sClassName];
    }

    /**
     * Find a class name is register with Jaxon::CALLABLE_DIR type
     *
     * @param string        $sClassName            The class name of the callable object
     * @param string|null   $sNamespace            The namespace
     *
     * @return array|null
     */
    private function getOptionsFromNamespace($sClassName, $sNamespace = null)
    {
        // Find the corresponding namespace
        if($sNamespace === null)
        {
            foreach(array_keys($this->aNamespaceOptions) as $_sNamespace)
            {
                if(substr($sClassName, 0, strlen($_sNamespace) + 1) == $_sNamespace . '\\')
                {
                    $sNamespace = $_sNamespace;
                    break;
                }
            }
        }
        if($sNamespace === null)
        {
            return null; // Class not registered
        }

        // Get the class options
        $aOptions = $this->aNamespaceOptions[$sNamespace];
        $aDefaultOptions = []; // ['namespace' => $aOptions['namespace']];
        if(key_exists('separator', $aOptions))
        {
            $aDefaultOptions['separator'] = $aOptions['separator'];
        }
        return $this->getClassOptions($sClassName, $aOptions, $aDefaultOptions);
    }

    /**
     * Find a callable object by class name
     *
     * @param string        $sClassName            The class name of the callable object
     * @param array         $aOptions              The callable object options
     *
     * @return object
     */
    protected function _getCallableObject($sClassName, array $aOptions)
    {
        // Make sure the registered class exists
        if(key_exists('include', $aOptions))
        {
            require_once($aOptions['include']);
        }
        if(!class_exists($sClassName))
        {
            return null;
        }

        // Create the callable object
        $xCallableObject = new \Jaxon\Request\Support\CallableObject($sClassName);
        $this->aCallableOptions[$sClassName] = [];
        foreach($aOptions as $sName => $xValue)
        {
            if($sName == 'separator' || $sName == 'protected')
            {
                $xCallableObject->configure($sName, $xValue);
            }
            elseif(is_array($xValue) && $sName != 'include')
            {
                // These options are to be included in javascript code.
                $this->aCallableOptions[$sClassName][$sName] = $xValue;
            }
        }
        $this->aCallableObjects[$sClassName] = $xCallableObject;

        // Register the request factory for this callable object
        jaxon()->di()->set($sClassName . '_Factory_Rq', function () use ($sClassName) {
            $xCallableObject = $this->aCallableObjects[$sClassName];
            return new \Jaxon\Factory\Request\Portable($xCallableObject);
        });
        // Register the paginator factory for this callable object
        jaxon()->di()->set($sClassName . '_Factory_Pg', function () use ($sClassName) {
            $xCallableObject = $this->aCallableObjects[$sClassName];
            return new \Jaxon\Factory\Request\Paginator($xCallableObject);
        });

        return $xCallableObject;
    }

    /**
     * Find a callable object by class name
     *
     * @param string        $sClassName            The class name of the callable object
     *
     * @return object
     */
    public function getCallableObject($sClassName)
    {
        // Replace all separators ('.' and '_') with antislashes, and remove the antislashes
        // at the beginning and the end of the class name.
        $sClassName = trim(str_replace(['.', '_'], ['\\', '\\'], (string)$sClassName), '\\');

        if(key_exists($sClassName, $this->aCallableObjects))
        {
            return $this->aCallableObjects[$sClassName];
        }

        $aOptions = $this->getOptionsFromClass($sClassName);
        if($aOptions === null)
        {
            $aOptions = $this->getOptionsFromNamespace($sClassName);
        }
        if($aOptions === null)
        {
            return null;
        }

        return $this->_getCallableObject($sClassName, $aOptions);
    }

    /**
     * Create callable objects for all registered namespaces
     *
     * @return void
     */
    private function createCallableObjects()
    {
        // Create callable objects for registered classes
        foreach($this->aClassOptions as $sClassName => $aClassOptions)
        {
            if(!key_exists($sClassName, $this->aCallableObjects))
            {
                $this->_getCallableObject($sClassName, $aClassOptions);
            }
        }

        // Create callable objects for registered namespaces
        $sDS = DIRECTORY_SEPARATOR;
        foreach($this->aNamespaceOptions as $sNamespace => $aOptions)
        {
            if(key_exists($sNamespace, $this->aNamespaces))
            {
                continue;
            }

            $this->aNamespaces[$sNamespace] = $sNamespace;

            // Iterate on dir content
            $sDirectory = $aOptions['directory'];
            $itDir = new RecursiveDirectoryIterator($sDirectory);
            $itFile = new RecursiveIteratorIterator($itDir);
            foreach($itFile as $xFile)
            {
                // skip everything except PHP files
                if(!$xFile->isFile() || $xFile->getExtension() != 'php')
                {
                    continue;
                }

                // Find the class path (the same as the class namespace)
                $sClassPath = $sNamespace;
                $sRelativePath = substr($xFile->getPath(), strlen($sDirectory));
                $sRelativePath = trim(str_replace($sDS, '\\', $sRelativePath), '\\');
                if($sRelativePath != '')
                {
                    $sClassPath .= '\\' . $sRelativePath;
                }

                $this->aNamespaces[$sClassPath] = ['separator' => $aOptions['separator']];
                $sClassName = $sClassPath . '\\' . $xFile->getBasename('.php');

                if(!key_exists($sClassName, $this->aCallableObjects))
                {
                    $aClassOptions = $this->getOptionsFromNamespace($sClassName, $sNamespace);
                    if($aClassOptions !== null)
                    {
                        $this->_getCallableObject($sClassName, $aClassOptions);
                    }
                }
            }
        }
    }

    /**
     * Find a user registered callable object by class name
     *
     * @param string        $sClassName            The class name of the callable object
     *
     * @return object
     */
    protected function getRegisteredObject($sClassName)
    {
        // Get the corresponding callable object
        $xCallableObject = $this->getCallableObject($sClassName);
        return ($xCallableObject) ? $xCallableObject->getRegisteredObject() : null;
    }

    /**
     * Generate a hash for the registered callable objects
     *
     * @return string
     */
    public function generateHash()
    {
        $this->createCallableObjects();

        $sHash = '';
        foreach($this->aNamespaces as $sNamespace => $aOptions)
        {
            $sHash .= $sNamespace . $aOptions['separator'];
        }
        foreach($this->aCallableObjects as $sClassName => $xCallableObject)
        {
            $sHash .= $sClassName . implode('|', $xCallableObject->getMethods());
        }

        return md5($sHash);
    }

    /**
     * Generate client side javascript code for the registered callable objects
     *
     * @return string
     */
    public function getScript()
    {
        $this->createCallableObjects();

        $sPrefix = $this->getOption('core.prefix.class');

        $aJsClasses = [];
        $sCode = '';
        foreach(array_keys($this->aNamespaces) as $sNamespace)
        {
            $offset = 0;
            $sJsNamespace = str_replace('\\', '.', $sNamespace);
            $sJsNamespace .= '.Null'; // This is a sentinel. The last token is not processed in the while loop.
            while(($dotPosition = strpos($sJsNamespace, '.', $offset)) !== false)
            {
                $sJsClass = substr($sJsNamespace, 0, $dotPosition);
                // Generate code for this object
                if(!key_exists($sJsClass, $aJsClasses))
                {
                    $sCode .= "$sPrefix$sJsClass = {};\n";
                    $aJsClasses[$sJsClass] = $sJsClass;
                }
                $offset = $dotPosition + 1;
            }
        }

        foreach($this->aCallableObjects as $sClassName => $xCallableObject)
        {
            $aConfig = $this->aCallableOptions[$sClassName];
            $aCommonConfig = key_exists('*', $aConfig) ? $aConfig['*'] : [];

            $aMethods = [];
            foreach($xCallableObject->getMethods() as $sMethodName)
            {
                // Specific options for this method
                $aMethodConfig = key_exists($sMethodName, $aConfig) ?
                    array_merge($aCommonConfig, $aConfig[$sMethodName]) : $aCommonConfig;
                $aMethods[] = [
                    'name' => $sMethodName,
                    'config' => $aMethodConfig,
                ];
            }

            $sCode .= $this->render('jaxon::support/object.js', [
                'sPrefix' => $sPrefix,
                'sClass' => $xCallableObject->getJsName(),
                'aMethods' => $aMethods,
            ]);
        }

        return $sCode;
    }
}
