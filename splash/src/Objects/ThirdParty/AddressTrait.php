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

namespace Splash\Local\Objects\ThirdParty;

use Splash\Local\Services\AddressLinesManager;

/**
 * Dolibarr ThirdParty Address Fields
 */
trait AddressTrait
{
    /**
     * Build Address Fields using FieldFactory
     *
     * @return void
     */
    protected function buildAddressFields()
    {
        global $langs;

        $groupName = $langs->trans("CompanyAddress");
        //====================================================================//
        // Address
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("address")
            ->name($langs->trans("CompanyAddress"))
            ->group($groupName)
            ->isLogged()
            ->microData("http://schema.org/PostalAddress", "streetAddress")
        ;
        //====================================================================//
        // Address Complements (ADD2 / ADD3)
        // Only Available when ThirdParty Address Splitting is Enabled
        if (AddressLinesManager::isEnabled(AddressLinesManager::THIRDPARTY_MODE)) {
            AddressLinesManager::buildExtraFields($this->fieldsFactory(), $groupName, false);
        }
        //====================================================================//
        // Zip Code
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("zip")
            ->name($langs->trans("CompanyZip"))
            ->group($groupName)
            ->microData("http://schema.org/PostalAddress", "postalCode")
            ->addOption('maxLength', "18")
            ->isLogged()
            ->isListed()
        ;
        //====================================================================//
        // City Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("town")
            ->name($langs->trans("CompanyTown"))
            ->group($groupName)
            ->isLogged()
            ->microData("http://schema.org/PostalAddress", "addressLocality")
        ;
        //====================================================================//
        // Country Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("country")
            ->name($langs->trans("CompanyCountry"))
            ->group($groupName)
            ->isReadOnly()
            ->isListed()
        ;
        //====================================================================//
        // Country ISO Code
        $this->fieldsFactory()->create(SPL_T_COUNTRY)
            ->identifier("country_code")
            ->name($langs->trans("CountryCode"))
            ->group($groupName)
            ->isLogged()
            ->microData("http://schema.org/PostalAddress", "addressCountry")
        ;
        //====================================================================//
        // State Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("state")
            ->group($groupName)
            ->name($langs->trans("State"))
            ->isReadOnly()
        ;
        //====================================================================//
        // State code
        $this->fieldsFactory()->create(SPL_T_STATE)
            ->identifier("state_code")
            ->name($langs->trans("State Code"))
            ->group($groupName)
            ->microData("http://schema.org/PostalAddress", "addressRegion")
            ->isNotTested()
        ;
    }

    /**
     * Read ThirdParty Address Lines (ADD1 / ADD2 / ADD3)
     */
    protected function getAddressLinesFields(string $key, string $fieldName): void
    {
        if (!in_array($fieldName, AddressLinesManager::FIELDS, true)) {
            return;
        }
        $this->out[$fieldName] = AddressLinesManager::read(
            $this->object->address ?? null,
            $fieldName,
            AddressLinesManager::THIRDPARTY_MODE
        );

        unset($this->in[$key]);
    }

    /**
     * Write ThirdParty Address Lines (ADD1 / ADD2 / ADD3)
     */
    protected function setAddressLinesFields(string $fieldName, ?string $fieldData): void
    {
        //====================================================================//
        // All Address Lines are Consumed at Once, on First One Received
        if (!in_array($fieldName, AddressLinesManager::FIELDS, true)
            || !array_key_exists($fieldName, $this->in)) {
            return;
        }
        //====================================================================//
        // Current Field Value Wins: it was normalized by the Parser
        $receivedData = array($fieldName => $fieldData) + $this->in;
        $this->setSimple("address", AddressLinesManager::write(
            $this->object->address ?? null,
            $receivedData,
            AddressLinesManager::THIRDPARTY_MODE
        ));

        foreach (AddressLinesManager::FIELDS as $lineField) {
            unset($this->in[$lineField]);
        }
    }

    /**
     * Read requested Field
     *
     * @param string $key       Input List Key
     * @param string $fieldName Field Identifier / Name
     *
     * @return void
     */
    protected function getAddressFields(string $key, string $fieldName): void
    {
        //====================================================================//
        // READ Fields
        switch ($fieldName) {
            //====================================================================//
            // Direct Readings
            case 'zip':
            case 'town':
            case 'state':
            case 'state_code':
            case 'country':
            case 'country_code':
                $this->getSimple($fieldName);

                break;
            default:
                return;
        }

        unset($this->in[$key]);
    }

    /**
     * Write Given Fields
     *
     * @param string      $fieldName Field Identifier / Name
     * @param null|string $fieldData Field Data
     *
     * @return void
     */
    protected function setAddressFields(string $fieldName, ?string $fieldData)
    {
        //====================================================================//
        // WRITE Field
        switch ($fieldName) {
            case 'zip':
            case 'town':
                $this->setSimple($fieldName, $fieldData);

                break;
            case 'country_code':
                $this->setSimple("country_id", $this->getCountryByCode((string) $fieldData));

                break;
            case 'state_code':
                //====================================================================//
                // If Country Also Changed => Update First
                if (!empty($this->in["country_code"]) && is_string($this->in["country_code"])) {
                    $this->setSimple("country_id", $this->getCountryByCode($this->in["country_code"]));
                }
                $stateId = $this->getStateByCode((string) $fieldData, $this->object->country_id);
                $this->setSimple("state_id", $stateId);

                break;
            default:
                return;
        }
        unset($this->in[$fieldName]);
    }
}
