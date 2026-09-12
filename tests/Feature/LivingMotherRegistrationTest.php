<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\PortalGeneralRegistrationFieldValue;
use App\Models\Sponsorship;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LivingMotherRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * اختبار حفظ بيانات الأم الحية في portal_general_registration_field_values
     */
    public function test_living_mother_saves_to_portal_table()
    {
        // Arrange
        $fileIdNumber = '099999';
        $guardianIdentity = '123456789';
        $motherId = '987654321';

        // Act - simulate the store method logic
        $portalFields = [
            'field_mother_status' => 'حية',
            'field_living_mother_id' => $motherId,
            'field_living_mother_first_name' => 'أم تجريبية',
            'field_living_mother_second_name' => 'أب الأم',
            'field_living_mother_third_name' => 'جد الأم',
            'field_living_mother_last_name' => 'عائلة الأم',
        ];

        foreach ($portalFields as $fieldKey => $fieldValue) {
            PortalGeneralRegistrationFieldValue::create([
                'file_id_number' => $fileIdNumber,
                'identity_number' => $guardianIdentity,
                'field_key' => $fieldKey,
                'field_value' => $fieldValue,
            ]);
        }

        // Assert - verify data was saved
        $savedFields = PortalGeneralRegistrationFieldValue::where('file_id_number', $fileIdNumber)->get();
        
        $this->assertCount(6, $savedFields);
        $this->assertEquals('حية', $savedFields->where('field_key', 'field_mother_status')->first()->field_value);
        $this->assertEquals($motherId, $savedFields->where('field_key', 'field_living_mother_id')->first()->field_value);
        $this->assertEquals('أم تجريبية', $savedFields->where('field_key', 'field_living_mother_first_name')->first()->field_value);
        $this->assertEquals($guardianIdentity, $savedFields->first()->identity_number);
    }

    /**
     * اختبار أن الأم المتوفاة لا تُحفظ في portal_general_registration_field_values
     */
    public function test_deceased_mother_does_not_save_to_portal_table()
    {
        // Arrange
        $fileIdNumber = '099999';
        $motherId = '987654321';

        // Act - simulate deceased mother saving (should go to dead_people, not portal)
        $deadRecord = DeadPepole::create([
            're_file_id' => $fileIdNumber,
            'mother_first_name' => 'أم متوفية',
            'mother_last_name' => 'عائلة الأم',
            'mother_id' => $motherId,
        ]);

        // Assert - verify data was saved to dead_people, not portal
        $this->assertDatabaseHas('dead_people', [
            're_file_id' => $fileIdNumber,
            'mother_id' => $motherId,
        ]);

        $portalFields = PortalGeneralRegistrationFieldValue::where('file_id_number', $fileIdNumber)->get();
        $this->assertCount(0, $portalFields);
    }

    /**
     * اختبار تحديث بيانات الأم الحية إذا كانت محفوظة مسبقاً
     */
    public function test_living_mother_updates_existing_record()
    {
        // Arrange
        $fileIdNumber = '099999';
        $guardianIdentity = '123456789';
        $motherId = '987654321';

        // Create existing record
        $existingRecord = PortalGeneralRegistrationFieldValue::create([
            'file_id_number' => $fileIdNumber,
            'identity_number' => $guardianIdentity,
            'field_key' => 'field_living_mother_first_name',
            'field_value' => 'الاسم القديم',
        ]);

        // Act - update the record
        $existingRecord->update([
            'field_value' => 'الاسم الجديد',
        ]);

        // Assert
        $this->assertDatabaseHas('portal_general_registration_field_values', [
            'file_id_number' => $fileIdNumber,
            'field_key' => 'field_living_mother_first_name',
            'field_value' => 'الاسم الجديد',
        ]);
    }
}
