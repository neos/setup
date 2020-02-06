<?php
namespace Neos\Setup\ViewHelpers\Widget\Controller;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\FluidAdaptor\Core\Widget\AbstractWidgetController;

/**
 * Controller for the DatabaseSelector Fluid Widget
 */
class DatabaseSelectorController extends AbstractWidgetController
{
    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    const MINIMUM_MYSQL_VERSION = '5.7';
    const MINIMUM_MARIA_DB_VERSION = '10.2.2';

    /**
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('driverDropdownFieldId', $this->widgetConfiguration['driverDropdownFieldId']);
        $this->view->assign('userFieldId', $this->widgetConfiguration['userFieldId']);
        $this->view->assign('passwordFieldId', $this->widgetConfiguration['passwordFieldId']);
        $this->view->assign('hostFieldId', $this->widgetConfiguration['hostFieldId']);
        $this->view->assign('dbNameTextFieldId', $this->widgetConfiguration['dbNameTextFieldId']);
        $this->view->assign('dbNameDropdownFieldId', $this->widgetConfiguration['dbNameDropdownFieldId']);
        $this->view->assign('statusContainerId', $this->widgetConfiguration['statusContainerId']);
        $this->view->assign('metadataStatusContainerId', $this->widgetConfiguration['metadataStatusContainerId']);
    }

    /**
     * @param string $driver
     * @param string $user
     * @param string $password
     * @param string $host
     * @return string
     */
    public function checkConnectionAction($driver, $user, $password, $host)
    {
        $this->response->setContentType('application/json');
        $connectionSettings = $this->buildConnectionSettingsArray($driver, $user, $password, $host);
        try {
            $connection = $this->getConnectionAndConnect($connectionSettings);
            $databases = $connection->getSchemaManager()->listDatabases();
            $result = ['success' => true, 'databases' => $databases];
        } catch (\PDOException $exception) {
            $result = ['success' => false, 'errorMessage' => $exception->getMessage(), 'errorCode' => $exception->getCode()];
        } catch (\Doctrine\DBAL\DBALException $exception) {
            $result = ['success' => false, 'errorMessage' => $exception->getMessage(), 'errorCode' => $exception->getCode()];
        } catch (\Exception $exception) {
            $result = ['success' => false, 'errorMessage' => 'Unexpected exception (check logs)', 'errorCode' => $exception->getCode()];
        }

        return json_encode($result);
    }

    /**
     * This fetches information about the database provided, in particular the charset being used.
     * Depending on whether it is utf8 or not, the (JSON-) response is layed out accordingly.
     *
     * @param string $driver
     * @param string $user
     * @param string $password
     * @param string $host
     * @param string $databaseName
     * @return string
     */
    public function getMetadataAction($driver, $user, $password, $host, $databaseName)
    {
        $this->response->setContentType('application/json');
        $connectionSettings = $this->buildConnectionSettingsArray($driver, $user, $password, $host);
        $connectionSettings['dbname'] = $databaseName;
        $result = [];
        try {
            $connection = $this->getConnectionAndConnect($connectionSettings);
            $databasePlatform = $connection->getDatabasePlatform();
            if ($databasePlatform instanceof MySqlPlatform) {
                $databaseVersionQueryResult = $connection->executeQuery('SELECT VERSION()')->fetch();
                $databaseVersion = isset($databaseVersionQueryResult['VERSION()']) ? $databaseVersionQueryResult['VERSION()'] : null;
                if (isset($databaseVersion) && $this->databaseSupportsUtf8Mb4($databaseVersion) === false) {
                    $result[] = [
                        'level' => 'error',
                        'message' => sprintf('The minimum required version for MySQL is "%s" or "%s" for MariaDB.', self::MINIMUM_MYSQL_VERSION, self::MINIMUM_MARIA_DB_VERSION)
                    ];
                }

                $charsetQueryResult = $connection->executeQuery('SHOW VARIABLES LIKE \'character_set_database\'')->fetch();
                $databaseCharacterSet = strtolower($charsetQueryResult['Value']);
                if (isset($databaseCharacterSet)) {
                    if ($databaseCharacterSet === 'utf8mb4') {
                        $result[] = ['level' => 'notice', 'message' => 'The selected database\'s character set is set to "utf8mb4" which is the recommended setting for MySQL/MariaDB databases.'];
                    } else {
                        $result[] = [
                            'level' => 'warning',
                            'message' => sprintf('The selected database\'s character set is "%s", however changing it to "utf8mb4" is urgently recommended. This setup tool won\'t do this for you.', $databaseCharacterSet)
                        ];
                    }
                }
            } elseif ($databasePlatform instanceof PostgreSqlPlatform) {
                $charsetQueryResult = $connection->executeQuery('SELECT pg_encoding_to_char(encoding) FROM pg_database WHERE datname = ?', [$databaseName])->fetch();
                $databaseCharacterSet = strtolower($charsetQueryResult['pg_encoding_to_char']);
                if (isset($databaseCharacterSet)) {
                    if ($databaseCharacterSet === 'utf8') {
                        $result[] = ['level' => 'notice', 'message' => 'The selected database\'s character set is set to "utf8" which is the recommended setting for PostgreSQL databases.'];
                    } else {
                        $result[] = [
                            'level' => 'warning',
                            'message' => sprintf('The selected database\'s character set is "%s", however changing it to "utf8" is urgently recommended. This setup tool won\'t do this for you.', $databaseCharacterSet)
                        ];
                    }
                }
            } else {
                $result[] = ['level' => 'error', 'message' => sprintf('Only MySQL/MariaDB and PostgreSQL are supported, the selected database is "%s".', $databasePlatform->getName())];
            }
        } catch (\PDOException $exception) {
            $result = ['level' => 'error', 'message' => $exception->getMessage(), 'errorCode' => $exception->getCode()];
        } catch (\Doctrine\DBAL\DBALException $exception) {
            $result = ['level' => 'error', 'message' => $exception->getMessage(), 'errorCode' => $exception->getCode()];
        } catch (\Exception $exception) {
            $result = ['level' => 'error', 'message' => 'Unexpected exception', 'errorCode' => $exception->getCode()];
        }

        return json_encode($result);
    }

    /**
     * @param string $driver
     * @param string $user
     * @param string $password
     * @param string $host
     * @return array
     */
    protected function buildConnectionSettingsArray($driver, $user, $password, $host)
    {
        $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow');
        $connectionSettings = $settings['persistence']['backendOptions'];
        $connectionSettings['driver'] = $driver;
        $connectionSettings['user'] = $user;
        $connectionSettings['password'] = $password;
        $connectionSettings['host'] = $host;
        if ($connectionSettings['driver'] === 'pdo_pgsql') {
            $connectionSettings['dbname'] = 'template1';
            // Postgres natively supports multibyte-UTF8. It does not know utf8mb4
            $connectionSettings['charset'] = 'utf8';

            return $connectionSettings;
        }

        unset($connectionSettings['dbname']);

        return $connectionSettings;
    }

    /**
     * @param array $connectionSettings
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnectionAndConnect(array $connectionSettings)
    {
        $connection = DriverManager::getConnection($connectionSettings);
        $connection->connect();

        return $connection;
    }

    /**
     * Check if MySQL based database supports utf8mb4 character set.
     *
     * @param string $databaseVersion
     * @return bool
     */
    protected function databaseSupportsUtf8Mb4(string $databaseVersion): bool
    {
        if (strpos($databaseVersion, '-MariaDB') !== false &&
            version_compare($databaseVersion, self::MINIMUM_MARIA_DB_VERSION) === -1
        ) {
            return false;
        }

        if (preg_match('([a-zA-Z])', $databaseVersion) === 0 &&
            version_compare($databaseVersion, self::MINIMUM_MYSQL_VERSION) === -1
        ) {
            return false;
        }

        return true;
    }
}
