<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

CAdminMessage::ShowNote(Loc::getMessage('SKLYAR_DS_STEP_FINISH_0011_1'));
?>

<form action="<?php echo $APPLICATION->GetCurPage(); ?>" method="get">
    <input type="hidden" name="lang" value="<?php echo htmlspecialcharsbx(LANGUAGE_ID); ?>">
    <input
        type="submit"
        value="<?php echo htmlspecialcharsbx(Loc::getMessage('SKLYAR_DS_INSTALL_STEP_FINISH_BACK')); ?>"
        class="adm-btn-save"
    >
</form>
