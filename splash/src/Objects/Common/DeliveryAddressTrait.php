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

namespace Splash\Local\Objects\Common;

use Contact;
use Splash\Client\Splash;
use Splash\Local\Dictionary\StaticContactTypes;
use Splash\Local\Objects\Address;
use Splash\Local\Services\AddressLinesManager;
use Splash\Local\Services\ContactsManager;

/**
 * ReadOnly Access to Order Delivery Address Fields
 */
trait DeliveryAddressTrait
{
    /**
     * @var null|Contact
     */
    protected ?Contact $deliveryContact = null;

    /**
     * Build Fields using FieldFactory
     */
    protected function buildDeliveryContactFields(): void
    {
        global $langs;

        $langs->load("deliveries");
        $groupName = $langs->trans("Delivery");

        //====================================================================//
        // Company
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("company")
            ->name($langs->trans("ThirdParty"))
            ->microData("http://schema.org/Organization", "legalName")
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // Contact Full Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("fullname")
            ->name($langs->trans("Lastname"))
            ->microData("http://schema.org/PostalAddress", "alternateName")
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // Phone
        $this->fieldsFactory()->create(SPL_T_PHONE)
            ->identifier("phone")
            ->group($groupName)
            ->name($langs->trans("Phone"))
            ->microData("http://schema.org/PostalAddress", "telephone")
            ->isReadOnly()
        ;
        //====================================================================//
        // Mobile Phone
        $this->fieldsFactory()->create(SPL_T_PHONE)
            ->identifier("phone_mobile")
            ->group($groupName)
            ->name($langs->trans("PhoneMobile"))
            ->microData("http://schema.org/Person", "telephone")
            ->isReadOnly()
        ;
        //====================================================================//
        // Email
        $this->fieldsFactory()->create(SPL_T_EMAIL)
            ->identifier("delivery_email")
            ->group($groupName)
            ->name($langs->trans("Email"))
            ->microData("http://schema.org/DeliveryAddress", "email")
            ->isReadOnly()
        ;
    }

    /**
     * Build Fields using FieldFactory
     */
    protected function buildDeliveryAddressFields(): void
    {
        global $langs;

        $groupName = $langs->trans("Delivery");

        //====================================================================//
        // Address
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("address")
            ->name($langs->trans("Address"))
            ->microData("http://schema.org/PostalAddress", "streetAddress")
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // Address Complements (ADD2 / ADD3)
        // Only Available when Delivery Address Splitting is Enabled
        if (AddressLinesManager::isEnabled(AddressLinesManager::DELIVERY_MODE)) {
            AddressLinesManager::buildExtraFields($this->fieldsFactory(), $groupName, true);
        }
        //====================================================================//
        // Zip Code
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("zip")
            ->name($langs->trans("Zip"))
            ->microData("http://schema.org/PostalAddress", "postalCode")
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // City Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("town")
            ->name($langs->trans("Town"))
            ->microData("http://schema.org/PostalAddress", "addressLocality")
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // State Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("state")
            ->name($langs->trans("StateShort"))
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // State code
        $this->fieldsFactory()->create(SPL_T_STATE)
            ->Identifier("state_code")
            ->name($langs->trans("StateCode"))
            ->Group($groupName)
            ->MicroData("http://schema.org/PostalAddress", "addressRegion")
            ->isReadOnly()
        ;
        //====================================================================//
        // Country Name
        $this->fieldsFactory()->create(SPL_T_VARCHAR)
            ->identifier("country")
            ->name($langs->trans("Country"))
            ->group($groupName)
            ->isReadOnly()
        ;
        //====================================================================//
        // Country ISO Code
        $this->fieldsFactory()->create(SPL_T_COUNTRY)
            ->identifier("country_code")
            ->name($langs->trans("CountryCode"))
            ->microData("http://schema.org/PostalAddress", "addressCountry")
            ->group($groupName)
            ->isReadOnly()
        ;
    }

    /**
     * Read requested Field
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getDeliveryAddressFields(string $key, string $fieldName): void
    {
        //====================================================================//
        // READ Fields
        switch ($fieldName) {
            //====================================================================//
            // Delivery ThirdParty Name
            case 'company':
                $this->object->thirdparty ?? $this->object->fetch_thirdparty();
                $this->out[$fieldName] = !empty($this->object->thirdparty->name)
                    ? $this->object->thirdparty->name
                    : null
                ;

                break;
                //====================================================================//
                // Delivery Contact Full Name
            case 'fullname':
                $contact = $this->getDeliveryContact();
                $this->out[$fieldName] = $contact
                    ? trim(sprintf("%s %s", $contact->firstname, $contact->lastname))
                    : null
                ;

                break;
                //====================================================================//
                // Contact Phone with Merge
            case 'phone':
                $contact = $this->getDeliveryContact();
                if ($contact) {
                    $this->out[$fieldName] = $contact->phone_pro ?: $contact->phone_perso ?: null;
                } else {
                    $this->out[$fieldName] = null;
                }

                break;
                //====================================================================//
                // Contact Phone with Merge
            case 'delivery_email':
                $contact = $this->getDeliveryContact();
                if ($contact) {
                    $this->out[$fieldName] = $contact->email ?: null;
                } else {
                    $this->out[$fieldName] = null;
                }

                break;
            default:
                return;
        }
        unset($this->in[$key]);
    }

    /**
     * Read Delivery Address Lines (ADD1 / ADD2 / ADD3)
     */
    protected function getDeliveryLinesFields(string $key, string $fieldName): void
    {
        if (!in_array($fieldName, AddressLinesManager::FIELDS, true)) {
            return;
        }
        $contact = $this->getDeliveryContact();
        $this->out[$fieldName] = AddressLinesManager::read(
            $contact->address ?? null,
            $fieldName,
            AddressLinesManager::DELIVERY_MODE
        );

        unset($this->in[$key]);
    }

    /**
     * Read requested Field
     *
     * @SuppressWarnings(CyclomaticComplexity)
     */
    protected function getDeliverySimpleFields(string $key, string $fieldName): void
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
            case 'phone_mobile':
                $this->getDeliveryContact();
                $this->getSimple($fieldName, "deliveryContact");

                break;
            default:
                return;
        }
        unset($this->in[$key]);
    }

    /**
     * Read requested Field
     */
    private function getDeliveryContact(): ?Contact
    {
        //====================================================================//
        // Already Loaded
        if (isset($this->deliveryContact)) {
            return $this->deliveryContact;
        }

        //====================================================================//
        // Load Delivery Address
        try {
            /** @var Address $splAddress */
            $splAddress = Splash::object("Address");
        } catch (\Exception $e) {
            return null;
        }
        $contactId = ContactsManager::getFirstContactId($this->object, StaticContactTypes::SHIPPING);
        if (!$contactId) {
            return null;
        }

        return $this->deliveryContact = $splAddress->load($contactId);
    }
}
