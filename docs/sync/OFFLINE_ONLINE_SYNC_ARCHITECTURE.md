# Offline-Online Synchronization Architecture Report
## AlHayah Orphans System - Capacitor + Laravel + Google Drive (Rclone)

---

## 📋 Executive Summary

This document provides a comprehensive architecture for implementing a robust, professional-grade offline-online synchronization system for the AlHayah Orphans management application. The system will leverage **Capacitor** for mobile capabilities, **Laravel** backend API, and **Google Drive (Rclone)** for cloud storage.

### Key Requirements:
- ✅ **Full Offline Support**: Work without internet connection
- ✅ **Automatic Synchronization**: Sync when internet is available
- ✅ **Data Persistence**: No data deletion from device
- ✅ **Media Handling**: Photos/Videos never deleted
- ✅ **Multi-Channel Sync**: Separate sync queues for data and media
- ✅ **Smart Data Fetching**: Multi-level lookup (internal DB → civil registry)
- ✅ **Filtered Sync**: Exclude disbursed sponsorships from sync
- ✅ **Admin Corrections**: UI for fixing civil registry names
- ✅ **Auto-Routing**: Classify and route person data to correct tables
- ✅ **Encrypted Storage**: Database and credentials encrypted
- ✅ **Session Management**: 10-day login persistence

### Advanced Sync Flow

```
┌─────────────────────────────────────────────────────────────┐
│  STEP 1: Fetch Eligible Sponsorships                       │
│  • Filter: Exclude "ارسل للصرف" and "تم الصرف" statuses   │
│  • Result: Active sponsorships only                        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  STEP 2: Multi-Level Person Data Lookup                    │
│                                                             │
│  Level 1: relation_id_number in internal tables            │
│  ├─ Search: data, dead_people, re_people                   │
│  └─ If Found: Extract 10 fields + bank info → DONE ✅      │
│                                                             │
│  Level 2: Fallback to Civil Registry                       │
│  ├─ Search: civilregistry.persons by CI_ID_NUM             │
│  ├─ If Found: Mark as "needs_correction"                   │
│  └─ Present to Admin for name verification                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  STEP 3: Admin Name Correction (if needed)                 │
│  • Display: Original civil registry name                   │
│  • Input: Four-field form (first, father, grand, family)   │
│  • Verify: Admin role required                             │
│  • Submit: Corrected quadruple name                        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  STEP 4: Smart Data Routing & Update                       │
│                                                             │
│  IF person_type == "guardian":                              │
│  └─ UPDATE data table (10 fields + bank)                   │
│                                                             │
│  IF person_type == "orphan" OR "family_member":             │
│  └─ UPDATE re_people table (name, ID, gender, birth date)  │
│                                                             │
│  IF person_type == "deceased_father" OR "deceased_mother":  │
│  └─ UPDATE dead_people table (deceased info)               │
│                                                             │
│  ALWAYS: UPDATE guardian_bank_accounts (if provided)        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  STEP 5: Update Sponsorship Record                         │
│  • Link via: relation_id_number + identity_number          │
│  • Update: orphan_name, guardian_name                      │
│  • Update: guardian_identity_number                        │
│  • Status: Maintain current sponsorship_status_id          │
└─────────────────────────────────────────────────────────────┘
```

### Editable Fields (Mobile App Only)

**10 Core Fields + Bank Information:**

1. Orphan Name (4 fields: first, father, grand, family)
2. Orphan Identity Number
3. Gender
4. Birth Date
5. Guardian Name (4 fields - optional)
6. Guardian Identity Number (optional)
7. Phone Number
8. Alternative Phone (optional)
9. Province
10. Detailed Address

**Plus:**
- Bank Name
- IBAN USD
- IBAN Shekel
- Account Number/Phone
- Check Account Status
- Account Owner Identity

---

## 🗺️ System Architecture Roadmap

```
┌─────────────────────────────────────────────────────────────────┐
│                    CAPACITOR MOBILE APP                         │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              LOCAL STORAGE LAYER                          │  │
│  │  • SQLite Database (Data Tables)                          │  │
│  │  • IndexedDB (Metadata & Sync Queue)                      │  │
│  │  • FileSystem (Photos/Videos)                             │  │
│  └────────────────┬──────────────────────────────────────────┘  │
│                   │                                              │
│  ┌────────────────▼──────────────────────────────────────────┐  │
│  │         SYNC ORCHESTRATION ENGINE                         │  │
│  │  ┌──────────────────┐    ┌──────────────────┐            │  │
│  │  │  Data Sync Queue │    │ Media Sync Queue │            │  │
│  │  └──────────────────┘    └──────────────────┘            │  │
│  │  • Network Status Monitor (Capacitor Network)            │  │
│  │  • Priority-based Queue Management                       │  │
│  │  • Conflict Resolution Engine                            │  │
│  │  • Retry Logic with Exponential Backoff                  │  │
│  └────────────────┬──────────────────────────────────────────┘  │
└───────────────────┼──────────────────────────────────────────────┘
                    │
        ┌───────────▼───────────┐
        │  INTERNET CONNECTION  │
        │   Status Monitor      │
        └───────────┬───────────┘
                    │
    ┌───────────────┴───────────────┐
    │                               │
┌───▼────────────┐      ┌──────────▼─────────┐
│ LARAVEL API    │      │  GOOGLE DRIVE      │
│ Backend Server │      │  (via Rclone)      │
│                │      │                    │
│ • REST API     │      │ • Photo Storage    │
│ • Auth (JWT)   │      │ • Video Storage    │
│ • Data Sync    │◄─────┤ • Document Storage │
│ • Validation   │      │ • Metadata Sync    │
└────────┬───────┘      └────────────────────┘
         │
    ┌────▼─────────────────┐
    │   MySQL DATABASE     │
    │  ┌────────────────┐  │
    │  │ dead_people    │  │
    │  │ re_people      │  │
    │  │ data           │  │
    │  │ sponsorships   │  │
    │  │ attachments    │  │
    │  └────────────────┘  │
    └──────────────────────┘
```

---

## 📊 Database Tables Analysis

### Core Sync Tables (Priority: Critical)

#### 1. **data** Table
- **Purpose**: Main orphan/family data
- **Sync Strategy**: Bidirectional with conflict resolution
- **Key Fields**:
  - `id`, `file_id_number` (unique identifier)
  - Personal info: `data_first_name`, `data_father_name`, `data_family_name`
  - Normalized fields: `data_first_name_normalized`, etc.
  - Relationships: `data_relationship`, `data_request_status`
  - Location: `data_province`, `data_city`
  - Contact: `data_phone_number`, `data_alt_phone_number`
- **Sync Trigger**: On create, update, delete
- **Conflict Resolution**: Last-write-wins with timestamp comparison

#### 2. **re_people** Table
- **Purpose**: Registered people/children data
- **Sync Strategy**: Bidirectional
- **Key Fields**:
  - `id`, `registration_id`, `person_id`
  - Personal: `first_name`, `second_name`, `third_name`, `last_name`
  - Status: `sponsorship_status`
  - Health: `person_health_status`
  - Documents: `person_birth_certificate`, `person_photo`
- **Sync Trigger**: On create, update
- **Foreign Keys**: Links to sponsorships

#### 3. **dead_people** Table
- **Purpose**: Deceased parents information
- **Sync Strategy**: Bidirectional
- **Key Fields**:
  - `id`, `re_file_id` (links to data.file_id_number)
  - Father: `father_first_name`, `father_id`, `father_death_date`, `father_death_reason`
  - Mother: `mother_first_name`, `mother_id`, `mother_death_date`, `mother_death_reason`
- **Sync Trigger**: On create, update
- **Related**: Death certificates stored in attachments

#### 4. **sponsorships** Table
- **Purpose**: Sponsorship records
- **Sync Strategy**: Server-authoritative (read-only on mobile)
- **Key Fields**:
  - `id`, `sponsor_id`, `internal_file_number`, `external_file_number`
  - `identity_number`, `orphan_name`, `guardian_name`
  - Duration: `sponsorship_start_date`, `sponsorship_end_date`
  - Status: `sponsorship_status_id`, `sponsorship_type_id`
- **Sync Trigger**: Pull from server only
- **Access**: Read-only offline, updates require server sync

#### 5. **guardian_bank_accounts** Table
- **Purpose**: Guardian bank account information
- **Sync Strategy**: Bidirectional with conflict resolution
- **Key Fields**:
  - `id`, `guardian_registration` (links to data.file_id_number)
  - Bank: `bank_name`, `iban_usd`, `iban_shekel`
  - Account: `account_number_or_related_phone_number`, `check_account`
  - Guardian: `re_id_number`, `re_guardian_name`, `re_phone_number`
  - Owner: `person_owner_identity_number`
- **Sync Trigger**: On create, update
- **Mobile Access**: Full create/edit access for field workers

### Supporting Tables

#### 6. **attachments** Table
- **Purpose**: File metadata tracking
- **Sync Strategy**: Metadata to Laravel, Files to Google Drive
- **Key Fields**:
  - `id`, `person_identity_number`
  - File info: `stored_file_name`, `file_path`, `file_type`
  - Google Drive: `google_drive_id`, `google_drive_url` (from migration analysis)
- **Sync Process**:
  1. Upload file to Google Drive (via Rclone)
  2. Sync metadata to Laravel
  3. Update local record with Drive ID

#### 7. **sync_logs** Table
- **Purpose**: Track all sync operations and history
- **Sync Strategy**: Local logging with periodic server upload
- **Key Fields**:
  - `id`, `device_id`, `user_id`
  - Operation: `table_name`, `record_id`, `operation_type`
  - Status: `sync_status`, `synced_at`, `last_attempt_at`
  - Files: `file_hash`, `file_synced`, `file_verified`
  - Metadata: `error_message`, `retry_count`, `created_at`
- **Purpose**: Track which files/data synced successfully and when

---

## 📝 Mobile App Editable Fields

### Fields Available for Data Entry/Edit in Capacitor App

**The mobile app is limited to entering/editing ONLY the following fields:**

#### Orphan Information (re_people table)
1. **Orphan Name** (`first_name`, `second_name`, `third_name`, `last_name`)
2. **Orphan Identity Number** (`person_id`)
3. **Gender** (`person_gender`)
4. **Birth Date** (`person_birth_date`)

#### Guardian Information (data table)
5. **Guardian Name** (`data_first_name`, `data_father_name`, `data_grand_father_name`, `data_family_name`) - Optional
6. **Guardian Identity Number** (`data_id_number`) - Optional
7. **Phone Number** (`data_phone_number`)
8. **Alternative Phone** (`data_alt_phone_number`) - Optional

#### Location Information
9. **Province** (`data_province`)
10. **Detailed Address** (`data_current_address`)

#### Bank Information (guardian_bank_accounts table)
- **Bank Name** (`bank_name`)
- **IBAN USD** (`iban_usd`)
- **IBAN Shekel** (`iban_shekel`)
- **Account Number/Phone** (`account_number_or_related_phone_number`)
- **Check Account** (`check_account`)
- **Account Owner Identity** (`person_owner_identity_number`)

#### Media Files (attachments)
- **Photos** (via Camera)
- **Videos** (via Camera)
- **Documents** (via File picker)

**⚠️ CRITICAL: All other fields are READ-ONLY on mobile and managed by the server.**

---

## 🔄 Synchronization Channels

### Channel 1: Database Synchronization
**Priority**: High | **Frequency**: Continuous when online

```javascript
// Sync Flow
1. Detect network connection
2. Retrieve pending changes from local queue
3. POST /api/sync/data-batch
4. Server validates and processes
5. Return server timestamp and conflicts
6. Update local database
7. Mark items as synced
```

**API Endpoints Needed**:
```
POST   /api/sync/data              - Sync single data record
POST   /api/sync/data-batch        - Batch sync multiple records
POST   /api/sync/re-people         - Sync re_people records
POST   /api/sync/dead-people       - Sync dead_people records
POST   /api/sync/bank-accounts     - Sync guardian_bank_accounts records
GET    /api/sync/pull              - Pull server changes since timestamp
POST   /api/sync/conflict-resolve  - Handle sync conflicts
POST   /api/sync/logs              - Upload sync logs to server
```

**Payload Structure**:
```json
{
  "table": "data",
  "operation": "create|update|delete",
  "data": {
    "file_id_number": "12345",
    "data_first_name": "أحمد",
    "data_father_name": "محمد",
    "...": "..."
  },
  "client_timestamp": "2026-01-10T10:30:00Z",
  "device_id": "unique-device-identifier",
  "sync_priority": "high|medium|low"
}
```

### Channel 2: Media Synchronization (Google Drive)
**Priority**: Medium | **Frequency**: Background when WiFi available

```javascript
// Media Sync Flow
1. Capture photo/video via Capacitor Camera
2. Save to local filesystem
3. Add to media sync queue with metadata
4. When WiFi detected:
   a. Upload to Google Drive via Rclone API
   b. Get Drive file ID and URL
   c. POST metadata to Laravel /api/attachments
   d. Update local attachment record
   e. Mark as synced (but KEEP local file)
```

**API Endpoints Needed**:
```
POST   /api/attachments/upload     - Upload file metadata after Drive upload
POST   /api/attachments/batch      - Batch upload multiple file metadata
GET    /api/attachments/{id}       - Get attachment details
DELETE /api/attachments/{id}       - Delete file reference (not local file)
```

**Media Upload Strategy**:
- **Images**: Upload immediately when WiFi
- **Videos**: Upload during off-peak hours (configurable)
- **Batch Size**: Max 5 concurrent uploads
- **Retry**: 3 attempts with exponential backoff

### Channel 3: Smart Data Synchronization with Multi-Level Fetching
**Priority**: Critical | **Frequency**: Initial sync and periodic updates

#### Sync Flow Logic

```typescript
// Multi-level data synchronization process
class SmartSyncService {
    
    /**
     * Step 1: Filter sponsorships by status
     * Exclude records with status: "ارسل للصرف" or "تم الصرف"
     */
    async fetchEligibleSponsorships(): Promise<Sponsorship[]> {
        const excludedStatuses = ['ارسل للصرف', 'تم الصرف'];
        
        const response = await ApiService.post('/api/sync/sponsorships-filtered', {
            exclude_statuses: excludedStatuses
        });
        
        return response.data.sponsorships;
    }
    
    /**
     * Step 2: Fetch person data using multi-level lookup
     */
    async fetchPersonData(sponsorship: any) {
        // Level 1: Check relation_id_number in data, dead_people, re_people
        if (sponsorship.relation_id_number) {
            const internalData = await this.fetchFromInternalTables(
                sponsorship.relation_id_number
            );
            
            if (internalData) {
                return await this.extractEditableFields(internalData);
            }
        }
        
        // Level 2: Fallback to Civil Registry lookup
        const civilData = await this.fetchFromCivilRegistry(
            sponsorship.identity_number,
            sponsorship.guardian_identity_number
        );
        
        if (civilData) {
            // Needs admin correction
            return {
                ...civilData,
                needs_correction: true,
                source: 'civil_registry'
            };
        }
        
        return null;
    }
    
    /**
     * Level 1: Search in internal tables (data, dead_people, re_people)
     */
    private async fetchFromInternalTables(relationId: string) {
        const response = await ApiService.get(
            `/api/sync/person-by-relation/${relationId}`
        );
        
        return response.data;
    }
    
    /**
     * Level 2: Search in Civil Registry database
     */
    private async fetchFromCivilRegistry(orphanId: string, guardianId?: string) {
        const response = await ApiService.post('/api/sync/civil-registry-lookup', {
            orphan_identity: orphanId,
            guardian_identity: guardianId
        });
        
        return response.data;
    }
    
    /**
     * Extract only editable fields (10 main fields + bank info)
     */
    private async extractEditableFields(data: any) {
        return {
            // Orphan info
            orphan_first_name: data.first_name,
            orphan_second_name: data.second_name,
            orphan_third_name: data.third_name,
            orphan_last_name: data.last_name,
            orphan_identity: data.person_id,
            orphan_gender: data.person_gender,
            orphan_birth_date: data.person_birth_date,
            
            // Guardian info (optional)
            guardian_first_name: data.data_first_name || null,
            guardian_father_name: data.data_father_name || null,
            guardian_grand_father_name: data.data_grand_father_name || null,
            guardian_family_name: data.data_family_name || null,
            guardian_identity: data.data_id_number || null,
            
            // Contact & location
            phone_number: data.data_phone_number,
            alt_phone_number: data.data_alt_phone_number || null,
            province: data.data_province,
            detailed_address: data.data_current_address,
            
            // Bank info
            bank_accounts: data.bank_accounts || [],
            
            // Metadata
            needs_correction: false,
            source: 'internal_database'
        };
    }
}
```

#### API Endpoints for Smart Sync

```
POST   /api/sync/sponsorships-filtered     - Get eligible sponsorships
GET    /api/sync/person-by-relation/{id}   - Fetch by relation_id_number
POST   /api/sync/civil-registry-lookup     - Search civil registry
POST   /api/sync/correct-person-name       - Submit corrected name
POST   /api/sync/update-person-data        - Update person in correct table
```

#### Data Classification & Auto-routing

```typescript
class PersonDataRouter {
    /**
     * Route person data to correct table based on person type
     */
    async routePersonUpdate(personData: any, personType: string) {
        switch (personType) {
            case 'guardian':
                return await this.updateGuardianData(personData);
            
            case 'family_member':
            case 'orphan':
                return await this.updateOrphanData(personData);
            
            case 'deceased_father':
            case 'deceased_mother':
                return await this.updateDeceasedData(personData);
            
            default:
                throw new Error('Unknown person type');
        }
    }
    
    /**
     * Update guardian in 'data' table
     */
    private async updateGuardianData(data: any) {
        await ApiService.post('/api/sync/data', {
            table: 'data',
            operation: 'update',
            data: {
                data_first_name: data.guardian_first_name,
                data_father_name: data.guardian_father_name,
                data_grand_father_name: data.guardian_grand_father_name,
                data_family_name: data.guardian_family_name,
                data_id_number: data.guardian_identity,
                data_phone_number: data.phone_number,
                data_alt_phone_number: data.alt_phone_number,
                data_province: data.province,
                data_current_address: data.detailed_address
            }
        });
        
        // Update sponsorships table
        await this.updateSponsorshipRecord(data);
    }
    
    /**
     * Update orphan/family member in 're_people' table
     */
    private async updateOrphanData(data: any) {
        await ApiService.post('/api/sync/re-people', {
            table: 're_people',
            operation: 'update',
            data: {
                first_name: data.orphan_first_name,
                second_name: data.orphan_second_name,
                third_name: data.orphan_third_name,
                last_name: data.orphan_last_name,
                person_id: data.orphan_identity,
                person_gender: data.orphan_gender,
                person_birth_date: data.orphan_birth_date
            }
        });
        
        await this.updateSponsorshipRecord(data);
    }
    
    /**
     * Update deceased parent in 'dead_people' table
     */
    private async updateDeceasedData(data: any) {
        const isfather = data.person_type === 'deceased_father';
        
        await ApiService.post('/api/sync/dead-people', {
            table: 'dead_people',
            operation: 'update',
            data: isfather ? {
                father_first_name: data.first_name,
                father_second_name: data.second_name,
                father_third_name: data.third_name,
                father_last_name: data.last_name,
                father_id: data.identity_number
            } : {
                mother_first_name: data.first_name,
                mother_second_name: data.second_name,
                mother_third_name: data.third_name,
                mother_last_name: data.last_name,
                mother_id: data.identity_number
            }
        });
        
        await this.updateSponsorshipRecord(data);
    }
    
    /**
     * Update sponsorships table after person data update
     */
    private async updateSponsorshipRecord(data: any) {
        await ApiService.post('/api/sync/sponsorships/update', {
            relation_id_number: data.relation_id_number,
            identity_number: data.orphan_identity,
            guardian_identity_number: data.guardian_identity,
            orphan_name: `${data.orphan_first_name} ${data.orphan_second_name} ${data.orphan_third_name} ${data.orphan_last_name}`,
            guardian_name: data.guardian_first_name ? 
                `${data.guardian_first_name} ${data.guardian_father_name} ${data.guardian_grand_father_name} ${data.guardian_family_name}` : null
        });
    }
}
```

---

## 💾 Local Storage Architecture (Capacitor)

### 1. SQLite Database (Primary Data Storage)

```sql
-- Local mirror of server tables
CREATE TABLE data_local (
    id INTEGER PRIMARY KEY,
    file_id_number INTEGER UNIQUE,
    -- All fields from server table
    ...,
    -- Sync metadata
    sync_status TEXT DEFAULT 'pending', -- pending|synced|conflict
    last_synced_at TIMESTAMP,
    client_updated_at TIMESTAMP,
    device_id TEXT,
    is_deleted BOOLEAN DEFAULT 0
);

CREATE TABLE re_people_local (
    id INTEGER PRIMARY KEY,
    -- All fields from server table
    ...,
    sync_status TEXT DEFAULT 'pending',
    last_synced_at TIMESTAMP,
    client_updated_at TIMESTAMP
);

CREATE TABLE dead_people_local (
    id INTEGER PRIMARY KEY,
    -- All fields from server table
    ...,
    sync_status TEXT DEFAULT 'pending',
    last_synced_at TIMESTAMP
);

CREATE TABLE sponsorships_local (
    id INTEGER PRIMARY KEY,
    -- All fields from server table (read-only)
    ...,
    last_synced_at TIMESTAMP
);

CREATE TABLE guardian_bank_accounts_local (
    id INTEGER PRIMARY KEY,
    guardian_registration INTEGER,
    bank_name INTEGER,
    iban_usd TEXT,
    iban_shekel TEXT,
    account_number_or_related_phone_number TEXT,
    check_account INTEGER,
    re_id_number TEXT,
    re_guardian_name TEXT,
    re_phone_number TEXT,
    person_owner_identity_number TEXT,
    -- Sync metadata
    sync_status TEXT DEFAULT 'pending',
    last_synced_at TIMESTAMP,
    client_updated_at TIMESTAMP,
    device_id TEXT
);

-- Sync queue table
CREATE TABLE sync_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    table_name TEXT,
    record_id INTEGER,
    operation TEXT, -- create|update|delete
    payload TEXT, -- JSON payload
    priority INTEGER DEFAULT 5, -- 1=highest, 10=lowest
    retry_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL
);

CREATE INDEX idx_sync_queue_priority ON sync_queue(priority, created_at);

-- Sync logs table for tracking history
CREATE TABLE sync_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id TEXT NOT NULL,
    user_id INTEGER,
    table_name TEXT,
    record_id INTEGER,
    operation_type TEXT, -- create|update|delete|file_upload
    sync_status TEXT, -- success|failed|pending
    synced_at TIMESTAMP,
    last_attempt_at TIMESTAMP,
    -- File tracking
    file_path TEXT,
    file_hash TEXT,
    file_synced BOOLEAN DEFAULT 0,
    file_verified BOOLEAN DEFAULT 0,
    google_drive_id TEXT,
    -- Error tracking
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sync_logs_device ON sync_logs(device_id, created_at);
CREATE INDEX idx_sync_logs_status ON sync_logs(sync_status, synced_at);
CREATE INDEX idx_sync_logs_file ON sync_logs(file_hash, file_synced);

-- Track last successful sync per table
CREATE TABLE last_sync_timestamps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    table_name TEXT UNIQUE,
    last_pull_at TIMESTAMP,
    last_push_at TIMESTAMP,
    records_synced INTEGER DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_last_sync_table ON last_sync_timestamps(table_name);

-- Enable SQLite encryption (SQLCipher)
PRAGMA key = 'your-secure-encryption-key-here';
PRAGMA cipher_page_size = 4096;
PRAGMA kdf_iter = 256000;
```

**🔒 Database Protection:**
- Database encrypted with SQLCipher
- Access only through app with valid credentials
- Encryption key derived from user password + device ID
- No external database access allowed

### 2. IndexedDB (Media Queue & Metadata)

```javascript
// Database: alhayah_sync
const dbSchema = {
    stores: {
        // Media sync queue
        media_queue: {
            keyPath: 'id',
            autoIncrement: true,
            indexes: {
                'person_id': { unique: false },
                'sync_status': { unique: false },
                'created_at': { unique: false }
            }
        },
        // Offline actions log
        offline_actions: {
            keyPath: 'id',
            autoIncrement: true,
            indexes: {
                'timestamp': { unique: false },
                'synced': { unique: false }
            }
        },
        // App configuration
        app_config: {
            keyPath: 'key'
        }
    }
};

// Media queue entry structure
{
    id: 1,
    person_identity_number: '123456789',
    file_type: 'photo', // photo|video|document
    local_uri: 'file:///data/photos/IMG_001.jpg',
    file_size: 2048576,
    mime_type: 'image/jpeg',
    sync_status: 'pending', // pending|uploading|synced|failed
    google_drive_id: null,
    retry_count: 0,
    created_at: '2026-01-10T10:00:00Z',
    synced_at: null,
    error_message: null
}
```

### 3. Capacitor Filesystem (Media Files)

```
/data/user/0/com.alhayah.orphans/files/
├── photos/
│   ├── {person_id}_{timestamp}.jpg
│   ├── {person_id}_{timestamp}.jpg
│   └── ...
├── videos/
│   ├── {person_id}_{timestamp}.mp4
│   ├── {person_id}_{timestamp}.mp4
│   └── ...
└── documents/
    ├── {person_id}_{doc_type}_{timestamp}.pdf
    └── ...
```

**File Naming Convention**:
```
{person_identity_number}_{document_type}_{timestamp}_{random}.{ext}

Example: 123456789_birth_cert_20260110103045_a3f2.jpg
```

---

## ⚙️ Capacitor Implementation Details

### Required Capacitor Plugins

```json
{
  "dependencies": {
    "@capacitor/core": "^5.0.0",
    "@capacitor/android": "^5.0.0",
    "@capacitor/ios": "^5.0.0",
    "@capacitor/network": "^5.0.0",
    "@capacitor/camera": "^5.0.0",
    "@capacitor/filesystem": "^5.0.0",
    "@capacitor/storage": "^1.2.5",
    "@capacitor-community/sqlite": "^5.0.0",
    "@capacitor/splash-screen": "^5.0.0",
    "@capacitor/status-bar": "^5.0.0",
    "crypto-js": "^4.1.1"
  }
}
```

### Authentication & Initial Login

**⚠️ CRITICAL REQUIREMENTS:**
- First-time login MUST be online
- Session valid for 10 days
- Credentials stored encrypted
- No account creation from mobile app
- Login through browser only (web interface)

```typescript
// services/auth.service.ts
import { Network } from '@capacitor/network';
import { Storage } from '@capacitor/storage';
import CryptoJS from 'crypto-js';

class AuthService {
    private isFirstLogin: boolean = true;
    private readonly SESSION_DURATION = 10 * 24 * 60 * 60 * 1000; // 10 days

    async initialize() {
        const { value } = await Storage.get({ key: 'has_logged_in' });
        this.isFirstLogin = !value;
        
        // Check session expiry
        await this.checkSessionExpiry();
    }
    
    /**
     * Check if session is still valid
     */
    async checkSessionExpiry() {
        const { value: loginTime } = await Storage.get({ key: 'login_timestamp' });
        
        if (loginTime) {
            const elapsed = Date.now() - parseInt(loginTime);
            
            if (elapsed > this.SESSION_DURATION) {
                // Session expired - require re-login
                await this.logout();
                throw new Error('جلسة العمل منتهية. يرجى تسجيل الدخول مرة أخرى');
            }
        }
    }

    async login(username: string, password: string) {
        // Check if this is first login
        if (this.isFirstLogin) {
            // MUST have internet connection for first login
            const networkStatus = await Network.getStatus();
            
            if (!networkStatus.connected) {
                throw new Error('تسجيل الدخول الأول يتطلب اتصال بالإنترنت');
            }
        }

        try {
            // Authenticate with server
            const response = await ApiService.post('/api/auth/login', {
                username,
                password,
                device_id: await this.getDeviceId()
            });

            if (response.success) {
                // Generate encryption key from device ID
                const deviceId = await this.getDeviceId();
                const encryptionKey = CryptoJS.SHA256(deviceId + 'alhayah_salt').toString();
                
                // Save token (encrypted)
                await Storage.set({
                    key: 'auth_token',
                    value: this.encrypt(response.token, encryptionKey)
                });

                // Save user data (encrypted)
                await Storage.set({
                    key: 'user_data',
                    value: this.encrypt(JSON.stringify(response.user), encryptionKey)
                });
                
                // Save encrypted credentials (for auto-login)
                await Storage.set({
                    key: 'credentials',
                    value: this.encrypt(JSON.stringify({
                        username,
                        password
                    }), encryptionKey)
                });
                
                // Save login timestamp
                await Storage.set({
                    key: 'login_timestamp',
                    value: Date.now().toString()
                });

                // Mark as logged in
                await Storage.set({
                    key: 'has_logged_in',
                    value: 'true'
                });

                this.isFirstLogin = false;
                
                // Set database encryption key
                await this.setDatabaseEncryption(username, deviceId);

                // Initial data sync after first login
                if (response.initial_login) {
                    await this.performInitialSync();
                }

                return response;
            }
        } catch (error) {
            console.error('Login failed:', error);
            throw error;
        }
    }

    async performInitialSync() {
        console.log('🔄 Performing initial data sync...');
        
        try {
            // Pull all initial data from server
            const response = await ApiService.get('/api/sync/initial-data');
            
            if (response.success) {
                // Save to local database
                await DatabaseService.importInitialData(response.data);
                
                // Update last sync timestamps
                await this.updateSyncTimestamps();
                
                console.log('✅ Initial sync completed');
            }
        } catch (error) {
            console.error('❌ Initial sync failed:', error);
            throw error;
        }
    }

    async getDeviceId(): Promise<string> {
        let { value } = await Storage.get({ key: 'device_id' });
        
        if (!value) {
            // Generate unique device ID
            value = `device_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            await Storage.set({ key: 'device_id', value });
        }
        
        return value;
    }

    private async updateSyncTimestamps() {
        const timestamp = new Date().toISOString();
        const tables = ['data', 're_people', 'dead_people', 'sponsorships', 'guardian_bank_accounts'];
        
        for (const table of tables) {
            await SQLiteService.execute(
                `INSERT OR REPLACE INTO last_sync_timestamps (table_name, last_pull_at, updated_at) VALUES (?, ?, ?)`,
                [table, timestamp, timestamp]
            );
        }
    }
    
    /**
     * Encrypt data using AES
     */
    private encrypt(data: string, key: string): string {
        return CryptoJS.AES.encrypt(data, key).toString();
    }
    
    /**
     * Decrypt data using AES
     */
    private decrypt(ciphertext: string, key: string): string {
        const bytes = CryptoJS.AES.decrypt(ciphertext, key);
        return bytes.toString(CryptoJS.enc.Utf8);
    }
    
    /**
     * Set database encryption using SQLCipher
     */
    private async setDatabaseEncryption(username: string, deviceId: string) {
        // Generate database encryption key
        const dbKey = CryptoJS.SHA256(username + deviceId + 'alhayah_db').toString();
        
        // Set SQLite encryption key
        await SQLiteService.execute(`PRAGMA key = '${dbKey}'`);
        await SQLiteService.execute('PRAGMA cipher_page_size = 4096');
        await SQLiteService.execute('PRAGMA kdf_iter = 256000');
        
        console.log('🔒 Database encryption enabled');
    }
    
    /**
     * Get decrypted credentials for auto-login
     */
    async getStoredCredentials(): Promise<{username: string, password: string} | null> {
        const { value } = await Storage.get({ key: 'credentials' });
        
        if (!value) return null;
        
        try {
            const deviceId = await this.getDeviceId();
            const encryptionKey = CryptoJS.SHA256(deviceId + 'alhayah_salt').toString();
            const decrypted = this.decrypt(value, encryptionKey);
            
            return JSON.parse(decrypted);
        } catch (error) {
            console.error('Failed to decrypt credentials:', error);
            return null;
        }
    }
    
    /**
     * Logout and clear all encrypted data
     */
    async logout() {
        await Storage.remove({ key: 'auth_token' });
        await Storage.remove({ key: 'user_data' });
        await Storage.remove({ key: 'credentials' });
        await Storage.remove({ key: 'login_timestamp' });
        
        console.log('🚪 User logged out');
    }
}
```

### Network Detection Service

```typescript
// services/network.service.ts
import { Network } from '@capacitor/network';

class NetworkService {
    private isOnline: boolean = false;
    private listeners: Function[] = [];

    async initialize() {
        // Check initial status
        const status = await Network.getStatus();
        this.isOnline = status.connected;

        // Listen for changes
        Network.addListener('networkStatusChange', (status) => {
            this.isOnline = status.connected;
            this.notifyListeners(status);
            
            if (status.connected) {
                // Trigger sync when coming online
                SyncService.triggerSync();
            }
        });
    }

    isConnected(): boolean {
        return this.isOnline;
    }

    onStatusChange(callback: Function) {
        this.listeners.push(callback);
    }

    private notifyListeners(status: any) {
        this.listeners.forEach(cb => cb(status));
    }
}
```

### Sync Orchestration Service

```typescript
// services/sync.service.ts
import { SQLiteService } from './sqlite.service';
import { NetworkService } from './network.service';
import { ApiService } from './api.service';

class SyncService {
    private syncInProgress: boolean = false;
    private syncInterval: any = null;

    async initialize() {
        // Auto-sync every 5 minutes when online
        this.syncInterval = setInterval(() => {
            if (NetworkService.isConnected() && !this.syncInProgress) {
                this.triggerSync();
            }
        }, 5 * 60 * 1000);
    }

    async triggerSync() {
        if (this.syncInProgress) return;

        this.syncInProgress = true;
        console.log('🔄 Starting synchronization...');

        try {
            // Step 1: Sync database changes
            await this.syncDatabaseChanges();

            // Step 2: Sync media files
            await this.syncMediaFiles();

            console.log('✅ Synchronization completed');
        } catch (error) {
            console.error('❌ Sync error:', error);
        } finally {
            this.syncInProgress = false;
        }
    }

    private async syncDatabaseChanges() {
        console.log('📊 Syncing database changes...');

        // Get pending items from sync queue
        const pendingItems = await SQLiteService.query(
            'SELECT * FROM sync_queue WHERE synced_at IS NULL ORDER BY priority, created_at LIMIT 50'
        );

        console.log(`📦 Found ${pendingItems.length} pending items to sync`);

        // Log sync start
        await this.logSyncOperation('sync_batch_start', {
            pending_count: pendingItems.length
        });

        for (const item of pendingItems) {
            try {
                const response = await ApiService.post('/api/sync/data-batch', {
                    table: item.table_name,
                    operation: item.operation,
                    data: JSON.parse(item.payload)
                });

                if (response.success) {
                    const syncedAt = new Date().toISOString();
                    
                    // Mark as synced
                    await SQLiteService.execute(
                        'UPDATE sync_queue SET synced_at = ? WHERE id = ?',
                        [syncedAt, item.id]
                    );

                    // Update local record
                    await SQLiteService.execute(
                        `UPDATE ${item.table_name}_local SET sync_status = 'synced', last_synced_at = ? WHERE id = ?`,
                        [syncedAt, item.record_id]
                    );

                    // Log successful sync
                    await this.logSyncOperation('sync_success', {
                        table: item.table_name,
                        record_id: item.record_id,
                        operation: item.operation,
                        synced_at: syncedAt
                    });

                    // Update last sync timestamp for table
                    await SQLiteService.execute(
                        `UPDATE last_sync_timestamps SET last_push_at = ?, records_synced = records_synced + 1, updated_at = ? WHERE table_name = ?`,
                        [syncedAt, syncedAt, item.table_name]
                    );
                }
            } catch (error) {
                // Increment retry count
                await SQLiteService.execute(
                    'UPDATE sync_queue SET retry_count = retry_count + 1 WHERE id = ?',
                    [item.id]
                );

                // Log failed sync
                await this.logSyncOperation('sync_failed', {
                    table: item.table_name,
                    record_id: item.record_id,
                    operation: item.operation,
                    error: error.message,
                    retry_count: item.retry_count + 1
                });
            }
        }

        // Log sync batch completion
        await this.logSyncOperation('sync_batch_complete', {
            total: pendingItems.length
        });
    }

    private async logSyncOperation(operationType: string, data: any) {
        try {
            const deviceId = await AuthService.getDeviceId();
            const userId = await AuthService.getUserId();
            
            await SQLiteService.execute(
                `INSERT INTO sync_logs (device_id, user_id, table_name, record_id, operation_type, sync_status, last_attempt_at, error_message, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [
                    deviceId,
                    userId,
                    data.table || null,
                    data.record_id || null,
                    operationType,
                    data.error ? 'failed' : 'success',
                    new Date().toISOString(),
                    data.error || null,
                    new Date().toISOString()
                ]
            );
        } catch (error) {
            console.error('Failed to log sync operation:', error);
        }
    }

    private async syncMediaFiles() {
        console.log('📸 Syncing media files...');

        const db = await openDB('alhayah_sync');
        const pendingMedia = await db.getAllFromIndex(
            'media_queue',
            'sync_status',
            'pending'
        );

        // Only sync on WiFi
        const networkStatus = await Network.getStatus();
        if (networkStatus.connectionType !== 'wifi') {
            console.log('⏸ Waiting for WiFi to sync media');
            return;
        }

        for (const media of pendingMedia.slice(0, 5)) { // Max 5 concurrent
            try {
                await this.uploadMediaToGoogleDrive(media);
            } catch (error) {
                console.error('Media upload error:', error);
            }
        }
    }

    private async uploadMediaToGoogleDrive(media: any) {
        // Step 1: Upload to Google Drive via Rclone
        const driveResult = await RcloneService.upload(media.local_uri, {
            person_id: media.person_identity_number,
            file_type: media.file_type
        });

        // Step 2: Send metadata to Laravel
        await ApiService.post('/api/attachments/upload', {
            person_identity_number: media.person_identity_number,
            stored_file_name: driveResult.filename,
            file_path: driveResult.path,
            file_type: media.file_type,
            google_drive_id: driveResult.id,
            google_drive_url: driveResult.webViewLink
        });

        // Step 3: Update local IndexedDB
        const db = await openDB('alhayah_sync');
        const syncedAt = new Date().toISOString();
        
        await db.put('media_queue', {
            ...media,
            sync_status: 'synced',
            google_drive_id: driveResult.id,
            synced_at: syncedAt
        });

        // Step 4: Log file sync with hash verification
        const fileHash = await this.calculateFileHash(media.local_uri);
        
        await SQLiteService.execute(
            `INSERT INTO sync_logs (device_id, user_id, operation_type, sync_status, synced_at, file_path, file_hash, file_synced, file_verified, google_drive_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [
                await AuthService.getDeviceId(),
                await AuthService.getUserId(),
                'file_upload',
                'success',
                syncedAt,
                media.local_uri,
                fileHash,
                1, // file_synced = true
                1, // file_verified = true
                driveResult.id,
                syncedAt
            ]
        );

        console.log('✅ Media synced and logged:', media.local_uri);
    }

    private async calculateFileHash(filePath: string): Promise<string> {
        // Calculate SHA-256 hash of file for verification
        const fileData = await Filesystem.readFile({
            path: filePath
        });
        
        return CryptoJS.SHA256(fileData.data).toString();
    }
}
```

### Photography Module Integration

```typescript
// services/camera.service.ts
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { Filesystem, Directory } from '@capacitor/filesystem';

class CameraService {
    async capturePhoto(personId: string) {
        // Take photo
        const image = await Camera.getPhoto({
            quality: 90,
            allowEditing: false,
            resultType: CameraResultType.Uri,
            source: CameraSource.Camera
        });

        // Generate filename
        const filename = `${personId}_photo_${Date.now()}.jpg`;
        
        // Save to filesystem
        const savedFile = await Filesystem.copy({
            from: image.path!,
            to: `photos/${filename}`,
            directory: Directory.Data
        });

        // Add to sync queue
        const db = await openDB('alhayah_sync');
        await db.add('media_queue', {
            person_identity_number: personId,
            file_type: 'photo',
            local_uri: savedFile.uri,
            file_size: await this.getFileSize(savedFile.uri),
            mime_type: 'image/jpeg',
            sync_status: 'pending',
            created_at: new Date().toISOString()
        });

        // Trigger sync if online
        if (NetworkService.isConnected()) {
            SyncService.triggerSync();
        }

        return savedFile;
    }

    async captureVideo(personId: string) {
        // Similar implementation for video
        // Use Media plugin for video capture
    }
}
```

### Admin Name Correction UI Component

**For Civil Registry data that needs correction (admin role only)**

```typescript
// components/NameCorrectionForm.tsx
import React, { useState } from 'react';

interface NameCorrectionProps {
    personData: any;
    personType: 'orphan' | 'guardian' | 'deceased_father' | 'deceased_mother';
    onSubmit: (correctedData: any) => void;
}

const NameCorrectionForm: React.FC<NameCorrectionProps> = ({ personData, personType, onSubmit }) => {
    const [formData, setFormData] = useState({
        first_name: '',
        second_name: '',
        third_name: '',
        last_name: ''
    });
    
    // Check if user has admin role
    const hasAdminRole = async () => {
        const userData = await AuthService.getUserData();
        return userData.role === 'admin' || userData.role === 'super_admin';
    };
    
    const handleSubmit = async () => {
        const isAdmin = await hasAdminRole();
        
        if (!isAdmin) {
            alert('غير مصرح لك بتعديل البيانات');
            return;
        }
        
        // Submit corrected name
        await ApiService.post('/api/sync/correct-person-name', {
            person_identity: personData.identity_number,
            person_type: personType,
            corrected_name: formData,
            source_data: personData
        });
        
        onSubmit(formData);
    };
    
    return (
        <div className="name-correction-container">
            <div className="alert alert-warning">
                <strong>⚠️ تنبيه:</strong> البيانات من السجل المدني تحتاج إلى مراجعة
            </div>
            
            {/* Display original name from civil registry */}
            <div className="original-name">
                <h4>الاسم الحالي من السجل المدني:</h4>
                <p className="text-muted">{personData.full_name}</p>
            </div>
            
            {/* Four fields for correct name entry */}
            <div className="correction-fields">
                <h4>أدخل الاسم الرباعي الصحيح:</h4>
                
                <div className="form-group">
                    <label>الاسم الأول</label>
                    <input 
                        type="text"
                        className="form-control"
                        value={formData.first_name}
                        onChange={(e) => setFormData({...formData, first_name: e.target.value})}
                        placeholder="الاسم الأول"
                    />
                </div>
                
                <div className="form-group">
                    <label>اسم الأب</label>
                    <input 
                        type="text"
                        className="form-control"
                        value={formData.second_name}
                        onChange={(e) => setFormData({...formData, second_name: e.target.value})}
                        placeholder="اسم الأب"
                    />
                </div>
                
                <div className="form-group">
                    <label>اسم الجد</label>
                    <input 
                        type="text"
                        className="form-control"
                        value={formData.third_name}
                        onChange={(e) => setFormData({...formData, third_name: e.target.value})}
                        placeholder="اسم الجد"
                    />
                </div>
                
                <div className="form-group">
                    <label>اسم العائلة</label>
                    <input 
                        type="text"
                        className="form-control"
                        value={formData.last_name}
                        onChange={(e) => setFormData({...formData, last_name: e.target.value})}
                        placeholder="اسم العائلة"
                    />
                </div>
                
                <button 
                    className="btn btn-primary"
                    onClick={handleSubmit}
                >
                    ✅ تأكيد وحفظ الاسم الصحيح
                </button>
            </div>
        </div>
    );
};

export default NameCorrectionForm;
```

---

## 🔐 Laravel Backend Implementation

### Sync API Controller

```php
// app/Http/Controllers/Api/SyncController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\Sponsorship;

class SyncController extends Controller
{
    /**
     * Sync single data record
     */
    public function syncData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table' => 'required|in:data,re_people,dead_people',
            'operation' => 'required|in:create,update,delete',
            'data' => 'required|array',
            'client_timestamp' => 'required|date',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $result = $this->processSync(
                $request->table,
                $request->operation,
                $request->data,
                $request->client_timestamp,
                $request->device_id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $result,
                'server_timestamp' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch sync multiple records
     */
    public function syncDataBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.table' => 'required|in:data,re_people,dead_people,guardian_bank_accounts',
            'items.*.operation' => 'required|in:create,update,delete',
            'items.*.data' => 'required|array',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $results = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->items as $index => $item) {
                try {
                    $result = $this->processSync(
                        $item['table'],
                        $item['operation'],
                        $item['data'],
                        $item['client_timestamp'] ?? now(),
                        $request->device_id
                    );
                    
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'synced' => count($results),
                'failed' => count($errors),
                'results' => $results,
                'errors' => $errors,
                'server_timestamp' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Batch sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pull server changes since timestamp
     */
    public function pullChanges(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'last_sync' => 'required|date',
            'tables' => 'array',
            'tables.*' => 'in:data,re_people,dead_people,sponsorships'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tables = $request->tables ?? ['data', 're_people', 'dead_people', 'sponsorships'];
        $lastSync = $request->last_sync;
        $changes = [];

        foreach ($tables as $table) {
            $model = $this->getModel($table);
            
            $changes[$table] = $model::where('updated_at', '>', $lastSync)
                ->get()
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'changes' => $changes,
            'server_timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Process individual sync operation
     */
    private function processSync($table, $operation, $data, $clientTimestamp, $deviceId)
    {
        $model = $this->getModel($table);

        switch ($operation) {
            case 'create':
                $record = $model::create($data);
                return $record;

            case 'update':
                $identifier = $this->getIdentifier($table, $data);
                $record = $model::where($identifier['key'], $identifier['value'])->first();
                
                if (!$record) {
                    throw new \Exception("Record not found");
                }

                // Conflict detection
                if ($record->updated_at > $clientTimestamp) {
                    return [
                        'conflict' => true,
                        'server_data' => $record,
                        'client_data' => $data,
                        'message' => 'Server has newer version'
                    ];
                }

                $record->update($data);
                return $record;

            case 'delete':
                $identifier = $this->getIdentifier($table, $data);
                $record = $model::where($identifier['key'], $identifier['value'])->first();
                
                if ($record) {
                    $record->delete();
                }
                
                return ['deleted' => true];

            default:
                throw new \Exception("Invalid operation");
        }
    }

    /**
     * Get model instance by table name
     */
    private function getModel($table)
    {
        return match($table) {
            'data' => new Data(),
            're_people' => new \App\Models\RePeople(),
            'dead_people' => new DeadPepole(),
            'sponsorships' => new Sponsorship(),
            'guardian_bank_accounts' => new \App\Models\GuardianBankAccount(),
            default => throw new \Exception("Invalid table")
        };
    }

    /**
     * Get unique identifier for record
     */
    private function getIdentifier($table, $data)
    {
        return match($table) {
            'data' => ['key' => 'file_id_number', 'value' => $data['file_id_number']],
            're_people' => ['key' => 'person_id', 'value' => $data['person_id']],
            'dead_people' => ['key' => 're_file_id', 'value' => $data['re_file_id']],
            'sponsorships' => ['key' => 'id', 'value' => $data['id']],
            'guardian_bank_accounts' => ['key' => 'id', 'value' => $data['id']],
            default => throw new \Exception("Invalid table")
        };
    }

    /**
     * Get filtered sponsorships (exclude "ارسل للصرف" and "تم الصرف")
     */
    public function getFilteredSponsorships(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exclude_statuses' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get status IDs to exclude
            $excludeStatusIds = \DB::table('sponsorship_statuses')
                ->whereIn('description', $request->exclude_statuses)
                ->pluck('id')
                ->toArray();

            // Fetch eligible sponsorships
            $sponsorships = Sponsorship::whereNotIn('sponsorship_status_id', $excludeStatusIds)
                ->with(['sponsor', 'sponsorshipType', 'sponsorshipStatus'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'sponsorships' => $sponsorships,
                    'count' => $sponsorships->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sponsorships',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch person data by relation_id_number from internal tables
     */
    public function getPersonByRelation($relationId)
    {
        try {
            $personData = [];

            // Search in data table (guardian)
            $guardian = Data::where('file_id_number', $relationId)->first();
            if ($guardian) {
                $personData['guardian'] = $guardian;
            }

            // Search in re_people table (orphan)
            $orphan = \App\Models\RePeople::where('registration_id', $relationId)->first();
            if ($orphan) {
                $personData['orphan'] = $orphan;
            }

            // Search in dead_people table
            $deceased = DeadPepole::where('re_file_id', $relationId)->first();
            if ($deceased) {
                $personData['deceased'] = $deceased;
            }

            // Get bank accounts
            if ($guardian) {
                $bankAccounts = \App\Models\GuardianBankAccount::where(
                    'guardian_registration', 
                    $guardian->file_id_number
                )->get();
                $personData['bank_accounts'] = $bankAccounts;
            }

            return response()->json([
                'success' => true,
                'data' => $personData,
                'found' => !empty($personData)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lookup in Civil Registry database
     */
    public function civilRegistryLookup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orphan_identity' => 'required|string',
            'guardian_identity' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = [];

            // Search for orphan in civil registry
            $orphanData = \DB::connection('civilregistry')
                ->table('persons')
                ->where('CI_ID_NUM', $request->orphan_identity)
                ->first();

            if ($orphanData) {
                $results['orphan'] = [
                    'identity_number' => $orphanData->CI_ID_NUM,
                    'full_name' => trim(
                        ($orphanData->CI_FIRST_NAME_AR ?? '') . ' ' .
                        ($orphanData->CI_FATHER_NAME_AR ?? '') . ' ' .
                        ($orphanData->CI_GRAND_FATHER_NAME_AR ?? '') . ' ' .
                        ($orphanData->CI_FAMILY_NAME_AR ?? '')
                    ),
                    'needs_correction' => true
                ];
            }

            // Search for guardian if provided
            if ($request->guardian_identity) {
                $guardianData = \DB::connection('civilregistry')
                    ->table('persons')
                    ->where('CI_ID_NUM', $request->guardian_identity)
                    ->first();

                if ($guardianData) {
                    $results['guardian'] = [
                        'identity_number' => $guardianData->CI_ID_NUM,
                        'full_name' => trim(
                            ($guardianData->CI_FIRST_NAME_AR ?? '') . ' ' .
                            ($guardianData->CI_FATHER_NAME_AR ?? '') . ' ' .
                            ($guardianData->CI_GRAND_FATHER_NAME_AR ?? '') . ' ' .
                            ($guardianData->CI_FAMILY_NAME_AR ?? '')
                        ),
                        'needs_correction' => true
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'source' => 'civil_registry'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Civil registry lookup failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit corrected person name (admin only)
     */
    public function correctPersonName(Request $request)
    {
        // Verify admin role
        if (!$request->user() || !in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'person_identity' => 'required|string',
            'person_type' => 'required|in:orphan,guardian,deceased_father,deceased_mother',
            'corrected_name' => 'required|array',
            'corrected_name.first_name' => 'required|string',
            'corrected_name.second_name' => 'required|string',
            'corrected_name.third_name' => 'required|string',
            'corrected_name.last_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $correctedName = $request->corrected_name;
            $personType = $request->person_type;

            // Route to correct table based on person type
            switch ($personType) {
                case 'guardian':
                    Data::updateOrCreate(
                        ['data_id_number' => $request->person_identity],
                        [
                            'data_first_name' => $correctedName['first_name'],
                            'data_father_name' => $correctedName['second_name'],
                            'data_grand_father_name' => $correctedName['third_name'],
                            'data_family_name' => $correctedName['last_name']
                        ]
                    );
                    break;

                case 'orphan':
                    \App\Models\RePeople::updateOrCreate(
                        ['person_id' => $request->person_identity],
                        [
                            'first_name' => $correctedName['first_name'],
                            'second_name' => $correctedName['second_name'],
                            'third_name' => $correctedName['third_name'],
                            'last_name' => $correctedName['last_name']
                        ]
                    );
                    break;

                case 'deceased_father':
                case 'deceased_mother':
                    $isfather = $personType === 'deceased_father';
                    $prefix = $isfather ? 'father' : 'mother';
                    
                    DeadPepole::updateOrCreate(
                        [$prefix . '_id' => $request->person_identity],
                        [
                            $prefix . '_first_name' => $correctedName['first_name'],
                            $prefix . '_second_name' => $correctedName['second_name'],
                            $prefix . '_third_name' => $correctedName['third_name'],
                            $prefix . '_last_name' => $correctedName['last_name']
                        ]
                    );
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Name corrected successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to correct name',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receive sync logs from mobile app
     */
    public function receiveSyncLogs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logs' => 'required|array',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store logs in server database
            foreach ($request->logs as $log) {
                \DB::table('mobile_sync_logs')->insert([
                    'device_id' => $request->device_id,
                    'user_id' => $log['user_id'] ?? null,
                    'table_name' => $log['table_name'] ?? null,
                    'record_id' => $log['record_id'] ?? null,
                    'operation_type' => $log['operation_type'],
                    'sync_status' => $log['sync_status'],
                    'synced_at' => $log['synced_at'] ?? null,
                    'file_path' => $log['file_path'] ?? null,
                    'file_hash' => $log['file_hash'] ?? null,
                    'file_synced' => $log['file_synced'] ?? 0,
                    'google_drive_id' => $log['google_drive_id'] ?? null,
                    'error_message' => $log['error_message'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Logs received successfully',
                'count' => count($request->logs)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

### Attachment/Media Controller

```php
// app/Http/Controllers/Api/AttachmentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attachment;
use Illuminate\Support\Facades\Validator;

class AttachmentController extends Controller
{
    /**
     * Upload file metadata (file already uploaded to Google Drive)
     */
    public function uploadMetadata(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'person_identity_number' => 'required|string',
            'stored_file_name' => 'required|string',
            'file_path' => 'required|string',
            'file_type' => 'required|string',
            'google_drive_id' => 'required|string',
            'google_drive_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attachment = Attachment::create([
                'person_identity_number' => $request->person_identity_number,
                'stored_file_name' => $request->stored_file_name,
                'file_path' => $request->file_path,
                'file_type' => $request->file_type,
                'google_drive_id' => $request->google_drive_id,
                'google_drive_url' => $request->google_drive_url
            ]);

            return response()->json([
                'success' => true,
                'data' => $attachment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save attachment metadata',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch upload multiple file metadata
     */
    public function uploadBatchMetadata(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attachments' => 'required|array',
            'attachments.*.person_identity_number' => 'required|string',
            'attachments.*.stored_file_name' => 'required|string',
            'attachments.*.file_path' => 'required|string',
            'attachments.*.file_type' => 'required|string',
            'attachments.*.google_drive_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $saved = [];
        $errors = [];

        foreach ($request->attachments as $index => $data) {
            try {
                $attachment = Attachment::create($data);
                $saved[] = $attachment;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'saved' => count($saved),
            'failed' => count($errors),
            'data' => $saved,
            'errors' => $errors
        ]);
    }

    /**
     * Get attachments for a person
     */
    public function getPersonAttachments($identityNumber)
    {
        $attachments = Attachment::where('person_identity_number', $identityNumber)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments
        ]);
    }
}
```

---

## 🌐 Google Drive Integration (Rclone)

### Rclone Configuration

```ini
# ~/.config/rclone/rclone.conf

[alhayah_drive]
type = drive
scope = drive
token = {"access_token":"...","token_type":"Bearer","refresh_token":"...","expiry":"2026-01-11T10:00:00Z"}
team_drive = 
root_folder_id = <YOUR_FOLDER_ID>
```

### Rclone Service (Node.js/PHP Wrapper)

```php
// app/Services/RcloneService.php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class RcloneService
{
    private $remoteName = 'alhayah_drive';
    private $basePath = 'AlHayah_Orphans';

    /**
     * Upload file to Google Drive
     */
    public function upload($localPath, $remotePath, $metadata = [])
    {
        try {
            // Build remote path
            $fullRemotePath = "{$this->remoteName}:{$this->basePath}/{$remotePath}";

            // Execute rclone copy
            $result = Process::run([
                'rclone',
                'copy',
                $localPath,
                $fullRemotePath,
                '--progress',
                '--stats', '1s'
            ]);

            if ($result->successful()) {
                // Get file info
                $fileInfo = $this->getFileInfo($remotePath);

                Log::info('Rclone upload successful', [
                    'local_path' => $localPath,
                    'remote_path' => $remotePath,
                    'file_info' => $fileInfo
                ]);

                return [
                    'success' => true,
                    'path' => $remotePath,
                    'drive_id' => $fileInfo['ID'] ?? null,
                    'size' => $fileInfo['Size'] ?? null
                ];
            }

            throw new \Exception($result->errorOutput());

        } catch (\Exception $e) {
            Log::error('Rclone upload failed', [
                'error' => $e->getMessage(),
                'local_path' => $localPath
            ]);

            throw $e;
        }
    }

    /**
     * Get file information from Google Drive
     */
    public function getFileInfo($remotePath)
    {
        $fullRemotePath = "{$this->remoteName}:{$this->basePath}/{$remotePath}";

        $result = Process::run([
            'rclone',
            'lsjson',
            $fullRemotePath
        ]);

        if ($result->successful()) {
            $files = json_decode($result->output(), true);
            return $files[0] ?? null;
        }

        return null;
    }

    /**
     * Create folder structure
     */
    public function createFolder($folderPath)
    {
        $fullRemotePath = "{$this->remoteName}:{$this->basePath}/{$folderPath}";

        $result = Process::run([
            'rclone',
            'mkdir',
            $fullRemotePath
        ]);

        return $result->successful();
    }

    /**
     * List files in folder
     */
    public function listFiles($folderPath = '')
    {
        $fullRemotePath = "{$this->remoteName}:{$this->basePath}/{$folderPath}";

        $result = Process::run([
            'rclone',
            'lsjson',
            $fullRemotePath
        ]);

        if ($result->successful()) {
            return json_decode($result->output(), true);
        }

        return [];
    }
}
```

### Google Drive Folder Structure

```
AlHayah_Orphans/
├── photos/
│   ├── 2026/
│   │   ├── 01/
│   │   │   ├── 123456789_photo_20260110103045.jpg
│   │   │   └── ...
│   │   └── 02/
│   └── ...
├── videos/
│   ├── 2026/
│   │   └── 01/
│   │       ├── 123456789_video_20260110110000.mp4
│   │       └── ...
├── documents/
│   ├── birth_certificates/
│   │   └── 123456789_birth_cert_20260110.pdf
│   ├── death_certificates/
│   └── identity_documents/
└── backups/
    └── database/
```

---

## 🔄 Conflict Resolution Strategy

### Conflict Types

1. **Update-Update Conflict**: Both client and server modified same record
2. **Delete-Update Conflict**: Client deleted, server updated (or vice versa)
3. **Create-Create Conflict**: Same record created on both sides

### Resolution Rules

```typescript
// Conflict resolution logic
class ConflictResolver {
    resolve(conflict: SyncConflict): Resolution {
        switch (conflict.type) {
            case 'update-update':
                // Last-write-wins based on timestamp
                if (conflict.clientTimestamp > conflict.serverTimestamp) {
                    return Resolution.CLIENT_WINS;
                } else {
                    return Resolution.SERVER_WINS;
                }

            case 'delete-update':
                // Server deletion takes precedence
                if (conflict.serverOperation === 'delete') {
                    return Resolution.SERVER_WINS;
                }
                // Otherwise, keep the update
                return Resolution.CLIENT_WINS;

            case 'create-create':
                // Server record takes precedence
                // Merge unique client data as new record with different ID
                return Resolution.MERGE;

            default:
                // Manual resolution required
                return Resolution.MANUAL;
        }
    }
}
```

---

## 📱 PWA Enhancement

### Service Worker for Offline Support

```javascript
// public/sw.js

const CACHE_NAME = 'alhayah-v1';
const urlsToCache = [
    '/',
    '/pwa/data.html',
    '/pwa/photography.html',
    '/pwa/css/style.css',
    '/pwa/js/app.js',
    '/pwa/logo01.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Cache hit - return response
                if (response) {
                    return response;
                }

                return fetch(event.request).then((response) => {
                    // Don't cache if not a success response
                    if (!response || response.status !== 200) {
                        return response;
                    }

                    // Clone response
                    const responseToCache = response.clone();

                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(event.request, responseToCache);
                        });

                    return response;
                });
            })
    );
});

// Background sync
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-data') {
        event.waitUntil(syncData());
    } else if (event.tag === 'sync-media') {
        event.waitUntil(syncMedia());
    }
});
```

### Background Sync Registration

```javascript
// Register background sync
if ('serviceWorker' in navigator && 'sync' in registration) {
    navigator.serviceWorker.ready.then((registration) => {
        // Register data sync
        registration.sync.register('sync-data');
        
        // Register media sync (when WiFi available)
        if (navigator.connection.type === 'wifi') {
            registration.sync.register('sync-media');
        }
    });
}
```

---

## 🔒 Security Considerations

### 1. Authentication & Authorization

```typescript
// JWT Token Storage
import { Storage } from '@capacitor/storage';

class AuthService {
    async saveToken(token: string) {
        await Storage.set({
            key: 'auth_token',
            value: token
        });
    }

    async getToken(): Promise<string | null> {
        const { value } = await Storage.get({ key: 'auth_token' });
        return value;
    }

    async addAuthHeader(request: any) {
        const token = await this.getToken();
        if (token) {
            request.headers['Authorization'] = `Bearer ${token}`;
        }
        return request;
    }
}
```

### 2. Data Encryption

```typescript
// Encrypt sensitive data before storage
import CryptoJS from 'crypto-js';

class EncryptionService {
    private key = 'your-encryption-key'; // Store securely

    encrypt(data: any): string {
        return CryptoJS.AES.encrypt(
            JSON.stringify(data),
            this.key
        ).toString();
    }

    decrypt(ciphertext: string): any {
        const bytes = CryptoJS.AES.decrypt(ciphertext, this.key);
        return JSON.parse(bytes.toString(CryptoJS.enc.Utf8));
    }
}
```

### 3. API Rate Limiting

```php
// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/sync/data', [SyncController::class, 'syncData']);
    Route::post('/sync/data-batch', [SyncController::class, 'syncDataBatch']);
});
```

---

## 📊 Performance Optimization

### 1. Database Indexing

```sql
-- Add indexes for faster sync queries
CREATE INDEX idx_data_updated_at ON data(updated_at);
CREATE INDEX idx_data_sync_status ON data_local(sync_status);
CREATE INDEX idx_attachments_person ON attachments(person_identity_number);
CREATE INDEX idx_sync_queue_status ON sync_queue(synced_at, priority);
```

### 2. Batch Operations

- Sync max 50 records per batch
- Media upload max 5 concurrent
- Queue processing every 5 minutes
- Exponential backoff on failures

### 3. Lazy Loading

```typescript
// Load data on demand
class DataService {
    async loadOrphans(page: number = 1, limit: number = 20) {
        const offset = (page - 1) * limit;
        
        return await SQLiteService.query(
            `SELECT * FROM re_people_local 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?`,
            [limit, offset]
        );
    }
}
```

---

## 🧪 Testing Strategy

### Unit Tests

```typescript
// tests/sync.service.test.ts
describe('SyncService', () => {
    it('should sync pending data when online', async () => {
        // Mock network as online
        NetworkService.isConnected = jest.fn(() => true);
        
        // Add test data to sync queue
        await SQLiteService.execute(
            'INSERT INTO sync_queue (table_name, operation, payload) VALUES (?, ?, ?)',
            ['data', 'create', JSON.stringify({file_id_number: 123})]
        );
        
        // Trigger sync
        await SyncService.triggerSync();
        
        // Verify sync completed
        const queue = await SQLiteService.query(
            'SELECT * FROM sync_queue WHERE synced_at IS NOT NULL'
        );
        
        expect(queue.length).toBe(1);
    });
});
```

### Integration Tests

```php
// tests/Feature/SyncControllerTest.php
class SyncControllerTest extends TestCase
{
    public function test_sync_data_creates_record()
    {
        $response = $this->postJson('/api/sync/data', [
            'table' => 'data',
            'operation' => 'create',
            'data' => [
                'file_id_number' => 12345,
                'data_first_name' => 'Ahmed',
                // ... other fields
            ],
            'client_timestamp' => now()->toIso8601String(),
            'device_id' => 'test-device'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('data', [
            'file_id_number' => 12345,
            'data_first_name' => 'Ahmed'
        ]);
    }
}
```

---

## 📈 Monitoring & Logging

### Client-Side Logging

```typescript
class SyncLogger {
    static log(event: string, data: any) {
        const entry = {
            timestamp: new Date().toISOString(),
            event: event,
            data: data
        };

        // Store in IndexedDB
        const db = await openDB('alhayah_sync');
        await db.add('offline_actions', entry);

        // Send to server when online
        if (NetworkService.isConnected()) {
            ApiService.post('/api/logs/sync', entry);
        }
    }
}

// Usage
SyncLogger.log('sync_started', { queue_size: 10 });
SyncLogger.log('sync_completed', { synced: 8, failed: 2 });
```

### Server-Side Monitoring

```php
// Monitor sync performance
Log::channel('sync')->info('Sync request', [
    'device_id' => $request->device_id,
    'table' => $request->table,
    'operation' => $request->operation,
    'duration' => $duration,
    'success' => $success
]);
```

---

## 🚀 Deployment Checklist

### Backend (Laravel)

- [ ] Configure `.env` with database credentials
- [ ] Run migrations: `php artisan migrate`
- [ ] Install Rclone and configure Google Drive
- [ ] Set up API routes in `routes/api.php`
- [ ] Configure CORS for mobile app
- [ ] Set up SSL certificate
- [ ] Configure queue workers: `php artisan queue:work`
- [ ] Set up scheduled tasks in cron

### Mobile App (Capacitor)

- [ ] Install Capacitor and required plugins
- [ ] Install SQLCipher plugin for encrypted database
- [ ] Configure `capacitor.config.json`
- [ ] Integrate login UI from `pwa` folder
- [ ] Add session management (10-day expiry)
- [ ] Implement encrypted credential storage
- [ ] Add admin role verification
- [ ] Create name correction UI component
- [ ] Build app: `ionic build` or `npm run build`
- [ ] Sync with platforms: `npx cap sync`
- [ ] Configure Android permissions in `AndroidManifest.xml`
- [ ] Configure iOS permissions in `Info.plist`
- [ ] Test encryption and database protection
- [ ] Test multi-level sync logic
- [ ] Test on physical devices
- [ ] Generate signed APK/IPA

### Google Drive

- [ ] Create Google Cloud project
- [ ] Enable Google Drive API
- [ ] Create OAuth 2.0 credentials
- [ ] Configure Rclone with credentials
- [ ] Create folder structure
- [ ] Test upload/download operations

---

## 📝 API Routes Summary

```
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL API ROUTES                       │
├─────────────────────────────────────────────────────────────┤
│ POST   /api/sync/data                    - Sync single record         │
│ POST   /api/sync/data-batch              - Sync batch records         │
│ POST   /api/sync/re-people               - Sync re_people             │
│ POST   /api/sync/dead-people             - Sync dead_people           │
│ POST   /api/sync/bank-accounts           - Sync bank accounts         │
│ GET    /api/sync/pull                    - Pull server changes        │
│ GET    /api/sync/initial-data            - Initial data pull          │
│ POST   /api/sync/conflict-resolve        - Resolve conflicts          │
│ POST   /api/sync/logs                    - Upload sync logs           │
│                                                                        │
│ POST   /api/sync/sponsorships-filtered   - Get eligible sponsorships  │
│ GET    /api/sync/person-by-relation/{id} - Fetch by relation ID       │
│ POST   /api/sync/civil-registry-lookup   - Search civil registry      │
│ POST   /api/sync/correct-person-name     - Correct name (admin)       │
│ POST   /api/sync/update-person-data      - Update person data         │
│ POST   /api/sync/sponsorships/update     - Update sponsorship record  │
│                                                             │
│ POST   /api/attachments/upload     - Upload file metadata  │
│ POST   /api/attachments/batch      - Batch file metadata   │
│ GET    /api/attachments/{id}       - Get attachment info   │
│ GET    /api/attachments/person/{id}- Get person attachments│
│                                                             │
│ POST   /api/auth/login             - User login            │
│ POST   /api/auth/logout            - User logout           │
│ POST   /api/auth/refresh           - Refresh token         │
│                                                             │
│ POST   /api/logs/sync              - Submit sync logs      │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Implementation Priority

### Phase 1: Foundation (Week 1-2)
1. ✅ Set up Capacitor project
2. ✅ Implement SQLite local database
3. ✅ Create sync queue system
4. ✅ Implement network detection
5. ✅ Basic Laravel API endpoints

### Phase 2: Core Sync (Week 3-4)
1. ✅ Database synchronization logic
2. ✅ Conflict resolution
3. ✅ Offline data creation/editing
4. ✅ Background sync service
5. ✅ API integration

### Phase 3: Media Sync (Week 5-6)
1. ✅ Camera integration
2. ✅ Local file storage
3. ✅ Google Drive upload (Rclone)
4. ✅ Media sync queue
5. ✅ Attachment metadata sync

### Phase 4: Testing & Optimization (Week 7-8)
1. ✅ Unit tests
2. ✅ Integration tests
3. ✅ Performance optimization
4. ✅ Security audit
5. ✅ User acceptance testing

---

## ⚠️ Critical Notes

### Data Integrity

1. **Never Delete Local Data**: All data remains on device permanently
2. **Sync Flags**: Use `sync_status` field, never delete records
3. **Soft Deletes**: Mark as deleted, sync to server, but keep locally
4. **Media Files**: Keep all photos/videos even after successful upload
5. **File Verification**: Use SHA-256 hash to verify successful upload
6. **Sync Logging**: Track every sync operation in sync_logs table
7. **Timestamp Tracking**: Maintain last_sync_timestamps for each table

### Network Strategy

1. **WiFi Only for Media**: Large files only on WiFi
2. **Cellular for Data**: Small data records can sync on cellular
3. **Retry Logic**: Exponential backoff (1s, 2s, 4s, 8s, 16s)
4. **Queue Priority**: Critical data first, media second

### User Experience

1. **Offline Indicator**: Clear UI showing offline mode
2. **Sync Progress**: Show sync status and progress
3. **Error Handling**: User-friendly error messages
4. **Conflict UI**: Allow user to resolve conflicts manually if needed
5. **First Login**: Require internet connection for initial authentication
6. **Sync History**: Show users which files/data have been synced
7. **Field Restrictions**: Only allow editing of specified fields (10 main fields + bank info)
8. **Verification Status**: Display sync verification status for uploaded files
9. **Login UI**: Login page integrated in PWA (no registration)
10. **Session Indicator**: Show remaining session time (10 days countdown)
11. **Name Correction**: Admin-only UI for correcting civil registry names
12. **Auto-routing**: Automatic classification of person type and data routing

### Authentication & Security

1. **Login Method**: Web-based login only (browser interface)
2. **No Registration**: Account creation disabled in mobile app
3. **Session Duration**: 10 days with auto-logout on expiry
4. **Credential Storage**: AES-256 encrypted username/password
5. **Database Encryption**: SQLCipher with user-derived key
6. **Access Control**: Database accessible only through app
7. **Auto-login**: Encrypted credentials allow seamless re-authentication
8. **Role-based Access**: Admin role required for name corrections

---

## 📚 Dependencies & Tools

### Capacitor Plugins
```json
{
  "@capacitor/core": "^5.0.0",
  "@capacitor/network": "^5.0.0",
  "@capacitor/camera": "^5.0.0",
  "@capacitor/filesystem": "^5.0.0",
  "@capacitor/storage": "^1.2.5",
  "@capacitor-community/sqlite": "^5.0.0"
}
```

### Laravel Packages
```json
{
  "laravel/sanctum": "^3.0",
  "laravel/telescope": "^4.0",
  "spatie/laravel-permission": "^5.0"
}
```

### External Tools
- **Rclone**: v1.60+ for Google Drive sync
- **SQLite**: 3.35+ for mobile database
- **Node.js**: v18+ for build process

---

## 🔗 Resources

- [Capacitor Documentation](https://capacitorjs.com/docs)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
- [Rclone Documentation](https://rclone.org/docs/)
- [Google Drive API](https://developers.google.com/drive)
- [IndexedDB API](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API)

---

## ✅ Success Criteria

1. ✅ App works fully offline
2. ✅ Data syncs automatically when online
3. ✅ No data loss on device
4. ✅ Photos/videos upload to Google Drive
5. ✅ Conflicts resolved automatically or manually
6. ✅ Fast sync performance (<5 seconds for 50 records)
7. ✅ Secure data transmission (HTTPS, JWT)
8. ✅ User-friendly interface with sync status

---

**Document Version**: 1.0  
**Last Updated**: January 10, 2026  
**Author**: GitHub Copilot  
**Status**: Ready for Implementation

---

## 🎯 Advanced Sync Implementation Summary

### Multi-Level Data Fetching Logic

1. **Level 1**: Check `sponsorships` table → Filter out ("ارسل للصرف", "تم الصرف")
2. **Level 2**: Lookup `relation_id_number` in (`data`, `dead_people`, `re_people`)
3. **Level 3**: If not found → Search `civilregistry.persons` by `CI_ID_NUM`
4. **Level 4**: Admin name correction UI → Route to appropriate table
5. **Level 5**: Auto-update `sponsorships` table with corrected data

### Data Routing by Person Type

- **Guardian** → Update `data` table
- **Orphan/Family Member** → Update `re_people` table
- **Deceased Father/Mother** → Update `dead_people` table
- **Bank Info** → Update `guardian_bank_accounts` table
- **Sponsorship Link** → Update via `relation_id_number` + `identity_number`

### Security Enhancements

✅ **Database Encryption**: SQLCipher with user-derived keys
✅ **Credential Encryption**: AES-256 for username/password
✅ **Session Management**: 10-day auto-logout
✅ **Access Control**: Database accessible only through app
✅ **Role-Based**: Admin-only name correction
✅ **No Registration**: Login only (web interface)

### Sync Verification & Logging

- File hash (SHA-256) tracking
- Sync logs for every operation
- Last sync timestamps per table
- File upload verification
- Google Drive ID linkage

---

**End of Document**
