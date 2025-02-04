<?php

/**
 * Template.php - Trait for template functions
 *
 * @package jaxon-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2016 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Traits;

use Jaxon\DI\Container;

trait Template
{
    /**
     * Add a namespace to the template system
     *
     * @param string        $sNamespace         The namespace name
     * @param string        $sDirectory         The namespace directory
     * @param string        $sExtension         The extension to append to template names
     *
     * @return void
     */
    public function addViewNamespace($sNamespace, $sDirectory, $sExtension = '')
    {
        return Container::getInstance()->getTemplate()->addNamespace($sNamespace, $sDirectory, $sExtension);
    }

    /**
     * Set a new directory for pagination templates
     *
     * @param string        $sDirectory             The directory path
     *
     * @return void
     */
    public function setPaginationDir($sDirectory)
    {
        return Container::getInstance()->getTemplate()->setPaginationDir($sDirectory);
    }
}
