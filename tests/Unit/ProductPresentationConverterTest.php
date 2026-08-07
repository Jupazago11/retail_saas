<?php

namespace Tests\Unit;

use App\Services\Products\ProductPresentationConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductPresentationConverterTest extends TestCase
{
    public function test_it_converts_presentation_quantity_to_base_quantity(): void
    {
        $converter = new ProductPresentationConverter();

        $result = $converter->toBaseQuantity('2.500000', '12.000000');

        $this->assertSame('30.000000', $result);
    }

    public function test_it_converts_base_quantity_to_presentation_quantity(): void
    {
        $converter = new ProductPresentationConverter();

        $result = $converter->fromBaseQuantity('18.000000', '6.000000');

        $this->assertSame('3.000000', $result);
    }

    public function test_it_rejects_non_positive_conversion_factors(): void
    {
        $converter = new ProductPresentationConverter();

        $this->expectException(InvalidArgumentException::class);

        $converter->toBaseQuantity('1.000000', '0');
    }
}
