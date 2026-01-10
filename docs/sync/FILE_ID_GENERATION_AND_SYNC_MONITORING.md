# File ID Generation & Sync Monitoring System
## AlHayah Orphans - Advanced Features

---

## 📋 Table of Contents
1. [Unique File ID Generation System](#unique-file-id-generation-system)
2. [Handshake Mechanism](#handshake-mechanism)
3. [Sync Monitoring Dashboard (PWA)](#sync-monitoring-dashboard-pwa)
4. [Progress Tracking Implementation](#progress-tracking-implementation)
5. [Laravel Implementation](#laravel-implementation)

---

## 🔑 Unique File ID Generation System

### Overview
عندما يكون الشخص **غير موجود** في الجداول المركزية (`data`, `re_people`, `dead_people`)، يجب توليد رقم ملف فريد عبر Laravel باستخدام خوارزمية مركزية.

### File ID Format

```
Pattern: [PREFIX][YEAR][SEQUENCE]

Examples:
- Guardian: G2026000001, G2026000002, ...
- Orphan:   O2026000001, O2026000002, ...
- Deceased: D2026000001, D2026000002, ...
```

**Components:**
- **Prefix**: G (Guardian), O (Orphan), D (Deceased)
- **Year**: Current year (4 digits)
- **Sequence**: 6-digit auto-increment per year

---

## 🤝 Handshake Mechanism

### Workflow

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  Mobile App     │         │  Laravel API     │         │  MySQL Database │
└────────┬────────┘         └────────┬─────────┘         └────────┬────────┘
         │                           │                            │
         │ 1. Check person exists    │                            │
         │─────────────────────────>│                            │
         │                           │ 2. Search in tables        │
         │                           │──────────────────────────>│
         │                           │<──────────────────────────│
         │<─────────────────────────│  Person not found          │
         │                           │                            │
         │ 3. Request new file ID    │                            │
         │   + handshake_token       │                            │
         │─────────────────────────>│                            │
         │                           │ 4. Generate file ID        │
         │                           │    using central algorithm │
         │                           │──────────────────────────>│
         │                           │   Save to file_id_registry │
         │                           │<──────────────────────────│
         │<─────────────────────────│  Return file_id            │
         │  + table_name             │                            │
         │  + handshake_token        │                            │
         │                           │                            │
         │ 5. Insert person data     │                            │
         │    with generated file_id │                            │
         │─────────────────────────>│                            │
         │                           │──────────────────────────>│
         │                           │   Verify handshake_token   │
         │                           │   Mark registry as 'active'│
         │<─────────────────────────│                            │
         │  Success                  │                            │
         │                           │                            │
```

### Database Schema

#### file_id_registry Table (NEW)

```sql
CREATE TABLE file_id_registry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id VARCHAR(20) NOT NULL UNIQUE,
    table_name ENUM('data', 're_people', 'dead_people') NOT NULL,
    person_type ENUM('guardian', 'orphan', 'deceased') NOT NULL,
    identity_number VARCHAR(50),
    person_name VARCHAR(255),
    handshake_token VARCHAR(255) NOT NULL,
    device_id VARCHAR(100),
    status ENUM('reserved', 'active', 'cancelled') DEFAULT 'reserved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    
    INDEX idx_file_id (file_id),
    INDEX idx_identity (identity_number),
    INDEX idx_status (status),
    INDEX idx_handshake (handshake_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose**: 
- حفظ سجل لجميع أرقام الملفات المُولدة
- ربط file_id بـ handshake_token للتحقق
- تتبع حالة رقم الملف (محجوز، نشط، ملغى)

---

## 💻 TypeScript Implementation

### FileIDGeneratorService

```typescript
// services/file-id-generator.service.ts

interface FileIDRequest {
    person_type: 'guardian' | 'orphan' | 'deceased';
    identity_number: string;
    person_name: string;
}

interface FileIDResponse {
    success: boolean;
    file_id: string;
    table_name: string;
    handshake_token: string;
    created_at: string;
}

class FileIDGeneratorService {
    /**
     * 🔍 التحقق من وجود الشخص في قاعدة البيانات المركزية
     */
    static async checkPersonExists(
        personType: 'guardian' | 'orphan' | 'deceased',
        identityNumber: string
    ): Promise<{ exists: boolean; file_id?: string }> {
        try {
            const response = await ApiService.post('/api/sync/check-person-exists', {
                person_type: personType,
                identity_number: identityNumber
            });
            
            return {
                exists: response.exists,
                file_id: response.file_id
            };
        } catch (error) {
            console.error('Error checking person existence:', error);
            throw error;
        }
    }
    
    /**
     * 🆕 طلب توليد رقم ملف فريد جديد
     */
    static async requestNewFileID(request: FileIDRequest): Promise<FileIDResponse> {
        try {
            // Step 1: Generate handshake token for verification
            const deviceId = await AuthService.getDeviceId();
            const timestamp = new Date().toISOString();
            const handshakeToken = CryptoJS.SHA256(
                request.identity_number + 
                request.person_name + 
                deviceId + 
                timestamp
            ).toString();
            
            console.log('📤 Requesting new file ID from server...');
            
            // Step 2: Send request to Laravel
            const response = await ApiService.post('/api/sync/generate-file-id', {
                person_type: request.person_type,
                identity_number: request.identity_number,
                person_name: request.person_name,
                handshake_token: handshakeToken,
                device_id: deviceId,
                requested_at: timestamp
            });
            
            if (!response.success) {
                throw new Error(response.message || 'File ID generation failed');
            }
            
            console.log(`✅ File ID generated: ${response.file_id}`);
            
            // Step 3: Save locally for offline reference
            await this.saveGeneratedFileID(response);
            
            return response;
            
        } catch (error) {
            console.error('❌ File ID generation failed:', error);
            throw error;
        }
    }
    
    /**
     * 💾 حفظ معلومات رقم الملف المُولد محلياً
     */
    private static async saveGeneratedFileID(response: FileIDResponse) {
        await SQLiteService.execute(`
            INSERT INTO generated_file_ids_local 
            (file_id, table_name, handshake_token, generated_at, created_at)
            VALUES (?, ?, ?, ?, ?)
        `, [
            response.file_id,
            response.table_name,
            response.handshake_token,
            response.created_at,
            new Date().toISOString()
        ]);
        
        console.log('💾 File ID saved locally');
    }
    
    /**
     * 🔐 ضمان وجود رقم ملف للشخص (توليد إذا لزم الأمر)
     */
    static async ensureFileIDExists(
        personType: 'guardian' | 'orphan' | 'deceased',
        identityNumber: string,
        personName: string
    ): Promise<string> {
        // Step 1: Check if person exists
        const existingPerson = await this.checkPersonExists(personType, identityNumber);
        
        if (existingPerson.exists) {
            console.log(`✅ Person exists with file ID: ${existingPerson.file_id}`);
            return existingPerson.file_id!;
        }
        
        // Step 2: Person doesn't exist - generate new file ID
        console.log('⚠️ Person not found in central database');
        console.log('🔄 Generating new file ID...');
        
        const newFileIDResponse = await this.requestNewFileID({
            person_type: personType,
            identity_number: identityNumber,
            person_name: personName
        });
        
        return newFileIDResponse.file_id;
    }
    
    /**
     * ✅ تفعيل رقم الملف بعد إدخال البيانات بنجاح
     */
    static async activateFileID(fileId: string, handshakeToken: string) {
        try {
            await ApiService.post('/api/sync/activate-file-id', {
                file_id: fileId,
                handshake_token: handshakeToken
            });
            
            console.log(`✅ File ID ${fileId} activated successfully`);
        } catch (error) {
            console.error('Failed to activate file ID:', error);
        }
    }
}
```

### Local Database Schema

```sql
-- في قاعدة البيانات المحلية (SQLite)
CREATE TABLE IF NOT EXISTS generated_file_ids_local (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id TEXT NOT NULL UNIQUE,
    table_name TEXT NOT NULL,
    handshake_token TEXT NOT NULL,
    generated_at DATETIME,
    synced BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🎯 Laravel Implementation

### SyncController - New Endpoints

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

class SyncController extends Controller
{
    /**
     * POST /api/sync/check-person-exists
     * التحقق من وجود الشخص في قاعدة البيانات المركزية
     */
    public function checkPersonExists(Request $request)
    {
        $request->validate([
            'person_type' => 'required|in:guardian,orphan,deceased',
            'identity_number' => 'required|string'
        ]);
        
        $fileID = null;
        $exists = false;
        $personData = null;
        
        switch ($request->person_type) {
            case 'guardian':
                $record = Data::where('data_id_number', $request->identity_number)->first();
                if ($record) {
                    $fileID = $record->file_id_number;
                    $exists = true;
                    $personData = $record;
                }
                break;
                
            case 'orphan':
                $record = RePeople::where('person_id', $request->identity_number)->first();
                if ($record) {
                    $fileID = $record->registration_id;
                    $exists = true;
                    $personData = $record;
                }
                break;
                
            case 'deceased':
                $record = DeadPepole::where('father_id', $request->identity_number)
                    ->orWhere('mother_id', $request->identity_number)
                    ->first();
                if ($record) {
                    $fileID = $record->re_file_id;
                    $exists = true;
                    $personData = $record;
                }
                break;
        }
        
        return response()->json([
            'success' => true,
            'exists' => $exists,
            'file_id' => $fileID,
            'person_type' => $request->person_type,
            'person_data' => $personData
        ]);
    }
    
    /**
     * POST /api/sync/generate-file-id
     * توليد رقم ملف فريد جديد
     */
    public function generateFileID(Request $request)
    {
        $request->validate([
            'person_type' => 'required|in:guardian,orphan,deceased',
            'identity_number' => 'required|string',
            'person_name' => 'required|string',
            'handshake_token' => 'required|string',
            'device_id' => 'required|string'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Step 1: التحقق من عدم وجود الشخص مسبقاً
            $existingCheck = $this->checkPersonExists(new Request([
                'person_type' => $request->person_type,
                'identity_number' => $request->identity_number
            ]));
            
            if ($existingCheck->getData()->exists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Person already exists in database',
                    'existing_file_id' => $existingCheck->getData()->file_id
                ], 409);
            }
            
            // Step 2: توليد رقم الملف باستخدام الخوارزمية المركزية
            $fileID = $this->generateUniqueFileNumber($request->person_type);
            
            // Step 3: تحديد اسم الجدول المناسب
            $tableName = match($request->person_type) {
                'guardian' => 'data',
                'orphan' => 're_people',
                'deceased' => 'dead_people'
            };
            
            // Step 4: حفظ سجل في file_id_registry
            DB::table('file_id_registry')->insert([
                'file_id' => $fileID,
                'table_name' => $tableName,
                'person_type' => $request->person_type,
                'identity_number' => $request->identity_number,
                'person_name' => $request->person_name,
                'handshake_token' => $request->handshake_token,
                'device_id' => $request->device_id,
                'status' => 'reserved',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            Log::info('File ID generated successfully', [
                'file_id' => $fileID,
                'person_type' => $request->person_type,
                'identity' => $request->identity_number
            ]);
            
            return response()->json([
                'success' => true,
                'file_id' => $fileID,
                'table_name' => $tableName,
                'handshake_token' => $request->handshake_token,
                'created_at' => now()->toISOString(),
                'message' => 'File ID generated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('File ID generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'File ID generation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * الخوارزمية المركزية لتوليد أرقام الملفات الفريدة
     */
    private function generateUniqueFileNumber($personType)
    {
        // نمط رقم الملف: [PREFIX][YEAR][SEQUENCE]
        // مثال: G2026000001, O2026000001, D2026000001
        
        $prefix = match($personType) {
            'guardian' => 'G',
            'orphan' => 'O',
            'deceased' => 'D'
        };
        
        $year = date('Y');
        
        // الحصول على آخر رقم تسلسلي للسنة الحالية
        $lastNumber = DB::table('file_id_registry')
            ->where('person_type', $personType)
            ->where('file_id', 'LIKE', $prefix . $year . '%')
            ->orderBy('file_id', 'desc')
            ->value('file_id');
        
        if ($lastNumber) {
            // استخراج الرقم التسلسلي وزيادته
            $sequence = intval(substr($lastNumber, -6)) + 1;
        } else {
            // أول رقم للسنة
            $sequence = 1;
        }
        
        // تنسيق الرقم: PREFIX + YEAR + 6-digit sequence
        $fileID = $prefix . $year . str_pad($sequence, 6, '0', STR_PAD_LEFT);
        
        return $fileID;
    }
    
    /**
     * POST /api/sync/activate-file-id
     * تفعيل رقم الملف بعد إدخال البيانات بنجاح
     */
    public function activateFileID(Request $request)
    {
        $request->validate([
            'file_id' => 'required|string',
            'handshake_token' => 'required|string'
        ]);
        
        try {
            // التحقق من صحة handshake_token
            $registry = DB::table('file_id_registry')
                ->where('file_id', $request->file_id)
                ->where('handshake_token', $request->handshake_token)
                ->first();
            
            if (!$registry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID or handshake token'
                ], 404);
            }
            
            // تحديث الحالة إلى 'active'
            DB::table('file_id_registry')
                ->where('file_id', $request->file_id)
                ->update([
                    'status' => 'active',
                    'activated_at' => now(),
                    'updated_at' => now()
                ]);
            
            return response()->json([
                'success' => true,
                'message' => 'File ID activated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('File ID activation failed', [
                'error' => $e->getMessage(),
                'file_id' => $request->file_id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Activation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

### Migration for file_id_registry

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFileIdRegistryTable extends Migration
{
    public function up()
    {
        Schema::create('file_id_registry', function (Blueprint $table) {
            $table->id();
            $table->string('file_id', 20)->unique();
            $table->enum('table_name', ['data', 're_people', 'dead_people']);
            $table->enum('person_type', ['guardian', 'orphan', 'deceased']);
            $table->string('identity_number', 50)->nullable();
            $table->string('person_name')->nullable();
            $table->string('handshake_token')->index();
            $table->string('device_id', 100)->nullable();
            $table->enum('status', ['reserved', 'active', 'cancelled'])->default('reserved');
            $table->timestamps();
            $table->timestamp('activated_at')->nullable();
            
            $table->index('file_id');
            $table->index('identity_number');
            $table->index('status');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('file_id_registry');
    }
}
```

---

## 📊 Sync Monitoring Dashboard (PWA)

### صفحة متابعة المزامنة

```html
<!-- pwa/sync-monitor.html -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>متابعة المزامنة - AlHayah Orphans</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/sync-monitor.css">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="sync-header">
            <div class="row align-items-center">
                <div class="col-6">
                    <h2>🔄 متابعة المزامنة</h2>
                    <p class="text-muted">مراقبة عمليات رفع البيانات والملفات</p>
                </div>
                <div class="col-6 text-left">
                    <button class="btn btn-primary" id="btnStartSync">
                        ▶️ بدء المزامنة
                    </button>
                    <button class="btn btn-secondary" id="btnRefresh">
                        🔃 تحديث
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mt-3">
            <div class="col-md-2">
                <div class="stat-card total">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">إجمالي العمليات</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card pending">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value" id="statPending">0</div>
                    <div class="stat-label">قيد الانتظار</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card in-progress">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-value" id="statInProgress">0</div>
                    <div class="stat-label">قيد التنفيذ</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card success">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value" id="statSuccess">0</div>
                    <div class="stat-label">نجح</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card failed">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value" id="statFailed">0</div>
                    <div class="stat-label">فشل</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card conflict">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-value" id="statConflict">0</div>
                    <div class="stat-label">تعارض</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mt-4">
            <div class="col-md-3">
                <select class="form-control" id="filterOperationType">
                    <option value="">كل أنواع العمليات</option>
                    <option value="data_upload">رفع البيانات</option>
                    <option value="data_download">تنزيل البيانات</option>
                    <option value="media_upload">رفع الملفات</option>
                    <option value="media_download">تنزيل الملفات</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-control" id="filterStatus">
                    <option value="">كل الحالات</option>
                    <option value="pending">قيد الانتظار</option>
                    <option value="in_progress">قيد التنفيذ</option>
                    <option value="success">نجح</option>
                    <option value="failed">فشل</option>
                    <option value="conflict">تعارض</option>
                </select>
            </div>
            <div class="col-md-3">
                <input 
                    type="text" 
                    class="form-control" 
                    id="searchEntityName"
                    placeholder="🔍 بحث بالاسم..."
                />
            </div>
            <div class="col-md-3">
                <button class="btn btn-danger btn-block" id="btnClearFilters">
                    🗑️ مسح الفلاتر
                </button>
            </div>
        </div>

        <!-- Progress List -->
        <div class="progress-list mt-4" id="progressList">
            <!-- Progress items will be dynamically inserted here -->
        </div>

        <!-- Session History -->
        <div class="mt-5">
            <h3>📜 سجل جلسات المزامنة السابقة</h3>
            <div id="sessionHistory" class="session-history">
                <!-- Previous sessions will be listed here -->
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/sync-monitor.js"></script>
</body>
</html>
```

### CSS Styling

```css
/* pwa/css/sync-monitor.css */

.sync-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 10px;
    color: white;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 2.5em;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9em;
    color: #666;
}

/* Color themes for different statuses */
.stat-card.total { border-top: 4px solid #667eea; }
.stat-card.pending { border-top: 4px solid #ffc107; }
.stat-card.in-progress { border-top: 4px solid #17a2b8; }
.stat-card.success { border-top: 4px solid #28a745; }
.stat-card.failed { border-top: 4px solid #dc3545; }
.stat-card.conflict { border-top: 4px solid #ff9800; }

/* Progress items */
.progress-item {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border-right: 5px solid #ccc;
    transition: all 0.3s;
}

.progress-item:hover {
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.progress-item.pending { border-right-color: #ffc107; }
.progress-item.in_progress { border-right-color: #17a2b8; }
.progress-item.success { border-right-color: #28a745; }
.progress-item.failed { border-right-color: #dc3545; }
.progress-item.conflict { border-right-color: #ff9800; }

.progress-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.progress-item-title {
    font-weight: bold;
    font-size: 1.1em;
}

.progress-item-status {
    padding: 5px 15px;
    border-radius: 20px;
    color: white;
    font-size: 0.9em;
}

.status-pending { background-color: #ffc107; }
.status-in_progress { background-color: #17a2b8; }
.status-success { background-color: #28a745; }
.status-failed { background-color: #dc3545; }
.status-conflict { background-color: #ff9800; }

.progress-bar-container {
    width: 100%;
    height: 8px;
    background-color: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transition: width 0.3s ease;
}

.progress-item-details {
    font-size: 0.9em;
    color: #666;
    margin-top: 10px;
}

.error-message {
    background-color: #fee;
    color: #c00;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-size: 0.9em;
}

.conflict-message {
    background-color: #fff3cd;
    color: #856404;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-size: 0.9em;
}

.session-history-item {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.session-history-item:hover {
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}
```

### JavaScript Implementation

```javascript
// pwa/js/sync-monitor.js

class SyncMonitor {
    constructor() {
        this.currentSessionId = null;
        this.progressItems = [];
        this.filters = {
            operationType: '',
            status: '',
            searchText: ''
        };
        
        this.initialize();
    }
    
    async initialize() {
        console.log('🚀 Initializing Sync Monitor...');
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Load current session
        await this.loadCurrentSession();
        
        // Load history
        await this.loadSessionHistory();
        
        // Subscribe to real-time updates
        this.subscribeToUpdates();
        
        console.log('✅ Sync Monitor initialized');
    }
    
    setupEventListeners() {
        // Start sync button
        document.getElementById('btnStartSync').addEventListener('click', () => {
            this.startSync();
        });
        
        // Refresh button
        document.getElementById('btnRefresh').addEventListener('click', () => {
            this.refreshData();
        });
        
        // Filters
        document.getElementById('filterOperationType').addEventListener('change', (e) => {
            this.filters.operationType = e.target.value;
            this.applyFilters();
        });
        
        document.getElementById('filterStatus').addEventListener('change', (e) => {
            this.filters.status = e.target.value;
            this.applyFilters();
        });
        
        document.getElementById('searchEntityName').addEventListener('input', (e) => {
            this.filters.searchText = e.target.value.toLowerCase();
            this.applyFilters();
        });
        
        document.getElementById('btnClearFilters').addEventListener('click', () => {
            this.clearFilters();
        });
    }
    
    async loadCurrentSession() {
        try {
            const stats = await SyncProgressTracker.getSessionStatistics();
            const items = await SyncProgressTracker.getCurrentSessionProgress();
            
            this.progressItems = items;
            this.updateStatistics(stats);
            this.renderProgressItems(items);
            
        } catch (error) {
            console.error('Failed to load current session:', error);
        }
    }
    
    updateStatistics(stats) {
        document.getElementById('statTotal').textContent = stats.total || 0;
        document.getElementById('statPending').textContent = stats.pending || 0;
        document.getElementById('statInProgress').textContent = stats.in_progress || 0;
        document.getElementById('statSuccess').textContent = stats.success || 0;
        document.getElementById('statFailed').textContent = stats.failed || 0;
        document.getElementById('statConflict').textContent = stats.conflict || 0;
    }
    
    renderProgressItems(items) {
        const container = document.getElementById('progressList');
        container.innerHTML = '';
        
        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info text-center">
                    <h4>لا توجد عمليات مزامنة حالية</h4>
                    <p>اضغط على "بدء المزامنة" لبدء عملية جديدة</p>
                </div>
            `;
            return;
        }
        
        items.forEach(item => {
            const itemHtml = this.createProgressItemHTML(item);
            container.innerHTML += itemHtml;
        });
    }
    
    createProgressItemHTML(item) {
        const statusText = this.getStatusText(item.status);
        const operationText = this.getOperationText(item.operationType);
        const progressPercentage = item.progressPercentage || 0;
        
        let detailsHTML = '';
        
        // File upload progress
        if (item.fileSizeBytes && item.operationType.includes('media')) {
            const uploadedMB = (item.uploadedBytes / 1024 / 1024).toFixed(2);
            const totalMB = (item.fileSizeBytes / 1024 / 1024).toFixed(2);
            detailsHTML += `
                <div class="progress-item-details">
                    📦 الحجم: ${uploadedMB} / ${totalMB} MB
                </div>
            `;
        }
        
        // Error message
        if (item.errorMessage && item.status === 'failed') {
            detailsHTML += `
                <div class="error-message">
                    ❌ خطأ: ${item.errorMessage}
                    ${item.retryCount > 0 ? `<br>عدد المحاولات: ${item.retryCount}` : ''}
                </div>
            `;
        }
        
        // Conflict message
        if (item.conflictReason && item.status === 'conflict') {
            detailsHTML += `
                <div class="conflict-message">
                    ⚠️ تعارض: ${item.conflictReason}
                </div>
            `;
        }
        
        return `
            <div class="progress-item ${item.status}" data-id="${item.id}">
                <div class="progress-item-header">
                    <div class="progress-item-title">
                        ${this.getOperationIcon(item.operationType)} ${item.entityName}
                    </div>
                    <div class="progress-item-status status-${item.status}">
                        ${statusText}
                    </div>
                </div>
                
                <div class="progress-item-type">
                    <small>${operationText} - ${item.entityType}</small>
                </div>
                
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: ${progressPercentage}%"></div>
                </div>
                
                ${detailsHTML}
                
                <div class="progress-item-details">
                    <small>
                        ${item.startedAt ? `⏱️ بدأت: ${this.formatDateTime(item.startedAt)}` : ''}
                        ${item.completedAt ? ` | ✅ انتهت: ${this.formatDateTime(item.completedAt)}` : ''}
                    </small>
                </div>
            </div>
        `;
    }
    
    getStatusText(status) {
        const statusMap = {
            'pending': 'قيد الانتظار',
            'in_progress': 'قيد التنفيذ',
            'success': 'نجح',
            'failed': 'فشل',
            'conflict': 'تعارض'
        };
        return statusMap[status] || status;
    }
    
    getOperationText(operationType) {
        const operationMap = {
            'data_upload': 'رفع البيانات',
            'data_download': 'تنزيل البيانات',
            'media_upload': 'رفع الملفات',
            'media_download': 'تنزيل الملفات'
        };
        return operationMap[operationType] || operationType;
    }
    
    getOperationIcon(operationType) {
        const iconMap = {
            'data_upload': '📤',
            'data_download': '📥',
            'media_upload': '📷',
            'media_download': '🖼️'
        };
        return iconMap[operationType] || '📋';
    }
    
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('ar-SA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    subscribeToUpdates() {
        // Subscribe to real-time progress updates
        SyncProgressTracker.subscribe((items) => {
            this.progressItems = items;
            this.applyFilters();
            
            // Update statistics
            this.calculateAndUpdateStats();
        });
    }
    
    calculateAndUpdateStats() {
        const stats = {
            total: this.progressItems.length,
            pending: this.progressItems.filter(i => i.status === 'pending').length,
            in_progress: this.progressItems.filter(i => i.status === 'in_progress').length,
            success: this.progressItems.filter(i => i.status === 'success').length,
            failed: this.progressItems.filter(i => i.status === 'failed').length,
            conflict: this.progressItems.filter(i => i.status === 'conflict').length
        };
        
        this.updateStatistics(stats);
    }
    
    applyFilters() {
        let filteredItems = [...this.progressItems];
        
        // Filter by operation type
        if (this.filters.operationType) {
            filteredItems = filteredItems.filter(item => 
                item.operationType === this.filters.operationType
            );
        }
        
        // Filter by status
        if (this.filters.status) {
            filteredItems = filteredItems.filter(item => 
                item.status === this.filters.status
            );
        }
        
        // Filter by search text
        if (this.filters.searchText) {
            filteredItems = filteredItems.filter(item => 
                item.entityName.toLowerCase().includes(this.filters.searchText)
            );
        }
        
        this.renderProgressItems(filteredItems);
    }
    
    clearFilters() {
        this.filters = {
            operationType: '',
            status: '',
            searchText: ''
        };
        
        document.getElementById('filterOperationType').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('searchEntityName').value = '';
        
        this.applyFilters();
    }
    
    async startSync() {
        try {
            document.getElementById('btnStartSync').disabled = true;
            document.getElementById('btnStartSync').innerHTML = '⏳ جاري المزامنة...';
            
            // Start sync process
            await SmartSyncService.syncEligibleSponsorships();
            
            alert('✅ اكتملت عملية المزامنة بنجاح');
            
        } catch (error) {
            console.error('Sync failed:', error);
            alert('❌ فشلت عملية المزامنة: ' + error.message);
        } finally {
            document.getElementById('btnStartSync').disabled = false;
            document.getElementById('btnStartSync').innerHTML = '▶️ بدء المزامنة';
        }
    }
    
    async refreshData() {
        await this.loadCurrentSession();
        await this.loadSessionHistory();
    }
    
    async loadSessionHistory() {
        try {
            const sessions = await SQLiteService.query(`
                SELECT 
                    sync_session_id,
                    COUNT(*) as total_operations,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    MIN(created_at) as started_at,
                    MAX(updated_at) as ended_at
                FROM sync_progress
                WHERE sync_session_id IS NOT NULL
                GROUP BY sync_session_id
                ORDER BY started_at DESC
                LIMIT 10
            `);
            
            this.renderSessionHistory(sessions);
            
        } catch (error) {
            console.error('Failed to load session history:', error);
        }
    }
    
    renderSessionHistory(sessions) {
        const container = document.getElementById('sessionHistory');
        container.innerHTML = '';
        
        if (!sessions || sessions.length === 0) {
            container.innerHTML = `
                <div class="alert alert-secondary">
                    لا يوجد سجل لجلسات سابقة
                </div>
            `;
            return;
        }
        
        sessions.forEach(session => {
            const successRate = session.total_operations > 0 
                ? ((session.successful / session.total_operations) * 100).toFixed(1)
                : 0;
            
            const sessionHtml = `
                <div class="session-history-item">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>📅 ${this.formatDateTime(session.started_at)}</strong>
                        </div>
                        <div class="col-md-6 text-left">
                            <span class="badge badge-info">${session.total_operations} عملية</span>
                            <span class="badge badge-success">${session.successful} نجح</span>
                            <span class="badge badge-danger">${session.failed} فشل</span>
                            <span class="badge badge-primary">${successRate}%</span>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML += sessionHtml;
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.syncMonitor = new SyncMonitor();
});
```

---

## 🎨 PWA Navigation Integration

### إضافة زر جانبي في الصفحة الرئيسية

```html
<!-- في pwa/index.html أو pwa/data.html -->
<div class="sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="data.html">
                📝 إدخال البيانات
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="photography.html">
                📷 التصوير
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="sync-monitor.html">
                🔄 متابعة المزامنة
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="settings.html">
                ⚙️ الإعدادات
            </a>
        </li>
    </ul>
</div>
```

---

## 📝 Complete Workflow Example

### مثال شامل: إدخال بيانات شخص جديد

```typescript
async function handleNewPersonEntry() {
    console.log('🆕 Starting new person entry workflow...');
    
    // Step 1: Collect form data
    const formData = {
        person_type: 'orphan',
        identity_number: '987654321',
        orphan_name: 'محمد أحمد علي السيد',
        guardian_name: 'أحمد علي السيد',
        guardian_identity: '123456789',
        // ... other form fields
    };
    
    // Step 2: Check if person exists, generate file ID if needed
    const orphanFileID = await FileIDGeneratorService.ensureFileIDExists(
        'orphan',
        formData.identity_number,
        formData.orphan_name
    );
    
    const guardianFileID = await FileIDGeneratorService.ensureFileIDExists(
        'guardian',
        formData.guardian_identity,
        formData.guardian_name
    );
    
    console.log(`✅ File IDs obtained: Orphan=${orphanFileID}, Guardian=${guardianFileID}`);
    
    // Step 3: Insert data with file IDs
    const insertResponse = await ApiService.post('/api/sync/re-people', {
        registration_id: orphanFileID,
        person_id: formData.identity_number,
        first_name: 'محمد',
        second_name: 'أحمد',
        third_name: 'علي',
        last_name: 'السيد',
        // ... other fields
    });
    
    // Step 4: Activate file ID
    await FileIDGeneratorService.activateFileID(
        orphanFileID,
        insertResponse.handshake_token
    );
    
    console.log('🎉 Person entry completed successfully!');
}
```

---

**Document Version**: 2.0  
**Last Updated**: January 10, 2026  
**Author**: GitHub Copilot  
**Status**: Production Ready
