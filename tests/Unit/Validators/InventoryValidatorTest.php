<?php

declare(strict_types=1);

namespace GOLS\Tests\Unit\Validators;

use GOLS\Validators\InventoryValidator;
use GOLS\ValidationResult;
use PHPUnit\Framework\TestCase;

class InventoryValidatorTest extends TestCase
{
    private InventoryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InventoryValidator();
    }

    private function validData(): array
    {
        return [
            'gold_type' => 'bar',
            'weight' => 100.5,
            'purity' => 0.9999,
            'serial_number' => 'ABC123',
            'vault_location' => 'Vault A',
            'user_id' => 1,
            'insurance_status' => 1,
        ];
    }

    // --- Success cases ---

    public function testValidDataPassesValidation(): void
    {
        $result = $this->validator->validate($this->validData());

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testAllGoldTypesAreValid(): void
    {
        foreach (['bar', 'coin', 'grain', 'round'] as $type) {
            $data = $this->validData();
            $data['gold_type'] = $type;

            $result = $this->validator->validate($data);
            $this->assertTrue($result->isValid, "Gold type '$type' should be valid.");
        }
    }

    public function testMinimumValidWeight(): void
    {
        $data = $this->validData();
        $data['weight'] = 0.001;

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testMaximumValidWeight(): void
    {
        $data = $this->validData();
        $data['weight'] = 999999.999;

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testMinimumValidPurity(): void
    {
        $data = $this->validData();
        $data['purity'] = 0.001;

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testMaximumValidPurity(): void
    {
        $data = $this->validData();
        $data['purity'] = 0.9999;

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testInsuranceStatusZeroIsValid(): void
    {
        $data = $this->validData();
        $data['insurance_status'] = 0;

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testInsuranceStatusStringOneIsValid(): void
    {
        $data = $this->validData();
        $data['insurance_status'] = '1';

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testInsuranceStatusStringZeroIsValid(): void
    {
        $data = $this->validData();
        $data['insurance_status'] = '0';

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    // --- gold_type validation ---

    public function testMissingGoldTypeFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['gold_type']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('gold_type', $result->errors);
        $this->assertSame('Gold type is required.', $result->errors['gold_type']);
    }

    public function testEmptyGoldTypeFailsValidation(): void
    {
        $data = $this->validData();
        $data['gold_type'] = '';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('gold_type', $result->errors);
    }

    public function testInvalidGoldTypeFailsValidation(): void
    {
        $data = $this->validData();
        $data['gold_type'] = 'nugget';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Gold type must be one of: bar, coin, grain, round.', $result->errors['gold_type']);
    }

    // --- weight validation ---

    public function testMissingWeightFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['weight']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('weight', $result->errors);
        $this->assertSame('Weight is required.', $result->errors['weight']);
    }

    public function testNonNumericWeightFailsValidation(): void
    {
        $data = $this->validData();
        $data['weight'] = 'heavy';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Weight must be a numeric value.', $result->errors['weight']);
    }

    public function testZeroWeightFailsValidation(): void
    {
        $data = $this->validData();
        $data['weight'] = 0;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Weight must be a positive value.', $result->errors['weight']);
    }

    public function testNegativeWeightFailsValidation(): void
    {
        $data = $this->validData();
        $data['weight'] = -5.0;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Weight must be a positive value.', $result->errors['weight']);
    }

    public function testWeightExceedingMaxFailsValidation(): void
    {
        $data = $this->validData();
        $data['weight'] = 1000000.0;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Weight must not exceed 999999.999.', $result->errors['weight']);
    }

    // --- purity validation ---

    public function testMissingPurityFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['purity']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('purity', $result->errors);
        $this->assertSame('Purity is required.', $result->errors['purity']);
    }

    public function testNonNumericPurityFailsValidation(): void
    {
        $data = $this->validData();
        $data['purity'] = 'pure';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Purity must be a numeric value.', $result->errors['purity']);
    }

    public function testPurityBelowMinimumFailsValidation(): void
    {
        $data = $this->validData();
        $data['purity'] = 0.0009;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Purity must be between 0.001 and 0.9999.', $result->errors['purity']);
    }

    public function testPurityAboveMaximumFailsValidation(): void
    {
        $data = $this->validData();
        $data['purity'] = 1.0;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Purity must be between 0.001 and 0.9999.', $result->errors['purity']);
    }

    public function testPurityZeroFailsValidation(): void
    {
        $data = $this->validData();
        $data['purity'] = 0;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Purity must be between 0.001 and 0.9999.', $result->errors['purity']);
    }

    // --- serial_number validation ---

    public function testMissingSerialNumberFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['serial_number']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('serial_number', $result->errors);
        $this->assertSame('Serial number is required.', $result->errors['serial_number']);
    }

    public function testEmptySerialNumberFailsValidation(): void
    {
        $data = $this->validData();
        $data['serial_number'] = '';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Serial number is required.', $result->errors['serial_number']);
    }

    public function testNonAlphanumericSerialNumberFailsValidation(): void
    {
        $data = $this->validData();
        $data['serial_number'] = 'ABC-123';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Serial number must be alphanumeric.', $result->errors['serial_number']);
    }

    public function testSerialNumberWithSpacesFailsValidation(): void
    {
        $data = $this->validData();
        $data['serial_number'] = 'ABC 123';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Serial number must be alphanumeric.', $result->errors['serial_number']);
    }

    public function testSerialNumberExceeding50CharsFailsValidation(): void
    {
        $data = $this->validData();
        $data['serial_number'] = str_repeat('A', 51);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Serial number must not exceed 50 characters.', $result->errors['serial_number']);
    }

    public function testSerialNumberExactly50CharsIsValid(): void
    {
        $data = $this->validData();
        $data['serial_number'] = str_repeat('A', 50);

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    // --- vault_location validation ---

    public function testMissingVaultLocationFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['vault_location']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('vault_location', $result->errors);
        $this->assertSame('Vault location is required.', $result->errors['vault_location']);
    }

    public function testEmptyVaultLocationFailsValidation(): void
    {
        $data = $this->validData();
        $data['vault_location'] = '';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Vault location is required.', $result->errors['vault_location']);
    }

    public function testWhitespaceOnlyVaultLocationFailsValidation(): void
    {
        $data = $this->validData();
        $data['vault_location'] = '   ';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Vault location is required.', $result->errors['vault_location']);
    }

    // --- user_id validation ---

    public function testMissingUserIdFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['user_id']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertSame('User ID is required.', $result->errors['user_id']);
    }

    public function testZeroUserIdFailsValidation(): void
    {
        $data = $this->validData();
        $data['user_id'] = 0;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    public function testNegativeUserIdFailsValidation(): void
    {
        $data = $this->validData();
        $data['user_id'] = -1;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    public function testNonNumericUserIdFailsValidation(): void
    {
        $data = $this->validData();
        $data['user_id'] = 'abc';

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    public function testFloatUserIdFailsValidation(): void
    {
        $data = $this->validData();
        $data['user_id'] = 1.5;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('User ID must be a positive integer.', $result->errors['user_id']);
    }

    // --- insurance_status validation ---

    public function testMissingInsuranceStatusFailsValidation(): void
    {
        $data = $this->validData();
        unset($data['insurance_status']);

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('insurance_status', $result->errors);
        $this->assertSame('Insurance status is required.', $result->errors['insurance_status']);
    }

    public function testNullInsuranceStatusFailsValidation(): void
    {
        $data = $this->validData();
        $data['insurance_status'] = null;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Insurance status must be a boolean value (0 or 1).', $result->errors['insurance_status']);
    }

    public function testInvalidInsuranceStatusFailsValidation(): void
    {
        $data = $this->validData();
        $data['insurance_status'] = 2;

        $result = $this->validator->validate($data);

        $this->assertFalse($result->isValid);
        $this->assertSame('Insurance status must be a boolean value (0 or 1).', $result->errors['insurance_status']);
    }

    // --- Multiple errors ---

    public function testMultipleMissingFieldsReturnMultipleErrors(): void
    {
        $result = $this->validator->validate([]);

        $this->assertFalse($result->isValid);
        $this->assertArrayHasKey('gold_type', $result->errors);
        $this->assertArrayHasKey('weight', $result->errors);
        $this->assertArrayHasKey('purity', $result->errors);
        $this->assertArrayHasKey('serial_number', $result->errors);
        $this->assertArrayHasKey('vault_location', $result->errors);
        $this->assertArrayHasKey('user_id', $result->errors);
        $this->assertArrayHasKey('insurance_status', $result->errors);
        $this->assertCount(7, $result->errors);
    }

    // --- String numeric inputs (simulating form data) ---

    public function testStringNumericWeightIsValid(): void
    {
        $data = $this->validData();
        $data['weight'] = '250.5';

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testStringNumericPurityIsValid(): void
    {
        $data = $this->validData();
        $data['purity'] = '0.999';

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }

    public function testStringIntegerUserIdIsValid(): void
    {
        $data = $this->validData();
        $data['user_id'] = '5';

        $result = $this->validator->validate($data);
        $this->assertTrue($result->isValid);
    }
}
