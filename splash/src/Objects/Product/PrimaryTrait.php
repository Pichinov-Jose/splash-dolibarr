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
use Splash\Client\Splash;
use Splash\Local\Local;
use Splash\Local\Services\ProductIdentifier;

/**
 * Products Search by Primary Field
 */
trait PrimaryTrait
{
    /**
     * Products are identified by their barcode instead of their reference
     *
     * Splash requires ALL primary keys to match: adding the barcode next to
     * the reference would narrow the identification instead of widening it.
     * The primary key is therefore a choice, the reference by default.
     *
     * @return bool
     */
    public static function isBarcodePrimary(): bool
    {
        return "barcode" === Local::getParameter("SPLASH_PRODUCT_PRIMARY");
    }

    /**
     * {@inheritdoc}
     */
    public function getByPrimary(array $keys): ?string
    {
        global $db;
        //====================================================================//
        // Stack Trace
        Splash::log()->trace();
        //====================================================================//
        // Products Identified by Barcode
        if (self::isBarcodePrimary()) {
            return $this->getByPrimaryBarcode($keys['barcode'] ?? null);
        }
        //====================================================================//
        // Detect Primary Keys
        $ref = $keys['ref'] ?? null;
        if (empty($ref)) {
            return null;
        }

        //====================================================================//
        // Search by Raw Reference
        // Kept first: references written without Dolibarr business objects
        // (SQL imports, older data) may be stored unsanitized.
        $this->object = new Product($db);
        if (1 == $this->object->fetch(0, $ref)) {
            return $this->getObjectIdentifier();
        }
        //====================================================================//
        // Search by Normalized Reference
        // Dolibarr sanitizes references on create & update ("A/B" => "A_B"):
        // without this, a product created by Splash is never found again.
        $normalizedRef = ProductIdentifier::normalizeRef($ref);
        if ($normalizedRef === $ref) {
            return null;
        }
        $this->object = new Product($db);
        if (1 == $this->object->fetch(0, $normalizedRef)) {
            return $this->getObjectIdentifier();
        }

        return null;
    }

    /**
     * Identify a Product by its Barcode
     *
     * A barcode shared by several products (variants carrying the maker's
     * code, data entry duplicates) identifies nothing: return null rather
     * than pick one of them at random.
     *
     * @param null|scalar $barcode
     *
     * @return null|string
     */
    private function getByPrimaryBarcode($barcode): ?string
    {
        global $db;
        //====================================================================//
        // Safety Check
        $barcode = trim((string) $barcode);
        if ('' === $barcode) {
            return null;
        }
        //====================================================================//
        // Search Products using this Barcode
        $sql = "SELECT p.rowid FROM ".MAIN_DB_PREFIX."product as p";
        $sql .= " WHERE p.barcode = '".$db->escape($barcode)."'";
        $sql .= " AND p.entity IN (".getEntity('product').")";
        $sql .= " LIMIT 2";
        $result = $db->query($sql);
        if (!$result) {
            return Splash::log()->errNull($db->lasterror());
        }
        //====================================================================//
        // Ensure Only One Product Found
        if (1 != $db->num_rows($result)) {
            return null;
        }
        $row = $db->fetch_object($result);
        //====================================================================//
        // Load Product
        $this->object = new Product($db);
        if (1 == $this->object->fetch((int) $row->rowid)) {
            return $this->getObjectIdentifier();
        }

        return null;
    }
}
