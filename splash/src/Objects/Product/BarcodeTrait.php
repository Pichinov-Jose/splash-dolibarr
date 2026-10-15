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

namespace Splash\Local\Objects\Product;

use Product;
use Splash\Local\Local;

/**
 * Dolibarr Products BarCodes Fieldsconst
 */
trait BarcodeTrait
{
    /**
     * @var string
     */
    private string $defaultBarcodeType;

    /**
     * Barcode Type Codes indexed by Dolibarr Ids
     *
     * @var null|array<int, string>
     */
    private static ?array $barcodeTypeCodes = null;

    /**
     * Barcode Types holding a GTIN (EAN / UPC / ISBN)
     *
     * @var string[]
     */
    private static array $gtinBarcodes = array(
        "EAN8", "EAN13", "UPC", "ISBN",
    );

    /**
     * List of Integer barcodes Types
     *
     * @var string[]
     */
    private static array $intBarcodes = array(
        "EAN8", "EAN13", "UPC",
        "ISBN", "C39",
    );

    /**
     * List of Known Barcodes Schemas
     *
     * @var array<string, string>
     */
    private static array $knownBarcodes = array(
        "EAN8" => "gtin8",
        "EAN13" => "gtin13",
        "UPC" => "gtin12",
        "ISBN" => "gtin14",
    );

    /**
     * Build Fields using FieldFactory
     *
     * @return void
     */
    protected function buildBarcodeFields()
    {
        global $langs;

        //====================================================================//
        // Bar Code Value
        // Used as Primary Key, a barcode is a string: an integer would drop
        // the leading zeros of EAN codes and never match the other side.
        $this->fieldsFactory()->create(self::isBarcodePrimary() ? SPL_T_VARCHAR : $this->getBarcodeFormat())
            ->identifier("barcode")
            ->name($langs->trans("BarcodeValue"))
            ->description($langs->trans("BarcodeValue")." (".$this->getDefaultBarcodeType().")")
            ->microData("http://schema.org/Product", $this->getBarcodeSchema())
            ->isIndexed()
            ->isPrimary(self::isBarcodePrimary())
        ;
    }

    /**
     * Read requested Field
     *
     * @param string $key       Input List Key
     * @param string $fieldName Field Identifier / Name
     *
     * @return void
     */
    protected function getBarcodeFields(string $key, string $fieldName): void
    {
        //====================================================================//
        // READ Fields
        switch ($fieldName) {
            //====================================================================//
            // Direct Readings
            case 'barcode':
                $this->getSimple($fieldName);
                //====================================================================//
                // Only expose GTIN barcodes: internal codes (Code 128 shelf labels...)
                // must not reach the GTIN fields of a shop or a marketplace.
                if (Local::getParameter("SPLASH_BARCODE_GTIN_ONLY") && !$this->isGtinBarcode()) {
                    $this->out[$fieldName] = null;
                }

                break;
            default:
                return;
        }

        unset($this->in[$key]);
    }

    /**
     * Check if Current Product Barcode is a GTIN (EAN-8, UPC-A, EAN-13, GTIN-14)
     *
     * A barcode typed with a non GTIN symbology is never a GTIN, whatever its digits.
     * An untyped barcode is a GTIN when its length and check digit are valid.
     *
     * @return bool
     */
    private function isGtinBarcode(): bool
    {
        $barcode = trim((string) $this->object->barcode);
        //====================================================================//
        // Check Barcode Type
        $typeId = (int) $this->object->barcode_type;
        if ($typeId > 0) {
            $typeCode = self::getBarcodeTypeCodes()[$typeId] ?? "";
            if (!in_array($typeCode, self::$gtinBarcodes, true)) {
                return false;
            }
        }
        //====================================================================//
        // Check Length & Digits
        if (!preg_match('/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $barcode)) {
            return false;
        }
        //====================================================================//
        // Check GS1 Check Digit
        $digits = array_map('intval', str_split(strrev(substr($barcode, 0, -1))));
        $sum = 0;
        foreach ($digits as $index => $digit) {
            $sum += $digit * ((0 == $index % 2) ? 3 : 1);
        }

        return ((10 - ($sum % 10)) % 10) == (int) substr($barcode, -1);
    }

    /**
     * Load Barcode Type Codes from Dictionary
     *
     * @return array<int, string>
     */
    private static function getBarcodeTypeCodes(): array
    {
        global $db;

        if (isset(self::$barcodeTypeCodes)) {
            return self::$barcodeTypeCodes;
        }
        self::$barcodeTypeCodes = array();
        $result = $db->query("SELECT rowid, code FROM ".MAIN_DB_PREFIX."c_barcode_type");
        if ($result) {
            while ($row = $db->fetch_object($result)) {
                self::$barcodeTypeCodes[(int) $row->rowid] = (string) $row->code;
            }
        }

        return self::$barcodeTypeCodes;
    }

    /**
     * Write Given Fields
     *
     * @param string      $fieldName Field Identifier / Name
     * @param null|string $fieldData Field Data
     *
     * @return void
     */
    protected function setBarcodeFields(string $fieldName, ?string $fieldData): void
    {
        //====================================================================//
        // WRITE Field
        switch ($fieldName) {
            //====================================================================//
            // Direct Writings
            case 'barcode':
                $this->setSimple($fieldName, $fieldData);

                break;
            default:
                return;
        }
        unset($this->in[$fieldName]);
    }

    /**
     * Detect Default BarCode Type Code
     *
     * @return string
     */
    private function getDefaultBarcodeType(): string
    {
        global $db, $conf;
        //====================================================================//
        // Load from Cache
        if (isset($this->defaultBarcodeType)) {
            return $this->defaultBarcodeType;
        }
        //====================================================================//
        // Check if Default Bar Code Type is Defined
        if (empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
            return $this->defaultBarcodeType = "";
        }
        //====================================================================//
        // Load Bar Code Type Values
        $common = new Product($db);
        $common->barcode_type = $conf->global->PRODUIT_DEFAULT_BARCODE_TYPE;
        $common->barcode_type_code = "";
        $common->fetch_barcode();
        //====================================================================//
        // Return Bar Code Name
        $this->defaultBarcodeType = empty($common->barcode_type_code) ? "" : $common->barcode_type_code;

        return $this->defaultBarcodeType;
    }

    /**
     * Get Default BarCode Type Splash Type
     *
     * @return string
     */
    private function getBarcodeFormat(): string
    {
        //====================================================================//
        // Get Default Bar Code Type Name
        $dfType = $this->getDefaultBarcodeType();
        //====================================================================//
        // Check if Int Barcode Type
        if (empty($dfType) || !in_array($dfType, self::$intBarcodes, true)) {
            return SPL_T_VARCHAR;
        }

        return SPL_T_INT;
    }

    /**
     * Get Default BarCode Type Splash Type
     *
     * @return string
     */
    private function getBarcodeSchema(): string
    {
        //====================================================================//
        // Get Default Bar Code Type Name
        $dfType = $this->getDefaultBarcodeType();
        //====================================================================//
        // Check if Int Barcode Type
        if (empty($dfType) || !isset(self::$knownBarcodes[$dfType])) {
            return "qrcode";
        }

        return self::$knownBarcodes[$dfType];
    }
}
