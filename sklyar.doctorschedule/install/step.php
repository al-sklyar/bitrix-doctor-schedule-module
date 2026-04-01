<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$moduleId = 'sklyar.doctorschedule';
?>

<form action="<?php echo $APPLICATION->GetCurPage(); ?>" method="post">
    <?php echo bitrix_sessid_post(); ?>
    <input type="hidden" name="lang" value="<?php echo htmlspecialcharsbx(LANGUAGE_ID); ?>">
    <input type="hidden" name="id" value="<?php echo htmlspecialcharsbx($moduleId); ?>">
    <input type="hidden" name="install" value="Y">
    <input type="hidden" name="step" value="2">

    <div style="padding: 16px 0;">
        <label>
            <input type="checkbox" name="install_demo_data" value="Y">
            <?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_INSTALL_STEP_DEMO_DATA')); ?>
        </label>
    </div>

    <input
        type="submit"
        name="inst"
        value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_INSTALL_STEP_CONTINUE')); ?>"
        class="adm-btn-save"
    >
</form>
