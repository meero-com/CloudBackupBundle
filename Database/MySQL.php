<?php
namespace Dizda\CloudBackupBundle\Database;

use Symfony\Component\Process\ProcessUtils;

/**
 * Class MySQL.
 *
 * @author  Jonathan Dizdarevic <dizda@dizda.fr>
 */
class MySQL extends BaseDatabase
{
    const DB_PATH = 'mysql';
    const CONFIGURATION_FILE_NAME = 'mysql.cnf';

    private $database;
    private $auth = '';
    private $fileName;
    private $ignoreTables = '';
    private $params;

    /**
     * DB Auth.
     *
     * @param array $params
     * @param string $basePath
     */
    public function __construct($params, $basePath)
    {
        parent::__construct($basePath);
        $this->params = $params['mysql'];

        $this->preparePath();
        $this->prepareFileName();
        $this->prepareIgnoreTables();
        $this->prepareConfigurationFile();
    }

    /**
     * Prepare a database name and a file dump name for mysqldump command
     */
    protected function prepareFileName()
    {
        if ($this->params['all_databases']) {
            $this->database = '--all-databases';
            $this->fileName = 'all-databases.sql';
        } else {
            $this->database = $this->params['database'];
            $this->fileName = $this->database . '.sql';
        }
    }

    /**
     * Prepare ignore tables attribute for mysqldump command
     */
    protected function prepareIgnoreTables()
    {
        if (isset($this->params['ignore_tables'])) {
            foreach ($this->params['ignore_tables'] as $ignoreTable) {
                if ($this->params['all_databases']) {
                    if (false === strpos($ignoreTable, '.')) {
                        throw new \LogicException(
                            'When dumping all databases both database and table must be specified when ignoring table'
                        );
                    }
                    $this->ignoreTables .= sprintf('--ignore-table=%s ', $ignoreTable);
                } else {
                    $this->ignoreTables .= sprintf('--ignore-table=%s.%s ', $this->params['database'], $ignoreTable);
                }
            }
        }
    }

    /**
     * Prepare mysql configuration file for connection
     */
    protected function prepareConfigurationFile()
    {
        if ($this->params['db_user']) {
            $cnfParams['user']  = $this->params['db_user'];
            $cnfFile            = "[client]\n";

            if ($this->params['db_password']) {
                $cnfParams += array(
                    "password"  => $this->params['db_password'],
                    "host"      => $this->params['db_host'],
                    "port"      => $this->params['db_port']
                );
            }

            foreach ($cnfParams as $key => $value) {
                $cnfFile .= "$key = \"$value\"\n";
            }

            $this->filesystem->dumpFile($this->getConfigurationFilePath(), $cnfFile, 0600);
            $this->auth = sprintf("--defaults-extra-file=\"%s\"", $this->getConfigurationFilePath());
        }
    }

    /**
     * Remove mysql configuration file from backup files
     */
    protected function removeConfigurationFile()
    {
        $this->filesystem->remove($this->getConfigurationFilePath());
    }

    /**
     * Gets mysql configuration file full path
     * @return string
     */
    protected function getConfigurationFilePath()
    {
        return $this->dataPath . static::CONFIGURATION_FILE_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function dump()
    {
        $this->execute($this->getCommand());
        $this->removeConfigurationFile();
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand()
    {
        return sprintf('mysqldump %s %s %s > %s',
            $this->auth,
            $this->database,
            $this->ignoreTables,
            ProcessUtils::escapeArgument($this->dataPath.$this->fileName)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'MySQL';
    }
}
