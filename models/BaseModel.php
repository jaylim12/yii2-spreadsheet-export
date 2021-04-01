<?php

namespace jaylim12\models;

use Yii;
use yii\db\Query;
use yii\db\Connection;
use yii\base\Component;
use yii\base\InvalidConfigException;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

abstract class BaseModel extends Component
{
    /**
     * @var int query batch size
     */
    public $batchSize = 100;

    private $_colAttrs;
    private $_colSize;

    protected $_db;

    abstract public function fields();

    public function setDb($array)
    {
        if ($array instanceof Connection) {
            $this->_db = $array;
            return ;
        }

        $this->_db = Yii::createObject($array);
    }

    public function getDb()
    {
        if (!isset($this->_db)) {
            throw new InvalidConfigException('db connection has not been configured.');
        }

        if (!($this->_db instanceof Connection)) {
            throw new InvalidConfigException('Invalid db connection.');
        }

        return $this->_db;
    }

    public function getAttributes()
    {
        $this->generateFields();

        return array_keys($this->_colAttrs);
    }

    public function getColumnSize()
    {
        if (isset($this->_colSize)) {
            return $this->_colSize;
        }

        return $this->_colSize = count($this->getAttributes());
    }

    private function generateFields()
    {
        if (isset($this->_colAttrs)) {
            return ;
        }

        $column = 1;
        foreach ($this->fields() as $key => $value) {
            if (is_numeric($key)) {
                $this->_colAttrs[$value] = Yii::createObject([
                    'class'  => 'jaylim12\spreadsheet\ColumnAttribute',
                    'title'  => $value,
                    'column' => $column,
                ]);
            } else {
                $value['class']  = 'jaylim12\spreadsheet\ColumnAttribute';
                $value['column'] = $column;
                if (!isset($value['title'])) {
                    $value['title'] = $key;
                }
                $this->_colAttrs[$key]   = Yii::createObject($value);
            }
            ++$column;
        }
    }

    public function getColumnAttributes()
    {
        $this->generateFields();

        return $this->_colAttrs;
    }

    public function hasColumnAttribute($attribute)
    {
        $this->generateFields();

        return array_key_exists($attribute, $this->_colAttrs);
    }

    public function getColumnAttribute($attribute)
    {
        $this->generateFields();

        if (!array_key_exists($attribute, $this->_colAttrs)) {
            throw new InvalidConfigException("Invalid attribute {$attribute}");
        }

        return $this->_colAttrs[$attribute];
    }

    abstract public function getQuery();

    public function each()
    {
        $query = $this->getQuery();
        if (!($query instanceof Query)) {
            throw new InvalidConfigException('Invalid query.');
        }

        return $query->each($this->batchSize, $this->db);
    }

    public function count()
    {
        $query = $this->getQuery();
        return $query->count('*', $this->db);
    }
}
