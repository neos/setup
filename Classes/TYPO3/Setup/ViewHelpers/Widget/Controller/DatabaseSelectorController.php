<?php
namespace TYPO3\Setup\ViewHelpers\Widget\Controller;

/*
 * This file is part of the TYPO3.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;

/**
 * Controller for the DatabaseSelector Fluid Widget
 */
class DatabaseSelectorController extends \Neos\FluidAdaptor\Core\Widget\AbstractWidgetController
{
    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

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
        $this->response->setHeader('Content-Type', 'application/json');
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
        $this->response->setHeader('Content-Type', 'application/json');
        $connectionSettings = $this->buildConnectionSettingsArray($driver, $user, $password, $host);
        $connectionSettings['dbname'] = $databaseName;
        try {
            $connection = $this->getConnectionAndConnect($connectionSettings);
            $databasePlatform = $connection->getDatabasePlatform();
            if ($databasePlatform instanceof MySqlPlatform) {
                $queryResult = $connection->executeQuery('SHOW VARIABLES LIKE \'character_set_database\'')->fetch();
                $databaseCharacterSet = strtolower($queryResult['Value']);
            } elseif ($databasePlatform instanceof PostgreSqlPlatform) {
                $queryResult = $connection->executeQuery('SELECT pg_encoding_to_char(encoding) FROM pg_database WHERE datname = ?', [$databaseName])->fetch();
                $databaseCharacterSet = strtolower($queryResult['pg_encoding_to_char']);
            } else {
                $result = ['level' => 'error', 'message' => sprintf('Only MySQL/MariaDB and PostgreSQL are supported, the selected database is "%s".', $databasePlatform->getName())];
            }
            if (isset($databaseCharacterSet)) {
                if ($databaseCharacterSet === 'utf8') {
                    $result = ['level' => 'notice', 'message' => 'The selected database\'s character set is set to "utf8" which is the recommended setting.'];
                } else {
                    $result = [
                        'level' => 'warning',
                        'message' => sprintf('The selected database\'s character set is "%s", however changing it to "utf8" is urgently recommended. This setup tool won\'t do this for you.', $databaseCharacterSet)
                    ];
                }
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

            return $connectionSettings;
        } else {
            unset($connectionSettings['dbname']);

            return $connectionSettings;
        }
    }

    /**
     * @param array $connectionSettings
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnectionAndConnect(array $connectionSettings)
    {
        $connection = \Doctrine\DBAL\DriverManager::getConnection($connectionSettings);
        $connection->connect();

        return $connection;
    }
}
