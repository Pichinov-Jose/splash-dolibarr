<?php

/*
 *  This file is part of SplashSync Project.
 *
 *  Copyright (C) Splash Sync  <www.splashsync.com>
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

use Splash\Local\Services\AddressLinesManager;

global $db, $action, $conf, $langs, $error, $form;

//====================================================================//
// Open ThirdParty & Contacts Configuration Tab
print load_fiche_titre($langs->trans("SPL_ThirdParty_Config"), "", "company");

echo '<table class="noborder" width="100%"><tbody>';

//====================================================================//
// Split ThirdParty Address on Line Breaks
echo '  <tr class="pair">';
echo '      <td>'.$form->textwithpicto(
    $langs->trans("SPL_SplitThirdPartyAddress"),
    $langs->trans("SPL_SplitThirdPartyAddress_T")
).'</td>';
if (AddressLinesManager::isEnabled(AddressLinesManager::THIRDPARTY_MODE)) {
    echo '<td><a href="'.filter_input(INPUT_SERVER, "PHP_SELF").'?action=UpdateThirdPartySplitAddress&SplitAddress=0">';
    echo img_picto($langs->trans("Enabled"), 'switch_on');
    echo '</a></td>';
} else {
    echo '<td><a href="'.filter_input(INPUT_SERVER, "PHP_SELF").'?action=UpdateThirdPartySplitAddress&SplitAddress=1">';
    echo img_picto($langs->trans("Disabled"), 'switch_off');
    echo '</a></td>';
}
echo '  </tr>';

//====================================================================//
// Split Contact Address on Line Breaks
echo '  <tr class="impair">';
echo '      <td>'.$form->textwithpicto(
    $langs->trans("SPL_SplitContactAddress"),
    $langs->trans("SPL_SplitContactAddress_T")
).'</td>';
if (AddressLinesManager::isEnabled(AddressLinesManager::CONTACT_MODE)) {
    echo '<td><a href="'.filter_input(INPUT_SERVER, "PHP_SELF").'?action=UpdateContactSplitAddress&SplitAddress=0">';
    echo img_picto($langs->trans("Enabled"), 'switch_on');
    echo '</a></td>';
} else {
    echo '<td><a href="'.filter_input(INPUT_SERVER, "PHP_SELF").'?action=UpdateContactSplitAddress&SplitAddress=1">';
    echo img_picto($langs->trans("Disabled"), 'switch_off');
    echo '</a></td>';
}
echo '  </tr>';

echo '</tbody></table>';
