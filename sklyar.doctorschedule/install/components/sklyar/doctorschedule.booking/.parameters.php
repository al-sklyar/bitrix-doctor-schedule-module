<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$arComponentParameters = [
    'PARAMETERS' => [
        'TITLE' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('SKLYAR_DS__PARAMETERS_PHP_0015_1'),
            'TYPE' => 'STRING',
            'DEFAULT' => Loc::getMessage('SKLYAR_DS__PARAMETERS_PHP_0017_1'),
        ],
    ],
];
