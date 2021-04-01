<?php

namespace jaylim12\events;

use yii\base\Event;

class ProgressEvent extends Event
{
    public $progress = 0;
}
