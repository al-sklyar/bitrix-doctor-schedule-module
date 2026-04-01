<?php

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
    $APPLICATION->AuthForm(Loc::getMessage('SKLYAR_DS_BOOKING_ACCESS_DENIED'));
}

if (!Loader::includeModule($moduleId)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    CAdminMessage::ShowMessage(Loc::getMessage('SKLYAR_DS_BOOKING_MODULE_NOT_INSTALLED'));

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

function sklyarDsFormatMinutesValue($totalMinutes)
{
    $hours = (int) floor(((int) $totalMinutes) / 60);
    $minutes = ((int) $totalMinutes) % 60;

    return sprintf('%02d:%02d', $hours, $minutes);
}

function sklyarDsBuildLikeCondition($fieldName, $value)
{
    global $DB;

    $escapedValue = $DB->ForSql((string) $value);

    return sprintf("%s LIKE '%%%s%%'", $fieldName, $escapedValue);
}

function sklyarDsNormalizeDateFilterValue($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $date = \DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return '';
    }

    return $value;
}

function sklyarDsFormatDateValue($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d.m.Y', $timestamp);
}

function sklyarDsFormatDateTimeValue($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d.m.Y H:i', $timestamp);
}

function sklyarDsRenderMultilineValue(array $lines)
{
    $preparedLines = [];

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '') {
            continue;
        }

        $preparedLines[] = htmlspecialcharsbx($line);
    }

    if (!$preparedLines) {
        return '&mdash;';
    }

    return implode('<br>', $preparedLines);
}

$tableId = 'tbl_sklyar_ds_booking';
$sort = new CAdminSorting($tableId, 'BOOKING_DATE', 'desc');
$adminList = new CAdminList($tableId, $sort);
$request = Application::getInstance()->getContext()->getRequest();

$confirmId = (int) $request->getQuery('confirm_id', 0);

if ($confirmId > 0 && check_bitrix_sessid()) {
    $confirmSql = sprintf(
        "UPDATE `sklyar_ds_booking` SET `status` = 'confirmed', `updated_at` = %s WHERE `id` = %d AND `status` = 'new'",
        $DB->CurrentTimeFunction(),
        $confirmId
    );
    $DB->Query($confirmSql);

    LocalRedirect(
        $APPLICATION->GetCurPageParam(
            'lang=' . urlencode((string) LANGUAGE_ID) . '&confirmed=Y',
            ['confirm_id', 'confirmed', 'sessid']
        )
    );
}

$filterFields = [
    'find_id',
    'find_patient_name',
    'find_doctor_name',
    'find_status',
    'find_booking_date_from',
    'find_booking_date_to',
];

$adminList->InitFilter($filterFields);

$find_id = trim((string) $request->getQuery('find_id', ''));
$find_patient_name = trim((string) $request->getQuery('find_patient_name', ''));
$find_doctor_name = trim((string) $request->getQuery('find_doctor_name', ''));
$find_status = trim((string) $request->getQuery('find_status', ''));
$find_booking_date_from = sklyarDsNormalizeDateFilterValue($request->getQuery('find_booking_date_from', ''));
$find_booking_date_to = sklyarDsNormalizeDateFilterValue($request->getQuery('find_booking_date_to', ''));

$whereConditions = [];

if ($find_id !== '') {
    if (ctype_digit($find_id)) {
        $whereConditions[] = '`id` = ' . (int) $find_id;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_patient_name !== '') {
    $whereConditions[] = sklyarDsBuildLikeCondition('`patient_name`', $find_patient_name);
}

if ($find_doctor_name !== '') {
    $whereConditions[] = sklyarDsBuildLikeCondition('`doctor_name_snapshot`', $find_doctor_name);
}

if ($find_status !== '') {
    $whereConditions[] = sprintf("`status` = '%s'", $DB->ForSql($find_status, 50));
}

if ($find_booking_date_from !== '') {
    $whereConditions[] = sprintf("`booking_date` >= '%s'", $DB->ForSql($find_booking_date_from, 10));
}

if ($find_booking_date_to !== '') {
    $whereConditions[] = sprintf("`booking_date` <= '%s'", $DB->ForSql($find_booking_date_to, 10));
}

$sortColumnMap = [
    'ID' => '`id`',
    'BOOKING_DATE' => '`booking_date`',
    'TIME_RANGE' => '`time_from_minutes`',
    'DOCTOR' => '`doctor_name_snapshot`',
    'SERVICE' => '`service_name_snapshot`',
    'PATIENT' => '`patient_name`',
    'STATUS' => '`status`',
    'CREATED_AT' => '`created_at`',
];

$sortColumnKey = isset($sortColumnMap[$by]) ? $by : 'BOOKING_DATE';
$sortColumnSql = $sortColumnMap[$sortColumnKey];
$sortOrderSql = strtoupper((string) $order) === 'ASC' ? 'ASC' : 'DESC';
$whereSql = $whereConditions ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = <<<SQL
    SELECT
        `id` AS ID,
        `booking_date` AS BOOKING_DATE,
        `time_from_minutes` AS TIME_FROM_MINUTES,
        `time_to_minutes` AS TIME_TO_MINUTES,
        `doctor_name_snapshot` AS DOCTOR_NAME_SNAPSHOT,
        `service_name_snapshot` AS SERVICE_NAME_SNAPSHOT,
        `appointment_type_name_snapshot` AS APPOINTMENT_TYPE_NAME_SNAPSHOT,
        `location_name_snapshot` AS LOCATION_NAME_SNAPSHOT,
        `patient_name` AS PATIENT_NAME,
        `patient_phone` AS PATIENT_PHONE,
        `patient_email` AS PATIENT_EMAIL,
        `patient_comment` AS PATIENT_COMMENT,
        `visit_address` AS VISIT_ADDRESS,
        `online_link` AS ONLINE_LINK,
        `price_snapshot` AS PRICE_SNAPSHOT,
        `currency_snapshot` AS CURRENCY_SNAPSHOT,
        `status` AS STATUS,
        `created_at` AS CREATED_AT,
        `updated_at` AS UPDATED_AT
    FROM `sklyar_ds_booking`
    {$whereSql}
    ORDER BY {$sortColumnSql} {$sortOrderSql}, `id` DESC
    SQL;

$result = $DB->Query($sql);
$result = new CAdminResult($result, $tableId);
$result->NavStart(20);
$adminList->NavText($result->GetNavPrint(Loc::getMessage('SKLYAR_DS_BOOKING_TITLE')));

$adminList->AddHeaders([
    [
        'id' => 'ID',
        'content' => 'ID',
        'default' => true,
        'sort' => 'ID',
    ],
    [
        'id' => 'BOOKING_DATE',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_DATE'),
        'default' => true,
        'sort' => 'BOOKING_DATE',
    ],
    [
        'id' => 'TIME_RANGE',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_TIME'),
        'default' => true,
        'sort' => 'TIME_RANGE',
    ],
    [
        'id' => 'DOCTOR',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_DOCTOR'),
        'default' => true,
        'sort' => 'DOCTOR',
    ],
    [
        'id' => 'SERVICE',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_SERVICE'),
        'default' => true,
        'sort' => 'SERVICE',
    ],
    [
        'id' => 'PATIENT',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_PATIENT'),
        'default' => true,
        'sort' => 'PATIENT',
    ],
    [
        'id' => 'STATUS',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_STATUS'),
        'default' => true,
        'sort' => 'STATUS',
    ],
    [
        'id' => 'PRICE',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_PRICE'),
        'default' => true,
    ],
    [
        'id' => 'DETAILS',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_DETAILS'),
        'default' => true,
    ],
    [
        'id' => 'CREATED_AT',
        'content' => Loc::getMessage('SKLYAR_DS_BOOKING_HEADER_CREATED_AT'),
        'default' => true,
        'sort' => 'CREATED_AT',
    ],
]);

while ($record = $result->Fetch()) {
    $row = &$adminList->AddRow((int) $record['ID'], $record);

    $timeRange = sklyarDsFormatMinutesValue($record['TIME_FROM_MINUTES']) . ' - '
        . sklyarDsFormatMinutesValue($record['TIME_TO_MINUTES']);

    $serviceLines = [
        (string) $record['SERVICE_NAME_SNAPSHOT'],
        (string) $record['APPOINTMENT_TYPE_NAME_SNAPSHOT'],
    ];

    if (trim((string) $record['LOCATION_NAME_SNAPSHOT']) !== '') {
        $serviceLines[] = Loc::getMessage('SKLYAR_DS_BOOKING_SERVICE_LOCATION') . ' ' . (string) $record['LOCATION_NAME_SNAPSHOT'];
    }

    $patientLines = [
        (string) $record['PATIENT_NAME'],
        (string) $record['PATIENT_PHONE'],
    ];

    if (trim((string) $record['PATIENT_EMAIL']) !== '') {
        $patientLines[] = (string) $record['PATIENT_EMAIL'];
    }

    $detailLines = [];

    if (trim((string) $record['VISIT_ADDRESS']) !== '') {
        $detailLines[] = Loc::getMessage('SKLYAR_DS_BOOKING_DETAILS_VISIT_ADDRESS') . ' ' . (string) $record['VISIT_ADDRESS'];
    }

    if (trim((string) $record['ONLINE_LINK']) !== '') {
        $detailLines[] = Loc::getMessage('SKLYAR_DS_BOOKING_DETAILS_ONLINE_LINK') . ' ' . (string) $record['ONLINE_LINK'];
    }

    if (trim((string) $record['PATIENT_COMMENT']) !== '') {
        $detailLines[] = Loc::getMessage('SKLYAR_DS_BOOKING_DETAILS_COMMENT') . ' ' . (string) $record['PATIENT_COMMENT'];
    }

    if (trim((string) $record['UPDATED_AT']) !== '') {
        $detailLines[] = Loc::getMessage('SKLYAR_DS_BOOKING_DETAILS_UPDATED_AT') . ' ' . sklyarDsFormatDateTimeValue($record['UPDATED_AT']);
    }

    $priceValue = trim((string) $record['PRICE_SNAPSHOT'] . ' ' . (string) $record['CURRENCY_SNAPSHOT']);
    $actions = [];

    if ((string) $record['STATUS'] === 'new') {
        $actions[] = [
            'ICON' => 'edit',
            'TEXT' => Loc::getMessage('SKLYAR_DS_BOOKING_ACTION_CONFIRM'),
            'ACTION' => $adminList->ActionRedirect(
                $APPLICATION->GetCurPageParam(
                    'lang=' . urlencode((string) LANGUAGE_ID)
                    . '&confirm_id=' . (int) $record['ID']
                    . '&sessid=' . bitrix_sessid(),
                    ['confirm_id', 'confirmed', 'sessid']
                )
            ),
        ];
    }

    $row->AddActions($actions);
    $row->AddViewField('ID', (string) (int) $record['ID']);
    $row->AddViewField('BOOKING_DATE', htmlspecialcharsbx(sklyarDsFormatDateValue($record['BOOKING_DATE'])));
    $row->AddViewField('TIME_RANGE', htmlspecialcharsbx($timeRange));
    $row->AddViewField('DOCTOR', htmlspecialcharsbx((string) $record['DOCTOR_NAME_SNAPSHOT']));
    $row->AddViewField('SERVICE', sklyarDsRenderMultilineValue($serviceLines));
    $row->AddViewField('PATIENT', sklyarDsRenderMultilineValue($patientLines));
    $row->AddViewField('STATUS', htmlspecialcharsbx((string) $record['STATUS']));
    $row->AddViewField('PRICE', $priceValue !== '' ? htmlspecialcharsbx($priceValue) : '&mdash;');
    $row->AddViewField('DETAILS', sklyarDsRenderMultilineValue($detailLines));
    $row->AddViewField('CREATED_AT', htmlspecialcharsbx(sklyarDsFormatDateTimeValue($record['CREATED_AT'])));
}

$adminList->CheckListMode();

$APPLICATION->SetTitle(Loc::getMessage('SKLYAR_DS_BOOKING_TITLE'));

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($request->getQuery('confirmed') === 'Y') {
    CAdminMessage::ShowMessage([
        'TYPE' => 'OK',
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_BOOKING_MESSAGE_CONFIRMED'),
    ]);
}

$filter = new CAdminFilter(
    $tableId . '_filter',
    [
        'ID',
        Loc::getMessage('SKLYAR_DS_BOOKING_FILTER_PATIENT'),
        Loc::getMessage('SKLYAR_DS_BOOKING_FILTER_DOCTOR'),
        Loc::getMessage('SKLYAR_DS_BOOKING_FILTER_STATUS'),
        Loc::getMessage('SKLYAR_DS_BOOKING_FILTER_BOOKING_DATE'),
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
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_FIELD_PATIENT')); ?>:</td>
        <td>
            <input
                type="text"
                name="find_patient_name"
                value="<?php echo htmlspecialcharsbx($find_patient_name); ?>"
                size="30"
            >
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_FIELD_DOCTOR')); ?>:</td>
        <td>
            <input
                type="text"
                name="find_doctor_name"
                value="<?php echo htmlspecialcharsbx($find_doctor_name); ?>"
                size="30"
            >
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_FIELD_STATUS')); ?>:</td>
        <td>
            <input
                type="text"
                name="find_status"
                value="<?php echo htmlspecialcharsbx($find_status); ?>"
                size="20"
            >
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_FIELD_BOOKING_DATE')); ?>:</td>
        <td>
            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_DATE_FROM')); ?>
            <input
                type="date"
                name="find_booking_date_from"
                value="<?php echo htmlspecialcharsbx($find_booking_date_from); ?>"
            >
            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_DATE_TO')); ?>
            <input
                type="date"
                name="find_booking_date_to"
                value="<?php echo htmlspecialcharsbx($find_booking_date_to); ?>"
            >
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
$adminList->DisplayList();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
