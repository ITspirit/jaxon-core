<?php

/**
 * Alert.php - Interface for alert messages.
 *
 * @package jaxon-dialogs
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2016 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Ui\Dialogs;

class Alert implements \Jaxon\Contracts\Dialogs\Alert
{
    use \Jaxon\Features\Dialogs\Alert;

    /**
     * Print an alert message.
     *
     * @param string              $message              The text of the message
     *
     * @return string|void
     */
    protected function alert($message)
    {
        if($this->getReturn())
        {
            return 'alert(' . $message . ')';
        }
    }

    /**
     * Print a success message.
     *
     * @param string              $message              The text of the message
     * @param string|null         $title                The title of the message
     *
     * @return string|void
     */
    public function success($message, $title = null)
    {
        return $this->alert($message);
    }

    /**
     * Print an information message.
     *
     * @param string              $message              The text of the message
     * @param string|null         $title                The title of the message
     *
     * @return string|void
     */
    public function info($message, $title = null)
    {
        return $this->alert($message);
    }

    /**
     * Print a warning message.
     *
     * @param string              $message              The text of the message
     * @param string|null         $title                The title of the message
     *
     * @return string|void
     */
    public function warning($message, $title = null)
    {
        return $this->alert($message);
    }

    /**
     * Print an error message.
     *
     * @param string              $message              The text of the message
     * @param string|null         $title                The title of the message
     *
     * @return string|void
     */
    public function error($message, $title = null)
    {
        return $this->alert($message);
    }
}