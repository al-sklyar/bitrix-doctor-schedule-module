<?php

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

global $APPLICATION;
global $DB;
global $USER;

Loc::loadMessages(__FILE__);

$moduleId = 'sklyar.doctorschedule';

if (!is_object($USER) || !$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ACCESS_DENIED'));
}

if (!Loader::includeModule($moduleId)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    CAdminMessage::ShowMessage(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MODULE_NOT_INSTALLED'));

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

if (!Loader::includeModule('highloadblock')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    CAdminMessage::ShowMessage(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HIGHLOADBLOCK_NOT_INSTALLED'));

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

function sklyarDsScheduleRuleGetWeekdayMap()
{
    return [
        1 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_1'),
        2 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_2'),
        3 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_3'),
        4 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_4'),
        5 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_5'),
        6 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_6'),
        7 => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_WEEKDAY_7'),
    ];
}

function sklyarDsScheduleRuleFormatMinutesValue($totalMinutes)
{
    $hours = (int) floor(((int) $totalMinutes) / 60);
    $minutes = ((int) $totalMinutes) % 60;

    return sprintf('%02d:%02d', $hours, $minutes);
}

function sklyarDsScheduleRuleNormalizeTimeValue($value)
{
    $value = trim((string) $value);

    if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $value, $matches)) {
        return null;
    }

    return ((int) $matches[1] * 60) + (int) $matches[2];
}

function sklyarDsScheduleRuleNormalizeSortValue($value)
{
    $value = trim((string) $value);

    if ($value === '' || !preg_match('/^-?\d+$/', $value)) {
        return 100;
    }

    return (int) $value;
}

function sklyarDsScheduleRuleGetDoctorDataClass()
{
    static $dataClass = null;
    static $initialized = false;

    if ($initialized) {
        return $dataClass;
    }

    $initialized = true;

    $highloadBlock = HighloadBlockTable::getList([
        'filter' => ['=TABLE_NAME' => 'sklyar_ds_doctor'],
        'limit' => 1,
    ])->fetch();

    if (!$highloadBlock) {
        return null;
    }

    $entity = HighloadBlockTable::compileEntity($highloadBlock);
    $dataClass = $entity->getDataClass();

    return $dataClass;
}

function sklyarDsScheduleRuleGetDoctorMap()
{
    $dataClass = sklyarDsScheduleRuleGetDoctorDataClass();
    $doctorMap = [];

    if ($dataClass === null) {
        return $doctorMap;
    }

    $doctorResult = $dataClass::getList([
        'select' => ['ID', 'UF_NAME', 'UF_ACTIVE', 'UF_SORT'],
        'order' => ['UF_SORT' => 'ASC', 'UF_NAME' => 'ASC', 'ID' => 'ASC'],
    ]);

    while ($doctor = $doctorResult->fetch()) {
        $doctorId = (int) $doctor['ID'];
        $doctorMap[$doctorId] = [
            'ID' => $doctorId,
            'NAME' => trim((string) $doctor['UF_NAME']),
            'ACTIVE' => (string) $doctor['UF_ACTIVE'],
        ];
    }

    return $doctorMap;
}

function sklyarDsScheduleRuleGetDoctorName($doctorId, array $doctorMap)
{
    $doctorId = (int) $doctorId;

    if (isset($doctorMap[$doctorId])) {
        $doctorName = trim((string) $doctorMap[$doctorId]['NAME']);

        if ($doctorName !== '') {
            return $doctorName;
        }
    }

    return '[ID ' . $doctorId . ']';
}

function sklyarDsScheduleRuleFetchRecordById($ruleId)
{
    global $DB;

    $ruleId = (int) $ruleId;

    if ($ruleId <= 0) {
        return null;
    }

    $sql = sprintf(
        'SELECT * FROM `sklyar_ds_schedule_rule` WHERE `id` = %d LIMIT 1',
        $ruleId
    );

    $result = $DB->Query($sql);
    $record = $result->Fetch();

    return $record ? $record : null;
}

function sklyarDsScheduleRuleFindOverlappingRecord($doctorId, $weekday, $timeFromMinutes, $timeToMinutes, $excludeRuleId = 0)
{
    global $DB;

    $conditions = [
        '`doctor_id` = ' . (int) $doctorId,
        '`weekday` = ' . (int) $weekday,
        '`time_from_minutes` < ' . (int) $timeToMinutes,
        '`time_to_minutes` > ' . (int) $timeFromMinutes,
    ];

    if ((int) $excludeRuleId > 0) {
        $conditions[] = '`id` <> ' . (int) $excludeRuleId;
    }

    $sql = sprintf(
        'SELECT * FROM `sklyar_ds_schedule_rule` WHERE %s ORDER BY `time_from_minutes` ASC, `id` ASC LIMIT 1',
        implode(' AND ', $conditions)
    );

    $result = $DB->Query($sql);
    $record = $result->Fetch();

    return $record ? $record : null;
}

function sklyarDsScheduleRuleGetListUrl()
{
    global $APPLICATION;

    return $APPLICATION->GetCurPage() . '?lang=' . urlencode((string) LANGUAGE_ID);
}

function sklyarDsScheduleRuleGetEditUrl($ruleId = 0, array $extra = [])
{
    $parameters = array_merge(
        [
            'lang' => LANGUAGE_ID,
            'action' => 'edit',
        ],
        $ruleId > 0 ? ['id' => (int) $ruleId] : [],
        $extra
    );

    return 'sklyar_doctorschedule_schedule_rule.php?' . http_build_query($parameters);
}

$request = Application::getInstance()->getContext()->getRequest();
$doctorMap = sklyarDsScheduleRuleGetDoctorMap();
$weekdayMap = sklyarDsScheduleRuleGetWeekdayMap();

$action = trim((string) $request->getQuery('action', ''));
$ruleId = (int) $request->getQuery('id');
$isEditMode = $action === 'edit';
$errors = [];

$formData = [
    'doctor_id' => '',
    'weekday' => '1',
    'time_from' => '09:00',
    'time_to' => '18:00',
    'active' => 'Y',
    'sort' => '100',
];

if ($ruleId > 0) {
    $existingRecord = sklyarDsScheduleRuleFetchRecordById($ruleId);

    if ($existingRecord) {
        $formData = [
            'doctor_id' => (string) (int) $existingRecord['doctor_id'],
            'weekday' => (string) (int) $existingRecord['weekday'],
            'time_from' => sklyarDsScheduleRuleFormatMinutesValue($existingRecord['time_from_minutes']),
            'time_to' => sklyarDsScheduleRuleFormatMinutesValue($existingRecord['time_to_minutes']),
            'active' => (string) $existingRecord['active'],
            'sort' => (string) (int) $existingRecord['sort'],
        ];
    } elseif ($isEditMode) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_NOT_FOUND');
        $ruleId = 0;
    }
}

if (
    $request->isPost()
    && check_bitrix_sessid()
    && ($request->getPost('save') !== null || $request->getPost('apply') !== null)
) {
    $isEditMode = true;
    $ruleId = (int) $request->getPost('id');
    $formData = [
        'doctor_id' => trim((string) $request->getPost('doctor_id')),
        'weekday' => trim((string) $request->getPost('weekday')),
        'time_from' => trim((string) $request->getPost('time_from')),
        'time_to' => trim((string) $request->getPost('time_to')),
        'active' => $request->getPost('active') === 'Y' ? 'Y' : 'N',
        'sort' => trim((string) $request->getPost('sort')),
    ];

    $doctorId = ctype_digit($formData['doctor_id']) ? (int) $formData['doctor_id'] : 0;
    $weekday = ctype_digit($formData['weekday']) ? (int) $formData['weekday'] : 0;
    $timeFromMinutes = sklyarDsScheduleRuleNormalizeTimeValue($formData['time_from']);
    $timeToMinutes = sklyarDsScheduleRuleNormalizeTimeValue($formData['time_to']);
    $sortValue = sklyarDsScheduleRuleNormalizeSortValue($formData['sort']);

    if ($doctorId <= 0 || !isset($doctorMap[$doctorId])) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_DOCTOR_REQUIRED');
    }

    if (!isset($weekdayMap[$weekday])) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_WEEKDAY_REQUIRED');
    }

    if ($timeFromMinutes === null) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_TIME_FROM_INVALID');
    }

    if ($timeToMinutes === null) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_TIME_TO_INVALID');
    }

    if ($timeFromMinutes !== null && $timeToMinutes !== null && $timeFromMinutes >= $timeToMinutes) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_TIME_RANGE_INVALID');
    }

    if (
        !$errors
        && $doctorId > 0
        && isset($weekdayMap[$weekday])
        && $timeFromMinutes !== null
        && $timeToMinutes !== null
    ) {
        $overlappingRecord = sklyarDsScheduleRuleFindOverlappingRecord(
            $doctorId,
            $weekday,
            $timeFromMinutes,
            $timeToMinutes,
            $ruleId
        );

        if ($overlappingRecord) {
            $errors[] = Loc::getMessage(
                'SKLYAR_DS_SCHEDULE_RULE_ERROR_OVERLAP',
                [
                    '#WEEKDAY#' => $weekdayMap[$weekday],
                    '#TIME_FROM#' => sklyarDsScheduleRuleFormatMinutesValue($overlappingRecord['time_from_minutes']),
                    '#TIME_TO#' => sklyarDsScheduleRuleFormatMinutesValue($overlappingRecord['time_to_minutes']),
                ]
            );
        }
    }

    if (!$errors) {
        $activeValue = $formData['active'] === 'Y' ? 'Y' : 'N';
        $queryResult = false;

        if ($ruleId > 0) {
            $queryResult = $DB->Query(
                sprintf(
                    "UPDATE `sklyar_ds_schedule_rule` SET `doctor_id` = %d, `weekday` = %d, `time_from_minutes` = %d, `time_to_minutes` = %d, `active` = '%s', `sort` = %d, `updated_at` = NOW() WHERE `id` = %d",
                    $doctorId,
                    $weekday,
                    $timeFromMinutes,
                    $timeToMinutes,
                    $DB->ForSql($activeValue, 1),
                    $sortValue,
                    $ruleId
                ),
                true
            );
        } else {
            $queryResult = $DB->Query(
                sprintf(
                    "INSERT INTO `sklyar_ds_schedule_rule` (`doctor_id`, `weekday`, `time_from_minutes`, `time_to_minutes`, `active`, `sort`, `created_at`, `updated_at`) VALUES (%d, %d, %d, %d, '%s', %d, NOW(), NOW())",
                    $doctorId,
                    $weekday,
                    $timeFromMinutes,
                    $timeToMinutes,
                    $DB->ForSql($activeValue, 1),
                    $sortValue
                ),
                true
            );

            if ($queryResult !== false) {
                $ruleId = (int) $DB->LastID();
            }
        }

        if ($queryResult === false) {
            $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_SAVE_FAILED');
        } else {
            if ($request->getPost('apply') !== null) {
                LocalRedirect(
                    sklyarDsScheduleRuleGetEditUrl(
                        $ruleId,
                        [
                            'saved' => 'Y',
                        ]
                    )
                );
            }

            LocalRedirect(sklyarDsScheduleRuleGetListUrl() . '&saved=Y');
        }
    }
}

if (
    !$request->isPost()
    && $request->getQuery('action') === 'delete'
    && $ruleId > 0
    && check_bitrix_sessid()
) {
    $deleteResult = $DB->Query(
        sprintf(
            'DELETE FROM `sklyar_ds_schedule_rule` WHERE `id` = %d',
            $ruleId
        ),
        true
    );

    if ($deleteResult !== false) {
        LocalRedirect(sklyarDsScheduleRuleGetListUrl() . '&deleted=Y');
    }

    $errors[] = Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ERROR_DELETE_FAILED');
}

if ($isEditMode) {
    $APPLICATION->SetTitle(
        $ruleId > 0
            ? Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_TITLE_EDIT')
            : Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_TITLE_ADD')
    );

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    if ($request->getQuery('saved') === 'Y' && !$errors) {
        CAdminMessage::ShowMessage([
            'MESSAGE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MESSAGE_SAVED'),
            'TYPE' => 'OK',
        ]);
    }

    if ($errors) {
        CAdminMessage::ShowMessage([
            'MESSAGE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MESSAGE_ACTION_FAILED'),
            'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $errors)),
            'HTML' => true,
            'TYPE' => 'ERROR',
        ]);
    }

    $contextMenu = new CAdminContextMenu([
        [
            'TEXT' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MENU_LIST'),
            'LINK' => sklyarDsScheduleRuleGetListUrl(),
            'TITLE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MENU_LIST_TITLE'),
            'ICON' => 'btn_list',
        ],
    ]);
    $contextMenu->Show();

    $tabControl = new CAdminTabControl(
        'sklyar_ds_schedule_rule_tab_control',
        [
            [
                'DIV' => 'edit1',
                'TAB' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_TAB_EDIT'),
                'TITLE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_TAB_EDIT_TITLE'),
            ],
        ]
    );
    ?>
    <form method="post" action="<?php echo htmlspecialcharsbx(sklyarDsScheduleRuleGetEditUrl($ruleId)); ?>">
        <?php echo bitrix_sessid_post(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $ruleId; ?>">
        <?php
        $tabControl->Begin();
        $tabControl->BeginNextTab();
        ?>
        <tr>
            <td width="40%"><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_DOCTOR')); ?>:</td>
            <td>
                <select name="doctor_id">
                    <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_DOCTOR_SELECT')); ?></option>
                    <?php
                    foreach ($doctorMap as $doctor) {
                        $doctorId = (int) $doctor['ID'];
                        ?>
                        <option
                            value="<?php echo $doctorId; ?>"
                            <?php echo (string) $doctorId === $formData['doctor_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialcharsbx($doctor['NAME'] !== '' ? $doctor['NAME'] : '[ID ' . $doctorId . ']'); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_WEEKDAY')); ?>:</td>
            <td>
                <select name="weekday">
                    <?php
                    foreach ($weekdayMap as $weekdayValue => $weekdayName) {
                        ?>
                        <option
                            value="<?php echo (int) $weekdayValue; ?>"
                            <?php echo (string) $weekdayValue === $formData['weekday'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialcharsbx($weekdayName); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_TIME_FROM')); ?>:</td>
            <td>
                <input
                    type="time"
                    name="time_from"
                    value="<?php echo htmlspecialcharsbx($formData['time_from']); ?>"
                >
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_TIME_TO')); ?>:</td>
            <td>
                <input
                    type="time"
                    name="time_to"
                    value="<?php echo htmlspecialcharsbx($formData['time_to']); ?>"
                >
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_SORT')); ?>:</td>
            <td>
                <input
                    type="text"
                    name="sort"
                    value="<?php echo htmlspecialcharsbx($formData['sort']); ?>"
                    size="10"
                >
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_ACTIVITY')); ?>:</td>
            <td>
                <input type="hidden" name="active" value="N">
                <label>
                    <input
                        type="checkbox"
                        name="active"
                        value="Y"
                        <?php echo $formData['active'] === 'Y' ? 'checked' : ''; ?>
                    >
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_ACTIVE')); ?>
                </label>
            </td>
        </tr>
        <tr class="adm-detail-content-btns">
            <td colspan="2">
                <input
                    type="submit"
                    name="save"
                    value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_BTN_SAVE')); ?>"
                    class="adm-btn-save"
                >
                <input
                    type="submit"
                    name="apply"
                    value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_BTN_APPLY')); ?>"
                >
                <input
                    type="button"
                    value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_BTN_CANCEL')); ?>"
                    onclick="window.location='<?php echo CUtil::JSEscape(sklyarDsScheduleRuleGetListUrl()); ?>';"
                >
            </td>
        </tr>
        <?php
        $tabControl->End();
        ?>
    </form>
    <?php

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

$APPLICATION->SetTitle(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_TITLE_LIST'));

$tableId = 'tbl_sklyar_ds_schedule_rule';
$sort = new CAdminSorting($tableId, 'SORT', 'asc');
$adminList = new CAdminList($tableId, $sort);

$filterFields = [
    'find_id',
    'find_doctor_id',
    'find_weekday',
    'find_active',
];

$adminList->InitFilter($filterFields);

$find_id = trim((string) $request->getQuery('find_id', ''));
$find_doctor_id = trim((string) $request->getQuery('find_doctor_id', ''));
$find_weekday = trim((string) $request->getQuery('find_weekday', ''));
$find_active = trim((string) $request->getQuery('find_active', ''));

$whereConditions = [];

if ($find_id !== '') {
    if (ctype_digit($find_id)) {
        $whereConditions[] = '`id` = ' . (int) $find_id;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_doctor_id !== '') {
    if (ctype_digit($find_doctor_id)) {
        $whereConditions[] = '`doctor_id` = ' . (int) $find_doctor_id;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_weekday !== '') {
    if (ctype_digit($find_weekday) && isset($weekdayMap[(int) $find_weekday])) {
        $whereConditions[] = '`weekday` = ' . (int) $find_weekday;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_active !== '') {
    if ($find_active === 'Y' || $find_active === 'N') {
        $whereConditions[] = sprintf("`active` = '%s'", $DB->ForSql($find_active, 1));
    } else {
        $whereConditions[] = '1 = 0';
    }
}

$sortColumnMap = [
    'ID' => '`id`',
    'DOCTOR' => '`doctor_id`',
    'WEEKDAY' => '`weekday`',
    'TIME_FROM' => '`time_from_minutes`',
    'TIME_TO' => '`time_to_minutes`',
    'ACTIVE' => '`active`',
    'SORT' => '`sort`',
    'UPDATED_AT' => '`updated_at`',
];

$sortColumnKey = isset($sortColumnMap[$by]) ? $by : 'SORT';
$sortColumnSql = $sortColumnMap[$sortColumnKey];
$sortOrderSql = strtoupper((string) $order) === 'DESC' ? 'DESC' : 'ASC';
$whereSql = $whereConditions ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = <<<SQL
    SELECT
        `id` AS ID,
        `doctor_id` AS DOCTOR_ID,
        `weekday` AS WEEKDAY,
        `time_from_minutes` AS TIME_FROM_MINUTES,
        `time_to_minutes` AS TIME_TO_MINUTES,
        `active` AS ACTIVE,
        `sort` AS SORT,
        `updated_at` AS UPDATED_AT
    FROM `sklyar_ds_schedule_rule`
    {$whereSql}
    ORDER BY {$sortColumnSql} {$sortOrderSql}, `id` DESC
    SQL;

$result = $DB->Query($sql);
$result = new CAdminResult($result, $tableId);
$result->NavStart(20);
$adminList->NavText($result->GetNavPrint(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_TITLE_LIST')));

$adminList->AddHeaders([
    [
        'id' => 'ID',
        'content' => 'ID',
        'default' => true,
        'sort' => 'ID',
    ],
    [
        'id' => 'DOCTOR',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_DOCTOR'),
        'default' => true,
        'sort' => 'DOCTOR',
    ],
    [
        'id' => 'WEEKDAY',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_WEEKDAY'),
        'default' => true,
        'sort' => 'WEEKDAY',
    ],
    [
        'id' => 'TIME_FROM',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_TIME_FROM'),
        'default' => true,
        'sort' => 'TIME_FROM',
    ],
    [
        'id' => 'TIME_TO',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_TIME_TO'),
        'default' => true,
        'sort' => 'TIME_TO',
    ],
    [
        'id' => 'ACTIVE',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_ACTIVE'),
        'default' => true,
        'sort' => 'ACTIVE',
    ],
    [
        'id' => 'SORT',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_SORT'),
        'default' => true,
        'sort' => 'SORT',
    ],
    [
        'id' => 'UPDATED_AT',
        'content' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_HEADER_UPDATED_AT'),
        'default' => true,
        'sort' => 'UPDATED_AT',
    ],
]);

while ($record = $result->Fetch()) {
    $recordId = (int) $record['ID'];
    $row = &$adminList->AddRow($recordId, $record);
    $editUrl = sklyarDsScheduleRuleGetEditUrl($recordId);
    $deleteUrl = sklyarDsScheduleRuleGetListUrl()
        . '&action=delete&id=' . $recordId
        . '&sessid=' . bitrix_sessid();

    $row->AddViewField('ID', (string) $recordId);
    $row->AddViewField(
        'DOCTOR',
        htmlspecialcharsbx(sklyarDsScheduleRuleGetDoctorName($record['DOCTOR_ID'], $doctorMap))
    );
    $row->AddViewField(
        'WEEKDAY',
        htmlspecialcharsbx(
            isset($weekdayMap[(int) $record['WEEKDAY']])
                ? $weekdayMap[(int) $record['WEEKDAY']]
                : Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_VALUE_UNKNOWN')
        )
    );
    $row->AddViewField(
        'TIME_FROM',
        htmlspecialcharsbx(sklyarDsScheduleRuleFormatMinutesValue($record['TIME_FROM_MINUTES']))
    );
    $row->AddViewField(
        'TIME_TO',
        htmlspecialcharsbx(sklyarDsScheduleRuleFormatMinutesValue($record['TIME_TO_MINUTES']))
    );
    $row->AddViewField(
        'ACTIVE',
        htmlspecialcharsbx(
            $record['ACTIVE'] === 'Y'
                ? Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_VALUE_YES')
                : Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_VALUE_NO')
        )
    );
    $row->AddViewField('SORT', (string) (int) $record['SORT']);
    $row->AddViewField('UPDATED_AT', htmlspecialcharsbx((string) $record['UPDATED_AT']));

    $row->AddActions([
        [
            'ICON' => 'edit',
            'TEXT' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ACTION_EDIT'),
            'ACTION' => $adminList->ActionRedirect($editUrl),
            'DEFAULT' => true,
        ],
        [
            'ICON' => 'delete',
            'TEXT' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ACTION_DELETE'),
            'ACTION' => "if (confirm('"
                . CUtil::JSEscape(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_ACTION_DELETE_CONFIRM'))
                . "')) { window.location='"
                . CUtil::JSEscape($deleteUrl)
                . "'; }",
        ],
    ]);
}

$adminList->CheckListMode();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($request->getQuery('saved') === 'Y' && !$errors) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MESSAGE_SAVED'),
        'TYPE' => 'OK',
    ]);
}

if ($request->getQuery('deleted') === 'Y' && !$errors) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MESSAGE_DELETED'),
        'TYPE' => 'OK',
    ]);
}

if ($errors) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MESSAGE_ACTION_FAILED'),
        'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $errors)),
        'HTML' => true,
        'TYPE' => 'ERROR',
    ]);
}

$contextMenu = new CAdminContextMenu([
    [
        'TEXT' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MENU_ADD'),
        'LINK' => sklyarDsScheduleRuleGetEditUrl(),
        'TITLE' => Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_MENU_ADD_TITLE'),
        'ICON' => 'btn_new',
    ],
]);

$filter = new CAdminFilter(
    $tableId . '_filter',
    [
        'ID',
        Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FILTER_DOCTOR'),
        Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FILTER_WEEKDAY'),
        Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FILTER_ACTIVITY'),
    ]
);
?>

<form name="find_form" method="get" action="<?php echo htmlspecialcharsbx($APPLICATION->GetCurPage()); ?>">
    <input type="hidden" name="lang" value="<?php echo htmlspecialcharsbx(LANGUAGE_ID); ?>">
    <?php
    $filter->Begin();
    ?>
    <tr>
        <td>ID:</td>
        <td>
            <input
                type="text"
                name="find_id"
                value="<?php echo htmlspecialcharsbx($find_id); ?>"
                size="10"
            >
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_DOCTOR')); ?>:</td>
        <td>
            <select name="find_doctor_id">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FILTER_ALL')); ?></option>
                <?php
                foreach ($doctorMap as $doctor) {
                    $doctorId = (int) $doctor['ID'];
                    ?>
                    <option
                        value="<?php echo $doctorId; ?>"
                        <?php echo (string) $doctorId === $find_doctor_id ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialcharsbx($doctor['NAME'] !== '' ? $doctor['NAME'] : '[ID ' . $doctorId . ']'); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_WEEKDAY')); ?>:</td>
        <td>
            <select name="find_weekday">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FILTER_ALL')); ?></option>
                <?php
                foreach ($weekdayMap as $weekdayValue => $weekdayName) {
                    ?>
                    <option
                        value="<?php echo (int) $weekdayValue; ?>"
                        <?php echo (string) $weekdayValue === $find_weekday ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialcharsbx($weekdayName); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FIELD_ACTIVITY')); ?>:</td>
        <td>
            <select name="find_active">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_FILTER_ALL')); ?></option>
                <option value="Y" <?php echo $find_active === 'Y' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_VALUE_YES')); ?>
                </option>
                <option value="N" <?php echo $find_active === 'N' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_VALUE_NO')); ?>
                </option>
            </select>
        </td>
    </tr>
    <?php
    $filter->Buttons([
        'table_id' => $tableId,
        'url' => $APPLICATION->GetCurPage() . '?lang=' . urlencode((string) LANGUAGE_ID),
        'form' => 'find_form',
    ]);
    $filter->End();
    ?>
</form>

<?php
$contextMenu->Show();

$adminList->DisplayList();
?>
<script>
    (function () {
        var excelText = <?php echo CUtil::PhpToJSObject(Loc::getMessage('SKLYAR_DS_SCHEDULE_RULE_EXCEL_EXPORT')); ?>;
        var nodes = document.querySelectorAll('a, span, button, input');

        for (var index = 0; index < nodes.length; index++) {
            var node = nodes[index];
            var nodeText = '';

            if (typeof node.value === 'string' && node.value.trim() !== '') {
                nodeText = node.value.trim();
            } else if (typeof node.textContent === 'string') {
                nodeText = node.textContent.trim();
            }

            if (nodeText === excelText && node.parentNode) {
                node.parentNode.removeChild(node);
            }
        }
    }());
</script>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
