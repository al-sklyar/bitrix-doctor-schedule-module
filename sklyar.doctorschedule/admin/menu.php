<?php

use Bitrix\Main\Localization\Loc;

global $USER;

Loc::loadMessages(__FILE__);

if (!is_object($USER) || !$USER->IsAdmin()) {
    return false;
}

return [
    'parent_menu' => 'global_menu_services',
    'section' => 'sklyar_doctorschedule',
    'sort' => 1000,
    'text' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_ROOT_TEXT'),
    'title' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_ROOT_TITLE'),
    'icon' => 'util_menu_icon',
    'page_icon' => 'util_page_icon',
    'items_id' => 'menu_sklyar_doctorschedule',
    'items' => [
        [
            'text' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_SCHEDULE_RULE_TEXT'),
            'title' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_SCHEDULE_RULE_TITLE'),
            'url' => 'sklyar_doctorschedule_schedule_rule.php?lang=' . LANGUAGE_ID,
            'more_url' => [
                'sklyar_doctorschedule_schedule_rule.php',
            ],
        ],
        [
            'text' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_SERVICE_PRICE_TEXT'),
            'title' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_SERVICE_PRICE_TITLE'),
            'url' => 'sklyar_doctorschedule_service_price.php?lang=' . LANGUAGE_ID,
            'more_url' => [
                'sklyar_doctorschedule_service_price.php',
            ],
        ],
        [
            'text' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_BOOKING_TEXT'),
            'title' => Loc::getMessage('SKLYAR_DS_ADMIN_MENU_BOOKING_TITLE'),
            'url' => 'sklyar_doctorschedule_booking.php?lang=' . LANGUAGE_ID,
            'more_url' => [
                'sklyar_doctorschedule_booking.php',
            ],
        ],
    ],
];
