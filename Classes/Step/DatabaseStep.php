<?php
namespace Neos\Setup\Step;

/*
 * This file is part of the Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Utility\Arrays;
use Neos\Flow\Validation\Validator\NotEmptyValidator;
use Neos\Form\Core\Model\FormDefinition;
use Neos\Setup\Exception as SetupException;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStep extends AbstractStep
{
    /**
     * @var \Neos\Flow\Configuration\Source\YamlSource
     * @Flow\Inject
     */
    protected $configurationSource;

    /**
     * @var \Neos\Flow\Security\Policy\PolicyService
     * @Flow\Inject
     */
    protected $policyService;

    /**
     * Returns the form definitions for the step
     *
     * @param FormDefinition $formDefinition
     * @return void
     */
    protected function buildForm(FormDefinition $formDefinition)
    {
        $page1 = $formDefinition->createPage('page1');
        $page1->setRenderingOption('header', 'Configure database');

        $introduction = $page1->createElement('introduction', 'Neos.Form:StaticText');
        $introduction->setProperty('text', 'Please enter database details below:');

        $connectionSection = $page1->createElement('connectionSection', 'Neos.Form:Section');
        $connectionSection->setLabel('Connection');

        $databaseDriver = $connectionSection->createElement('driver', 'Neos.Form:SingleSelectDropdown');
        $databaseDriver->setLabel('DB Driver');
        $databaseDriver->setProperty('options', $this->getAvailableDrivers());
        $databaseDriver->setDefaultValue(Arrays::getValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.driver'));
        $databaseDriver->addValidator(new NotEmptyValidator());

        $databaseUser = $connectionSection->createElement('user', 'Neos.Form:SingleLineText');
        $databaseUser->setLabel('DB Username');
        $databaseUser->setDefaultValue(Arrays::getValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.user'));
        $databaseUser->addValidator(new NotEmptyValidator());

        $databasePassword = $connectionSection->createElement('password', 'Neos.Form:Password');
        $databasePassword->setLabel('DB Password');
        $databasePassword->setDefaultValue(Arrays::getValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.password'));

        $databaseHost = $connectionSection->createElement('host', 'Neos.Form:SingleLineText');
        $databaseHost->setLabel('DB Host');
        $defaultHost = Arrays::getValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.host');
        if ($defaultHost === null) {
            $defaultHost = '127.0.0.1';
        }
        $databaseHost->setDefaultValue($defaultHost);
        $databaseHost->addValidator(new NotEmptyValidator());

        $databaseSection = $page1->createElement('databaseSection', 'Neos.Form:Section');
        $databaseSection->setLabel('Database');

        $databaseName = $databaseSection->createElement('dbname', 'Neos.Setup:DatabaseSelector');
        $databaseName->setLabel('DB Name');
        $databaseName->setProperty('driverDropdownFieldId', $databaseDriver->getUniqueIdentifier());
        $databaseName->setProperty('userFieldId', $databaseUser->getUniqueIdentifier());
        $databaseName->setProperty('passwordFieldId', $databasePassword->getUniqueIdentifier());
        $databaseName->setProperty('hostFieldId', $databaseHost->getUniqueIdentifier());
        $databaseName->setDefaultValue(Arrays::getValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.dbname'));
        $databaseName->addValidator(new NotEmptyValidator());
    }

    /**
     * This method is called when the form of this step has been submitted
     *
     * @param array $formValues
     * @return void
     * @throws \Exception
     */
    public function postProcessFormValues(array $formValues)
    {
        $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.driver', $formValues['driver']);
        $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.dbname', $formValues['dbname']);
        $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.user', $formValues['user']);
        $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.password', $formValues['password']);
        $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.host', $formValues['host']);
        // Postgres natively supports multibyte-UTF8. It does not know utf8mb4, which is the default now
        if ($formValues['driver'] === 'pdo_pgsql') {
            $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Flow.persistence.backendOptions.charset', 'utf8');
        }
        $this->configurationSource->save(FLOW_PATH_CONFIGURATION . ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $this->distributionSettings);

        $this->configurationManager->refreshConfiguration();

        $settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow');
        $connectionSettings = $settings['persistence']['backendOptions'];
        try {
            $this->connectToDatabase($connectionSettings);
        } catch (\Exception $exception) {
            if (!$exception instanceof DBALException && !$exception instanceof \PDOException) {
                throw $exception;
            }
            try {
                $this->createDatabase($connectionSettings, $formValues['dbname']);
            } catch (DBALException $exception) {
                throw new SetupException(sprintf('Database "%s" could not be created. Please check the permissions for user "%s". DBAL Exception: "%s"', $formValues['dbname'], $formValues['user'], $exception->getMessage()), 1351000841, $exception);
            } catch (\PDOException $exception) {
                throw new SetupException(sprintf('Database "%s" could not be created. Please check the permissions for user "%s". PDO Exception: "%s"', $formValues['dbname'], $formValues['user'], $exception->getMessage()), 1346758663, $exception);
            }
            try {
                $this->connectToDatabase($connectionSettings);
            } catch (DBALException $exception) {
                throw new SetupException(sprintf('Could not connect to database "%s". Please check the permissions for user "%s". DBAL Exception: "%s"', $formValues['dbname'], $formValues['user'], $exception->getMessage()), 1351000864);
            } catch (\PDOException $exception) {
                throw new SetupException(sprintf('Could not connect to database "%s". Please check the permissions for user "%s". PDO Exception: "%s"', $formValues['dbname'], $formValues['user'], $exception->getMessage()), 1346758737);
            }
        }

        $migrationExecuted = Scripts::executeCommand('neos.flow:doctrine:migrate', $settings, false);
        if ($migrationExecuted !== true) {
            throw new SetupException(sprintf('Could not execute database migrations. Please check the permissions for user "%s" and execute "./flow neos.flow:doctrine:migrate" manually.', $formValues['user']), 1346759486);
        }

        $this->resetPolicyRolesCacheAfterDatabaseChanges();
    }

    /**
     * A changed database needs to resynchronize the roles
     *
     * @return void
     */
    public function resetPolicyRolesCacheAfterDatabaseChanges()
    {
        $this->policyService->reset();
    }

    /**
     * Tries to connect to the database using the specified $connectionSettings
     *
     * @param array $connectionSettings array in the format array('user' => 'dbuser', 'password' => 'dbpassword', 'host' => 'dbhost', 'dbname' => 'dbname')
     * @return void
     * @throws \PDOException if the connection fails
     */
    protected function connectToDatabase(array $connectionSettings)
    {
        $connection = DriverManager::getConnection($connectionSettings);
        $connection->connect();
    }

    /**
     * Connects to the database using the specified $connectionSettings
     * and tries to create a database named $databaseName.
     *
     * @param array $connectionSettings array in the format array('user' => 'dbuser', 'password' => 'dbpassword', 'host' => 'dbhost', 'dbname' => 'dbname')
     * @param string $databaseName name of the database to create
     * @throws \Neos\Setup\Exception
     * @return void
     */
    protected function createDatabase(array $connectionSettings, $databaseName)
    {
        unset($connectionSettings['dbname']);
        $connection = DriverManager::getConnection($connectionSettings);
        $databasePlatform = $connection->getSchemaManager()->getDatabasePlatform();
        $databaseName = $databasePlatform->quoteIdentifier($databaseName);
        // we are not using $databasePlatform->getCreateDatabaseSQL() below since we want to specify charset and collation
        if ($databasePlatform instanceof MySqlPlatform) {
            $connection->executeUpdate(sprintf('CREATE DATABASE %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $databaseName));
        } elseif ($databasePlatform instanceof PostgreSqlPlatform) {
            $connection->executeUpdate(sprintf('CREATE DATABASE %s WITH ENCODING = %s', $databaseName, "'UTF8'"));
        } else {
            throw new SetupException(sprintf('The given database platform "%s" is not supported.', $databasePlatform->getName()), 1386454885);
        }
        $connection->close();
    }

    /**
     * Return an array with driver.
     *
     * This is built on supported drivers (those we actually provide migration for in Flow and Neos), filtered to show
     * only available options (needed extension loaded, actually usable in current setup).
     *
     * @return array
     */
    protected function getAvailableDrivers()
    {
        $supportedDrivers = [
            'pdo_mysql' => 'MySQL/MariaDB via PDO',
            'mysqli' => 'MySQL/MariaDB via mysqli',
            'pdo_pgsql' => 'PostgreSQL via PDO'
        ];

        $availableDrivers = [];
        foreach ($supportedDrivers as $driver => $label) {
            if (extension_loaded($driver)) {
                $availableDrivers[$driver] = $label;
            }
        }

        return $availableDrivers;
    }
}
