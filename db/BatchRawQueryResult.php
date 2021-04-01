<?php

namespace jaylim12\db;

use Yii;
use yii\db\Command;
use yii\db\Connection;
use yii\db\DataReader;
use yii\db\BatchQueryResult;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

class BatchRawQueryResult extends BaseObject implements \Iterator
{
    /**
     * @var int the number of rows to be returned in each batch.
     */
    public $batchSize = 100;
    /**
     * @var bool whether to return a single row during each iteration.
     * If false, a whole batch of rows will be returned in each iteration.
     */
    public $each = false;

    /**
     * @var Command the sql command.
     */
    private $_command;

    /**
     * @var DataReader the data reader associated with this batch query.
     */
    private $_dataReader;
    /**
     * @var array the data retrieved in the current batch
     */
    private $_batch;
    /**
     * @var mixed the value for the current iteration
     */
    private $_value;
    /**
     * @var string|int the key for the current iteration
     */
    private $_key;
    /**
     * @var int MSSQL error code for exception that is thrown when last batch is size less than specified batch size
     * @see https://github.com/yiisoft/yii2/issues/10023
     */
    private $mssqlNoMoreRowsErrorCode = -13;


    /**
     * Destructor.
     */
    public function __destruct()
    {
        // make sure cursor is closed
        $this->reset();
    }

    public function setCommand(Command $command)
    {
        $this->_command = $command;
    }

    public function getCommand()
    {
        if (empty($this->_command)) {
            throw new InvalidConfigException("Command has not been configured.");
        }

        return $this->_command;
    }

    /**
     * Resets the batch query.
     * This method will clean up the existing batch query so that a new batch query can be performed.
     */
    public function reset()
    {
        if ($this->_dataReader !== null) {
            $this->_dataReader->close();
        }
        $this->_dataReader = null;
        $this->_batch = null;
        $this->_value = null;
        $this->_key = null;
    }

    /**
     * Resets the iterator to the initial state.
     * This method is required by the interface [[\Iterator]].
     */
    public function rewind()
    {
        $this->reset();
        $this->next();
    }

    /**
     * Moves the internal pointer to the next dataset.
     * This method is required by the interface [[\Iterator]].
     */
    public function next()
    {
        if ($this->_batch === null || !$this->each || $this->each && next($this->_batch) === false) {
            $this->_batch = $this->fetchData();
            reset($this->_batch);
        }

        if ($this->each) {
            $this->_value = current($this->_batch);
            if (key($this->_batch) !== null) {
                $this->_key = $this->_key === null ? 0 : $this->_key + 1;
            } else {
                $this->_key = null;
            }
        } else {
            $this->_value = $this->_batch;
            $this->_key = $this->_key === null ? 0 : $this->_key + 1;
        }
    }

    /**
     * Fetches the next batch of data.
     * @return array the data fetched
     * @throws Exception
     */
    protected function fetchData()
    {
        if ($this->_dataReader === null) {
            $this->_dataReader = $this->getCommand()->query();
        }

        return $this->getRows();
    }

    /**
     * Reads and collects rows for batch
     * @return array
     * @since 2.0.23
     */
    protected function getRows()
    {
        $rows = [];
        $count = 0;

        try {
            while ($count++ < $this->batchSize && ($row = $this->_dataReader->read())) {
                $rows[] = $row;
            }
        } catch (\PDOException $e) {
            $errorCode = isset($e->errorInfo[1]) ? $e->errorInfo[1] : null;
            if ($this->getDbDriverName() !== 'sqlsrv' || $errorCode !== $this->mssqlNoMoreRowsErrorCode) {
                throw $e;
            }
        }

        return $rows;
    }

    /**
     * Returns the index of the current dataset.
     * This method is required by the interface [[\Iterator]].
     * @return int the index of the current row.
     */
    public function key()
    {
        return $this->_key;
    }

    /**
     * Returns the current dataset.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current dataset.
     */
    public function current()
    {
        return $this->_value;
    }

    /**
     * Returns whether there is a valid dataset at the current position.
     * This method is required by the interface [[\Iterator]].
     * @return bool whether there is a valid dataset at the current position.
     */
    public function valid()
    {
        return !empty($this->_batch);
    }

    /**
     * Gets db driver name from the db connection that is passed to the `batch()`, if it is not passed it uses
     * connection from the active record model
     * @return string|null
     */
    private function getDbDriverName()
    {
        if (isset($this->db->driverName)) {
            return $this->db->driverName;
        }

        if (!empty($this->_batch)) {
            $key = array_keys($this->_batch)[0];
            if (isset($this->_batch[$key]->db->driverName)) {
                return $this->_batch[$key]->db->driverName;
            }
        }

        return null;
    }
}
