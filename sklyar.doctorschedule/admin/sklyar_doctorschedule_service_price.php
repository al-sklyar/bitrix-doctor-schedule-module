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
    $APPLICATION->AuthForm(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ACCESS_DENIED'));
}

if (!Loader::includeModule($moduleId)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    CAdminMessage::ShowMessage(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MODULE_NOT_INSTALLED'));

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

if (!Loader::includeModule('highloadblock')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    CAdminMessage::ShowMessage(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HIGHLOADBLOCK_NOT_INSTALLED'));

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

    return;
}

function sklyarDsServicePriceNormalizeSortValue($value)
{
    $value = trim((string) $value);

    if ($value === '' || !preg_match('/^-?\d+$/', $value)) {
        return 100;
    }

    return (int) $value;
}

function sklyarDsServicePriceNormalizeIntegerValue($value)
{
    $value = trim((string) $value);

    if ($value === '' || !preg_match('/^\d+$/', $value)) {
        return null;
    }

    return (int) $value;
}

function sklyarDsServicePriceNormalizePriceValue($value)
{
    $value = trim((string) $value);
    $value = str_replace(',', '.', $value);

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        return null;
    }

    return number_format((float) $value, 2, '.', '');
}

function sklyarDsServicePriceNormalizeCurrencyValue($value)
{
    $value = strtoupper(trim((string) $value));

    if (!preg_match('/^[A-Z]{3}$/', $value)) {
        return null;
    }

    return $value;
}

function sklyarDsServicePriceGetReferenceDataClass($tableName)
{
    static $dataClassMap = [];

    if (array_key_exists($tableName, $dataClassMap)) {
        return $dataClassMap[$tableName];
    }

    $highloadBlock = HighloadBlockTable::getList([
        'filter' => ['=TABLE_NAME' => (string) $tableName],
        'limit' => 1,
    ])->fetch();

    if (!$highloadBlock) {
        $dataClassMap[$tableName] = null;

        return null;
    }

    $entity = HighloadBlockTable::compileEntity($highloadBlock);
    $dataClassMap[$tableName] = $entity->getDataClass();

    return $dataClassMap[$tableName];
}

function sklyarDsServicePriceGetReferenceMap($tableName)
{
    $dataClass = sklyarDsServicePriceGetReferenceDataClass($tableName);
    $referenceMap = [];

    if ($dataClass === null) {
        return $referenceMap;
    }

    $referenceResult = $dataClass::getList([
        'select' => ['ID', 'UF_NAME', 'UF_ACTIVE', 'UF_SORT'],
        'order' => ['UF_SORT' => 'ASC', 'UF_NAME' => 'ASC', 'ID' => 'ASC'],
    ]);

    while ($referenceItem = $referenceResult->fetch()) {
        $referenceId = (int) $referenceItem['ID'];
        $referenceMap[$referenceId] = [
            'ID' => $referenceId,
            'NAME' => trim((string) $referenceItem['UF_NAME']),
            'ACTIVE' => (string) $referenceItem['UF_ACTIVE'],
        ];
    }

    return $referenceMap;
}

function sklyarDsServicePriceGetReferenceName($referenceId, array $referenceMap, $emptyLabel)
{
    $referenceId = (int) $referenceId;

    if (isset($referenceMap[$referenceId])) {
        $referenceName = trim((string) $referenceMap[$referenceId]['NAME']);

        if ($referenceName !== '') {
            return $referenceName;
        }
    }

    return (string) $emptyLabel;
}

function sklyarDsServicePriceFetchRecordById($recordId)
{
    global $DB;

    $recordId = (int) $recordId;

    if ($recordId <= 0) {
        return null;
    }

    $sql = sprintf(
        'SELECT * FROM `sklyar_ds_service_price` WHERE `id` = %d LIMIT 1',
        $recordId
    );

    $result = $DB->Query($sql);
    $record = $result->Fetch();

    return $record ? $record : null;
}

function sklyarDsServicePriceGetListUrl()
{
    global $APPLICATION;

    return $APPLICATION->GetCurPage() . '?lang=' . urlencode((string) LANGUAGE_ID);
}

function sklyarDsServicePriceGetEditUrl($recordId = 0, array $extra = [])
{
    $parameters = array_merge(
        [
            'lang' => LANGUAGE_ID,
            'action' => 'edit',
        ],
        $recordId > 0 ? ['id' => (int) $recordId] : [],
        $extra
    );

    return 'sklyar_doctorschedule_service_price.php?' . http_build_query($parameters);
}

$request = Application::getInstance()->getContext()->getRequest();
$doctorMap = sklyarDsServicePriceGetReferenceMap('sklyar_ds_doctor');
$serviceMap = sklyarDsServicePriceGetReferenceMap('sklyar_ds_service');
$appointmentTypeMap = sklyarDsServicePriceGetReferenceMap('sklyar_ds_appointment_type');
$locationMap = sklyarDsServicePriceGetReferenceMap('sklyar_ds_location');

$action = trim((string) $request->getQuery('action', ''));
$recordId = (int) $request->getQuery('id');
$isEditMode = $action === 'edit';
$errors = [];

$formData = [
    'doctor_id' => '',
    'service_id' => '',
    'appointment_type_id' => '',
    'location_id' => '',
    'price' => '0.00',
    'currency' => 'RUB',
    'duration_minutes' => '30',
    'active' => 'Y',
    'sort' => '100',
];

if ($recordId > 0) {
    $existingRecord = sklyarDsServicePriceFetchRecordById($recordId);

    if ($existingRecord) {
        $formData = [
            'doctor_id' => (string) (int) $existingRecord['doctor_id'],
            'service_id' => (string) (int) $existingRecord['service_id'],
            'appointment_type_id' => (string) (int) $existingRecord['appointment_type_id'],
            'location_id' => $existingRecord['location_id'] === null || $existingRecord['location_id'] === ''
                ? ''
                : (string) (int) $existingRecord['location_id'],
            'price' => number_format((float) $existingRecord['price'], 2, '.', ''),
            'currency' => (string) $existingRecord['currency'],
            'duration_minutes' => (string) (int) $existingRecord['duration_minutes'],
            'active' => (string) $existingRecord['active'],
            'sort' => (string) (int) $existingRecord['sort'],
        ];
    } elseif ($isEditMode) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_NOT_FOUND');
        $recordId = 0;
    }
}

if (
    $request->isPost()
    && check_bitrix_sessid()
    && ($request->getPost('save') !== null || $request->getPost('apply') !== null)
) {
    $isEditMode = true;
    $recordId = (int) $request->getPost('id');
    $formData = [
        'doctor_id' => trim((string) $request->getPost('doctor_id')),
        'service_id' => trim((string) $request->getPost('service_id')),
        'appointment_type_id' => trim((string) $request->getPost('appointment_type_id')),
        'location_id' => trim((string) $request->getPost('location_id')),
        'price' => trim((string) $request->getPost('price')),
        'currency' => trim((string) $request->getPost('currency')),
        'duration_minutes' => trim((string) $request->getPost('duration_minutes')),
        'active' => $request->getPost('active') === 'Y' ? 'Y' : 'N',
        'sort' => trim((string) $request->getPost('sort')),
    ];

    $doctorId = sklyarDsServicePriceNormalizeIntegerValue($formData['doctor_id']);
    $serviceId = sklyarDsServicePriceNormalizeIntegerValue($formData['service_id']);
    $appointmentTypeId = sklyarDsServicePriceNormalizeIntegerValue($formData['appointment_type_id']);
    $locationId = $formData['location_id'] === '' ? null : sklyarDsServicePriceNormalizeIntegerValue($formData['location_id']);
    $priceValue = sklyarDsServicePriceNormalizePriceValue($formData['price']);
    $currencyValue = sklyarDsServicePriceNormalizeCurrencyValue($formData['currency']);
    $durationMinutes = sklyarDsServicePriceNormalizeIntegerValue($formData['duration_minutes']);
    $sortValue = sklyarDsServicePriceNormalizeSortValue($formData['sort']);

    if ($doctorId === null || !isset($doctorMap[$doctorId])) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_DOCTOR_REQUIRED');
    }

    if ($serviceId === null || !isset($serviceMap[$serviceId])) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_SERVICE_REQUIRED');
    }

    if ($appointmentTypeId === null || !isset($appointmentTypeMap[$appointmentTypeId])) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_APPOINTMENT_TYPE_REQUIRED');
    }

    if ($locationId !== null && !isset($locationMap[$locationId])) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_LOCATION_INVALID');
    }

    if ($priceValue === null) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_PRICE_INVALID');
    }

    if ($currencyValue === null) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_CURRENCY_INVALID');
    }

    if ($durationMinutes === null || $durationMinutes <= 0) {
        $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_DURATION_INVALID');
    }

    if (!$errors) {
        $activeValue = $formData['active'] === 'Y' ? 'Y' : 'N';
        $locationSqlValue = $locationId === null ? 'NULL' : (string) $locationId;
        $queryResult = false;

        if ($recordId > 0) {
            $queryResult = $DB->Query(
                sprintf(
                    "UPDATE `sklyar_ds_service_price` SET `doctor_id` = %d, `service_id` = %d, `appointment_type_id` = %d, `location_id` = %s, `price` = '%s', `currency` = '%s', `duration_minutes` = %d, `active` = '%s', `sort` = %d, `updated_at` = NOW() WHERE `id` = %d",
                    $doctorId,
                    $serviceId,
                    $appointmentTypeId,
                    $locationSqlValue,
                    $DB->ForSql($priceValue, 10),
                    $DB->ForSql($currencyValue, 3),
                    $durationMinutes,
                    $DB->ForSql($activeValue, 1),
                    $sortValue,
                    $recordId
                ),
                true
            );
        } else {
            $queryResult = $DB->Query(
                sprintf(
                    "INSERT INTO `sklyar_ds_service_price` (`doctor_id`, `service_id`, `appointment_type_id`, `location_id`, `price`, `currency`, `duration_minutes`, `active`, `sort`, `created_at`, `updated_at`) VALUES (%d, %d, %d, %s, '%s', '%s', %d, '%s', %d, NOW(), NOW())",
                    $doctorId,
                    $serviceId,
                    $appointmentTypeId,
                    $locationSqlValue,
                    $DB->ForSql($priceValue, 10),
                    $DB->ForSql($currencyValue, 3),
                    $durationMinutes,
                    $DB->ForSql($activeValue, 1),
                    $sortValue
                ),
                true
            );

            if ($queryResult !== false) {
                $recordId = (int) $DB->LastID();
            }
        }

        if ($queryResult === false) {
            $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_SAVE_FAILED');
        } else {
            if ($request->getPost('apply') !== null) {
                LocalRedirect(
                    sklyarDsServicePriceGetEditUrl(
                        $recordId,
                        [
                            'saved' => 'Y',
                        ]
                    )
                );
            }

            LocalRedirect(sklyarDsServicePriceGetListUrl() . '&saved=Y');
        }
    }
}

if (
    !$request->isPost()
    && $request->getQuery('action') === 'delete'
    && $recordId > 0
    && check_bitrix_sessid()
) {
    $deleteResult = $DB->Query(
        sprintf(
            'DELETE FROM `sklyar_ds_service_price` WHERE `id` = %d',
            $recordId
        ),
        true
    );

    if ($deleteResult !== false) {
        LocalRedirect(sklyarDsServicePriceGetListUrl() . '&deleted=Y');
    }

    $errors[] = Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ERROR_DELETE_FAILED');
}

if ($isEditMode) {
    $APPLICATION->SetTitle(
        $recordId > 0
            ? Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_TITLE_EDIT')
            : Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_TITLE_ADD')
    );

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    if ($request->getQuery('saved') === 'Y' && !$errors) {
        CAdminMessage::ShowMessage([
            'MESSAGE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MESSAGE_SAVED'),
            'TYPE' => 'OK',
        ]);
    }

    if ($errors) {
        CAdminMessage::ShowMessage([
            'MESSAGE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MESSAGE_ACTION_FAILED'),
            'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $errors)),
            'HTML' => true,
            'TYPE' => 'ERROR',
        ]);
    }

    $contextMenu = new CAdminContextMenu([
        [
            'TEXT' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MENU_LIST'),
            'LINK' => sklyarDsServicePriceGetListUrl(),
            'TITLE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MENU_LIST_TITLE'),
            'ICON' => 'btn_list',
        ],
    ]);
    $contextMenu->Show();

    $tabControl = new CAdminTabControl(
        'sklyar_ds_service_price_tab_control',
        [
            [
                'DIV' => 'edit1',
                'TAB' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_TITLE_LIST'),
                'TITLE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_TAB_EDIT_TITLE'),
            ],
        ]
    );
    ?>
    <form method="post" action="<?php echo htmlspecialcharsbx(sklyarDsServicePriceGetEditUrl($recordId)); ?>">
        <?php echo bitrix_sessid_post(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $recordId; ?>">
        <?php
        $tabControl->Begin();
        $tabControl->BeginNextTab();
        ?>
        <tr>
            <td width="40%"><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_DOCTOR')); ?>:</td>
            <td>
                <select name="doctor_id">
                    <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_DOCTOR_SELECT')); ?></option>
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
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_SERVICE')); ?>:</td>
            <td>
                <select name="service_id">
                    <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_SERVICE_SELECT')); ?></option>
                    <?php
                    foreach ($serviceMap as $service) {
                        $serviceId = (int) $service['ID'];
                        ?>
                        <option
                            value="<?php echo $serviceId; ?>"
                            <?php echo (string) $serviceId === $formData['service_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialcharsbx($service['NAME'] !== '' ? $service['NAME'] : '[ID ' . $serviceId . ']'); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_APPOINTMENT_TYPE')); ?>:</td>
            <td>
                <select name="appointment_type_id">
                    <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_APPOINTMENT_TYPE_SELECT')); ?></option>
                    <?php
                    foreach ($appointmentTypeMap as $appointmentType) {
                        $appointmentTypeId = (int) $appointmentType['ID'];
                        ?>
                        <option
                            value="<?php echo $appointmentTypeId; ?>"
                            <?php echo (string) $appointmentTypeId === $formData['appointment_type_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialcharsbx($appointmentType['NAME'] !== '' ? $appointmentType['NAME'] : '[ID ' . $appointmentTypeId . ']'); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_LOCATION')); ?>:</td>
            <td>
                <select name="location_id">
                    <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_LOCATION_EMPTY')); ?></option>
                    <?php
                    foreach ($locationMap as $location) {
                        $locationId = (int) $location['ID'];
                        ?>
                        <option
                            value="<?php echo $locationId; ?>"
                            <?php echo (string) $locationId === $formData['location_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialcharsbx($location['NAME'] !== '' ? $location['NAME'] : '[ID ' . $locationId . ']'); ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_PRICE')); ?>:</td>
            <td>
                <input
                    type="text"
                    name="price"
                    value="<?php echo htmlspecialcharsbx($formData['price']); ?>"
                    size="10"
                >
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_CURRENCY')); ?>:</td>
            <td>
                <input
                    type="text"
                    name="currency"
                    value="<?php echo htmlspecialcharsbx($formData['currency']); ?>"
                    size="5"
                    maxlength="3"
                >
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_DURATION')); ?>:</td>
            <td>
                <input
                    type="text"
                    name="duration_minutes"
                    value="<?php echo htmlspecialcharsbx($formData['duration_minutes']); ?>"
                    size="10"
                >
            </td>
        </tr>
        <tr>
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_SORT')); ?>:</td>
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
            <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_ACTIVITY')); ?>:</td>
            <td>
                <input type="hidden" name="active" value="N">
                <label>
                    <input
                        type="checkbox"
                        name="active"
                        value="Y"
                        <?php echo $formData['active'] === 'Y' ? 'checked' : ''; ?>
                    >
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_ACTIVE')); ?>
                </label>
            </td>
        </tr>
        <tr class="adm-detail-content-btns">
            <td colspan="2">
                <input
                    type="submit"
                    name="save"
                    value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_BTN_SAVE')); ?>"
                    class="adm-btn-save"
                >
                <input
                    type="submit"
                    name="apply"
                    value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_BTN_APPLY')); ?>"
                >
                <input
                    type="button"
                    value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_BTN_CANCEL')); ?>"
                    onclick="window.location='<?php echo CUtil::JSEscape(sklyarDsServicePriceGetListUrl()); ?>';"
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

$APPLICATION->SetTitle(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_TITLE_LIST'));

$tableId = 'tbl_sklyar_ds_service_price';
$sort = new CAdminSorting($tableId, 'SORT', 'asc');
$adminList = new CAdminList($tableId, $sort);

$filterFields = [
    'find_id',
    'find_doctor_id',
    'find_service_id',
    'find_appointment_type_id',
    'find_location_id',
    'find_active',
];

$adminList->InitFilter($filterFields);

$find_id = trim((string) $request->getQuery('find_id', ''));
$find_doctor_id = trim((string) $request->getQuery('find_doctor_id', ''));
$find_service_id = trim((string) $request->getQuery('find_service_id', ''));
$find_appointment_type_id = trim((string) $request->getQuery('find_appointment_type_id', ''));
$find_location_id = trim((string) $request->getQuery('find_location_id', ''));
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
    if (ctype_digit($find_doctor_id) && isset($doctorMap[(int) $find_doctor_id])) {
        $whereConditions[] = '`doctor_id` = ' . (int) $find_doctor_id;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_service_id !== '') {
    if (ctype_digit($find_service_id) && isset($serviceMap[(int) $find_service_id])) {
        $whereConditions[] = '`service_id` = ' . (int) $find_service_id;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_appointment_type_id !== '') {
    if (ctype_digit($find_appointment_type_id) && isset($appointmentTypeMap[(int) $find_appointment_type_id])) {
        $whereConditions[] = '`appointment_type_id` = ' . (int) $find_appointment_type_id;
    } else {
        $whereConditions[] = '1 = 0';
    }
}

if ($find_location_id !== '') {
    if ($find_location_id === 'NULL') {
        $whereConditions[] = '`location_id` IS NULL';
    } elseif (ctype_digit($find_location_id) && isset($locationMap[(int) $find_location_id])) {
        $whereConditions[] = '`location_id` = ' . (int) $find_location_id;
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
    'SERVICE' => '`service_id`',
    'APPOINTMENT_TYPE' => '`appointment_type_id`',
    'LOCATION' => '`location_id`',
    'PRICE' => '`price`',
    'DURATION' => '`duration_minutes`',
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
        `service_id` AS SERVICE_ID,
        `appointment_type_id` AS APPOINTMENT_TYPE_ID,
        `location_id` AS LOCATION_ID,
        `price` AS PRICE,
        `currency` AS CURRENCY,
        `duration_minutes` AS DURATION_MINUTES,
        `active` AS ACTIVE,
        `sort` AS SORT,
        `updated_at` AS UPDATED_AT
    FROM `sklyar_ds_service_price`
    {$whereSql}
    ORDER BY {$sortColumnSql} {$sortOrderSql}, `id` DESC
    SQL;

$result = $DB->Query($sql);
$result = new CAdminResult($result, $tableId);
$result->NavStart(20);
$adminList->NavText($result->GetNavPrint(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_TITLE_LIST')));

$adminList->AddHeaders([
    [
        'id' => 'ID',
        'content' => 'ID',
        'default' => true,
        'sort' => 'ID',
    ],
    [
        'id' => 'DOCTOR',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_DOCTOR'),
        'default' => true,
        'sort' => 'DOCTOR',
    ],
    [
        'id' => 'SERVICE',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_SERVICE'),
        'default' => true,
        'sort' => 'SERVICE',
    ],
    [
        'id' => 'APPOINTMENT_TYPE',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_APPOINTMENT_TYPE'),
        'default' => true,
        'sort' => 'APPOINTMENT_TYPE',
    ],
    [
        'id' => 'LOCATION',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_LOCATION'),
        'default' => true,
        'sort' => 'LOCATION',
    ],
    [
        'id' => 'PRICE',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_PRICE'),
        'default' => true,
        'sort' => 'PRICE',
    ],
    [
        'id' => 'DURATION',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_DURATION'),
        'default' => true,
        'sort' => 'DURATION',
    ],
    [
        'id' => 'ACTIVE',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_ACTIVE'),
        'default' => true,
        'sort' => 'ACTIVE',
    ],
    [
        'id' => 'SORT',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_SORT'),
        'default' => true,
        'sort' => 'SORT',
    ],
    [
        'id' => 'UPDATED_AT',
        'content' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_HEADER_UPDATED_AT'),
        'default' => true,
        'sort' => 'UPDATED_AT',
    ],
]);

while ($record = $result->Fetch()) {
    $currentRecordId = (int) $record['ID'];
    $row = &$adminList->AddRow($currentRecordId, $record);
    $editUrl = sklyarDsServicePriceGetEditUrl($currentRecordId);
    $deleteUrl = sklyarDsServicePriceGetListUrl()
        . '&action=delete&id=' . $currentRecordId
        . '&sessid=' . bitrix_sessid();

    $locationValue = $record['LOCATION_ID'] === null || $record['LOCATION_ID'] === ''
        ? Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_LOCATION_EMPTY')
        : sklyarDsServicePriceGetReferenceName(
            $record['LOCATION_ID'],
            $locationMap,
            Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_UNKNOWN')
        );

    $priceValue = number_format((float) $record['PRICE'], 2, '.', '') . ' ' . trim((string) $record['CURRENCY']);
    $durationValue = (int) $record['DURATION_MINUTES'] . ' ' . Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_DURATION_SUFFIX');

    $row->AddViewField('ID', (string) $currentRecordId);
    $row->AddViewField(
        'DOCTOR',
        htmlspecialcharsbx(
            sklyarDsServicePriceGetReferenceName(
                $record['DOCTOR_ID'],
                $doctorMap,
                Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_UNKNOWN')
            )
        )
    );
    $row->AddViewField(
        'SERVICE',
        htmlspecialcharsbx(
            sklyarDsServicePriceGetReferenceName(
                $record['SERVICE_ID'],
                $serviceMap,
                Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_UNKNOWN')
            )
        )
    );
    $row->AddViewField(
        'APPOINTMENT_TYPE',
        htmlspecialcharsbx(
            sklyarDsServicePriceGetReferenceName(
                $record['APPOINTMENT_TYPE_ID'],
                $appointmentTypeMap,
                Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_UNKNOWN')
            )
        )
    );
    $row->AddViewField('LOCATION', htmlspecialcharsbx($locationValue));
    $row->AddViewField('PRICE', htmlspecialcharsbx($priceValue));
    $row->AddViewField('DURATION', htmlspecialcharsbx($durationValue));
    $row->AddViewField(
        'ACTIVE',
        htmlspecialcharsbx(
            $record['ACTIVE'] === 'Y'
                ? Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_YES')
                : Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_NO')
        )
    );
    $row->AddViewField('SORT', (string) (int) $record['SORT']);
    $row->AddViewField('UPDATED_AT', htmlspecialcharsbx((string) $record['UPDATED_AT']));

    $row->AddActions([
        [
            'ICON' => 'edit',
            'TEXT' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ACTION_EDIT'),
            'ACTION' => $adminList->ActionRedirect($editUrl),
            'DEFAULT' => true,
        ],
        [
            'ICON' => 'delete',
            'TEXT' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ACTION_DELETE'),
            'ACTION' => "if (confirm('"
                . CUtil::JSEscape(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_ACTION_DELETE_CONFIRM'))
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
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MESSAGE_SAVED'),
        'TYPE' => 'OK',
    ]);
}

if ($request->getQuery('deleted') === 'Y' && !$errors) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MESSAGE_DELETED'),
        'TYPE' => 'OK',
    ]);
}

if ($errors) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MESSAGE_ACTION_FAILED'),
        'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $errors)),
        'HTML' => true,
        'TYPE' => 'ERROR',
    ]);
}

$contextMenu = new CAdminContextMenu([
    [
        'TEXT' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MENU_ADD'),
        'LINK' => sklyarDsServicePriceGetEditUrl(),
        'TITLE' => Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_MENU_ADD_TITLE'),
        'ICON' => 'btn_new',
    ],
]);

$filter = new CAdminFilter(
    $tableId . '_filter',
    [
        'ID',
        Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_DOCTOR'),
        Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_SERVICE'),
        Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_APPOINTMENT_TYPE'),
        Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_LOCATION'),
        Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_ACTIVITY'),
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
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_DOCTOR')); ?>:</td>
        <td>
            <select name="find_doctor_id">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_ALL')); ?></option>
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
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_SERVICE')); ?>:</td>
        <td>
            <select name="find_service_id">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_ALL')); ?></option>
                <?php
                foreach ($serviceMap as $service) {
                    $serviceId = (int) $service['ID'];
                    ?>
                    <option
                        value="<?php echo $serviceId; ?>"
                        <?php echo (string) $serviceId === $find_service_id ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialcharsbx($service['NAME'] !== '' ? $service['NAME'] : '[ID ' . $serviceId . ']'); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_APPOINTMENT_TYPE')); ?>:</td>
        <td>
            <select name="find_appointment_type_id">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_ALL')); ?></option>
                <?php
                foreach ($appointmentTypeMap as $appointmentType) {
                    $appointmentTypeId = (int) $appointmentType['ID'];
                    ?>
                    <option
                        value="<?php echo $appointmentTypeId; ?>"
                        <?php echo (string) $appointmentTypeId === $find_appointment_type_id ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialcharsbx($appointmentType['NAME'] !== '' ? $appointmentType['NAME'] : '[ID ' . $appointmentTypeId . ']'); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_LOCATION')); ?>:</td>
        <td>
            <select name="find_location_id">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_ALL')); ?></option>
                <option value="NULL" <?php echo $find_location_id === 'NULL' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_LOCATION_EMPTY')); ?>
                </option>
                <?php
                foreach ($locationMap as $location) {
                    $locationId = (int) $location['ID'];
                    ?>
                    <option
                        value="<?php echo $locationId; ?>"
                        <?php echo (string) $locationId === $find_location_id ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialcharsbx($location['NAME'] !== '' ? $location['NAME'] : '[ID ' . $locationId . ']'); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <td><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FIELD_ACTIVITY')); ?>:</td>
        <td>
            <select name="find_active">
                <option value=""><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_FILTER_ALL')); ?></option>
                <option value="Y" <?php echo $find_active === 'Y' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_YES')); ?>
                </option>
                <option value="N" <?php echo $find_active === 'N' ? 'selected' : ''; ?>>
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_VALUE_NO')); ?>
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
        var excelText = <?php echo CUtil::PhpToJSObject(Loc::getMessage('SKLYAR_DS_SERVICE_PRICE_EXCEL_EXPORT')); ?>;
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
