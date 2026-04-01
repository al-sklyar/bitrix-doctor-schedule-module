<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$moduleId = 'sklyar.doctorschedule';

global $APPLICATION;
global $USER;

if (!is_object($USER) || !$USER->IsAdmin()) {
    return;
}

$request = Application::getInstance()->getContext()->getRequest();
$consentTitleOptionName = 'consent_popup_title';
$consentTextOptionName = 'consent_text';
$defaultConsentTitle = Loc::getMessage('SKLYAR_DS_OPTIONS_DEFAULT_CONSENT_TITLE');
$defaultConsentText = Loc::getMessage('SKLYAR_DS_OPTIONS_DEFAULT_CONSENT_TEXT');

if ($request->isPost() && check_bitrix_sessid()) {
    Option::set(
        $moduleId,
        $consentTitleOptionName,
        trim((string) $request->getPost($consentTitleOptionName))
    );
    Option::set(
        $moduleId,
        $consentTextOptionName,
        trim((string) $request->getPost($consentTextOptionName))
    );

    LocalRedirect($APPLICATION->GetCurPageParam('mid=' . urlencode($moduleId) . '&lang=' . urlencode(LANGUAGE_ID), []));
}

$tabControl = new CAdminTabControl('sklyarDoctorScheduleOptions', [
    [
        'DIV' => 'main',
        'TAB' => Loc::getMessage('SKLYAR_DS_OPTIONS_TAB_MAIN'),
        'TITLE' => Loc::getMessage('SKLYAR_DS_OPTIONS_TAB_MAIN_TITLE'),
    ],
]);

$consentTitleValue = Option::get($moduleId, $consentTitleOptionName, $defaultConsentTitle);
$consentTextValue = Option::get($moduleId, $consentTextOptionName, $defaultConsentText);
?>
<form method="post" action="<?php echo htmlspecialcharsbx($APPLICATION->GetCurPage()); ?>?mid=<?php echo urlencode($moduleId); ?>&lang=<?php echo urlencode(LANGUAGE_ID); ?>">
    <?php
    echo bitrix_sessid_post();
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    ?>
    <tr class="heading">
        <td colspan="2"><?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_OPTIONS_SECTION_CONSENT')); ?></td>
    </tr>
    <tr>
        <td colspan="2">
            <div class="adm-info-message">
                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_OPTIONS_SECTION_CONSENT_HINT')); ?>
            </div>
        </td>
    </tr>
    <tr>
        <td width="40%" valign="top">
            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_OPTIONS_CONSENT_TITLE')); ?>
        </td>
        <td width="60%">
            <input
                class="adm-input"
                type="text"
                name="<?php echo htmlspecialcharsbx($consentTitleOptionName); ?>"
                value="<?php echo htmlspecialcharsbx($consentTitleValue); ?>"
                size="60"
                maxlength="255"
            >
            <div class="adm-info-message" style="margin-top: 8px;">
                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_OPTIONS_CONSENT_TITLE_HINT')); ?>
            </div>
        </td>
    </tr>
    <tr>
        <td width="40%" valign="top">
            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_OPTIONS_CONSENT_TEXT')); ?>
        </td>
        <td width="60%">
            <textarea
                class="adm-input"
                name="<?php echo htmlspecialcharsbx($consentTextOptionName); ?>"
                rows="12"
                style="width: 100%; max-width: 720px;"
            ><?php echo htmlspecialcharsbx($consentTextValue); ?></textarea>
            <div class="adm-info-message" style="margin-top: 8px;">
                <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_OPTIONS_CONSENT_TEXT_HINT')); ?>
            </div>
        </td>
    </tr>
    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" name="save" value="<?php echo htmlspecialcharsbx(Loc::getMessage('MAIN_SAVE')); ?>" class="adm-btn-save">
    <?php
    $tabControl->End();
    ?>
</form>
