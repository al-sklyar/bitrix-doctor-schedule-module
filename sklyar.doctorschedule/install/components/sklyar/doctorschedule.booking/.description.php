<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => Loc::getMessage('SKLYAR_DS__DESCRIPTION_PHP_0012_1'),
    'DESCRIPTION' => Loc::getMessage('SKLYAR_DS__DESCRIPTION_PHP_0013_1'),
    'ICON' => '',
    'SORT' => 10,
    'CACHE_PATH' => 'Y',
    'PATH' => [
        'ID' => 'sklyar',
        'NAME' => 'Sklyar',
        'CHILD' => [
            'ID' => 'sklyar.doctorschedule',
            'NAME' => Loc::getMessage('SKLYAR_DS__DESCRIPTION_PHP_0022_1'),
        ],
    ],
];
