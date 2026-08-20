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

namespace Splash\Local\Tests;

use PHPUnit\Framework\Assert;
use Splash\Local\Services\AddressLinesManager as Manager;
use Splash\Tests\Tools\TestCase;
use stdClass;

/**
 * Local Test Suite - Verify Splitting & Joining of Postal Address Lines
 */
class L08AddressLinesTest extends TestCase
{
    /**
     * Backup of Dolibarr Configuration
     *
     * @var null|object
     */
    private ?object $confBackup = null;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        global $conf;

        parent::setUp();
        $this->confBackup = is_object($conf) ? $conf : null;
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        global $conf;

        $conf = $this->confBackup;
        parent::tearDown();
    }

    /**
     * Test Splitting of a Dolibarr Address into Splash Lines
     *
     * @dataProvider splitProvider
     *
     * @param null|string             $address  Raw Dolibarr Address
     * @param array<int, null|string> $expected Expected Splash Lines
     *
     * @return void
     */
    public function testSplit(?string $address, array $expected): void
    {
        $lines = Manager::split($address);
        //====================================================================//
        // Splitting Always Returns a Fixed Number of Lines
        Assert::assertCount(Manager::MAX_LINES, $lines);
        Assert::assertSame($expected, $lines);
    }

    /**
     * Test Joining of Splash Lines into a Dolibarr Address
     *
     * @dataProvider joinProvider
     *
     * @param array<int, null|string> $lines    Splash Address Lines
     * @param null|string             $expected Expected Raw Dolibarr Address
     *
     * @return void
     */
    public function testJoin(array $lines, ?string $expected): void
    {
        Assert::assertSame($expected, Manager::join(...$lines));
    }

    /**
     * Test Writing a Single Line inside an Existing Address
     *
     * @dataProvider replaceProvider
     *
     * @param null|string $address   Raw Dolibarr Address
     * @param string      $fieldName Splash Field Identifier
     * @param null|string $fieldData New Line Value
     * @param null|string $expected  Expected Raw Dolibarr Address
     *
     * @return void
     */
    public function testReplace(?string $address, string $fieldName, ?string $fieldData, ?string $expected): void
    {
        Assert::assertSame($expected, Manager::replace($address, $fieldName, $fieldData));
    }

    /**
     * Test Read/Write Round Trip Keeps Address Lines Unchanged
     *
     * @dataProvider roundTripProvider
     *
     * @param array<int, null|string> $lines Splash Address Lines
     *
     * @return void
     */
    public function testRoundTrip(array $lines): void
    {
        //====================================================================//
        // Write (3 => 1) then Read (1 => 3)
        Assert::assertSame($lines, Manager::split(Manager::join(...$lines)));
    }

    /**
     * Test Mapping between Splash Fields & Line Indexes
     *
     * @return void
     */
    public function testFieldsMapping(): void
    {
        //====================================================================//
        // Each Declared Field Maps to its Own Index
        foreach (Manager::FIELDS as $index => $fieldName) {
            Assert::assertSame($index, Manager::getLineIndex($fieldName));
        }
        //====================================================================//
        // Unknown Fields Fallback on Primary Line
        Assert::assertSame(0, Manager::getLineIndex("unknown"));
        //====================================================================//
        // Only ADD2 & ADD3 are Extra Lines
        Assert::assertFalse(Manager::isExtraLine("address"));
        Assert::assertTrue(Manager::isExtraLine("address2"));
        Assert::assertTrue(Manager::isExtraLine("address3"));
        Assert::assertFalse(Manager::isExtraLine("unknown"));
    }

    /**
     * Test Reading Address Lines when Splitting is Enabled
     *
     * @return void
     */
    public function testReadWhenEnabled(): void
    {
        $this->setSplitMode(true);
        $address = "12 rue X\nBat. B\nEtage 3";
        Assert::assertSame("12 rue X", Manager::read($address, "address", Manager::CONTACT_MODE));
        Assert::assertSame("Bat. B", Manager::read($address, "address2", Manager::CONTACT_MODE));
        Assert::assertSame("Etage 3", Manager::read($address, "address3", Manager::CONTACT_MODE));
    }

    /**
     * Test Reading Address Lines when Splitting is Disabled
     *
     * @return void
     */
    public function testReadWhenDisabled(): void
    {
        $this->setSplitMode(false);
        $address = "12 rue X\nBat. B";
        //====================================================================//
        // Raw Address is Returned as Primary Line, Extra Lines Stay Empty
        Assert::assertSame($address, Manager::read($address, "address", Manager::CONTACT_MODE));
        Assert::assertNull(Manager::read($address, "address2", Manager::CONTACT_MODE));
        Assert::assertNull(Manager::read($address, "address3", Manager::CONTACT_MODE));
    }

    /**
     * Test Writing Address Lines when Splitting is Enabled
     *
     * @return void
     */
    public function testWriteWhenEnabled(): void
    {
        $this->setSplitMode(true);
        $address = "12 rue X\nBat. B\nEtage 3";
        Assert::assertSame(
            "1 rue Y\nBat. B\nEtage 3",
            Manager::write($address, "address", "1 rue Y", Manager::CONTACT_MODE)
        );
        Assert::assertSame(
            "12 rue X\nBat. C\nEtage 3",
            Manager::write($address, "address2", "Bat. C", Manager::CONTACT_MODE)
        );
        Assert::assertSame(
            "12 rue X\nBat. B",
            Manager::write($address, "address3", null, Manager::CONTACT_MODE)
        );
    }

    /**
     * Test Writing Address Lines when Splitting is Disabled
     *
     * @return void
     */
    public function testWriteWhenDisabled(): void
    {
        $this->setSplitMode(false);
        $address = "12 rue X\nBat. B";
        //====================================================================//
        // Primary Line Overwrites Whole Address, Extra Lines are Ignored
        Assert::assertSame("1 rue Y", Manager::write($address, "address", "1 rue Y", Manager::CONTACT_MODE));
        Assert::assertSame($address, Manager::write($address, "address2", "Bat. C", Manager::CONTACT_MODE));
        Assert::assertSame($address, Manager::write($address, "address3", "Etage 3", Manager::CONTACT_MODE));
    }

    /**
     * Test Complete Object Cycle, as Performed by Splash on Objects
     *
     * Splash writes address lines one by one, then reads them back.
     *
     * @return void
     */
    public function testObjectReadWriteCycle(): void
    {
        $this->setSplitMode(true);
        $expected = array("12 rue X", "Bat. B", "Etage 3");
        //====================================================================//
        // Write (3 => 1), One Line at a Time
        $address = null;
        foreach ($expected as $index => $value) {
            $address = Manager::write($address, Manager::FIELDS[$index], $value, Manager::CONTACT_MODE);
        }
        Assert::assertSame("12 rue X\nBat. B\nEtage 3", $address);
        //====================================================================//
        // Read (1 => 3)
        foreach ($expected as $index => $value) {
            Assert::assertSame($value, Manager::read($address, Manager::FIELDS[$index], Manager::CONTACT_MODE));
        }
    }

    /**
     * Addresses to Split
     *
     * @return array<string, array<int, mixed>>
     */
    public function splitProvider(): array
    {
        return array(
            'null' => array(null, array(null, null, null)),
            'empty' => array("", array(null, null, null)),
            'blank lines only' => array("\n\n   \n", array(null, null, null)),
            'single line' => array("12 rue de la Demo", array("12 rue de la Demo", null, null)),
            'two lines' => array("12 rue X\nBat. B", array("12 rue X", "Bat. B", null)),
            'three lines' => array("12 rue X\nBat. B\nEtage 3", array("12 rue X", "Bat. B", "Etage 3")),
            'empty lines removed' => array(
                "12 rue X\n\n\nBat. B\n   \nEtage 3",
                array("12 rue X", "Bat. B", "Etage 3")
            ),
            'overflow merged' => array(
                "12 rue X\nBat. B\nEtage 3\nPorte 4\nInterphone 12",
                array("12 rue X", "Bat. B", "Etage 3 Porte 4 Interphone 12")
            ),
            'windows line breaks' => array(
                "12 rue X\r\n\r\nBat. B\r\nEtage 3\r\nPorte 4",
                array("12 rue X", "Bat. B", "Etage 3 Porte 4")
            ),
            'trimmed' => array("  12 rue X  \n  Bat. B  ", array("12 rue X", "Bat. B", null)),
        );
    }

    /**
     * Address Lines to Join
     *
     * @return array<string, array<int, mixed>>
     */
    public function joinProvider(): array
    {
        return array(
            'all empty' => array(array(null, null, null), null),
            'blank strings' => array(array("", "   ", null), null),
            'single line' => array(array("12 rue X", null, null), "12 rue X"),
            'two lines' => array(array("12 rue X", "Bat. B", null), "12 rue X\nBat. B"),
            'three lines' => array(array("12 rue X", "Bat. B", "Etage 3"), "12 rue X\nBat. B\nEtage 3"),
            'gap removed' => array(array("12 rue X", null, "Etage 3"), "12 rue X\nEtage 3"),
            'trimmed' => array(array("  12 rue X  ", "  Bat. B  ", null), "12 rue X\nBat. B"),
        );
    }

    /**
     * Single Line Writings
     *
     * @return array<string, array<int, mixed>>
     */
    public function replaceProvider(): array
    {
        return array(
            'update first' => array("12 rue X\nBat. B\nEtage 3", "address", "1 rue Y", "1 rue Y\nBat. B\nEtage 3"),
            'update second' => array("12 rue X\nBat. B\nEtage 3", "address2", "Bat. C", "12 rue X\nBat. C\nEtage 3"),
            'update third' => array("12 rue X\nBat. B\nEtage 3", "address3", "Etage 4", "12 rue X\nBat. B\nEtage 4"),
            'clear second' => array("12 rue X\nBat. B\nEtage 3", "address2", null, "12 rue X\nEtage 3"),
            'clear all' => array("12 rue X", "address", null, null),
            'fill empty' => array(null, "address2", "Bat. B", "Bat. B"),
        );
    }

    /**
     * Address Lines for Round Trip
     *
     * @return array<string, array<int, mixed>>
     */
    public function roundTripProvider(): array
    {
        return array(
            'empty' => array(array(null, null, null)),
            'one line' => array(array("12 rue X", null, null)),
            'two lines' => array(array("12 rue X", "Bat. B", null)),
            'three lines' => array(array("12 rue X", "Bat. B", "Etage 3")),
        );
    }

    /**
     * Force Address Splitting Mode for Contacts
     *
     * @param bool $enabled
     *
     * @return void
     */
    private function setSplitMode(bool $enabled): void
    {
        global $conf;

        $conf = new stdClass();
        $conf->global = new stdClass();
        $conf->global->{Manager::CONTACT_MODE} = $enabled ? 1 : 0;
    }
}
