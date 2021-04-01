<?php

namespace jaylim12\spreadsheet;

use yii\base\BaseObject;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;


class ColumnAttribute extends BaseObject
{
    public $title;
    public $type;
    public $column;
}
