<?php

namespace Weareenvoy\CraftGreenhouse\Models;

use craft\base\Model;

class Settings extends Model
{
    public $apiKey = '';
    public $boardToken = '';
    public $urlBase = 'careers';

    public function rules()
    {
        return [
            [['apiKey', 'boardToken', 'urlBase'], 'required'],
        ];
    }
}
