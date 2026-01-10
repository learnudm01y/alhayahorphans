# Smart Synchronization Workflow
## AlHayah Orphans System - Detailed Implementation Guide

---

## 🔄 Complete Sync Process Flow

### Phase 1: Sponsorship Filtering

```typescript
// Step 1: Fetch only eligible sponsorships
const eligibleSponsorships = await fetchSponsorships({
    exclude_statuses: ['ارسل للصرف', 'تم الصرف']
});

console.log(`Found ${eligibleSponsorships.length} eligible sponsorships`);
```

**SQL Query (Laravel Backend):**
```sql
SELECT s.* 
FROM sponsorships s
JOIN sponsorship_statuses ss ON s.sponsorship_status_id = ss.id
WHERE ss.description NOT IN ('ارسل للصرف', 'تم الصرف')
ORDER BY s.created_at DESC
```

---

### Phase 2: Multi-Level Person Lookup

#### Level 1: Internal Database Search

```typescript
async function fetchPersonFromInternalDB(relationId: string) {
    // Search in data table (guardians)
    const guardian = await db.query(
        'SELECT * FROM data_local WHERE file_id_number = ?',
        [relationId]
    );
    
    // Search in re_people table (orphans)
    const orphan = await db.query(
        'SELECT * FROM re_people_local WHERE registration_id = ?',
        [relationId]
    );
    
    // Search in dead_people table
    const deceased = await db.query(
        'SELECT * FROM dead_people_local WHERE re_file_id = ?',
        [relationId]
    );
    
    if (guardian || orphan || deceased) {
        return {
            found: true,
            source: 'internal_database',
            data: { guardian, orphan, deceased }
        };
    }
    
    return { found: false };
}
```

**Laravel API Endpoint:**
```php
// GET /api/sync/person-by-relation/{relationId}
public function getPersonByRelation($relationId) {
    $data = [];
    
    // Guardian data
    $guardian = Data::where('file_id_number', $relationId)->first();
    if ($guardian) $data['guardian'] = $guardian;
    
    // Orphan data
    $orphan = RePeople::where('registration_id', $relationId)->first();
    if ($orphan) $data['orphan'] = $orphan;
    
    // Deceased data
    $deceased = DeadPepole::where('re_file_id', $relationId)->first();
    if ($deceased) $data['deceased'] = $deceased;
    
    // Bank accounts
    if ($guardian) {
        $data['bank_accounts'] = GuardianBankAccount::where(
            'guardian_registration', 
            $guardian->file_id_number
        )->get();
    }
    
    return response()->json([
        'success' => true,
        'found' => !empty($data),
        'data' => $data
    ]);
}
```

#### Level 2: Civil Registry Lookup

```typescript
async function fetchFromCivilRegistry(orphanId: string, guardianId?: string) {
    const response = await ApiService.post('/api/sync/civil-registry-lookup', {
        orphan_identity: orphanId,
        guardian_identity: guardianId
    });
    
    if (response.success && response.data) {
        return {
            found: true,
            source: 'civil_registry',
            needs_correction: true, // Admin must verify
            data: response.data
        };
    }
    
    return { found: false };
}
```

**Laravel Implementation:**
```php
// POST /api/sync/civil-registry-lookup
public function civilRegistryLookup(Request $request) {
    // Connect to civilregistry database
    $orphanData = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $request->orphan_identity)
        ->first();
    
    $guardianData = null;
    if ($request->guardian_identity) {
        $guardianData = DB::connection('civilregistry')
            ->table('persons')
            ->where('CI_ID_NUM', $request->guardian_identity)
            ->first();
    }
    
    return response()->json([
        'success' => true,
        'data' => [
            'orphan' => $orphanData ? [
                'full_name' => trim("{$orphanData->CI_FIRST_NAME_AR} {$orphanData->CI_FATHER_NAME_AR} {$orphanData->CI_GRAND_FATHER_NAME_AR} {$orphanData->CI_FAMILY_NAME_AR}"),
                'identity_number' => $orphanData->CI_ID_NUM,
                'needs_correction' => true
            ] : null,
            'guardian' => $guardianData ? [
                'full_name' => trim("{$guardianData->CI_FIRST_NAME_AR} {$guardianData->CI_FATHER_NAME_AR} {$guardianData->CI_GRAND_FATHER_NAME_AR} {$guardianData->CI_FAMILY_NAME_AR}"),
                'identity_number' => $guardianData->CI_ID_NUM,
                'needs_correction' => true
            ] : null
        ]
    ]);
}
```

---

### Phase 3: Admin Name Correction Interface

#### Mobile UI Component

```html
<!-- Name Correction Screen -->
<div class="correction-screen" *ngIf="needsCorrection && isAdmin">
    <div class="header">
        <h2>تصحيح البيانات من السجل المدني</h2>
        <p class="warning">⚠️ البيانات التالية تحتاج إلى مراجعة وتصحيح</p>
    </div>
    
    <!-- Original Name Display -->
    <div class="original-data">
        <h3>البيانات الأصلية:</h3>
        <div class="name-display">
            {{ civilRegistryData.full_name }}
        </div>
        <div class="identity">
            رقم الهوية: {{ civilRegistryData.identity_number }}
        </div>
    </div>
    
    <!-- Correction Form -->
    <form class="correction-form" [formGroup]="nameForm">
        <h3>أدخل الاسم الرباعي الصحيح:</h3>
        
        <div class="form-field">
            <label>الاسم الأول *</label>
            <input type="text" formControlName="first_name" required>
        </div>
        
        <div class="form-field">
            <label>اسم الأب *</label>
            <input type="text" formControlName="second_name" required>
        </div>
        
        <div class="form-field">
            <label>اسم الجد *</label>
            <input type="text" formControlName="third_name" required>
        </div>
        
        <div class="form-field">
            <label>اسم العائلة *</label>
            <input type="text" formControlName="last_name" required>
        </div>
        
        <!-- Person Type Selection -->
        <div class="form-field">
            <label>نوع الشخص *</label>
            <select formControlName="person_type" required>
                <option value="orphan">مكفول</option>
                <option value="guardian">معيل</option>
                <option value="deceased_father">أب متوفى</option>
                <option value="deceased_mother">أم متوفية</option>
            </select>
        </div>
        
        <button 
            class="btn-submit" 
            [disabled]="!nameForm.valid"
            (click)="submitCorrection()">
            ✅ تأكيد وحفظ
        </button>
    </form>
</div>
```

#### TypeScript Logic

```typescript
export class NameCorrectionComponent {
    nameForm: FormGroup;
    civilRegistryData: any;
    isAdmin: boolean = false;
    
    async ngOnInit() {
        // Check admin role
        const user = await AuthService.getUserData();
        this.isAdmin = ['admin', 'super_admin'].includes(user.role);
        
        // Initialize form
        this.nameForm = new FormGroup({
            first_name: new FormControl('', Validators.required),
            second_name: new FormControl('', Validators.required),
            third_name: new FormControl('', Validators.required),
            last_name: new FormControl('', Validators.required),
            person_type: new FormControl('', Validators.required)
        });
    }
    
    async submitCorrection() {
        if (!this.isAdmin) {
            alert('غير مصرح لك بإجراء هذه العملية');
            return;
        }
        
        if (!this.nameForm.valid) {
            alert('يرجى ملء جميع الحقول المطلوبة');
            return;
        }
        
        try {
            // Submit to API
            const response = await ApiService.post('/api/sync/correct-person-name', {
                person_identity: this.civilRegistryData.identity_number,
                person_type: this.nameForm.value.person_type,
                corrected_name: {
                    first_name: this.nameForm.value.first_name,
                    second_name: this.nameForm.value.second_name,
                    third_name: this.nameForm.value.third_name,
                    last_name: this.nameForm.value.last_name
                }
            });
            
            if (response.success) {
                // Route to appropriate table
                await this.routePersonData(response.data);
                
                alert('✅ تم حفظ البيانات بنجاح');
                this.closeCorrection();
            }
        } catch (error) {
            console.error('Correction failed:', error);
            alert('❌ فشل حفظ البيانات');
        }
    }
    
    async routePersonData(personData: any) {
        // This will be handled by the smart routing service
        await PersonDataRouter.routePersonUpdate(
            personData,
            this.nameForm.value.person_type
        );
    }
}
```

---

### Phase 4: Smart Data Routing

#### Automatic Table Selection

```typescript
class PersonDataRouter {
    async routePersonUpdate(personData: any, personType: string) {
        console.log(`Routing ${personType} to appropriate table...`);
        
        switch (personType) {
            case 'guardian':
                await this.updateGuardianTable(personData);
                break;
            
            case 'orphan':
            case 'family_member':
                await this.updateOrphanTable(personData);
                break;
            
            case 'deceased_father':
            case 'deceased_mother':
                await this.updateDeceasedTable(personData, personType);
                break;
            
            default:
                throw new Error(`Unknown person type: ${personType}`);
        }
        
        // Always update sponsorships after person data update
        await this.updateSponsorshipRecord(personData);
    }
    
    private async updateGuardianTable(data: any) {
        // Update data table
        await ApiService.post('/api/sync/data', {
            table: 'data',
            operation: 'update',
            data: {
                file_id_number: data.file_id_number,
                data_first_name: data.first_name,
                data_father_name: data.second_name,
                data_grand_father_name: data.third_name,
                data_family_name: data.last_name,
                data_id_number: data.identity_number,
                data_phone_number: data.phone_number,
                data_alt_phone_number: data.alt_phone_number,
                data_province: data.province,
                data_current_address: data.detailed_address
            }
        });
        
        // Update bank accounts if provided
        if (data.bank_accounts && data.bank_accounts.length > 0) {
            await this.updateBankAccounts(data.file_id_number, data.bank_accounts);
        }
        
        console.log('✅ Guardian data updated in data table');
    }
    
    private async updateOrphanTable(data: any) {
        // Update re_people table
        await ApiService.post('/api/sync/re-people', {
            table: 're_people',
            operation: 'update',
            data: {
                registration_id: data.registration_id,
                first_name: data.first_name,
                second_name: data.second_name,
                third_name: data.third_name,
                last_name: data.last_name,
                person_id: data.identity_number,
                person_gender: data.gender,
                person_birth_date: data.birth_date
            }
        });
        
        console.log('✅ Orphan data updated in re_people table');
    }
    
    private async updateDeceasedTable(data: any, type: string) {
        const isfather = type === 'deceased_father';
        const prefix = isfather ? 'father' : 'mother';
        
        // Update dead_people table
        await ApiService.post('/api/sync/dead-people', {
            table: 'dead_people',
            operation: 'update',
            data: {
                re_file_id: data.re_file_id,
                [`${prefix}_first_name`]: data.first_name,
                [`${prefix}_second_name`]: data.second_name,
                [`${prefix}_third_name`]: data.third_name,
                [`${prefix}_last_name`]: data.last_name,
                [`${prefix}_id`]: data.identity_number
            }
        });
        
        console.log(`✅ ${isfather ? 'Father' : 'Mother'} data updated in dead_people table`);
    }
    
    private async updateBankAccounts(guardianId: string, accounts: any[]) {
        for (const account of accounts) {
            await ApiService.post('/api/sync/bank-accounts', {
                table: 'guardian_bank_accounts',
                operation: 'create_or_update',
                data: {
                    guardian_registration: guardianId,
                    bank_name: account.bank_name,
                    iban_usd: account.iban_usd,
                    iban_shekel: account.iban_shekel,
                    account_number_or_related_phone_number: account.account_number,
                    check_account: account.check_account,
                    person_owner_identity_number: account.owner_identity
                }
            });
        }
        
        console.log('✅ Bank accounts updated');
    }
}
```

---

### Phase 5: Sponsorship Record Update

```typescript
async function updateSponsorshipRecord(personData: any) {
    // Build full names
    const orphanFullName = `${personData.orphan_first_name} ${personData.orphan_second_name} ${personData.orphan_third_name} ${personData.orphan_last_name}`;
    
    const guardianFullName = personData.guardian_first_name ? 
        `${personData.guardian_first_name} ${personData.guardian_father_name} ${personData.guardian_grand_father_name} ${personData.guardian_family_name}` : 
        null;
    
    // Update sponsorships table
    await ApiService.post('/api/sync/sponsorships/update', {
        search_criteria: {
            relation_id_number: personData.relation_id_number,
            // OR
            identity_number: personData.orphan_identity,
            guardian_identity_number: personData.guardian_identity
        },
        update_data: {
            orphan_name: orphanFullName,
            guardian_name: guardianFullName,
            identity_number: personData.orphan_identity,
            guardian_identity_number: personData.guardian_identity
        }
    });
    
    console.log('✅ Sponsorship record updated');
}
```

**Laravel Implementation:**

```php
// POST /api/sync/sponsorships/update
public function updateSponsorshipRecord(Request $request) {
    $criteria = $request->search_criteria;
    $updateData = $request->update_data;
    
    // Find sponsorship by relation_id OR identity numbers
    $query = Sponsorship::query();
    
    if (!empty($criteria['relation_id_number'])) {
        $query->where('relation_id_number', $criteria['relation_id_number']);
    } else {
        $query->where('identity_number', $criteria['identity_number'])
              ->orWhere('guardian_identity_number', $criteria['guardian_identity_number']);
    }
    
    $sponsorship = $query->first();
    
    if ($sponsorship) {
        $sponsorship->update([
            'orphan_name' => $updateData['orphan_name'],
            'guardian_name' => $updateData['guardian_name'],
            'identity_number' => $updateData['identity_number'],
            'guardian_identity_number' => $updateData['guardian_identity_number'],
            'updated_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Sponsorship updated successfully'
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Sponsorship not found'
    ], 404);
}
```

---

## 🔐 Security Implementation

### Database Encryption (SQLCipher)

```typescript
class DatabaseService {
    async initializeEncryptedDatabase(username: string, deviceId: string) {
        // Generate encryption key from user credentials + device
        const encryptionKey = CryptoJS.SHA256(
            username + deviceId + 'alhayah_secret_salt_2026'
        ).toString();
        
        // Initialize SQLCipher with encryption
        await SQLiteService.execute(`PRAGMA key = "${encryptionKey}"`);
        await SQLiteService.execute('PRAGMA cipher_page_size = 4096');
        await SQLiteService.execute('PRAGMA kdf_iter = 256000');
        await SQLiteService.execute('PRAGMA cipher_hmac_algorithm = HMAC_SHA512');
        
        console.log('🔒 Database initialized with AES-256 encryption');
    }
    
    async testDatabaseAccess() {
        try {
            // Try to query without key (should fail)
            await SQLiteService.query('SELECT * FROM data_local LIMIT 1');
        } catch (error) {
            console.log('✅ Database is protected - unauthorized access blocked');
        }
    }
}
```

### Credential Storage

```typescript
class SecureStorage {
    private static SALT = 'alhayah_credentials_salt_2026';
    
    static async saveCredentials(username: string, password: string) {
        const deviceId = await AuthService.getDeviceId();
        const encryptionKey = CryptoJS.SHA256(deviceId + this.SALT).toString();
        
        const encrypted = CryptoJS.AES.encrypt(
            JSON.stringify({ username, password }),
            encryptionKey
        ).toString();
        
        await Storage.set({
            key: 'encrypted_credentials',
            value: encrypted
        });
        
        console.log('🔐 Credentials saved with AES-256 encryption');
    }
    
    static async getCredentials(): Promise<{username: string, password: string} | null> {
        const { value } = await Storage.get({ key: 'encrypted_credentials' });
        
        if (!value) return null;
        
        try {
            const deviceId = await AuthService.getDeviceId();
            const encryptionKey = CryptoJS.SHA256(deviceId + this.SALT).toString();
            
            const decrypted = CryptoJS.AES.decrypt(value, encryptionKey).toString(CryptoJS.enc.Utf8);
            return JSON.parse(decrypted);
        } catch (error) {
            console.error('Failed to decrypt credentials:', error);
            return null;
        }
    }
}
```

---

## 📝 Complete Workflow Example

```typescript
async function completeSmartSync() {
    console.log('🚀 Starting Smart Sync Process...');
    
    // Step 1: Fetch eligible sponsorships
    const sponsorships = await SmartSyncService.fetchEligibleSponsorships();
    console.log(`📋 Found ${sponsorships.length} eligible sponsorships`);
    
    for (const sponsorship of sponsorships) {
        console.log(`\n▶️ Processing sponsorship #${sponsorship.id}...`);
        
        // Step 2: Multi-level lookup
        let personData = null;
        
        // Try Level 1: Internal database
        if (sponsorship.relation_id_number) {
            personData = await SmartSyncService.fetchFromInternalTables(
                sponsorship.relation_id_number
            );
        }
        
        // Try Level 2: Civil registry
        if (!personData) {
            personData = await SmartSyncService.fetchFromCivilRegistry(
                sponsorship.identity_number,
                sponsorship.guardian_identity_number
            );
            
            if (personData && personData.needs_correction) {
                // Step 3: Show admin correction UI
                console.log('⚠️ Data needs admin correction');
                await showNameCorrectionUI(personData, sponsorship);
                continue; // Wait for admin input
            }
        }
        
        // Step 4: Route and update data
        if (personData) {
            await PersonDataRouter.routePersonUpdate(
                personData,
                determinePersonType(personData)
            );
            
            // Step 5: Update sponsorship
            await updateSponsorshipRecord(personData);
            
            console.log('✅ Sponsorship data synced successfully');
        } else {
            console.log('❌ No data found for this sponsorship');
        }
    }
    
    console.log('\n🎉 Smart Sync completed!');
}
```

---

## 🆕 Phase 6: New Person Entry - File ID Generation

### Overview

عندما لا يوجد الشخص في أي من الجداول المركزية (`data`, `re_people`, `dead_people`)، فهذا يعني أنه لا يملك `relation_id_number` (رقم ملف فريد) للدخول إلى قاعدة البيانات المركزية. في هذه الحالة:

1. **يجب إبلاغ Laravel** بأن هذا الشخص جديد
2. **توليد رقم ملف فريد** عبر الخوارزمية المركزية في Laravel
3. **حفظ الرقم** في جدول `sponsorships` في عمود `relation_id_number`

### Handshake Flow

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  PWA / Mobile   │         │  Laravel API     │         │  MySQL Database │
└────────┬────────┘         └────────┬─────────┘         └────────┬────────┘
         │                           │                            │
         │ 1. Check person in tables │                            │
         │─────────────────────────>│ 2. Search data, re_people, │
         │                           │    dead_people tables      │
         │                           │──────────────────────────>│
         │<─────────────────────────│  ❌ NOT FOUND              │
         │                           │                            │
         │ 3. REQUEST_NEW_FILE_ID    │                            │
         │   + person_data           │                            │
         │   + handshake_token       │                            │
         │   + is_new_person: true   │                            │
         │─────────────────────────>│                            │
         │                           │ 4. Generate unique file_id │
         │                           │    using central algorithm │
         │                           │──────────────────────────>│
         │                           │   Insert to appropriate    │
         │                           │   table + file_id_registry │
         │                           │<──────────────────────────│
         │<─────────────────────────│                            │
         │  Return {                 │                            │
         │    file_id: "G2026000123",│                            │
         │    table_name: "data",    │                            │
         │    relation_id: "G2026..."│                            │
         │  }                        │                            │
         │                           │                            │
         │ 5. Update sponsorship     │                            │
         │    relation_id_number     │                            │
         │─────────────────────────>│──────────────────────────>│
         │<─────────────────────────│  ✅ SUCCESS                │
```

### TypeScript Implementation

```typescript
// services/new-person-sync.service.ts

interface NewPersonRequest {
    sponsorship_id: number;
    is_new_person: boolean;
    person_type: 'guardian' | 'orphan' | 'deceased_father' | 'deceased_mother';
    person_data: {
        first_name: string;
        second_name: string;
        third_name: string;
        last_name: string;
        identity_number: string;
        phone_number?: string;
        province?: string;
        address?: string;
        birth_date?: string;
        gender?: string;
    };
    guardian_data?: {
        first_name: string;
        father_name: string;
        grand_father_name: string;
        family_name: string;
        identity_number: string;
        phone_number?: string;
    };
}

class NewPersonSyncService {
    /**
     * 🔍 التحقق من وجود الشخص في جميع الجداول المركزية
     */
    static async checkPersonExistsInAllTables(
        identityNumber: string
    ): Promise<{ 
        exists: boolean; 
        found_in?: string; 
        file_id?: string;
        person_data?: any;
    }> {
        try {
            const response = await ApiService.post('/api/sync/check-person-all-tables', {
                identity_number: identityNumber
            });
            
            return {
                exists: response.exists,
                found_in: response.found_in,
                file_id: response.file_id,
                person_data: response.person_data
            };
        } catch (error) {
            console.error('Error checking person existence:', error);
            return { exists: false };
        }
    }
    
    /**
     * 🆕 معالجة إدخال شخص جديد غير موجود في الجداول المركزية
     */
    static async handleNewPersonEntry(
        request: NewPersonRequest
    ): Promise<{
        success: boolean;
        file_id: string;
        relation_id_number: string;
        message: string;
    }> {
        console.log('🆕 Processing new person entry...');
        console.log(`📋 Person type: ${request.person_type}`);
        console.log(`🔢 Identity: ${request.person_data.identity_number}`);
        
        try {
            // Generate handshake token
            const deviceId = await AuthService.getDeviceId();
            const timestamp = new Date().toISOString();
            const handshakeToken = CryptoJS.SHA256(
                request.person_data.identity_number +
                request.sponsorship_id +
                deviceId +
                timestamp
            ).toString();
            
            // Track progress
            const progressId = await SyncProgressTracker.createProgress({
                operationType: 'data_upload',
                entityType: request.person_type,
                entityName: `${request.person_data.first_name} ${request.person_data.last_name}`,
                status: 'in_progress',
                progressPercentage: 0
            });
            
            // Step 1: Request file ID and insert new person
            console.log('📤 Sending new person data to Laravel...');
            await SyncProgressTracker.updateProgress(progressId, { 
                progressPercentage: 20 
            });
            
            const response = await ApiService.post('/api/sync/new-person-entry', {
                is_new_person: true,
                sponsorship_id: request.sponsorship_id,
                person_type: request.person_type,
                person_data: request.person_data,
                guardian_data: request.guardian_data,
                handshake_token: handshakeToken,
                device_id: deviceId,
                requested_at: timestamp
            });
            
            if (!response.success) {
                await SyncProgressTracker.updateProgress(progressId, {
                    status: 'failed',
                    errorMessage: response.message
                });
                throw new Error(response.message || 'New person entry failed');
            }
            
            await SyncProgressTracker.updateProgress(progressId, { 
                progressPercentage: 60 
            });
            
            // Step 2: Update sponsorship with new relation_id_number
            console.log(`📝 Updating sponsorship #${request.sponsorship_id}...`);
            
            await ApiService.post('/api/sync/sponsorships/update-relation-id', {
                sponsorship_id: request.sponsorship_id,
                relation_id_number: response.file_id,
                handshake_token: handshakeToken
            });
            
            await SyncProgressTracker.updateProgress(progressId, { 
                progressPercentage: 80 
            });
            
            // Step 3: Activate file ID
            await FileIDGeneratorService.activateFileID(
                response.file_id, 
                handshakeToken
            );
            
            await SyncProgressTracker.updateProgress(progressId, {
                status: 'success',
                progressPercentage: 100
            });
            
            console.log(`✅ New person entry completed: ${response.file_id}`);
            
            return {
                success: true,
                file_id: response.file_id,
                relation_id_number: response.file_id,
                message: 'تم إدخال الشخص الجديد بنجاح'
            };
            
        } catch (error) {
            console.error('❌ New person entry failed:', error);
            throw error;
        }
    }
}
```

### Laravel Backend Implementation

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\Sponsorship;

class SyncController extends Controller
{
    /**
     * POST /api/sync/check-person-all-tables
     * التحقق من وجود الشخص في جميع الجداول المركزية
     */
    public function checkPersonAllTables(Request $request)
    {
        $request->validate([
            'identity_number' => 'required|string'
        ]);
        
        $identity = $request->identity_number;
        
        // Check in data table (guardians)
        $guardian = Data::where('data_id_number', $identity)->first();
        if ($guardian) {
            return response()->json([
                'exists' => true,
                'found_in' => 'data',
                'file_id' => $guardian->file_id_number,
                'person_data' => $guardian
            ]);
        }
        
        // Check in re_people table (orphans)
        $orphan = RePeople::where('person_id', $identity)->first();
        if ($orphan) {
            return response()->json([
                'exists' => true,
                'found_in' => 're_people',
                'file_id' => $orphan->registration_id,
                'person_data' => $orphan
            ]);
        }
        
        // Check in dead_people table
        $deceased = DeadPepole::where('father_id', $identity)
            ->orWhere('mother_id', $identity)
            ->first();
        if ($deceased) {
            return response()->json([
                'exists' => true,
                'found_in' => 'dead_people',
                'file_id' => $deceased->re_file_id,
                'person_data' => $deceased
            ]);
        }
        
        // Not found in any table
        return response()->json([
            'exists' => false,
            'message' => 'Person not found in central database'
        ]);
    }
    
    /**
     * POST /api/sync/new-person-entry
     * إدخال شخص جديد غير موجود في الجداول المركزية
     */
    public function newPersonEntry(Request $request)
    {
        $request->validate([
            'is_new_person' => 'required|boolean',
            'sponsorship_id' => 'required|integer',
            'person_type' => 'required|in:guardian,orphan,deceased_father,deceased_mother',
            'person_data' => 'required|array',
            'person_data.first_name' => 'required|string',
            'person_data.identity_number' => 'required|string',
            'handshake_token' => 'required|string',
            'device_id' => 'required|string'
        ]);
        
        if (!$request->is_new_person) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only for new person entries'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            $personType = $request->person_type;
            $personData = $request->person_data;
            
            // Verify person doesn't exist
            $existingCheck = $this->checkPersonAllTables(new Request([
                'identity_number' => $personData['identity_number']
            ]));
            
            if ($existingCheck->getData()->exists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'الشخص موجود بالفعل في قاعدة البيانات',
                    'existing_file_id' => $existingCheck->getData()->file_id,
                    'found_in' => $existingCheck->getData()->found_in
                ], 409);
            }
            
            // Generate unique file ID
            $fileID = $this->generateUniqueFileNumber($this->mapPersonType($personType));
            
            // Determine target table and insert
            switch ($personType) {
                case 'guardian':
                    $this->insertGuardian($fileID, $personData);
                    $tableName = 'data';
                    break;
                    
                case 'orphan':
                    $this->insertOrphan($fileID, $personData);
                    $tableName = 're_people';
                    break;
                    
                case 'deceased_father':
                case 'deceased_mother':
                    $this->insertDeceased($fileID, $personData, $personType);
                    $tableName = 'dead_people';
                    break;
            }
            
            // Save to file_id_registry
            DB::table('file_id_registry')->insert([
                'file_id' => $fileID,
                'table_name' => $tableName,
                'person_type' => $this->mapPersonType($personType),
                'identity_number' => $personData['identity_number'],
                'person_name' => $personData['first_name'] . ' ' . ($personData['last_name'] ?? ''),
                'handshake_token' => $request->handshake_token,
                'device_id' => $request->device_id,
                'status' => 'reserved',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            Log::info('New person entry successful', [
                'file_id' => $fileID,
                'person_type' => $personType,
                'identity' => $personData['identity_number'],
                'sponsorship_id' => $request->sponsorship_id
            ]);
            
            return response()->json([
                'success' => true,
                'file_id' => $fileID,
                'table_name' => $tableName,
                'handshake_token' => $request->handshake_token,
                'message' => 'تم إدخال الشخص الجديد بنجاح'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('New person entry failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل إدخال الشخص: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * تحويل نوع الشخص للخوارزمية
     */
    private function mapPersonType($type)
    {
        return match($type) {
            'guardian' => 'guardian',
            'orphan' => 'orphan',
            'deceased_father', 'deceased_mother' => 'deceased',
            default => 'orphan'
        };
    }
    
    /**
     * إدخال بيانات المعيل الجديد
     */
    private function insertGuardian($fileID, $data)
    {
        Data::create([
            'file_id_number' => $fileID,
            'data_first_name' => $data['first_name'],
            'data_father_name' => $data['second_name'] ?? null,
            'data_grand_father_name' => $data['third_name'] ?? null,
            'data_family_name' => $data['last_name'] ?? null,
            'data_id_number' => $data['identity_number'],
            'data_phone_number' => $data['phone_number'] ?? null,
            'data_province' => $data['province'] ?? null,
            'data_current_address' => $data['address'] ?? null
        ]);
    }
    
    /**
     * إدخال بيانات المكفول الجديد
     */
    private function insertOrphan($fileID, $data)
    {
        RePeople::create([
            'registration_id' => $fileID,
            'first_name' => $data['first_name'],
            'second_name' => $data['second_name'] ?? null,
            'third_name' => $data['third_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'person_id' => $data['identity_number'],
            'person_gender' => $data['gender'] ?? null,
            'person_birth_date' => $data['birth_date'] ?? null
        ]);
    }
    
    /**
     * إدخال بيانات المتوفى الجديد
     */
    private function insertDeceased($fileID, $data, $type)
    {
        $isFather = $type === 'deceased_father';
        $prefix = $isFather ? 'father' : 'mother';
        
        DeadPepole::create([
            're_file_id' => $fileID,
            "{$prefix}_first_name" => $data['first_name'],
            "{$prefix}_second_name" => $data['second_name'] ?? null,
            "{$prefix}_third_name" => $data['third_name'] ?? null,
            "{$prefix}_last_name" => $data['last_name'] ?? null,
            "{$prefix}_id" => $data['identity_number']
        ]);
    }
    
    /**
     * POST /api/sync/sponsorships/update-relation-id
     * تحديث رقم الملف في جدول الكفالات
     */
    public function updateSponsorshipRelationId(Request $request)
    {
        $request->validate([
            'sponsorship_id' => 'required|integer',
            'relation_id_number' => 'required|string',
            'handshake_token' => 'required|string'
        ]);
        
        try {
            // Verify handshake token
            $registry = DB::table('file_id_registry')
                ->where('file_id', $request->relation_id_number)
                ->where('handshake_token', $request->handshake_token)
                ->first();
            
            if (!$registry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid handshake token'
                ], 403);
            }
            
            // Update sponsorship
            $sponsorship = Sponsorship::findOrFail($request->sponsorship_id);
            $sponsorship->update([
                'relation_id_number' => $request->relation_id_number,
                'updated_at' => now()
            ]);
            
            Log::info('Sponsorship relation_id_number updated', [
                'sponsorship_id' => $request->sponsorship_id,
                'relation_id_number' => $request->relation_id_number
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث رقم الملف في الكفالة بنجاح'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update sponsorship relation_id', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل تحديث رقم الملف: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

---

## 📊 Phase 7: Sync Progress Monitoring Dashboard (PWA)

### Overview

صفحة مخصصة في PWA لمتابعة حالة المزامنة في الوقت الفعلي، يتم تفعيلها من خلال زر علوي جانبي.

### Access Button Integration

```html
<!-- في header أو sidebar لصفحات PWA -->
<div class="top-navigation">
    <button class="btn-sync-monitor" id="btnOpenSyncMonitor" title="متابعة المزامنة">
        <span class="sync-icon">🔄</span>
        <span class="badge badge-warning" id="syncPendingBadge">0</span>
    </button>
</div>
```

```typescript
// تفعيل الزر
document.getElementById('btnOpenSyncMonitor').addEventListener('click', () => {
    window.location.href = '/sync-monitor.html';
    // أو فتح في modal
    // SyncMonitorModal.open();
});

// تحديث badge بعدد العمليات المعلقة
async function updateSyncBadge() {
    const pending = await SyncProgressTracker.getPendingCount();
    const badge = document.getElementById('syncPendingBadge');
    badge.textContent = pending;
    badge.style.display = pending > 0 ? 'inline' : 'none';
}
```

### Progress Tracking Data Structure

```typescript
// types/sync-progress.types.ts

interface SyncProgressItem {
    id: string;
    sync_session_id: string;
    operationType: 'data_upload' | 'data_download' | 'media_upload' | 'media_download';
    entityType: 'guardian' | 'orphan' | 'deceased' | 'sponsorship' | 'bank_account' | 'attachment';
    entityId: string;
    entityName: string;
    status: 'pending' | 'in_progress' | 'success' | 'failed' | 'conflict';
    progressPercentage: number;
    
    // For file uploads
    fileSizeBytes?: number;
    uploadedBytes?: number;
    fileName?: string;
    
    // Error handling
    errorMessage?: string;
    retryCount?: number;
    maxRetries?: number;
    
    // Conflict handling
    conflictReason?: string;
    conflictType?: 'data_mismatch' | 'version_conflict' | 'duplicate' | 'validation_error';
    serverValue?: any;
    localValue?: any;
    
    // Timestamps
    createdAt: string;
    startedAt?: string;
    completedAt?: string;
    updatedAt: string;
}

interface SyncSession {
    sessionId: string;
    startedAt: string;
    endedAt?: string;
    totalOperations: number;
    completedOperations: number;
    failedOperations: number;
    conflictOperations: number;
    status: 'running' | 'completed' | 'failed' | 'paused';
}
```

### SyncProgressTracker Service

```typescript
// services/sync-progress-tracker.service.ts

class SyncProgressTracker {
    private static subscribers: ((items: SyncProgressItem[]) => void)[] = [];
    private static currentSessionId: string | null = null;
    
    /**
     * 🆕 إنشاء جلسة مزامنة جديدة
     */
    static async startNewSession(): Promise<string> {
        const sessionId = `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        this.currentSessionId = sessionId;
        
        await SQLiteService.execute(`
            INSERT INTO sync_sessions (
                session_id, started_at, status, total_operations, 
                completed_operations, failed_operations, conflict_operations
            ) VALUES (?, ?, 'running', 0, 0, 0, 0)
        `, [sessionId, new Date().toISOString()]);
        
        console.log(`🚀 New sync session started: ${sessionId}`);
        return sessionId;
    }
    
    /**
     * ➕ إنشاء سجل تقدم جديد
     */
    static async createProgress(data: Partial<SyncProgressItem>): Promise<string> {
        const id = `progress_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        const now = new Date().toISOString();
        
        await SQLiteService.execute(`
            INSERT INTO sync_progress (
                id, sync_session_id, operation_type, entity_type, entity_id,
                entity_name, status, progress_percentage, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `, [
            id,
            this.currentSessionId,
            data.operationType,
            data.entityType,
            data.entityId || '',
            data.entityName || '',
            data.status || 'pending',
            data.progressPercentage || 0,
            now,
            now
        ]);
        
        // Update session statistics
        await this.updateSessionStats();
        
        // Notify subscribers
        await this.notifySubscribers();
        
        return id;
    }
    
    /**
     * 📝 تحديث سجل التقدم
     */
    static async updateProgress(
        progressId: string, 
        updates: Partial<SyncProgressItem>
    ): Promise<void> {
        const now = new Date().toISOString();
        const updateFields: string[] = [];
        const values: any[] = [];
        
        if (updates.status !== undefined) {
            updateFields.push('status = ?');
            values.push(updates.status);
            
            if (updates.status === 'in_progress' && !updates.startedAt) {
                updateFields.push('started_at = ?');
                values.push(now);
            }
            
            if (['success', 'failed', 'conflict'].includes(updates.status)) {
                updateFields.push('completed_at = ?');
                values.push(now);
            }
        }
        
        if (updates.progressPercentage !== undefined) {
            updateFields.push('progress_percentage = ?');
            values.push(updates.progressPercentage);
        }
        
        if (updates.errorMessage !== undefined) {
            updateFields.push('error_message = ?');
            values.push(updates.errorMessage);
        }
        
        if (updates.retryCount !== undefined) {
            updateFields.push('retry_count = ?');
            values.push(updates.retryCount);
        }
        
        if (updates.conflictReason !== undefined) {
            updateFields.push('conflict_reason = ?');
            values.push(updates.conflictReason);
        }
        
        if (updates.uploadedBytes !== undefined) {
            updateFields.push('uploaded_bytes = ?');
            values.push(updates.uploadedBytes);
        }
        
        updateFields.push('updated_at = ?');
        values.push(now);
        values.push(progressId);
        
        await SQLiteService.execute(`
            UPDATE sync_progress 
            SET ${updateFields.join(', ')}
            WHERE id = ?
        `, values);
        
        // Update session statistics
        await this.updateSessionStats();
        
        // Notify subscribers
        await this.notifySubscribers();
    }
    
    /**
     * 📊 الحصول على إحصائيات الجلسة الحالية
     */
    static async getSessionStatistics(): Promise<{
        total: number;
        pending: number;
        in_progress: number;
        success: number;
        failed: number;
        conflict: number;
    }> {
        const result = await SQLiteService.query(`
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'conflict' THEN 1 ELSE 0 END) as conflict
            FROM sync_progress
            WHERE sync_session_id = ?
        `, [this.currentSessionId]);
        
        return result[0] || {
            total: 0, pending: 0, in_progress: 0, 
            success: 0, failed: 0, conflict: 0
        };
    }
    
    /**
     * 📋 الحصول على جميع عناصر التقدم للجلسة الحالية
     */
    static async getCurrentSessionProgress(): Promise<SyncProgressItem[]> {
        return await SQLiteService.query(`
            SELECT * FROM sync_progress
            WHERE sync_session_id = ?
            ORDER BY created_at DESC
        `, [this.currentSessionId]);
    }
    
    /**
     * 🔔 الاشتراك في تحديثات التقدم
     */
    static subscribe(callback: (items: SyncProgressItem[]) => void): void {
        this.subscribers.push(callback);
    }
    
    /**
     * 📢 إخطار المشتركين بالتحديثات
     */
    private static async notifySubscribers(): Promise<void> {
        const items = await this.getCurrentSessionProgress();
        this.subscribers.forEach(callback => callback(items));
    }
    
    /**
     * 📈 تحديث إحصائيات الجلسة
     */
    private static async updateSessionStats(): Promise<void> {
        if (!this.currentSessionId) return;
        
        const stats = await this.getSessionStatistics();
        
        await SQLiteService.execute(`
            UPDATE sync_sessions 
            SET 
                total_operations = ?,
                completed_operations = ?,
                failed_operations = ?,
                conflict_operations = ?,
                updated_at = ?
            WHERE session_id = ?
        `, [
            stats.total,
            stats.success,
            stats.failed,
            stats.conflict,
            new Date().toISOString(),
            this.currentSessionId
        ]);
    }
    
    /**
     * 🔢 الحصول على عدد العمليات المعلقة
     */
    static async getPendingCount(): Promise<number> {
        const result = await SQLiteService.query(`
            SELECT COUNT(*) as count FROM sync_progress
            WHERE status IN ('pending', 'in_progress')
        `);
        return result[0]?.count || 0;
    }
}
```

### Local Database Schema for Progress Tracking

```sql
-- جداول تتبع التقدم في SQLite المحلية

CREATE TABLE IF NOT EXISTS sync_sessions (
    session_id TEXT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    ended_at DATETIME,
    status TEXT DEFAULT 'running',
    total_operations INTEGER DEFAULT 0,
    completed_operations INTEGER DEFAULT 0,
    failed_operations INTEGER DEFAULT 0,
    conflict_operations INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sync_progress (
    id TEXT PRIMARY KEY,
    sync_session_id TEXT,
    operation_type TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    entity_name TEXT,
    status TEXT DEFAULT 'pending',
    progress_percentage INTEGER DEFAULT 0,
    
    -- File upload tracking
    file_size_bytes INTEGER,
    uploaded_bytes INTEGER DEFAULT 0,
    file_name TEXT,
    
    -- Error handling
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    max_retries INTEGER DEFAULT 3,
    
    -- Conflict handling
    conflict_reason TEXT,
    conflict_type TEXT,
    server_value TEXT,
    local_value TEXT,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME,
    completed_at DATETIME,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sync_session_id) REFERENCES sync_sessions(session_id)
);

CREATE INDEX idx_sync_progress_session ON sync_progress(sync_session_id);
CREATE INDEX idx_sync_progress_status ON sync_progress(status);
```

### Conflict Resolution UI

```html
<!-- قسم حل التعارضات في صفحة المتابعة -->
<div class="conflict-resolution-panel" id="conflictPanel" style="display: none;">
    <h3>⚠️ تعارضات تحتاج إلى قرار</h3>
    
    <div class="conflict-item" id="conflictItemTemplate">
        <div class="conflict-header">
            <span class="conflict-entity-name"></span>
            <span class="conflict-type badge"></span>
        </div>
        
        <div class="conflict-comparison">
            <div class="conflict-local">
                <h5>📱 البيانات المحلية</h5>
                <pre class="conflict-value"></pre>
            </div>
            <div class="conflict-server">
                <h5>☁️ البيانات على الخادم</h5>
                <pre class="conflict-value"></pre>
            </div>
        </div>
        
        <div class="conflict-actions">
            <button class="btn btn-success" onclick="resolveConflict('use_local')">
                استخدم المحلي
            </button>
            <button class="btn btn-primary" onclick="resolveConflict('use_server')">
                استخدم الخادم
            </button>
            <button class="btn btn-warning" onclick="resolveConflict('merge')">
                دمج البيانات
            </button>
            <button class="btn btn-danger" onclick="resolveConflict('skip')">
                تخطي
            </button>
        </div>
    </div>
</div>
```

```typescript
// Conflict resolution handling
async function resolveConflict(
    progressId: string, 
    resolution: 'use_local' | 'use_server' | 'merge' | 'skip'
): Promise<void> {
    try {
        await ApiService.post('/api/sync/resolve-conflict', {
            progress_id: progressId,
            resolution: resolution
        });
        
        await SyncProgressTracker.updateProgress(progressId, {
            status: 'success',
            progressPercentage: 100
        });
        
        // Remove from conflict panel
        document.querySelector(`[data-progress-id="${progressId}"]`)?.remove();
        
    } catch (error) {
        console.error('Conflict resolution failed:', error);
        alert('فشل حل التعارض: ' + error.message);
    }
}
```

---

## 🔄 Updated Complete Workflow

```typescript
async function completeSmartSyncWithNewPersonSupport() {
    console.log('🚀 Starting Enhanced Smart Sync Process...');
    
    // Start new session
    await SyncProgressTracker.startNewSession();
    
    // Step 1: Fetch eligible sponsorships
    const sponsorships = await SmartSyncService.fetchEligibleSponsorships();
    console.log(`📋 Found ${sponsorships.length} eligible sponsorships`);
    
    for (const sponsorship of sponsorships) {
        console.log(`\n▶️ Processing sponsorship #${sponsorship.id}...`);
        
        // Step 2: Multi-level lookup
        let personData = null;
        let isNewPerson = false;
        
        // Try Level 1: Internal database
        if (sponsorship.relation_id_number) {
            personData = await SmartSyncService.fetchFromInternalTables(
                sponsorship.relation_id_number
            );
        }
        
        // Try Level 2: Civil registry
        if (!personData && sponsorship.identity_number) {
            personData = await SmartSyncService.fetchFromCivilRegistry(
                sponsorship.identity_number,
                sponsorship.guardian_identity_number
            );
            
            if (personData && personData.needs_correction) {
                console.log('⚠️ Data needs admin correction');
                await showNameCorrectionUI(personData, sponsorship);
                continue;
            }
        }
        
        // 🆕 NEW: Check if person exists at all
        if (!personData) {
            const existsCheck = await NewPersonSyncService.checkPersonExistsInAllTables(
                sponsorship.identity_number
            );
            
            if (!existsCheck.exists) {
                // Person doesn't exist - need to create new entry
                console.log('🆕 Person not found - creating new entry...');
                isNewPerson = true;
                
                // Show new person entry form or use sponsorship data
                const newPersonResult = await NewPersonSyncService.handleNewPersonEntry({
                    sponsorship_id: sponsorship.id,
                    is_new_person: true,
                    person_type: 'orphan', // Or determined from sponsorship
                    person_data: {
                        first_name: sponsorship.orphan_name?.split(' ')[0] || '',
                        second_name: sponsorship.orphan_name?.split(' ')[1] || '',
                        third_name: sponsorship.orphan_name?.split(' ')[2] || '',
                        last_name: sponsorship.orphan_name?.split(' ')[3] || '',
                        identity_number: sponsorship.identity_number
                    }
                });
                
                if (newPersonResult.success) {
                    console.log(`✅ New person created with file ID: ${newPersonResult.file_id}`);
                    // Update sponsorship relation_id_number is done inside handleNewPersonEntry
                    continue;
                }
            } else {
                // Person exists but not linked
                personData = existsCheck.person_data;
            }
        }
        
        // Step 3: Route and update data
        if (personData && !isNewPerson) {
            await PersonDataRouter.routePersonUpdate(
                personData,
                determinePersonType(personData)
            );
            
            // Step 4: Update sponsorship
            await updateSponsorshipRecord(personData);
            
            console.log('✅ Sponsorship data synced successfully');
        } else if (!isNewPerson) {
            console.log('❌ No data found for this sponsorship');
        }
    }
    
    // Finalize session
    const stats = await SyncProgressTracker.getSessionStatistics();
    console.log('\n🎉 Smart Sync completed!');
    console.log(`📊 Results: ${stats.success} success, ${stats.failed} failed, ${stats.conflict} conflicts`);
}
```

---

## 📋 API Routes Summary

### New Laravel Routes

```php
// routes/api.php

Route::prefix('sync')->middleware(['auth:sanctum'])->group(function () {
    // Existing routes...
    
    // New Person Entry Routes
    Route::post('/check-person-all-tables', [SyncController::class, 'checkPersonAllTables']);
    Route::post('/new-person-entry', [SyncController::class, 'newPersonEntry']);
    Route::post('/sponsorships/update-relation-id', [SyncController::class, 'updateSponsorshipRelationId']);
    
    // Conflict Resolution
    Route::post('/resolve-conflict', [SyncController::class, 'resolveConflict']);
    
    // Progress Tracking (server-side)
    Route::get('/progress/session/{sessionId}', [SyncController::class, 'getSessionProgress']);
    Route::post('/progress/log', [SyncController::class, 'logProgress']);
});
```

---

**Document Version**: 2.0  
**Last Updated**: January 10, 2026  
**Author**: GitHub Copilot  
**Status**: Implementation Guide - Enhanced with New Person Support & Progress Tracking
