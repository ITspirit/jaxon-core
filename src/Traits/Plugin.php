<?php

/**
 * Sentry.php - Upload Trait
 *
 * The Jaxon class uses a modular plug-in system to facilitate the processing
 * of special Ajax requests made by a PHP page.
 * It generates Javascript that the page must include in order to make requests.
 * It handles the output of response commands (see <Jaxon\Response\Response>).
 * Many flags and settings can be adjusted to effect the behavior of the Jaxon class
 * as well as the client-side javascript.
 *
 * @package jaxon-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2017 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Traits;

use Jaxon\Request\Support\CallableRepository;
use \Jaxon\Request\Plugin\CallableClass;
use \Jaxon\Request\Plugin\CallableDir;
use \Jaxon\Request\Plugin\UserFunction;
use \Jaxon\Request\Plugin\FileUpload;

trait Plugin
{
    /**
     * Get the plugin manager
     *
     * @return Jaxon\Plugin\Manager
     */
    abstract public function getPluginManager();

    /**
     * Get the DI container
     *
     * @return Jaxon\DI\Container
     */
    abstract public function di();

    /**
     * Register a plugin
     *
     * Below is a table for priorities and their description:
     * - 0 thru 999: Plugins that are part of or extensions to the jaxon core
     * - 1000 thru 8999: User created plugins, typically, these plugins don't care about order
     * - 9000 thru 9999: Plugins that generally need to be last or near the end of the plugin list
     *
     * @param Jaxon\Plugin\Plugin   $xPlugin        An instance of a plugin
     * @param integer               $nPriority      The plugin priority, used to order the plugins
     *
     * @return void
     */
    public function registerPlugin(\Jaxon\Plugin\Plugin $xPlugin, $nPriority = 1000)
    {
        $this->getPluginManager()->registerPlugin($xPlugin, $nPriority);
    }

    /**
     * Register the Jaxon request plugins
     *
     * @return void
     */
    public function registerRequestPlugins()
    {
        $callableRepository = $this->di()->get(CallableRepository::class);
        $this->registerPlugin(new CallableClass($callableRepository), 101);
        $this->registerPlugin(new CallableDir($callableRepository), 102);
        $this->registerPlugin(new UserFunction(), 103);
        $this->registerPlugin(new FileUpload(), 104);
    }

    /**
     * Register the Jaxon response plugins
     *
     * @return void
     */
    public function registerResponsePlugins()
    {
        // Register an instance of the JQuery plugin
        $this->registerPlugin(new \Jaxon\Response\Plugin\JQuery(), 700);
    }
}
