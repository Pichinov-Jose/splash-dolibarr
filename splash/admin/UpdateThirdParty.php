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
// *******************************************************************//
// ACTIONS
// *******************************************************************//
//====================================================================//

//====================================================================//
// Update Split ThirdParty Address Mode
if ('UpdateThirdPartySplitAddress' == $action) {
    $splitAddress = GETPOST('SplitAddress') ? "1": "0";
    dolibarr_set_const($db, AddressLinesManager::THIRDPARTY_MODE, $splitAddress, 'chaine', 0, '', $conf->entity);
    setEventMessage($langs->trans("SetupSaved"), 'mesgs');
    header("location:".filter_input(INPUT_SERVER, "PHP_SELF"));
}

//====================================================================//
// Update Split Contact Address Mode
if ('UpdateContactSplitAddress' == $action) {
    $splitAddress = GETPOST('SplitAddress') ? "1": "0";
    dolibarr_set_const($db, AddressLinesManager::CONTACT_MODE, $splitAddress, 'chaine', 0, '', $conf->entity);
    setEventMessage($langs->trans("SetupSaved"), 'mesgs');
    header("location:".filter_input(INPUT_SERVER, "PHP_SELF"));
}
