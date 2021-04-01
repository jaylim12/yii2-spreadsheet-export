<?php

namespace jaylim12\models;

use Yii;
use yii\base\NotSupportedException;

class RawQueryModel extends BaseModel
{
    public $sql;

    protected $_dataReader;
    protected $_command;
    protected $_fields = [];

    public function getDataReader()
    {
        if (isset($this->_dataReader)) {
            return $this->_dataReader;
        }

        $this->_dataReader = $this->createCommand()->query();

        return $this->_dataReader;
    }

    public function createCommand()
    {
        if (isset($this->_command)) {
            return $this->_command;
        }

        $db = $this->getDb();
        return $this->_command = $db->createCommand($this->sql);
    }

    public function setFields($fields)
    {
        $this->_fields = $fields;
    }

    public function fields()
    {
        return $this->_fields;
    }

    public function getQuery()
    {
        throw new NotSupportedException();
    }

    public function each()
    {
        return Yii::createObject([
            'class'   => 'jaylim12\db\BatchRawQueryResult',
            'each'    => true,
            'command' => $this->createCommand(),
        ]);
    }

    public function count()
    {
        return $this->getDataReader()->count();
    }
}
