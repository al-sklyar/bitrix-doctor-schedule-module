<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$arTemplate = [
    'NAME' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPACT_NAME'),
    'DESCRIPTION' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPACT_DESCRIPTION'),
];
