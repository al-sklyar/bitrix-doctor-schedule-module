<?php

use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;

Loc::loadMessages(__FILE__);

if (class_exists('sklyar_doctorschedule')) {
    return;
}

class sklyar_doctorschedule extends CModule
{
    const HL_BLOCK_DOCTOR = 'doctor';
    const HL_BLOCK_SPECIALIZATION = 'specialization';
    const HL_BLOCK_APPOINTMENT_TYPE = 'appointment_type';
    const HL_BLOCK_LOCATION = 'location';
    const HL_BLOCK_SERVICE = 'service';

    public $MODULE_ID;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $this->MODULE_ID = 'sklyar.doctorschedule';

        $moduleVersion = [];
        $versionFile = __DIR__ . '/version.php';

        if (file_exists($versionFile)) {
            require $versionFile;

            if (isset($arModuleVersion) && is_array($arModuleVersion)) {
                $moduleVersion = $arModuleVersion;
            }
        }

        if (isset($moduleVersion['VERSION'])) {
            $this->MODULE_VERSION = $moduleVersion['VERSION'];
        } else {
            $this->MODULE_VERSION = '0.0.1';
        }

        if (isset($moduleVersion['VERSION_DATE'])) {
            $this->MODULE_VERSION_DATE = $moduleVersion['VERSION_DATE'];
        } else {
            $this->MODULE_VERSION_DATE = date('Y-m-d');
        }

        $this->MODULE_NAME = Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0061_1');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0062_1');
        $this->PARTNER_NAME = Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0063_1');
        $this->PARTNER_URI = 'https://skliar-cv.com/';
    }

    public function DoInstall()
    {
        global $APPLICATION;
        global $step;

        if (isset($_REQUEST['step'])) {
            $step = (int) $_REQUEST['step'];
        } else {
            $step = (int) $step;
        }

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0080_1') . $this->MODULE_NAME . '"',
                __DIR__ . '/step.php'
            );

            return;
        }

        if (!check_bitrix_sessid()) {
            return;
        }

        try {
            $this->installDatabase();
            RegisterModule($this->MODULE_ID);
            $this->InstallFiles();

            if ($this->shouldInstallDemoData()) {
                $this->installDemoData();
            }
        } catch (\Throwable $exception) {
            $this->safeRollbackInstall();
            $APPLICATION->ThrowException($exception->getMessage());

            return false;
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0107_1') . $this->MODULE_NAME . '"',
            __DIR__ . '/step_finish.php'
        );

    }

    public function DoUninstall()
    {
        global $APPLICATION;

        try {
            $this->UnInstallFiles();
            $this->uninstallDatabase();
            UnRegisterModule($this->MODULE_ID);
        } catch (\Exception $exception) {
            $APPLICATION->ThrowException($exception->getMessage());

            return false;
        }

        return true;
    }

    private function installDatabase()
    {
        $connection = Application::getConnection();
        $this->assertSupportedDatabase($connection->getType());
        $this->requireHighloadBlockModule();

        foreach ($this->getTableSqlMap() as $sql) {
            $connection->queryExecute($sql);
        }

        $this->installHighloadBlocks();
    }

    public function InstallFiles()
    {
        $componentSourceDirectory = __DIR__ . '/components';

        if (!is_dir($componentSourceDirectory)) {
            return;
        }

        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '';

        if ($documentRoot === '') {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0154_1'));
        }

        $componentTargetDirectory = $documentRoot . '/local/components';

        if (!CopyDirFiles($componentSourceDirectory, $componentTargetDirectory, true, true)) {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0160_1'));
        }
        $adminSourceDirectory = __DIR__ . '/admin';

        if (is_dir($adminSourceDirectory)) {
            $adminTargetDirectory = $documentRoot . '/bitrix/admin';

            if (!CopyDirFiles($adminSourceDirectory, $adminTargetDirectory, true, true)) {
                throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_ERROR_COPY_ADMIN'));
            }
        }
    }

    private function uninstallDatabase()
    {
        $connection = Application::getConnection();
        $this->assertSupportedDatabase($connection->getType());
        $this->requireHighloadBlockModule();

        $this->uninstallHighloadBlocks();

        foreach (array_reverse($this->getTableNames()) as $tableName) {
            $connection->queryExecute(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
        }
    }

    public function UnInstallFiles()
    {
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '';
        $componentRelativePath = '/local/components/sklyar/doctorschedule.booking';
        $vendorDirectoryPath = $documentRoot . '/local/components/sklyar';

        if ($documentRoot === '') {
            return;
        }

        if (is_dir($documentRoot . $componentRelativePath)) {
            DeleteDirFilesEx($componentRelativePath);
        }

        $adminFiles = [
            '/bitrix/admin/sklyar_doctorschedule_booking.php',
            '/bitrix/admin/sklyar_doctorschedule_schedule_rule.php',
            '/bitrix/admin/sklyar_doctorschedule_service_price.php',
        ];

        foreach ($adminFiles as $adminFile) {
            if (file_exists($documentRoot . $adminFile)) {
                DeleteDirFilesEx($adminFile);
            }
        }

        if (!is_dir($vendorDirectoryPath)) {
            return;
        }

        $vendorDirectoryItems = array_diff(scandir($vendorDirectoryPath), ['.', '..']);

        if (!$vendorDirectoryItems) {
            rmdir($vendorDirectoryPath);
        }
    }

    private function shouldInstallDemoData()
    {
        if (!isset($_REQUEST['install_demo_data'])) {
            return false;
        }

        if (!is_string($_REQUEST['install_demo_data'])) {
            return false;
        }

        return $_REQUEST['install_demo_data'] === 'Y';
    }

    private function installDemoData()
    {
        try {
            $demoData = $this->loadDemoData();
            $referenceMap = $this->installDemoHighloadBlockData($demoData);
            $this->installDemoTableData($demoData, $referenceMap);
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0244_1'),
                $exception
            );
        }
    }

    private function loadDemoData()
    {
        $demoDataFile = __DIR__ . '/demo_data.php';

        if (!file_exists($demoDataFile)) {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0255_1'));
        }

        $demoData = require $demoDataFile;

        if (!is_array($demoData)) {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0261_1'));
        }

        return $demoData;
    }

    private function installDemoHighloadBlockData(array $demoData)
    {
        $highloadBlockData = $this->getDemoSection($demoData, 'highload_blocks', 'demo_data.php');

        $referenceMap = [];

        try {
            $referenceMap['specializations'] = $this->installDemoHighloadBlockRows(
                $this->getSpecializationHighloadBlockBaseDefinition(),
                $this->getDemoSection($highloadBlockData, 'specializations', 'highload_blocks')
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0280_1'),
                $exception
            );
        }

        try {
            $referenceMap['appointment_types'] = $this->installDemoHighloadBlockRows(
                $this->getAppointmentTypeHighloadBlockBaseDefinition(),
                $this->getDemoSection($highloadBlockData, 'appointment_types', 'highload_blocks')
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0292_1'),
                $exception
            );
        }

        try {
            $referenceMap['locations'] = $this->installDemoHighloadBlockRows(
                $this->getLocationHighloadBlockBaseDefinition(),
                $this->getDemoSection($highloadBlockData, 'locations', 'highload_blocks')
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0304_1'),
                $exception
            );
        }

        try {
            $referenceMap['services'] = $this->installDemoHighloadBlockRows(
                $this->getServiceHighloadBlockBaseDefinition(),
                $this->getDemoSection($highloadBlockData, 'services', 'highload_blocks')
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0316_1'),
                $exception
            );
        }

        try {
            $referenceMap['doctors'] = $this->installDemoHighloadBlockRows(
                $this->getDoctorHighloadBlockBaseDefinition(),
                $this->prepareDoctorDemoRows(
                    $this->getDemoSection($highloadBlockData, 'doctors', 'highload_blocks'),
                    $referenceMap['specializations']
                )
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0331_1'),
                $exception
            );
        }

        return $referenceMap;
    }

    private function installDemoHighloadBlockRows(array $definition, array $rows)
    {
        try {
            $highloadBlock = $this->findHighloadBlock($definition['TABLE_NAME'], $definition['NAME']);
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0345_1'), $definition['TITLE']),
                $exception
            );
        }

        if ($highloadBlock === null) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0352_1'), $definition['TITLE'])
            );
        }

        try {
            $entity = HighloadBlockTable::compileEntity($highloadBlock);
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0360_1'), $definition['TITLE']),
                $exception
            );
        }

        $dataClass = $entity->getDataClass();
        $rowIdMap = [];

        foreach ($rows as $rowCode => $rowData) {
            if (!is_string($rowCode) || trim($rowCode) === '') {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0371_1'), $definition['TITLE'])
                );
            }

            if (!is_array($rowData)) {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0377_1'), $rowCode, $definition['TITLE'])
                );
            }

            $rowName = $this->getRequiredDemoValue($rowData, 'UF_NAME', $definition['TITLE'] . ':' . $rowCode);

            if (!is_string($rowName) || trim($rowName) === '') {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0385_1'), $rowCode, $definition['TITLE'])
                );
            }

            try {
                $rowIdMap[$rowCode] = $this->upsertDemoHighloadBlockRow($dataClass, $rowData);
            } catch (\Throwable $exception) {
                throw $this->createSystemException(
                    sprintf(
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0394_1'),
                        $rowCode,
                        $rowName,
                        $definition['TITLE']
                    ),
                    $exception
                );
            }
        }

        return $rowIdMap;
    }

    private function upsertDemoHighloadBlockRow($dataClass, array $rowData)
    {
        try {
            $existingRow = $dataClass::getList([
                'select' => ['ID'],
                'filter' => ['=UF_NAME' => $rowData['UF_NAME']],
                'limit' => 1,
            ])->fetch();

            if ($existingRow) {
                $rowId = (int) $existingRow['ID'];
                $updateResult = $dataClass::update($rowId, $rowData);

                if (!$updateResult->isSuccess()) {
                    throw new SystemException(implode(PHP_EOL, $updateResult->getErrorMessages()));
                }
            } else {
                $addResult = $dataClass::add($rowData);

                if (!$addResult->isSuccess()) {
                    throw new SystemException(implode(PHP_EOL, $addResult->getErrorMessages()));
                }

                $rowId = (int) $addResult->getId();
            }
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0434_1'), (string) $rowData['UF_NAME']),
                $exception
            );
        }

        return $rowId;
    }

    private function prepareDoctorDemoRows(array $doctorRows, array $specializationIdMap)
    {
        $preparedRows = [];

        foreach ($doctorRows as $doctorCode => $doctorRow) {
            if (!is_array($doctorRow)) {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0449_1'), $doctorCode)
                );
            }

            $specializationCodes = $this->getRequiredDemoValue($doctorRow, 'UF_SPECIALIZATIONS', 'doctors:' . $doctorCode);

            if (!is_array($specializationCodes)) {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0457_1'), $doctorCode)
                );
            }

            $preparedRows[$doctorCode] = $doctorRow;
            $preparedRows[$doctorCode]['UF_SPECIALIZATIONS'] = [];

            try {
                foreach ($specializationCodes as $specializationCode) {
                    $preparedRows[$doctorCode]['UF_SPECIALIZATIONS'][] = $this->resolveDemoReferenceId(
                        $specializationIdMap,
                        $specializationCode,
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0469_1')
                    );
                }
            } catch (\Throwable $exception) {
                throw $this->createSystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0474_1'), $doctorCode),
                    $exception
                );
            }
        }

        return $preparedRows;
    }

    private function installDemoTableData(array $demoData, array &$referenceMap)
    {
        $tableData = $this->getDemoSection($demoData, 'tables', 'demo_data.php');

        try {
            $this->installDemoScheduleRuleTableData(
                $this->getDemoSection($tableData, 'sklyar_ds_schedule_rule', 'tables'),
                $referenceMap['doctors']
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0494_1'),
                $exception
            );
        }

        try {
            $referenceMap['service_prices'] = $this->installDemoServicePriceTableData(
                $this->getDemoSection($tableData, 'sklyar_ds_service_price', 'tables'),
                $referenceMap
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0506_1'),
                $exception
            );
        }

        try {
            $this->installDemoBookingTableData(
                $this->getDemoSection($tableData, 'sklyar_ds_booking', 'tables'),
                $referenceMap
            );
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0518_1'),
                $exception
            );
        }
    }

    private function installDemoScheduleRuleTableData(array $rows, array $doctorIdMap)
    {
        $connection = Application::getConnection();

        foreach ($rows as $rowIndex => $rowData) {
            if (!is_array($rowData)) {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0531_1'), $rowIndex)
                );
            }

            try {
                $connection->add('sklyar_ds_schedule_rule', [
                    'doctor_id' => $this->resolveDemoReferenceId(
                        $doctorIdMap,
                        $this->getRequiredDemoValue($rowData, 'doctor_code', 'sklyar_ds_schedule_rule:' . $rowIndex),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0540_1')
                    ),
                    'weekday' => (int) $this->getRequiredDemoValue($rowData, 'weekday', 'sklyar_ds_schedule_rule:' . $rowIndex),
                    'time_from_minutes' => (int) $this->getRequiredDemoValue(
                        $rowData,
                        'time_from_minutes',
                        'sklyar_ds_schedule_rule:' . $rowIndex
                    ),
                    'time_to_minutes' => (int) $this->getRequiredDemoValue(
                        $rowData,
                        'time_to_minutes',
                        'sklyar_ds_schedule_rule:' . $rowIndex
                    ),
                    'active' => (string) $this->getRequiredDemoValue($rowData, 'active', 'sklyar_ds_schedule_rule:' . $rowIndex),
                    'sort' => (int) $this->getRequiredDemoValue($rowData, 'sort', 'sklyar_ds_schedule_rule:' . $rowIndex),
                    'created_at' => $this->getRequiredDemoDateTimeValue(
                        $rowData,
                        'created_at',
                        'sklyar_ds_schedule_rule:' . $rowIndex
                    ),
                    'updated_at' => $this->getOptionalDemoDateTimeValue(
                        $rowData,
                        'updated_at',
                        'sklyar_ds_schedule_rule:' . $rowIndex
                    ),
                ]);
            } catch (\Throwable $exception) {
                throw $this->createSystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0568_1'), $rowIndex),
                    $exception
                );
            }
        }
    }

    private function installDemoServicePriceTableData(array $rows, array $referenceMap)
    {
        $connection = Application::getConnection();
        $servicePriceIdMap = [];

        foreach ($rows as $rowCode => $rowData) {
            if (!is_string($rowCode) || trim($rowCode) === '') {
                throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0582_1'));
            }

            if (!is_array($rowData)) {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0587_1'), $rowCode)
                );
            }

            try {
                $servicePriceIdMap[$rowCode] = (int) $connection->add('sklyar_ds_service_price', [
                    'doctor_id' => $this->resolveDemoReferenceId(
                        $referenceMap['doctors'],
                        $this->getRequiredDemoValue($rowData, 'doctor_code', 'sklyar_ds_service_price:' . $rowCode),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0596_1')
                    ),
                    'service_id' => $this->resolveDemoReferenceId(
                        $referenceMap['services'],
                        $this->getRequiredDemoValue($rowData, 'service_code', 'sklyar_ds_service_price:' . $rowCode),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0601_1')
                    ),
                    'appointment_type_id' => $this->resolveDemoReferenceId(
                        $referenceMap['appointment_types'],
                        $this->getRequiredDemoValue(
                            $rowData,
                            'appointment_type_code',
                            'sklyar_ds_service_price:' . $rowCode
                        ),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0610_1')
                    ),
                    'location_id' => $this->resolveDemoReferenceId(
                        $referenceMap['locations'],
                        $this->getOptionalDemoValue($rowData, 'location_code'),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0615_1'),
                        true
                    ),
                    'price' => (string) $this->getRequiredDemoValue($rowData, 'price', 'sklyar_ds_service_price:' . $rowCode),
                    'currency' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'currency',
                        'sklyar_ds_service_price:' . $rowCode
                    ),
                    'duration_minutes' => (int) $this->getRequiredDemoValue(
                        $rowData,
                        'duration_minutes',
                        'sklyar_ds_service_price:' . $rowCode
                    ),
                    'active' => (string) $this->getRequiredDemoValue($rowData, 'active', 'sklyar_ds_service_price:' . $rowCode),
                    'sort' => (int) $this->getRequiredDemoValue($rowData, 'sort', 'sklyar_ds_service_price:' . $rowCode),
                    'created_at' => $this->getRequiredDemoDateTimeValue(
                        $rowData,
                        'created_at',
                        'sklyar_ds_service_price:' . $rowCode
                    ),
                    'updated_at' => $this->getOptionalDemoDateTimeValue(
                        $rowData,
                        'updated_at',
                        'sklyar_ds_service_price:' . $rowCode
                    ),
                ]);
            } catch (\Throwable $exception) {
                throw $this->createSystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0644_1'), $rowCode),
                    $exception
                );
            }
        }

        return $servicePriceIdMap;
    }

    private function installDemoBookingTableData(array $rows, array $referenceMap)
    {
        $connection = Application::getConnection();

        foreach ($rows as $rowIndex => $rowData) {
            if (!is_array($rowData)) {
                throw new SystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0660_1'), $rowIndex)
                );
            }

            try {
                $connection->add('sklyar_ds_booking', [
                    'service_price_id' => $this->resolveDemoReferenceId(
                        $referenceMap['service_prices'],
                        $this->getRequiredDemoValue($rowData, 'service_price_code', 'sklyar_ds_booking:' . $rowIndex),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0669_1')
                    ),
                    'doctor_id' => $this->resolveDemoReferenceId(
                        $referenceMap['doctors'],
                        $this->getRequiredDemoValue($rowData, 'doctor_code', 'sklyar_ds_booking:' . $rowIndex),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0674_1')
                    ),
                    'service_id' => $this->resolveDemoReferenceId(
                        $referenceMap['services'],
                        $this->getRequiredDemoValue($rowData, 'service_code', 'sklyar_ds_booking:' . $rowIndex),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0679_1')
                    ),
                    'appointment_type_id' => $this->resolveDemoReferenceId(
                        $referenceMap['appointment_types'],
                        $this->getRequiredDemoValue($rowData, 'appointment_type_code', 'sklyar_ds_booking:' . $rowIndex),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0684_1')
                    ),
                    'location_id' => $this->resolveDemoReferenceId(
                        $referenceMap['locations'],
                        $this->getOptionalDemoValue($rowData, 'location_code'),
                        Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0689_1'),
                        true
                    ),
                    'booking_date' => $this->getRequiredDemoDateValue(
                        $rowData,
                        'booking_date',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'time_from_minutes' => (int) $this->getRequiredDemoValue(
                        $rowData,
                        'time_from_minutes',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'time_to_minutes' => (int) $this->getRequiredDemoValue(
                        $rowData,
                        'time_to_minutes',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'patient_name' => (string) $this->getRequiredDemoValue($rowData, 'patient_name', 'sklyar_ds_booking:' . $rowIndex),
                    'patient_phone' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'patient_phone',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'patient_email' => $this->getOptionalDemoValue($rowData, 'patient_email'),
                    'patient_comment' => $this->getOptionalDemoValue($rowData, 'patient_comment'),
                    'visit_address' => $this->getOptionalDemoValue($rowData, 'visit_address'),
                    'online_link' => $this->getOptionalDemoValue($rowData, 'online_link'),
                    'price_snapshot' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'price_snapshot',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'currency_snapshot' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'currency_snapshot',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'doctor_name_snapshot' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'doctor_name_snapshot',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'service_name_snapshot' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'service_name_snapshot',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'appointment_type_name_snapshot' => (string) $this->getRequiredDemoValue(
                        $rowData,
                        'appointment_type_name_snapshot',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'location_name_snapshot' => $this->getOptionalDemoValue($rowData, 'location_name_snapshot'),
                    'status' => (string) $this->getRequiredDemoValue($rowData, 'status', 'sklyar_ds_booking:' . $rowIndex),
                    'created_at' => $this->getRequiredDemoDateTimeValue(
                        $rowData,
                        'created_at',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                    'updated_at' => $this->getOptionalDemoDateTimeValue(
                        $rowData,
                        'updated_at',
                        'sklyar_ds_booking:' . $rowIndex
                    ),
                ]);
            } catch (\Throwable $exception) {
                throw $this->createSystemException(
                    sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0757_1'), $rowIndex),
                    $exception
                );
            }
        }
    }

    private function getDemoSection(array $data, $sectionKey, $context)
    {
        if (!array_key_exists($sectionKey, $data)) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0768_1'), $context, $sectionKey)
            );
        }

        if (!is_array($data[$sectionKey])) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0774_1'), $sectionKey, $context)
            );
        }

        return $data[$sectionKey];
    }

    private function getRequiredDemoValue(array $rowData, $fieldName, $context)
    {
        if (!array_key_exists($fieldName, $rowData)) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0785_1'), $context, $fieldName)
            );
        }

        return $rowData[$fieldName];
    }

    private function getOptionalDemoValue(array $rowData, $fieldName)
    {
        if (!array_key_exists($fieldName, $rowData)) {
            return null;
        }

        return $rowData[$fieldName];
    }

    private function getRequiredDemoDateTimeValue(array $rowData, $fieldName, $context)
    {
        $value = $this->getRequiredDemoValue($rowData, $fieldName, $context);

        if (!is_string($value) || trim($value) === '') {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0807_1'), $fieldName, $context)
            );
        }

        try {
            return new DateTime($value, 'Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0815_1'), $fieldName, $context),
                $exception
            );
        }
    }

    private function getOptionalDemoDateTimeValue(array $rowData, $fieldName, $context)
    {
        $value = $this->getOptionalDemoValue($rowData, $fieldName);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0831_1'), $fieldName, $context)
            );
        }

        try {
            return new DateTime($value, 'Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0839_1'), $fieldName, $context),
                $exception
            );
        }
    }

    private function getRequiredDemoDateValue(array $rowData, $fieldName, $context)
    {
        $value = $this->getRequiredDemoValue($rowData, $fieldName, $context);

        if (!is_string($value) || trim($value) === '') {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0851_1'), $fieldName, $context)
            );
        }

        try {
            return new Date($value, 'Y-m-d');
        } catch (\Throwable $exception) {
            throw $this->createSystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0859_1'), $fieldName, $context),
                $exception
            );
        }
    }

    private function createSystemException($message, \Throwable $exception = null)
    {
        $exceptionMessage = '';

        if ($exception !== null) {
            $exceptionMessage = trim((string) $exception->getMessage());
        }

        if ($exceptionMessage !== '') {
            return new SystemException($message . PHP_EOL . $exceptionMessage);
        }

        return new SystemException($message);
    }

    private function resolveDemoReferenceId(array $referenceMap, $referenceCode, $entityName, $allowNull = false)
    {
        if ($referenceCode === null) {
            if ($allowNull) {
                return null;
            }

            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0888_1'), $entityName)
            );
        }

        if (!is_string($referenceCode) || trim($referenceCode) === '') {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0894_1'), $entityName)
            );
        }

        if (!array_key_exists($referenceCode, $referenceMap)) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_0900_1'), $referenceCode, $entityName)
            );
        }

        return (int) $referenceMap[$referenceCode];
    }

    private function installHighloadBlocks()
    {
        $specializationBlockId = $this->ensureHighloadBlock($this->getSpecializationHighloadBlockDefinition());
        $specializationNameFieldId = $this->getUserFieldId(
            'HLBLOCK_' . $specializationBlockId,
            'UF_NAME'
        );

        $this->ensureHighloadBlock($this->getAppointmentTypeHighloadBlockDefinition());
        $this->ensureHighloadBlock($this->getLocationHighloadBlockDefinition());
        $this->ensureHighloadBlock($this->getServiceHighloadBlockDefinition());
        $this->ensureHighloadBlock(
            $this->getDoctorHighloadBlockDefinition($specializationBlockId, $specializationNameFieldId)
        );
    }

    private function uninstallHighloadBlocks()
    {
        $definitions = [
            $this->getDoctorHighloadBlockBaseDefinition(),
            $this->getServiceHighloadBlockBaseDefinition(),
            $this->getLocationHighloadBlockBaseDefinition(),
            $this->getAppointmentTypeHighloadBlockBaseDefinition(),
            $this->getSpecializationHighloadBlockBaseDefinition(),
        ];

        foreach ($definitions as $definition) {
            $highloadBlock = $this->findHighloadBlock($definition['TABLE_NAME'], $definition['NAME']);

            if ($highloadBlock === null) {
                continue;
            }

            $deleteResult = HighloadBlockTable::delete((int) $highloadBlock['ID']);

            if (!$deleteResult->isSuccess()) {
                throw new SystemException(implode(PHP_EOL, $deleteResult->getErrorMessages()));
            }
        }
    }

    private function ensureHighloadBlock(array $definition)
    {
        $highloadBlock = $this->findHighloadBlock($definition['TABLE_NAME'], $definition['NAME']);

        if ($highloadBlock === null) {
            $addResult = HighloadBlockTable::add([
                'NAME' => $definition['NAME'],
                'TABLE_NAME' => $definition['TABLE_NAME'],
            ]);

            if (!$addResult->isSuccess()) {
                throw new SystemException(implode(PHP_EOL, $addResult->getErrorMessages()));
            }

            $highloadBlockId = (int) $addResult->getId();
        } else {
            $highloadBlockId = (int) $highloadBlock['ID'];
        }

        foreach ($this->getHighloadBlockLanguageMap($definition) as $languageId => $languageName) {
            $this->ensureHighloadBlockLang($highloadBlockId, $languageId, $languageName);
        }

        $entityId = 'HLBLOCK_' . $highloadBlockId;

        foreach ($definition['FIELDS'] as $fieldDefinition) {
            $this->ensureUserField($entityId, $fieldDefinition);
        }

        return $highloadBlockId;
    }

    private function ensureHighloadBlockLang($highloadBlockId, $languageId, $name)
    {
        $languageRow = HighloadBlockLangTable::getList([
            'filter' => [
                '=ID' => $highloadBlockId,
                '=LID' => $languageId,
            ],
            'limit' => 1,
        ])->fetch();

        if ($languageRow) {
            $updateResult = HighloadBlockLangTable::update(
                [
                    'ID' => $highloadBlockId,
                    'LID' => $languageId,
                ],
                [
                    'NAME' => $name,
                ]
            );

            if (!$updateResult->isSuccess()) {
                throw new SystemException(implode(PHP_EOL, $updateResult->getErrorMessages()));
            }

            return;
        }

        $addResult = HighloadBlockLangTable::add([
            'ID' => $highloadBlockId,
            'LID' => $languageId,
            'NAME' => $name,
        ]);

        if (!$addResult->isSuccess()) {
            throw new SystemException(implode(PHP_EOL, $addResult->getErrorMessages()));
        }
    }

    private function getHighloadBlockLanguageMap(array $definition)
    {
        $languageMap = [];

        if (!class_exists('CLanguage')) {
            return [
                'ru' => $definition['TITLE'],
            ];
        }

        $languageBy = 'sort';
        $languageOrder = 'asc';
        $languageIterator = CLanguage::GetList($languageBy, $languageOrder);

        while ($language = $languageIterator->Fetch()) {
            $languageId = (string) $language['LID'];
            $languageMap[$languageId] = $languageId === 'ru' ? $definition['TITLE'] : $definition['NAME'];
        }

        if (!$languageMap) {
            $languageMap['ru'] = $definition['TITLE'];
        }

        return $languageMap;
    }

    private function ensureUserField($entityId, array $fieldDefinition)
    {
        $existingField = CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => $fieldDefinition['FIELD_NAME'],
            ]
        )->Fetch();

        if ($existingField) {
            return (int) $existingField['ID'];
        }

        $userTypeEntity = new CUserTypeEntity();
        $fieldId = $userTypeEntity->Add(array_merge($fieldDefinition, ['ENTITY_ID' => $entityId]));

        if (!$fieldId) {
            global $APPLICATION;

            $exception = is_object($APPLICATION) ? $APPLICATION->GetException() : null;
            $message = $exception ? $exception->GetString() : Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1066_1');

            throw new SystemException($message);
        }

        return (int) $fieldId;
    }

    private function findHighloadBlock($tableName, $name)
    {
        $highloadBlock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
            'limit' => 1,
        ])->fetch();

        if ($highloadBlock) {
            return $highloadBlock;
        }

        $highloadBlock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $name],
            'limit' => 1,
        ])->fetch();

        if ($highloadBlock) {
            return $highloadBlock;
        }

        return null;
    }

    private function getUserFieldId($entityId, $fieldName)
    {
        $userField = CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => $fieldName,
            ]
        )->Fetch();

        if (!$userField) {
            throw new SystemException(
                sprintf(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1109_1'), $fieldName, $entityId)
            );
        }

        return (int) $userField['ID'];
    }

    private function getTableSqlMap()
    {
        return [
            'sklyar_ds_schedule_rule' => <<<SQL
                CREATE TABLE IF NOT EXISTS `sklyar_ds_schedule_rule` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `doctor_id` INT UNSIGNED NOT NULL,
                    `weekday` TINYINT UNSIGNED NOT NULL,
                    `time_from_minutes` SMALLINT UNSIGNED NOT NULL,
                    `time_to_minutes` SMALLINT UNSIGNED NOT NULL,
                    `active` CHAR(1) NOT NULL DEFAULT 'Y',
                    `sort` INT NOT NULL DEFAULT 100,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    KEY `ix_sklyar_ds_schedule_rule_doctor_weekday` (`doctor_id`, `weekday`),
                    KEY `ix_sklyar_ds_schedule_rule_active_sort` (`active`, `sort`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
            ,
            'sklyar_ds_service_price' => <<<SQL
                CREATE TABLE IF NOT EXISTS `sklyar_ds_service_price` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `doctor_id` INT UNSIGNED NOT NULL,
                    `service_id` INT UNSIGNED NOT NULL,
                    `appointment_type_id` INT UNSIGNED NOT NULL,
                    `location_id` INT UNSIGNED NULL,
                    `price` DECIMAL(10,2) NOT NULL,
                    `currency` CHAR(3) NOT NULL DEFAULT 'RUB',
                    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
                    `active` CHAR(1) NOT NULL DEFAULT 'Y',
                    `sort` INT NOT NULL DEFAULT 100,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    KEY `ix_sklyar_ds_service_price_doctor` (`doctor_id`),
                    KEY `ix_sklyar_ds_service_price_service` (`service_id`),
                    KEY `ix_sklyar_ds_service_price_appointment_type` (`appointment_type_id`),
                    KEY `ix_sklyar_ds_service_price_location` (`location_id`),
                    KEY `ix_sklyar_ds_service_price_active_sort` (`active`, `sort`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
            ,
            'sklyar_ds_booking' => <<<SQL
                CREATE TABLE IF NOT EXISTS `sklyar_ds_booking` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `service_price_id` INT UNSIGNED NOT NULL,
                    `doctor_id` INT UNSIGNED NOT NULL,
                    `service_id` INT UNSIGNED NOT NULL,
                    `appointment_type_id` INT UNSIGNED NOT NULL,
                    `location_id` INT UNSIGNED NULL,
                    `booking_date` DATE NOT NULL,
                    `time_from_minutes` SMALLINT UNSIGNED NOT NULL,
                    `time_to_minutes` SMALLINT UNSIGNED NOT NULL,
                    `patient_name` VARCHAR(255) NOT NULL,
                    `patient_phone` VARCHAR(50) NOT NULL,
                    `patient_email` VARCHAR(255) NULL,
                    `patient_comment` TEXT NULL,
                    `visit_address` TEXT NULL,
                    `online_link` VARCHAR(500) NULL,
                    `price_snapshot` DECIMAL(10,2) NOT NULL,
                    `currency_snapshot` CHAR(3) NOT NULL DEFAULT 'RUB',
                    `doctor_name_snapshot` VARCHAR(255) NOT NULL,
                    `service_name_snapshot` VARCHAR(255) NOT NULL,
                    `appointment_type_name_snapshot` VARCHAR(255) NOT NULL,
                    `location_name_snapshot` VARCHAR(255) NULL,
                    `status` VARCHAR(50) NOT NULL DEFAULT 'new',
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    KEY `ix_sklyar_ds_booking_service_price` (`service_price_id`),
                    KEY `ix_sklyar_ds_booking_doctor_date` (`doctor_id`, `booking_date`),
                    KEY `ix_sklyar_ds_booking_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
            ,
        ];
    }

    private function getTableNames()
    {
        return array_keys($this->getTableSqlMap());
    }

    private function getDoctorHighloadBlockDefinition($specializationBlockId, $specializationFieldId)
    {
        $definition = $this->getDoctorHighloadBlockBaseDefinition();
        $definition['FIELDS'] = [
            $this->getStringUserFieldDefinition('UF_NAME', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1204_1'), true),
            $this->getBooleanUserFieldDefinition('UF_ACTIVE', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1205_1'), true),
            $this->getIntegerUserFieldDefinition('UF_SORT', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1206_1'), 100),
            $this->getHlReferenceUserFieldDefinition(
                'UF_SPECIALIZATIONS',
                Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1209_1'),
                $specializationBlockId,
                $specializationFieldId,
                true
            ),
        ];

        return $definition;
    }

    private function getDoctorHighloadBlockBaseDefinition()
    {
        return [
            'KEY' => self::HL_BLOCK_DOCTOR,
            'NAME' => 'SklyarDsDoctor',
            'TABLE_NAME' => 'sklyar_ds_doctor',
            'TITLE' => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1225_1'),
            'FIELDS' => [],
        ];
    }

    private function getSpecializationHighloadBlockDefinition()
    {
        $definition = $this->getSpecializationHighloadBlockBaseDefinition();
        $definition['FIELDS'] = [
            $this->getStringUserFieldDefinition('UF_NAME', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1234_1'), true),
            $this->getBooleanUserFieldDefinition('UF_ACTIVE', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1235_1'), true),
            $this->getIntegerUserFieldDefinition('UF_SORT', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1236_1'), 100),
        ];

        return $definition;
    }

    private function getSpecializationHighloadBlockBaseDefinition()
    {
        return [
            'KEY' => self::HL_BLOCK_SPECIALIZATION,
            'NAME' => 'SklyarDsSpecialization',
            'TABLE_NAME' => 'sklyar_ds_specialization',
            'TITLE' => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1248_1'),
            'FIELDS' => [],
        ];
    }

    private function getAppointmentTypeHighloadBlockDefinition()
    {
        $definition = $this->getAppointmentTypeHighloadBlockBaseDefinition();
        $definition['FIELDS'] = [
            $this->getStringUserFieldDefinition('UF_NAME', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1257_1'), true),
            $this->getBooleanUserFieldDefinition('UF_ACTIVE', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1258_1'), true),
            $this->getIntegerUserFieldDefinition('UF_SORT', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1259_1'), 100),
        ];

        return $definition;
    }

    private function getAppointmentTypeHighloadBlockBaseDefinition()
    {
        return [
            'KEY' => self::HL_BLOCK_APPOINTMENT_TYPE,
            'NAME' => 'SklyarDsAppointmentType',
            'TABLE_NAME' => 'sklyar_ds_appointment_type',
            'TITLE' => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1271_1'),
            'FIELDS' => [],
        ];
    }

    private function getLocationHighloadBlockDefinition()
    {
        $definition = $this->getLocationHighloadBlockBaseDefinition();
        $definition['FIELDS'] = [
            $this->getStringUserFieldDefinition('UF_NAME', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1280_1'), true),
            $this->getStringUserFieldDefinition('UF_ADDRESS', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1281_1'), false, 255, 3),
            $this->getBooleanUserFieldDefinition('UF_ACTIVE', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1282_1'), true),
            $this->getIntegerUserFieldDefinition('UF_SORT', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1283_1'), 100),
        ];

        return $definition;
    }

    private function getLocationHighloadBlockBaseDefinition()
    {
        return [
            'KEY' => self::HL_BLOCK_LOCATION,
            'NAME' => 'SklyarDsLocation',
            'TABLE_NAME' => 'sklyar_ds_location',
            'TITLE' => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1295_1'),
            'FIELDS' => [],
        ];
    }

    private function getServiceHighloadBlockDefinition()
    {
        $definition = $this->getServiceHighloadBlockBaseDefinition();
        $definition['FIELDS'] = [
            $this->getStringUserFieldDefinition('UF_NAME', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1304_1'), true),
            $this->getStringUserFieldDefinition('UF_DESCRIPTION', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1305_1'), false, 255, 4),
            $this->getBooleanUserFieldDefinition('UF_ACTIVE', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1306_1'), true),
            $this->getIntegerUserFieldDefinition('UF_SORT', Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1307_1'), 100),
        ];

        return $definition;
    }

    private function getServiceHighloadBlockBaseDefinition()
    {
        return [
            'KEY' => self::HL_BLOCK_SERVICE,
            'NAME' => 'SklyarDsService',
            'TABLE_NAME' => 'sklyar_ds_service',
            'TITLE' => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1319_1'),
            'FIELDS' => [],
        ];
    }

    private function getStringUserFieldDefinition($fieldName, $label, $mandatory, $maxLength = 255, $rows = 1)
    {
        return [
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => 'string',
            'XML_ID' => $fieldName,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => $mandatory ? 'Y' : 'N',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'Y',
            'EDIT_FORM_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'SETTINGS' => [
                'SIZE' => 20,
                'ROWS' => $rows,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => $maxLength,
                'DEFAULT_VALUE' => '',
            ],
        ];
    }

    private function getIntegerUserFieldDefinition($fieldName, $label, $defaultValue)
    {
        return [
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => 'integer',
            'XML_ID' => $fieldName,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'SETTINGS' => [
                'DEFAULT_VALUE' => $defaultValue,
                'SIZE' => 20,
                'MIN_VALUE' => 0,
                'MAX_VALUE' => 2147483647,
            ],
        ];
    }

    private function getBooleanUserFieldDefinition($fieldName, $label, $defaultValue)
    {
        return [
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => 'boolean',
            'XML_ID' => $fieldName,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'SETTINGS' => [
                'DEFAULT_VALUE' => $defaultValue ? 1 : 0,
                'DISPLAY' => 'CHECKBOX',
                'LABEL' => [
                    0 => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1422_1'),
                    1 => Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1423_1'),
                ],
                'LABEL_CHECKBOX' => $label,
            ],
        ];
    }

    private function getHlReferenceUserFieldDefinition(
        $fieldName,
        $label,
        $highloadBlockId,
        $highloadFieldId,
        $multiple = false
    ) {
        return [
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => 'hlblock',
            'XML_ID' => $fieldName,
            'SORT' => 100,
            'MULTIPLE' => $multiple ? 'Y' : 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => $label,
                'en' => $fieldName,
            ],
            'SETTINGS' => [
                'DISPLAY' => 'LIST',
                'LIST_HEIGHT' => 5,
                'HLBLOCK_ID' => $highloadBlockId,
                'HLFIELD_ID' => $highloadFieldId,
                'DEFAULT_VALUE' => 0,
            ],
        ];
    }

    private function requireHighloadBlockModule()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1473_1'));
        }
    }

    private function assertSupportedDatabase($databaseType)
    {
        if ($databaseType !== 'mysql') {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_INSTALL_INDEX_1480_1'));
        }
    }

    private function safeRollbackInstall()
    {
        try {
            $this->UnInstallFiles();
        } catch (\Throwable $exception) {
        }

        try {
            $this->uninstallDatabase();
        } catch (\Throwable $exception) {
        }

        if (IsModuleInstalled($this->MODULE_ID)) {
            UnRegisterModule($this->MODULE_ID);
        }
    }
}
