<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$this->setFrameMode(true);
$stateJson = str_replace('</', '<\/', $arResult['STATE_JSON']);
$consentTitle = isset($arResult['CONSENT']['TITLE']) ? (string) $arResult['CONSENT']['TITLE'] : '';
$consentText = isset($arResult['CONSENT']['TEXT']) ? (string) $arResult['CONSENT']['TEXT'] : '';
$messages = [
    'weekdayLabels' => [
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_SUN'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_MON'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_TUE'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_WED'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_THU'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_FRI'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_WEEKDAY_SAT'),
    ],
    'monthLabels' => [
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_JAN'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_FEB'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_MAR'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_APR'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_MAY'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_JUN'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_JUL'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_AUG'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_SEP'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_OCT'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_NOV'),
        Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MONTH_DEC'),
    ],
    'noDoctors' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_NO_DOCTORS'),
    'noServices' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_NO_SERVICES'),
    'durationPrefix' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_DURATION_PREFIX'),
    'minutesShort' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MINUTES_SHORT'),
    'selectDoctorAndService' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SELECT_DOCTOR_AND_SERVICE'),
    'formatLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_FORMAT_LABEL'),
    'serviceLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SERVICE_LABEL'),
    'priceLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_PRICE_LABEL'),
    'durationLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_DURATION_LABEL'),
    'noDates' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_NO_DATES'),
    'locationAfterService' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_LOCATION_AFTER_SERVICE'),
    'selectDateFirst' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SELECT_DATE_FIRST'),
    'componentReady' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPONENT_READY'),
    'doctorLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_DOCTOR_LABEL'),
    'selectionServiceLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SELECTION_SERVICE_LABEL'),
    'selectionPriceLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SELECTION_PRICE_LABEL'),
    'selectedSlotLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SELECTED_SLOT_LABEL'),
    'slotLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SLOT_LABEL'),
    'chooseTime' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_CHOOSE_TIME'),
    'selectionTitle' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SELECTION_TITLE'),
    'bookActionLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_BOOK_ACTION_LABEL'),
    'modalTitle' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_TITLE'),
    'modalSubtitle' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_SUBTITLE'),
    'modalClose' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_CLOSE'),
    'modalPatientName' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_NAME'),
    'modalPatientNamePlaceholder' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_NAME_PLACEHOLDER'),
    'modalPatientPhone' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_PHONE'),
    'modalPatientPhonePlaceholder' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_PHONE_PLACEHOLDER'),
    'modalPatientEmail' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_EMAIL'),
    'modalPatientEmailPlaceholder' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_EMAIL_PLACEHOLDER'),
    'modalPatientComment' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_COMMENT'),
    'modalPatientCommentPlaceholder' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_COMMENT_PLACEHOLDER'),
    'modalCancel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_CANCEL'),
    'modalDone' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_DONE'),
    'modalSubmit' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_SUBMIT'),
    'modalSubmitLoading' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_SUBMIT_LOADING'),
    'modalSummaryTitle' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_SUMMARY_TITLE'),
    'successModalTitle' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SUCCESS_MODAL_TITLE'),
    'modalEmailHelp' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_EMAIL_HELP'),
    'requiredMark' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_REQUIRED_MARK'),
    'modalUnexpectedError' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_UNEXPECTED_ERROR'),
    'locationLabel' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_LOCATION_LABEL'),
    'validationRequiredSuffix' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_VALIDATION_REQUIRED_SUFFIX'),
    'validationPhoneInvalid' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_VALIDATION_PHONE_INVALID'),
    'validationEmailInvalid' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_VALIDATION_EMAIL_INVALID'),
    'validationConsentRequired' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_VALIDATION_CONSENT_REQUIRED'),
    'validationFormInvalid' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_VALIDATION_FORM_INVALID'),
    'compactSelectPrompt' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPACT_SELECT_PROMPT'),
    'compactNoOptions' => Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPACT_NO_OPTIONS'),
    'ajaxAction' => 'create_booking',
    'sessionId' => bitrix_sessid(),
];
$messagesJson = str_replace('</', '<\/', \Bitrix\Main\Web\Json::encode($messages));
?>
<div class="skds-booking skds-booking--compact js-skds-booking" id="<?php echo htmlspecialcharsbx($arResult['COMPONENT_UID']); ?>">
    <div class="skds-booking__shell">
        <div class="skds-booking__header">
            <div>
                <h2 class="skds-booking__title"><?php echo htmlspecialcharsbx($arResult['TITLE']); ?></h2>
                <p class="skds-booking__subtitle">
                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SUBTITLE')); ?>
                </p>
            </div>
        </div>

        <?php if (!$arResult['HAS_DATA']) { ?>
            <div class="skds-booking__empty">
                <?php echo htmlspecialcharsbx($arResult['EMPTY_MESSAGE']); ?>
            </div>
        <?php } else { ?>
            <script type="application/json" class="js-skds-booking-state"><?php echo $stateJson; ?></script>
            <script type="application/json" class="js-skds-booking-messages"><?php echo $messagesJson; ?></script>

            <div class="skds-booking__compact-layout">
                <div class="skds-booking__panel skds-booking__panel--filters">
                    <div class="skds-booking__panel-title">
                        <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPACT_CONTROLS_TITLE')); ?>
                    </div>

                    <div class="skds-booking__compact-grid">
                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SPECIALIZATION')); ?>
                            </div>
                            <div data-role="specializations"></div>
                        </div>

                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_DOCTOR')); ?>
                            </div>
                            <div data-role="doctors"></div>
                        </div>

                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_APPOINTMENT_TYPE')); ?>
                            </div>
                            <div data-role="appointment-types"></div>
                        </div>

                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SERVICE')); ?>
                            </div>
                            <div data-role="services"></div>
                        </div>

                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-head">
                                <div class="skds-booking__section-label">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_NEAREST_DATES')); ?>
                                </div>
                                <div class="skds-booking__section-note">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_NEXT_FREE_DAYS')); ?>
                                </div>
                            </div>
                            <div data-role="dates"></div>
                        </div>

                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-head">
                                <div class="skds-booking__section-label">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_LOCATION')); ?>
                                </div>
                            </div>
                            <div data-role="location"></div>
                            <div class="skds-booking__compact-note" data-role="location-note"></div>
                        </div>

                        <div class="skds-booking__compact-field">
                            <div class="skds-booking__section-head">
                                <div class="skds-booking__section-label">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_FREE_TIME')); ?>
                                </div>
                                <div class="skds-booking__section-note">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_FREE_TIME_NOTE')); ?>
                                </div>
                            </div>
                            <div data-role="slots"></div>
                        </div>
                    </div>
                </div>

                <div class="skds-booking__panel skds-booking__panel--schedule">
                    <div class="skds-booking__panel-title">
                        <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_COMPACT_SUMMARY_TITLE')); ?>
                    </div>
                    <div class="skds-booking__summary" data-role="summary"></div>
                    <div class="skds-booking__selection" data-role="selection"></div>
                </div>
            </div>

            <div class="skds-booking__modal" data-role="booking-modal" hidden>
                <div class="skds-booking__modal-backdrop" data-role="modal-close"></div>
                <div class="skds-booking__modal-dialog" role="dialog" aria-modal="true">
                    <button
                        class="skds-booking__modal-close"
                        type="button"
                        data-role="modal-close"
                        aria-label="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_CLOSE')); ?>"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>

                    <div class="skds-booking__modal-head">
                        <h3 class="skds-booking__modal-title">
                            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_TITLE')); ?>
                        </h3>
                        <p class="skds-booking__modal-subtitle">
                            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_SUBTITLE')); ?>
                        </p>
                    </div>

                    <div class="skds-booking__modal-summary" data-role="modal-summary"></div>
                    <div class="skds-booking__modal-feedback" data-role="modal-feedback" hidden></div>

                    <form class="skds-booking__modal-form" data-role="booking-form">
                        <label class="skds-booking__field">
                            <span class="skds-booking__field-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_NAME')); ?>
                                <span class="skds-booking__field-required">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_REQUIRED_MARK')); ?>
                                </span>
                            </span>
                            <input
                                class="skds-booking__input"
                                type="text"
                                name="patient_name"
                                data-role="field-patient-name"
                                maxlength="255"
                                autocomplete="name"
                                placeholder="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_NAME_PLACEHOLDER')); ?>"
                            >
                            <span class="skds-booking__field-error" data-role="error-patient-name"></span>
                        </label>

                        <label class="skds-booking__field">
                            <span class="skds-booking__field-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_PHONE')); ?>
                                <span class="skds-booking__field-required">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_REQUIRED_MARK')); ?>
                                </span>
                            </span>
                            <input
                                class="skds-booking__input"
                                type="tel"
                                name="patient_phone"
                                data-role="field-patient-phone"
                                maxlength="50"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_PHONE_PLACEHOLDER')); ?>"
                            >
                            <span class="skds-booking__field-error" data-role="error-patient-phone"></span>
                        </label>

                        <label class="skds-booking__field">
                            <span class="skds-booking__field-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_EMAIL')); ?>
                            </span>
                            <input
                                class="skds-booking__input"
                                type="email"
                                name="patient_email"
                                data-role="field-patient-email"
                                maxlength="255"
                                autocomplete="email"
                                placeholder="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_EMAIL_PLACEHOLDER')); ?>"
                            >
                            <span class="skds-booking__field-help">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_EMAIL_HELP')); ?>
                            </span>
                            <span class="skds-booking__field-error" data-role="error-patient-email"></span>
                        </label>

                        <label class="skds-booking__field">
                            <span class="skds-booking__field-label">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_COMMENT')); ?>
                            </span>
                            <textarea
                                class="skds-booking__textarea"
                                name="patient_comment"
                                data-role="field-patient-comment"
                                rows="4"
                                maxlength="2000"
                                placeholder="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_COMMENT_PLACEHOLDER')); ?>"
                            ></textarea>
                        </label>

                        <div class="skds-booking__field skds-booking__field--consent">
                            <label class="skds-booking__consent">
                                <input
                                    class="skds-booking__checkbox"
                                    type="checkbox"
                                    name="patient_consent"
                                    value="Y"
                                    data-role="field-patient-consent"
                                >
                                <span class="skds-booking__consent-text">
                                    <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_CONSENT')); ?>
                                    <span class="skds-booking__field-required">
                                        <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_REQUIRED_MARK')); ?>
                                    </span>
                                </span>
                            </label>
                            <button class="skds-booking__consent-link" type="button" data-role="consent-open">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_PATIENT_CONSENT_LINK')); ?>
                            </button>
                            <span class="skds-booking__field-error" data-role="error-patient-consent"></span>
                        </div>

                        <span class="skds-booking__field-error" data-role="error-slot"></span>

                        <div class="skds-booking__modal-actions">
                            <button class="skds-booking__modal-secondary" type="button" data-role="modal-close">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_CANCEL')); ?>
                            </button>
                            <button class="skds-booking__modal-submit" type="submit" data-role="modal-submit">
                                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_SUBMIT')); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="skds-booking__modal" data-role="booking-success-modal" hidden>
                <div class="skds-booking__modal-backdrop" data-role="success-modal-close"></div>
                <div class="skds-booking__modal-dialog skds-booking__modal-dialog--success" role="dialog" aria-modal="true">
                    <button
                        class="skds-booking__modal-close"
                        type="button"
                        data-role="success-modal-close"
                        aria-label="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_CLOSE')); ?>"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>

                    <div class="skds-booking__modal-head">
                        <h3 class="skds-booking__modal-title">
                            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_SUCCESS_MODAL_TITLE')); ?>
                        </h3>
                    </div>

                    <div class="skds-booking__modal-feedback is-success" data-role="success-modal-message"></div>
                    <div class="skds-booking__modal-summary" data-role="success-modal-summary"></div>

                    <div class="skds-booking__modal-actions">
                        <button class="skds-booking__modal-submit" type="button" data-role="success-modal-close">
                            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_DONE')); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="skds-booking__modal" data-role="consent-modal" hidden>
                <div class="skds-booking__modal-backdrop" data-role="consent-modal-close"></div>
                <div class="skds-booking__modal-dialog skds-booking__modal-dialog--text" role="dialog" aria-modal="true">
                    <button
                        class="skds-booking__modal-close"
                        type="button"
                        data-role="consent-modal-close"
                        aria-label="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_CLOSE')); ?>"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>

                    <div class="skds-booking__modal-head">
                        <h3 class="skds-booking__modal-title">
                            <?php echo htmlspecialcharsbx($consentTitle); ?>
                        </h3>
                    </div>

                    <div class="skds-booking__consent-modal-text">
                        <?php echo nl2br(htmlspecialcharsbx($consentText)); ?>
                    </div>

                    <div class="skds-booking__modal-actions">
                        <button class="skds-booking__modal-submit" type="button" data-role="consent-modal-close">
                            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_BOOKING_TEMPLATE_MODAL_DONE')); ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
