<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use Bitrix\Main\Web\Json;
use Bitrix\Highloadblock\HighloadBlockTable;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

class SklyarDoctorScheduleBookingComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        return [
            'TITLE' => isset($arParams['TITLE']) && trim((string) $arParams['TITLE']) !== ''
                ? trim((string) $arParams['TITLE'])
                : Loc::getMessage('SKLYAR_DS_CLASS_PHP_0024_1'),
            'CACHE_TYPE' => $arParams['CACHE_TYPE'] ?? 'N',
            'CACHE_TIME' => isset($arParams['CACHE_TIME']) ? (int) $arParams['CACHE_TIME'] : 3600,
        ];
    }

    public function executeComponent()
    {
        $isAjaxBookingRequest = $this->isAjaxBookingRequest();

        try {
            $this->checkRequiredModules();

            if ($isAjaxBookingRequest) {
                $this->handleAjaxBookingRequest();

                return;
            }

            $state = $this->buildState();
            $doctors = $state['doctors'];
            $consentSettings = $this->getConsentSettings();

            $this->arResult = [
                'COMPONENT_UID' => 'skds-booking-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8),
                'TITLE' => $this->arParams['TITLE'],
                'EMPTY_MESSAGE' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_0043_1'),
                'HAS_DATA' => !empty($doctors)
                    && !empty($state['services'])
                    && !empty($state['appointmentTypes'])
                    && !empty($state['servicePrices'])
                    && !empty($state['scheduleRules']),
                'STATE' => $state,
                'CONSENT' => $consentSettings,
            ];
            $this->arResult['STATE_JSON'] = Json::encode($state);

            $this->includeComponentTemplate();
        } catch (\Throwable $exception) {
            if ($isAjaxBookingRequest) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'errors' => [],
                ]);
            }

            ShowError($exception->getMessage());
        }
    }

    private function isAjaxBookingRequest(): bool
    {
        $request = Application::getInstance()->getContext()->getRequest();

        return $request->isPost() && (string) $request->getPost('sklyar_ds_action') === 'create_booking';
    }

    private function handleAjaxBookingRequest(): void
    {
        if (!check_bitrix_sessid()) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_INVALID_SESSION'),
                'errors' => [],
            ]);
        }

        $request = Application::getInstance()->getContext()->getRequest();
        $payload = [
            'servicePriceId' => (int) $request->getPost('service_price_id'),
            'doctorId' => (int) $request->getPost('doctor_id'),
            'serviceId' => (int) $request->getPost('service_id'),
            'appointmentTypeId' => (int) $request->getPost('appointment_type_id'),
            'locationId' => $request->getPost('location_id') !== null && $request->getPost('location_id') !== ''
                ? (int) $request->getPost('location_id')
                : null,
            'bookingDate' => trim((string) $request->getPost('booking_date')),
            'timeFromMinutes' => (int) $request->getPost('time_from_minutes'),
            'timeToMinutes' => (int) $request->getPost('time_to_minutes'),
            'patientName' => trim((string) $request->getPost('patient_name')),
            'patientPhone' => trim((string) $request->getPost('patient_phone')),
            'patientEmail' => trim((string) $request->getPost('patient_email')),
            'patientComment' => trim((string) $request->getPost('patient_comment')),
            'patientConsentAccepted' => (string) $request->getPost('patient_consent') === 'Y',
        ];

        $fieldErrors = $this->validateBookingPayload($payload);

        if (!empty($fieldErrors)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_FORM_INVALID'),
                'errors' => $fieldErrors,
            ]);
        }

        $servicePrice = $this->loadServicePriceById($payload['servicePriceId']);

        if ($servicePrice === null) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SERVICE_PRICE_NOT_FOUND'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SERVICE_PRICE_NOT_FOUND')],
            ]);
        }

        if (!$this->isPayloadConsistentWithServicePrice($payload, $servicePrice)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_INVALID'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_INVALID')],
            ]);
        }

        if ($this->isBookingSlotInPast($payload['bookingDate'], $payload['timeFromMinutes'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PAST_SLOT'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PAST_SLOT')],
            ]);
        }

        if (!$this->isBookingSlotInsideSchedule(
            $payload['doctorId'],
            $payload['bookingDate'],
            $payload['timeFromMinutes'],
            $payload['timeToMinutes']
        )) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_INVALID'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_INVALID')],
            ]);
        }

        if ($this->hasActiveBookingConflict(
            $payload['doctorId'],
            $payload['bookingDate'],
            $payload['timeFromMinutes'],
            $payload['timeToMinutes']
        )) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_TAKEN'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_TAKEN')],
            ]);
        }

        $doctor = $this->loadHighloadBlockRowById('sklyar_ds_doctor', $payload['doctorId'], ['ID', 'UF_NAME']);
        $service = $this->loadHighloadBlockRowById('sklyar_ds_service', $payload['serviceId'], ['ID', 'UF_NAME']);
        $appointmentType = $this->loadHighloadBlockRowById(
            'sklyar_ds_appointment_type',
            $payload['appointmentTypeId'],
            ['ID', 'UF_NAME']
        );
        $location = $payload['locationId'] !== null
            ? $this->loadHighloadBlockRowById('sklyar_ds_location', $payload['locationId'], ['ID', 'UF_NAME', 'UF_ADDRESS'])
            : null;

        if ($doctor === null || $service === null || $appointmentType === null) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_REFERENCE_NOT_FOUND'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_REFERENCE_NOT_FOUND')],
            ]);
        }

        if ($payload['locationId'] !== null && $location === null) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_REFERENCE_NOT_FOUND'),
                'errors' => ['slot' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_REFERENCE_NOT_FOUND')],
            ]);
        }

        $bookingId = $this->createBookingRecord($payload, $servicePrice, $doctor, $service, $appointmentType, $location);

        $this->sendJsonResponse([
            'success' => true,
            'message' => Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SUCCESS'),
            'booking' => [
                'id' => $bookingId,
                'servicePriceId' => (int) $payload['servicePriceId'],
                'doctorId' => (int) $payload['doctorId'],
                'bookingDate' => $payload['bookingDate'],
                'timeFromMinutes' => (int) $payload['timeFromMinutes'],
                'timeToMinutes' => (int) $payload['timeToMinutes'],
                'status' => 'new',
            ],
        ]);
    }

    private function validateBookingPayload(array $payload): array
    {
        $errors = [];

        if ($payload['patientName'] === '') {
            $errors['patientName'] = Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PATIENT_NAME_REQUIRED');
        }

        if ($payload['patientPhone'] === '') {
            $errors['patientPhone'] = Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PATIENT_PHONE_REQUIRED');
        } elseif (strlen(preg_replace('/\D+/', '', $payload['patientPhone'])) < 10) {
            $errors['patientPhone'] = Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PATIENT_PHONE_INVALID');
        }

        if ($payload['patientEmail'] !== '' && filter_var($payload['patientEmail'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['patientEmail'] = Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PATIENT_EMAIL_INVALID');
        }

        if (!$payload['patientConsentAccepted']) {
            $errors['patientConsent'] = Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_PATIENT_CONSENT_REQUIRED');
        }

        if (
            $payload['servicePriceId'] <= 0
            || $payload['doctorId'] <= 0
            || $payload['serviceId'] <= 0
            || $payload['appointmentTypeId'] <= 0
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['bookingDate'])
            || $payload['timeFromMinutes'] < 0
            || $payload['timeToMinutes'] <= $payload['timeFromMinutes']
        ) {
            $errors['slot'] = Loc::getMessage('SKLYAR_DS_CLASS_PHP_BOOKING_SLOT_INVALID');
        }

        return $errors;
    }

    private function loadServicePriceById(int $servicePriceId): ?array
    {
        $rows = $this->fetchTableRows(
            'sklyar_ds_service_price',
            [
                'id',
                'doctor_id',
                'service_id',
                'appointment_type_id',
                'location_id',
                'price',
                'currency',
                'duration_minutes',
            ],
            "id = {$servicePriceId} AND active = 'Y'",
            'id ASC'
        );

        return isset($rows[0]) ? $rows[0] : null;
    }

    private function isPayloadConsistentWithServicePrice(array $payload, array $servicePrice): bool
    {
        $expectedDuration = (int) $servicePrice['duration_minutes'];
        $duration = (int) $payload['timeToMinutes'] - (int) $payload['timeFromMinutes'];
        $servicePriceLocationId = $servicePrice['location_id'] !== null ? (int) $servicePrice['location_id'] : null;

        if ((int) $servicePrice['doctor_id'] !== (int) $payload['doctorId']) {
            return false;
        }

        if ((int) $servicePrice['service_id'] !== (int) $payload['serviceId']) {
            return false;
        }

        if ((int) $servicePrice['appointment_type_id'] !== (int) $payload['appointmentTypeId']) {
            return false;
        }

        if ($servicePriceLocationId !== $payload['locationId']) {
            return false;
        }

        return $duration === $expectedDuration;
    }

    private function isBookingSlotInPast(string $bookingDate, int $timeFromMinutes): bool
    {
        $slotStart = $this->buildPhpDateTime($bookingDate, $timeFromMinutes);
        $now = new \DateTimeImmutable();

        return $slotStart <= $now;
    }

    private function isBookingSlotInsideSchedule(
        int $doctorId,
        string $bookingDate,
        int $timeFromMinutes,
        int $timeToMinutes
    ): bool {
        $weekday = (int) (new \DateTimeImmutable($bookingDate))->format('N');
        $scheduleRules = $this->fetchTableRows(
            'sklyar_ds_schedule_rule',
            ['id', 'time_from_minutes', 'time_to_minutes'],
            "active = 'Y' AND doctor_id = {$doctorId} AND weekday = {$weekday}",
            'sort ASC, id ASC'
        );

        foreach ($scheduleRules as $scheduleRule) {
            if (
                $timeFromMinutes >= (int) $scheduleRule['time_from_minutes']
                && $timeToMinutes <= (int) $scheduleRule['time_to_minutes']
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasActiveBookingConflict(
        int $doctorId,
        string $bookingDate,
        int $timeFromMinutes,
        int $timeToMinutes
    ): bool {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $bookingDateSql = $sqlHelper->forSql($bookingDate);
        $query = "
            SELECT `id`
            FROM `sklyar_ds_booking`
            WHERE `doctor_id` = {$doctorId}
              AND `booking_date` = '{$bookingDateSql}'
              AND `status` NOT IN ('cancelled', 'rejected')
              AND {$timeFromMinutes} < `time_to_minutes`
              AND {$timeToMinutes} > `time_from_minutes`
            LIMIT 1
        ";

        return (bool) $connection->query($query)->fetch();
    }

    private function createBookingRecord(
        array $payload,
        array $servicePrice,
        array $doctor,
        array $service,
        array $appointmentType,
        ?array $location
    ): int {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $locationName = $location !== null ? trim((string) $location['UF_NAME']) : null;
        $visitAddress = $location !== null && isset($location['UF_ADDRESS'])
            ? trim((string) $location['UF_ADDRESS'])
            : null;
        $createdAt = date('Y-m-d H:i:s');

        $stringValueToSql = static function ($value) use ($sqlHelper): string {
            if ($value === null) {
                return 'NULL';
            }

            return "'" . $sqlHelper->forSql((string) $value) . "'";
        };

        $columns = [
            'service_price_id',
            'doctor_id',
            'service_id',
            'appointment_type_id',
            'location_id',
            'booking_date',
            'time_from_minutes',
            'time_to_minutes',
            'patient_name',
            'patient_phone',
            'patient_email',
            'patient_comment',
            'visit_address',
            'online_link',
            'price_snapshot',
            'currency_snapshot',
            'doctor_name_snapshot',
            'service_name_snapshot',
            'appointment_type_name_snapshot',
            'location_name_snapshot',
            'status',
            'created_at',
            'updated_at',
        ];

        $values = [
            (string) (int) $payload['servicePriceId'],
            (string) (int) $payload['doctorId'],
            (string) (int) $payload['serviceId'],
            (string) (int) $payload['appointmentTypeId'],
            $payload['locationId'] !== null ? (string) (int) $payload['locationId'] : 'NULL',
            $stringValueToSql($payload['bookingDate']),
            (string) (int) $payload['timeFromMinutes'],
            (string) (int) $payload['timeToMinutes'],
            $stringValueToSql($payload['patientName']),
            $stringValueToSql($payload['patientPhone']),
            $stringValueToSql($payload['patientEmail'] !== '' ? $payload['patientEmail'] : null),
            $stringValueToSql($payload['patientComment'] !== '' ? $payload['patientComment'] : null),
            $stringValueToSql($visitAddress !== '' ? $visitAddress : null),
            'NULL',
            number_format((float) $servicePrice['price'], 2, '.', ''),
            $stringValueToSql(trim((string) $servicePrice['currency'])),
            $stringValueToSql(trim((string) $doctor['UF_NAME'])),
            $stringValueToSql(trim((string) $service['UF_NAME'])),
            $stringValueToSql(trim((string) $appointmentType['UF_NAME'])),
            $stringValueToSql($locationName !== '' ? $locationName : null),
            $stringValueToSql('new'),
            $stringValueToSql($createdAt),
            'NULL',
        ];

        $connection->queryExecute(sprintf(
            'INSERT INTO `sklyar_ds_booking` (`%s`) VALUES (%s)',
            implode('`, `', $columns),
            implode(', ', $values)
        ));

        return (int) $connection->getInsertedId();
    }

    private function buildPhpDateTime(string $bookingDate, int $timeFromMinutes): \DateTimeImmutable
    {
        $hours = floor($timeFromMinutes / 60);
        $minutes = $timeFromMinutes % 60;

        return new \DateTimeImmutable(sprintf('%s %02d:%02d:00', $bookingDate, $hours, $minutes));
    }

    private function sendJsonResponse(array $payload): void
    {
        global $APPLICATION;

        $APPLICATION->RestartBuffer();
        header('Content-Type: application/json; charset=UTF-8');
        echo Json::encode($payload);
        die();
    }

    private function getConsentSettings(): array
    {
        $defaultTitle = Loc::getMessage('SKLYAR_DS_CLASS_PHP_CONSENT_POPUP_TITLE_DEFAULT');
        $defaultText = Loc::getMessage('SKLYAR_DS_CLASS_PHP_CONSENT_TEXT_DEFAULT');
        $title = trim((string) Option::get('sklyar.doctorschedule', 'consent_popup_title', $defaultTitle));
        $text = trim((string) Option::get('sklyar.doctorschedule', 'consent_text', $defaultText));

        if ($title === '') {
            $title = $defaultTitle;
        }

        if ($text === '') {
            $text = $defaultText;
        }

        return [
            'TITLE' => $title,
            'TEXT' => $text,
        ];
    }

    private function checkRequiredModules()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_CLASS_PHP_0062_1'));
        }

        if (!Loader::includeModule('sklyar.doctorschedule')) {
            throw new SystemException(Loc::getMessage('SKLYAR_DS_CLASS_PHP_0066_1'));
        }
    }

    private function buildState()
    {
        $specializations = $this->normalizeDictionaryItems(
            $this->loadHighloadBlockRows(
                'sklyar_ds_specialization',
                ['ID', 'UF_NAME', 'UF_SORT'],
                ['UF_SORT' => 'ASC', 'ID' => 'ASC']
            )
        );
        $appointmentTypes = $this->normalizeDictionaryItems(
            $this->loadHighloadBlockRows(
                'sklyar_ds_appointment_type',
                ['ID', 'UF_NAME', 'UF_SORT'],
                ['UF_SORT' => 'ASC', 'ID' => 'ASC']
            )
        );
        $locations = $this->normalizeDictionaryItems(
            $this->loadHighloadBlockRows(
                'sklyar_ds_location',
                ['ID', 'UF_NAME', 'UF_ADDRESS', 'UF_SORT'],
                ['UF_SORT' => 'ASC', 'ID' => 'ASC']
            ),
            ['address' => 'UF_ADDRESS']
        );
        $services = $this->normalizeDictionaryItems(
            $this->loadHighloadBlockRows(
                'sklyar_ds_service',
                ['ID', 'UF_NAME', 'UF_DESCRIPTION', 'UF_SORT'],
                ['UF_SORT' => 'ASC', 'ID' => 'ASC']
            ),
            ['description' => 'UF_DESCRIPTION']
        );
        $doctors = $this->normalizeDoctorItems(
            $this->loadHighloadBlockRows(
                'sklyar_ds_doctor',
                ['ID', 'UF_NAME', 'UF_SORT', 'UF_SPECIALIZATIONS'],
                ['UF_SORT' => 'ASC', 'ID' => 'ASC']
            )
        );

        $specializations = $this->filterSpecializationsByDoctors($specializations, $doctors);

        return [
            'today' => date('Y-m-d'),
            'initialDoctorId' => $this->resolveInitialDoctorId($doctors),
            'specializations' => array_map(static function (array $specialization) {
                return [
                    'id' => (int) $specialization['id'],
                    'name' => $specialization['name'],
                ];
            }, $specializations),
            'doctors' => array_map(static function (array $doctor) {
                return [
                    'id' => (int) $doctor['id'],
                    'name' => $doctor['name'],
                    'specializationIds' => array_map('intval', $doctor['specializationIds']),
                ];
            }, $doctors),
            'appointmentTypes' => array_map(static function (array $appointmentType) {
                return [
                    'id' => (int) $appointmentType['id'],
                    'name' => $appointmentType['name'],
                ];
            }, $appointmentTypes),
            'locations' => array_map(static function (array $location) {
                return [
                    'id' => (int) $location['id'],
                    'name' => $location['name'],
                    'address' => $location['address'],
                ];
            }, $locations),
            'services' => array_map(static function (array $service) {
                return [
                    'id' => (int) $service['id'],
                    'name' => $service['name'],
                    'description' => $service['description'],
                ];
            }, $services),
            'servicePrices' => $this->loadServicePrices(),
            'scheduleRules' => $this->loadScheduleRules(),
            'bookings' => $this->loadBookings(),
        ];
    }

    private function loadHighloadBlockRows(string $tableName, array $select, array $order): array
    {
        $highloadBlock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
            'limit' => 1,
        ])->fetch();

        if (!$highloadBlock) {
            throw new SystemException(sprintf(Loc::getMessage('SKLYAR_DS_CLASS_PHP_0162_1'), $tableName));
        }

        $entity = HighloadBlockTable::compileEntity($highloadBlock);
        $dataClass = $entity->getDataClass();

        return $dataClass::getList([
            'select' => $select,
            'filter' => ['=UF_ACTIVE' => 1],
            'order' => $order,
        ])->fetchAll();
    }

    private function loadHighloadBlockRowById(string $tableName, int $id, array $select): ?array
    {
        $highloadBlock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
            'limit' => 1,
        ])->fetch();

        if (!$highloadBlock) {
            throw new SystemException(sprintf(Loc::getMessage('SKLYAR_DS_CLASS_PHP_0162_1'), $tableName));
        }

        $entity = HighloadBlockTable::compileEntity($highloadBlock);
        $dataClass = $entity->getDataClass();
        $row = $dataClass::getList([
            'select' => $select,
            'filter' => [
                '=ID' => $id,
                '=UF_ACTIVE' => 1,
            ],
            'limit' => 1,
        ])->fetch();

        return $row !== false ? $row : null;
    }

    private function normalizeDictionaryItems(array $rows, array $fieldMap = []): array
    {
        $items = [];

        foreach ($rows as $row) {
            $item = [
                'id' => (int) $row['ID'],
                'name' => trim((string) $row['UF_NAME']),
                'sort' => isset($row['UF_SORT']) ? (int) $row['UF_SORT'] : 100,
            ];

            foreach ($fieldMap as $resultKey => $sourceField) {
                $item[$resultKey] = isset($row[$sourceField]) ? trim((string) $row[$sourceField]) : '';
            }

            $items[] = $item;
        }

        return $items;
    }

    private function normalizeDoctorItems(array $rows): array
    {
        $doctorMap = [];

        foreach ($rows as $row) {
            $doctorId = (int) $row['ID'];

            if (!isset($doctorMap[$doctorId])) {
                $doctorMap[$doctorId] = [
                    'id' => $doctorId,
                    'name' => trim((string) $row['UF_NAME']),
                    'sort' => isset($row['UF_SORT']) ? (int) $row['UF_SORT'] : 100,
                    'specializationIds' => [],
                ];
            }

            $specializationValues = [];

            if (array_key_exists('UF_SPECIALIZATIONS', $row)) {
                if (is_array($row['UF_SPECIALIZATIONS'])) {
                    $specializationValues = $row['UF_SPECIALIZATIONS'];
                } elseif ($row['UF_SPECIALIZATIONS'] !== null && $row['UF_SPECIALIZATIONS'] !== '') {
                    $specializationValues = [$row['UF_SPECIALIZATIONS']];
                }
            }

            foreach ($specializationValues as $specializationValue) {
                $specializationId = (int) $specializationValue;

                if ($specializationId > 0) {
                    $doctorMap[$doctorId]['specializationIds'][$specializationId] = $specializationId;
                }
            }
        }

        $doctors = array_values($doctorMap);

        foreach ($doctors as &$doctor) {
            $doctor['specializationIds'] = array_values($doctor['specializationIds']);
        }
        unset($doctor);

        usort($doctors, static function (array $leftDoctor, array $rightDoctor) {
            if ($leftDoctor['sort'] === $rightDoctor['sort']) {
                return $leftDoctor['id'] <=> $rightDoctor['id'];
            }

            return $leftDoctor['sort'] <=> $rightDoctor['sort'];
        });

        return $doctors;
    }

    private function filterSpecializationsByDoctors(array $specializations, array $doctors): array
    {
        $specializationUsageMap = [];

        foreach ($doctors as $doctor) {
            foreach ($doctor['specializationIds'] as $specializationId) {
                $specializationUsageMap[$specializationId] = true;
            }
        }

        return array_values(array_filter($specializations, static function (array $specialization) use ($specializationUsageMap) {
            return isset($specializationUsageMap[$specialization['id']]);
        }));
    }

    private function resolveInitialDoctorId(array $doctors): int
    {
        if (isset($doctors[0])) {
            return (int) $doctors[0]['id'];
        }

        return 0;
    }

    private function loadServicePrices(): array
    {
        return array_map(static function (array $servicePrice) {
            return [
                'id' => (int) $servicePrice['id'],
                'doctorId' => (int) $servicePrice['doctor_id'],
                'serviceId' => (int) $servicePrice['service_id'],
                'appointmentTypeId' => (int) $servicePrice['appointment_type_id'],
                'locationId' => $servicePrice['location_id'] !== null ? (int) $servicePrice['location_id'] : null,
                'price' => number_format((float) $servicePrice['price'], 2, '.', ''),
                'currency' => trim((string) $servicePrice['currency']),
                'durationMinutes' => (int) $servicePrice['duration_minutes'],
            ];
        }, $this->fetchTableRows(
            'sklyar_ds_service_price',
            [
                'id',
                'doctor_id',
                'service_id',
                'appointment_type_id',
                'location_id',
                'price',
                'currency',
                'duration_minutes',
                'sort',
            ],
            "active = 'Y'",
            'sort ASC, id ASC'
        ));
    }

    private function loadScheduleRules(): array
    {
        return array_map(static function (array $scheduleRule) {
            return [
                'id' => (int) $scheduleRule['id'],
                'doctorId' => (int) $scheduleRule['doctor_id'],
                'weekday' => (int) $scheduleRule['weekday'],
                'timeFromMinutes' => (int) $scheduleRule['time_from_minutes'],
                'timeToMinutes' => (int) $scheduleRule['time_to_minutes'],
            ];
        }, $this->fetchTableRows(
            'sklyar_ds_schedule_rule',
            ['id', 'doctor_id', 'weekday', 'time_from_minutes', 'time_to_minutes', 'sort'],
            "active = 'Y'",
            'sort ASC, id ASC'
        ));
    }

    private function loadBookings(): array
    {
        return array_map(static function (array $booking) {
            return [
                'id' => (int) $booking['id'],
                'servicePriceId' => (int) $booking['service_price_id'],
                'doctorId' => (int) $booking['doctor_id'],
                'bookingDate' => trim((string) $booking['booking_date']),
                'timeFromMinutes' => (int) $booking['time_from_minutes'],
                'timeToMinutes' => (int) $booking['time_to_minutes'],
                'status' => trim((string) $booking['status']),
            ];
        }, $this->fetchTableRows(
            'sklyar_ds_booking',
            [
                'id',
                'service_price_id',
                'doctor_id',
                'booking_date',
                'time_from_minutes',
                'time_to_minutes',
                'status',
            ],
            "status NOT IN ('cancelled', 'rejected')",
            'booking_date ASC, time_from_minutes ASC, id ASC'
        ));
    }

    private function fetchTableRows(string $tableName, array $columns, string $whereSql = '', string $orderSql = ''): array
    {
        $connection = Application::getConnection();
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM `' . $tableName . '`';

        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($orderSql !== '') {
            $sql .= ' ORDER BY ' . $orderSql;
        }

        return $connection->query($sql)->fetchAll();
    }
}
