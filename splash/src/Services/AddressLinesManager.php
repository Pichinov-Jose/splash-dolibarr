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

namespace Splash\Local\Services;

use Splash\Components\FieldsFactory;

/**
 * Splash Address Lines Manager
 *
 * Dolibarr stores postal addresses in a single multiline field, whereas Splash
 * splits them into three separate lines (ADD1 / ADD2 / ADD3).
 *
 * This service converts between both representations:
 *  - Reading  => one Dolibarr address is split into three Splash lines
 *  - Writing  => three Splash lines are joined back into one Dolibarr address
 */
class AddressLinesManager
{
    /**
     * Number of Address Lines Exposed by Splash
     *
     * @var int
     */
    public const MAX_LINES = 3;

    /**
     * Splash Fields Identifiers, Ordered by Line Index
     *
     * @var string[]
     */
    public const FIELDS = array("address", "address2", "address3");

    /**
     * Config Key => Enable Splitting of ThirdParty Address
     *
     * @var string
     */
    public const THIRDPARTY_MODE = "SPLASH_SPLIT_THIRDPARTY_ADDRESS";

    /**
     * Config Key => Enable Splitting of Contact Address
     *
     * @var string
     */
    public const CONTACT_MODE = "SPLASH_SPLIT_CONTACT_ADDRESS";

    /**
     * Config Key => Enable Splitting of Order Delivery Address
     *
     * @var string
     */
    public const DELIVERY_MODE = "SPLASH_SPLIT_DELIVERY_ADDRESS";

    /**
     * Schema.org Microdata Type used for all Address Lines
     *
     * @var string
     */
    private const ITEM_TYPE = "http://schema.org/PostalAddress";

    /**
     * Schema.org Microdata Properties, Ordered by Line Index
     *
     * @var string[]
     */
    private const ITEM_PROPS = array("streetAddress", "postOfficeBoxNumber", "extendedAddress");

    //====================================================================//
    // CONVERSION
    //====================================================================//

    /**
     * Split a Dolibarr Address into Splash Address Lines
     *
     * Empty lines are removed & extra lines are merged into the last one,
     * so that no address data is lost.
     *
     * @param null|string $address Raw Dolibarr Multiline Address
     *
     * @return array<int, null|string> Always MAX_LINES entries
     */
    public static function split(?string $address): array
    {
        //====================================================================//
        // Split on Line Breaks & Remove Empty Lines
        $lines = self::filterEmptyLines(preg_split('/\R/', (string) $address) ?: array());
        //====================================================================//
        // Merge Extra Lines into Last One to Prevent Data Loss
        if (count($lines) > self::MAX_LINES) {
            $extraLines = array_splice($lines, self::MAX_LINES - 1);
            $lines[] = implode(" ", $extraLines);
        }

        return array_pad($lines, self::MAX_LINES, null);
    }

    /**
     * Join Splash Address Lines into a Dolibarr Address
     *
     * Empty lines are removed so that no blank line is stored.
     *
     * @param null|string ...$lines Splash Address Lines
     *
     * @return null|string Raw Dolibarr Multiline Address
     */
    public static function join(?string ...$lines): ?string
    {
        $cleaned = self::filterEmptyLines(array_map('strval', $lines));

        return $cleaned ? implode("\n", $cleaned) : null;
    }

    /**
     * Replace a Single Line inside a Dolibarr Address
     *
     * Used on writing, since Splash sends address lines one by one.
     *
     * @param null|string $address   Raw Dolibarr Multiline Address
     * @param string      $fieldName Splash Field Identifier
     * @param null|string $fieldData New Line Value
     *
     * @return null|string Updated Raw Dolibarr Multiline Address
     */
    public static function replace(?string $address, string $fieldName, ?string $fieldData): ?string
    {
        $lines = self::split($address);
        $lines[self::getLineIndex($fieldName)] = $fieldData;

        return self::join(...$lines);
    }

    /**
     * Get Line Index of a Splash Address Field
     *
     * @param string $fieldName Splash Field Identifier
     *
     * @return int Line Index, Zero if Unknown
     */
    public static function getLineIndex(string $fieldName): int
    {
        $index = array_search($fieldName, self::FIELDS, true);

        return is_int($index) ? $index : 0;
    }

    /**
     * Check if a Splash Field is an Extra Address Line (ADD2 / ADD3)
     *
     * @param string $fieldName Splash Field Identifier
     *
     * @return bool
     */
    public static function isExtraLine(string $fieldName): bool
    {
        return in_array($fieldName, array_slice(self::FIELDS, 1), true);
    }

    //====================================================================//
    // OBJECTS READ / WRITE
    //====================================================================//

    /**
     * Read One Address Line from a Dolibarr Address
     *
     * When splitting is disabled, the raw address is returned as primary line
     * & extra lines are always empty.
     *
     * @param null|string $address   Raw Dolibarr Multiline Address
     * @param string      $fieldName Splash Field Identifier
     * @param string      $configKey Scope Config Key
     *
     * @return null|string
     */
    public static function read(?string $address, string $fieldName, string $configKey): ?string
    {
        //====================================================================//
        // Splitting is Disabled => Raw Address on Primary Line
        if (!self::isEnabled($configKey)) {
            return self::isExtraLine($fieldName) ? null : ($address ?: null);
        }

        return self::split($address)[self::getLineIndex($fieldName)];
    }

    /**
     * Write One Address Line into a Dolibarr Address
     *
     * When splitting is disabled, the primary line overwrites the whole address
     * & extra lines are ignored.
     *
     * @param null|string $address   Raw Dolibarr Multiline Address
     * @param string      $fieldName Splash Field Identifier
     * @param null|string $fieldData New Line Value
     * @param string      $configKey Scope Config Key
     *
     * @return null|string Updated Raw Dolibarr Multiline Address
     */
    public static function write(?string $address, string $fieldName, ?string $fieldData, string $configKey): ?string
    {
        //====================================================================//
        // Splitting is Disabled => Only Primary Line is Writable
        if (!self::isEnabled($configKey)) {
            return self::isExtraLine($fieldName) ? $address : $fieldData;
        }

        return self::replace($address, $fieldName, $fieldData);
    }

    //====================================================================//
    // FIELDS DEFINITION
    //====================================================================//

    /**
     * Build Extra Address Lines Fields (ADD2 / ADD3)
     *
     * The primary line (ADD1) is already declared by each Object.
     *
     * Each extra line is associated with all previous ones: since lines are
     * stored in a single Dolibarr field, filling ADD2 without ADD1 would shift
     * the value up on next reading.
     *
     * @param FieldsFactory $factory   Splash Fields Factory
     * @param string        $groupName Fields Group Name
     * @param bool          $readOnly  Declare Fields as ReadOnly
     *
     * @return void
     */
    public static function buildExtraFields(FieldsFactory $factory, string $groupName, bool $readOnly): void
    {
        global $langs;

        for ($index = 1; $index < self::MAX_LINES; $index++) {
            $factory->create(SPL_T_VARCHAR)
                ->identifier(self::FIELDS[$index])
                ->name($langs->trans("Address")." (".($index + 1).")")
                ->microData(self::ITEM_TYPE, self::ITEM_PROPS[$index])
                ->association(...array_slice(self::FIELDS, 0, $index))
                ->group($groupName)
                ->isReadOnly($readOnly)
            ;
        }
    }

    //====================================================================//
    // CONFIGURATION
    //====================================================================//

    /**
     * Check if Address Splitting is Enabled for a Given Scope
     *
     * @param string $configKey One of THIRDPARTY_MODE | CONTACT_MODE | DELIVERY_MODE
     *
     * @return bool
     */
    public static function isEnabled(string $configKey): bool
    {
        global $conf;

        return !empty($conf->global->{$configKey});
    }

    //====================================================================//
    // PRIVATE METHODS
    //====================================================================//

    /**
     * Trim Given Lines & Filter Out Empty Ones
     *
     * @param string[] $lines Raw Address Lines
     *
     * @return string[] Re-indexed, Non Empty Address Lines
     */
    private static function filterEmptyLines(array $lines): array
    {
        return array_values(array_filter(
            array_map('trim', $lines),
            static function (string $line): bool {
                return "" !== $line;
            }
        ));
    }
}
