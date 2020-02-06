<?php
namespace Neos\Setup\Core;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Files;

/**
 * This class checks the basic requirements and returns an error object in case
 * of missing requirements.
 *
 * @Flow\Proxy(false)
 * @Flow\Scope("singleton")
 */
class BasicRequirements
{
    /**
     * List of required PHP extensions and their error key if the extension was not found
     *
     * @var array
     */
    protected $requiredExtensions = [
        'Reflection' => 1329403179,
        'tokenizer' => 1329403180,
        'json' => 1329403181,
        'session' => 1329403182,
        'ctype' => 1329403183,
        'dom' => 1329403184,
        'date' => 1329403185,
        'libxml' => 1329403186,
        'xmlreader' => 1329403187,
        'xmlwriter' => 1329403188,
        'SimpleXML' => 1329403189,
        'openssl' => 1329403190,
        'pcre' => 1329403191,
        'zlib' => 1329403192,
        'filter' => 1329403193,
        'SPL' => 1329403194,
        'iconv' => 1329403195,
        'PDO' => 1329403196,
        'hash' => 1329403198
    ];

    /**
     * List of required PHP functions and their error key if the function was not found
     *
     * @var array
     */
    protected $requiredFunctions = [
        'exec' => 1330707108,
        'shell_exec' => 1330707133,
        'escapeshellcmd' => 1330707156,
        'escapeshellarg' => 1330707177
    ];

    /**
     * List of folders which need to be writable
     *
     * @var array
     */
    protected $requiredWritableFolders = ['Configuration', 'Data', 'Packages', 'Web/_Resources'];

    /**
     * Ensure that the environment and file permission requirements are fulfilled.
     *
     * @return \Neos\Error\Messages\Error if requirements are fulfilled, NULL is returned. else, an Error object is returned.
     */
    public function findError()
    {
        $requiredEnvironmentError = $this->ensureRequiredEnvironment();
        if ($requiredEnvironmentError !== null) {
            return $this->setErrorTitle($requiredEnvironmentError, 'Environment requirements not fulfilled');
        }

        $filePermissionsError = $this->checkFilePermissions();
        if ($filePermissionsError !== null) {
            return $this->setErrorTitle($filePermissionsError, 'Error with file system permissions');
        }

        return null;
    }

    /**
     * return a new error object which has all options like $error except the $title overridden.
     *
     * @param \Neos\Error\Messages\Error $error
     * @param string $title
     * @return \Neos\Error\Messages\Error
     */
    protected function setErrorTitle(Error $error, $title)
    {
        return new Error($error->getMessage(), $error->getCode(), $error->getArguments(), $title);
    }

    /**
     * Checks PHP version and other parameters of the environment
     *
     * @return mixed
     */
    protected function ensureRequiredEnvironment()
    {
        if (version_compare(phpversion(), \Neos\Flow\Core\Bootstrap::MINIMUM_PHP_VERSION, '<')) {
            return new Error('Flow requires PHP version %s or higher but your installed version is currently %s.', 1172215790, [\Neos\Flow\Core\Bootstrap::MINIMUM_PHP_VERSION, phpversion()]);
        }
        if (!extension_loaded('mbstring')) {
            return new Error('Flow requires the PHP extension "mbstring" to be available', 1207148809);
        }
        if (DIRECTORY_SEPARATOR !== '/' && PHP_WINDOWS_VERSION_MAJOR < 6) {
            return new Error('Flow does not support Windows versions older than Windows Vista or Windows Server 2008, because they lack proper support for symbolic links.', 1312463704);
        }
        foreach ($this->requiredExtensions as $extension => $errorKey) {
            if (!extension_loaded($extension)) {
                return new Error('Flow requires the PHP extension "%s" to be available.', $errorKey, [$extension]);
            }
        }
        foreach ($this->requiredFunctions as $function => $errorKey) {
            if (!function_exists($function)) {
                return new Error('Flow requires the PHP function "%s" to be available.', $errorKey, [$function]);
            }
        }

        // TODO: Check for database drivers? PDO::getAvailableDrivers()

        $method = new \ReflectionMethod(__CLASS__, __FUNCTION__);
        $docComment = $method->getDocComment();
        if ($docComment === false || $docComment === '') {
            return new Error('Reflection of doc comments is not supported by your PHP setup. Please check if you have installed an accelerator which removes doc comments.', 1329405326);
        }

        set_time_limit(0);

        if (ini_get('session.auto_start')) {
            return new Error('Flow requires the PHP setting "session.auto_start" set to off.', 1224003190);
        }

        return null;
    }

    /**
     * Check write permissions for folders used for writing files
     *
     * @return mixed
     */
    protected function checkFilePermissions()
    {
        foreach ($this->requiredWritableFolders as $folder) {
            $folderPath = FLOW_PATH_ROOT . $folder;
            if (!is_dir($folderPath) && !Files::is_link($folderPath)) {
                try {
                    Files::createDirectoryRecursively($folderPath);
                } catch (\Neos\Flow\Utility\Exception $exception) {
                    return new Error('Unable to create folder "%s". Check your file permissions (did you use flow:core:setfilepermissions?).', 1330363887, [$folderPath]);
                }
            }
            if (!is_writable($folderPath)) {
                return new Error('The folder "%s" is not writable. Check your file permissions (did you use flow:core:setfilepermissions?)', 1330372964, [$folderPath]);
            }
        }

        return null;
    }
}
