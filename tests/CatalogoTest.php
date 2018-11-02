<?php

namespace Tests;

use InvalidArgumentException;
use F72X\Sunat\Catalogo;
use PHPUnit\Framework\TestCase;

final class CatalogoTest extends TestCase {
    public function testMethodGetdocumentname() {
        self::assertEquals('NOTA DE DÉBITO', Catalogo::getDocumentName(Catalogo::DOCTYPE_NOTA_DEBITO));
    }
    /**
     * @expectedException InvalidArgumentException
     */
    public function testMethodGetdocumentnameProducesAnExceptionOnInvalidType() {
        Catalogo::getDocumentName('x');
    }

    public function testGetCatItems() {
        $expected = [
            'NIU' => ['id' => 'NIU', 'value' => 'UNIDAD (BIENES)'],
            'ZZ'  => ['id' => 'ZZ',  'value' => 'UNIDAD (SERVICIOS)']
        ];
        $actual = Catalogo::getCatItems(3);
        self::assertEquals($expected, $actual);
    }

    public static function testItemExist() {
        self::assertTrue(Catalogo::itemExist(3, 'NIU'));
        self::assertTrue(Catalogo::itemExist(3, 'ZZ'));
        self::assertFalse(Catalogo::itemExist(3, 'XX'));
    }

    public function testGetCatItem() {
        $expected = [
            'id'    => '01',
            'value' => 'Precio unitario (incluye el IGV)'
        ];
        $actual = Catalogo::getCatItem(16, '01');
        self::assertEquals($expected, $actual);
    }

    public function testGeneratePhpArrays() {
        $path = __DIR__ . '/../src/Sunat/catalogo';
        for ($i = 1; $i <= 3; $i++) {
             Catalogo::catItemsToPhpArray($i, "$path/CAT_0$i.php");
        }
        for ($i = 5; $i <= 9; $i++) {
             Catalogo::catItemsToPhpArray($i, "$path/CAT_0$i.php");
        }
        for ($i = 10; $i <= 11; $i++) {
             Catalogo::catItemsToPhpArray($i, "$path/CAT_$i.php");
        }
        for ($i = 13; $i <= 27; $i++) {
             Catalogo::catItemsToPhpArray($i, "$path/CAT_$i.php");
        }
        for ($i = 51; $i <= 59; $i++) {
             Catalogo::catItemsToPhpArray($i, "$path/CAT_$i.php");
        }
    }

}
