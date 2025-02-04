<?php

namespace Jaxon\Response\Plugin;

use Jaxon\Response\Plugin\JQuery\Dom\Element;

class JQuery extends \Jaxon\Plugin\Response
{
    use \Jaxon\Utils\Traits\Config;

    /**
     * Return the name of the plugin.
     *
     * @return string
     */
    public function getName()
    {
        return 'jquery';
    }

    /**
     * Generate a unique hash for each version of the plugin.
     *
     * @return string
     */
    public function generateHash()
    {
        // Use the version number as hash
        return '3.3.0';
    }

    /**
     * Return init script for the Jaxon JQuery plugin.
     *
     * The init code registers the "jquery" handler with the Jaxon javascript library,
     * together with a function wich runs the javascript code generated by the plugin.
     *
     * @return void
     */
    public function getScript()
    {
        return '
jaxon.command.handler.register("jquery", function(args) {
    jaxon.cmd.script.execute(args);
});
';
    }

    /**
     * Create a JQuery Element with a given selector, and link it to the current response.
     *
     * Since this element is linked to a response, its code will be automatically sent to the client.
     * The returned object can be used to call jQuery functions on the selected elements.
     *
     * @param string        $sSelector            The jQuery selector
     * @param string        $sContext             A context associated to the selector
     *
     * @return Element
     */
    public function element($sSelector = '', $sContext = '')
    {
        $xElement = new Element($sSelector, $sContext);
        $this->addCommand(array('cmd' => 'jquery'), $xElement);
        return $xElement;
    }
}
