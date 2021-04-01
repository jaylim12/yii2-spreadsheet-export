<?php

namespace jaylim12\components;

use Yii;
use yii\base\Component;
use jaylim12\models\BaseModel;
use jaylim12\events\ProgressEvent;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class SpreadsheetGenerator extends Component
{
    const EVENT_PROGRESS = 'onProgress';

    protected $_event;

    /**
     * @param \jaylim12\models\BaseModel $model
     *
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public function createSpreadsheet(BaseModel $model)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $event = $this->getEvent(true);
        $this->_event = $event;

        $this->trigger(self::EVENT_PROGRESS, $event);

        // build header
        $this->buildHeader($sheet, $model);

        $event->progress = 2;
        $this->trigger(self::EVENT_PROGRESS, $event);
        // build data
        $this->buildData($sheet, $model);

        // auto resize column
        $this->autoResizeColumn($sheet, $model);

        // set the cursor to A1
        $sheet->getStyleByColumnAndRow(1, 1);

        $event->progress = 98;
        $this->trigger(self::EVENT_PROGRESS, $event);

        return $spreadsheet;
    }

    public function createXlsx(Spreadsheet $spreadsheet, $savePath)
    {
        $writer = new Xlsx($spreadsheet);
        $event  = $this->getEvent();

        $event->progress = 99;
        $this->trigger(self::EVENT_PROGRESS, $event);

        $writer->save($savePath);

        $event->progress = 100;
        $this->trigger(self::EVENT_PROGRESS, $event);
    }

    protected function buildHeader(Worksheet $sheet, BaseModel $model)
    {
        $length = $model->getColumnSize();
        foreach ($model->getAttributes() as $key => $attr) {
            $colAttr = $model->getColumnAttribute($attr);
            $column  = $key + 1;

            $sheet->setCellValueExplicitByColumnAndRow($column, 1, $colAttr->title, DataType::TYPE_STRING2);
        }

        // style header
        $headerStyle = $sheet->getStyleByColumnAndRow(1, 1, $length, 1);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getBorders()->applyFromArray([
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => [
                    'rgb'     => '000000',
                ],
            ],
        ]);
        $headerStyle->getFill()->applyFromArray([
            'fillType'   => Fill::FILL_SOLID,
            'rotation'   => 0,
            'startColor' => [
                'rgb'    => 'C0C0C0',
            ],
            'endColor'   => [
                'rgb'    => 'C0C0C0',
            ],
        ]);
    }

    protected function buildData(Worksheet $sheet, BaseModel $model)
    {
        $row   = 2;
        $count = 0;
        $total = $model->count();
        $mark  = .1;
        $event = $this->getEvent();

        foreach ($model->each() as $data) {
            ++$count;
            if ($count >= $mark * $total) {
                $mark += .1;
                $event->progress = round($mark * 100, 2);
                if ($event->progress >= 97) {
                    $event->progress = 97;
                }

                $this->trigger(self::EVENT_PROGRESS, $event);
            }

            foreach ($data as $attr => $value) {
                if (!$model->hasColumnAttribute($attr)) {
                    continue ;
                }
                $colAttr  = $model->getColumnAttribute($attr);
                $dataType = $colAttr->type;

                if (!isset($dataType)) {
                    $dataType = DefaultValueBinder::dataTypeForValue($value);
                }

                $sheet->setCellValueExplicitByColumnAndRow($colAttr->column, $row, $value, $dataType);
            }
            ++$row;
        }
    }

    protected function autoResizeColumn(Worksheet $sheet, BaseModel $model)
    {
        foreach ($model->getColumnAttributes() as $colAttr) {
            $sheet->getColumnDimensionByColumn($colAttr->column)->setAutoSize(true);
        }
    }

    protected function getEvent($refreshNew = false)
    {
        if (isset($this->_event) && !$refreshNew) {
            return $this->_event;
        }

        return $this->_event = new ProgressEvent();
    }
}
