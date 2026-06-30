<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Validators;

use GOLS\ValidationResult;
use GOLS\Validators\ShipmentValidator;
use PHPUnit\Framework\TestCase;

class ShipmentValidatorTest extends TestCase
{
    private ShipmentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ShipmentValidator();
    }

    private function validData(): array
    {
        return [
            'street' => '123 Gold Lane',
            'city' => 'New York',
            'state_province' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'inventory_items' => [1, 2],
            'insurance_selected' => 1,
        ];
    }

    public function testValidDataPassesValidation(): void
    {
        $result = $this->validator->validate($this->validData());

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->errors);
    }

    public function testInsuranceSelectedFalsePassesValidation(): void
    {
        $data = $this->validData();
        $data['insurance_selected'] = 0;

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testInsuranceSelectedBoolTruePassesValidation(): void
    {
        $data = $this->validData();
        $data['insurance_selected'] = true;

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testInsuranceSelectedBoolFalsePassesValidation(): void
    {
        $data = $this->validData();
        $data['insurance_selected'] = false;

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    /**
     * @dataProvider missingAddressFieldsProvider
     */
    public function testMissingAddressFieldFails(string $field, string $expectedError): void
    {
        $data = $this->validData();
        unset($data[$field]);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey($field, $result->errors);
        $this->assertSame($expectedError, $result->errors[$field]);
    }

    public static function missingAddressFieldsProvider(): array
    {
        return [
            'street missing' => ['street', 'Street is required.'],
            'city missing' => ['city', 'City is required.'],
            'state_province missing' => ['state_province', 'State province is required.'],
            'postal_code missing' => ['postal_code', 'Postal code is required.'],
            'country missing' => ['country', 'Country is required.'],
        ];
    }

    /**
     * @dataProvider emptyAddressFieldsProvider
     */
    public function testEmptyAddressFieldFails(string $field): void
    {
        $data = $this->validData();
        $data[$field] = '';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey($field, $result->errors);
    }

    public static function emptyAddressFieldsProvider(): array
    {
        return [
            'street empty' => ['street'],
            'city empty' => ['city'],
            'state_province empty' => ['state_province'],
            'postal_code empty' => ['postal_code'],
            'country empty' => ['country'],
        ];
    }

    /**
     * @dataProvider whitespaceOnlyAddressFieldsProvider
     */
    public function testWhitespaceOnlyAddressFieldFails(string $field): void
    {
        $data = $this->validData();
        $data[$field] = '   ';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey($field, $result->errors);
    }

    public static function whitespaceOnlyAddressFieldsProvider(): array
    {
        return [
            'street whitespace' => ['street'],
            'city whitespace' => ['city'],
            'state_province whitespace' => ['state_province'],
            'postal_code whitespace' => ['postal_code'],
            'country whitespace' => ['country'],
        ];
    }

    public function testMissingInventoryItemsFails(): void
    {
        $data = $this->validData();
        unset($data['inventory_items']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('inventory_items', $result->errors);
        $this->assertSame('At least one inventory item is required.', $result->errors['inventory_items']);
    }

    public function testEmptyInventoryItemsArrayFails(): void
    {
        $data = $this->validData();
        $data['inventory_items'] = [];

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('inventory_items', $result->errors);
        $this->assertSame('At least one inventory item is required.', $result->errors['inventory_items']);
    }

    public function testInventoryItemsNotArrayFails(): void
    {
        $data = $this->validData();
        $data['inventory_items'] = 'not-an-array';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('inventory_items', $result->errors);
        $this->assertSame('Inventory items must be an array.', $result->errors['inventory_items']);
    }

    public function testMissingInsuranceSelectedFails(): void
    {
        $data = $this->validData();
        unset($data['insurance_selected']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('insurance_selected', $result->errors);
        $this->assertSame('Insurance selection is required.', $result->errors['insurance_selected']);
    }

    public function testInsuranceSelectedInvalidValueFails(): void
    {
        $data = $this->validData();
        $data['insurance_selected'] = 'yes';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('insurance_selected', $result->errors);
        $this->assertSame('Insurance selection must be a boolean value.', $result->errors['insurance_selected']);
    }

    public function testInsuranceSelectedNumericTwoFails(): void
    {
        $data = $this->validData();
        $data['insurance_selected'] = 2;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('insurance_selected', $result->errors);
    }

    public function testMultipleErrorsReturnedAtOnce(): void
    {
        $data = []; // All fields missing

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('street', $result->errors);
        $this->assertArrayHasKey('city', $result->errors);
        $this->assertArrayHasKey('state_province', $result->errors);
        $this->assertArrayHasKey('postal_code', $result->errors);
        $this->assertArrayHasKey('country', $result->errors);
        $this->assertArrayHasKey('inventory_items', $result->errors);
        $this->assertArrayHasKey('insurance_selected', $result->errors);
        $this->assertCount(7, $result->errors);
    }

    public function testSingleInventoryItemPasses(): void
    {
        $data = $this->validData();
        $data['inventory_items'] = [42];

        $result = $this->validator->validate($data);

        $this->assertTrue($result->isValid);
    }

    public function testNonStringAddressFieldFails(): void
    {
        $data = $this->validData();
        $data['street'] = 123;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('street', $result->errors);
    }
}
