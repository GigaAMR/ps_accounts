<?php

namespace PrestaShop\Module\PsAccounts\Service\Sentry;

use Raven_Client;

/**
 * Inheritance allow us to check data generated by Raven and filter errors
 * that are not related to the module.
 * Raven does not filter errors by itself depending on the appPath and any
 * excludedAppPaths, but declares what phase of the stack trace is outside the app.
 * We use this data to allow each module filtering their own errors.
 *
 * IMPORTANT NOTE: This class is present in this module during the
 * stabilisation phase, and will be moved later in a library.
 */
class ModuleFilteredRavenClient extends Raven_Client
{
    /**
     * @var string[]|null
     */
    protected $excluded_domains;

    /**
     * @param mixed $data
     * @param mixed $stack
     * @param mixed $vars
     *
     * @return array|mixed|null
     *
     * @throws \Exception
     */
    public function capture($data, $stack = null, $vars = null)
    {
        /*
            Content of $data:
            array:2 [▼
            "exception" => array:1 [▼
                "values" => array:1 [▼
                    0 => array:3 [▼
                        "value" => "Class 'DogeInPsFacebook' not found"
                        "type" => "Error"
                        "stacktrace" => array:1 [▼
                            "frames" => array:4 [▼
                                0 => array:7 [▼
                                    "filename" => "index.php"
                                    "lineno" => 93
                                    "function" => null
                                    "pre_context" => array:5 [▶]
                                    "context_line" => "    Dispatcher::getInstance()->dispatch();"
                                    "post_context" => array:2 [▶]
                                    "in_app" => false
                    1 => array:3 [▼
                        [Can be defined when a subexception is set]

        */
        if (!isset($data['exception']['values'][0]['stacktrace']['frames'])) {
            return null;
        }

        if ($this->isErrorFilteredByContext()) {
            return null;
        }

        $allowCapture = false;
        foreach ($data['exception']['values'] as $errorValues) {
            $allowCapture = $allowCapture || $this->isErrorInApp($errorValues);
        }

        if (!$allowCapture) {
            return null;
        }

        return parent::capture($data, $stack, $vars);
    }

    /**
     * @return self
     */
    public function setExcludedDomains(array $domains)
    {
        $this->excluded_domains = $domains;

        return $this;
    }

    /**
     * @return bool
     */
    private function isErrorInApp(array $data)
    {
        $lastFrame = end($data['stacktrace']['frames']);
        $lastFrameIsInApp = (isset($lastFrame['in_app']) && $lastFrame['in_app']);

        return $lastFrameIsInApp;
    }

    /**
     * Check the conditions in which the error is thrown, so we can apply filters
     *
     * @return bool
     */
    private function isErrorFilteredByContext()
    {
        if ($this->excluded_domains && !empty($_SERVER['REMOTE_ADDR'])) {
            foreach ($this->excluded_domains as $domain) {
                if (strpos($_SERVER['REMOTE_ADDR'], $domain) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}