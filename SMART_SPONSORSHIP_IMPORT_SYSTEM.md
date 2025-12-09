# Smart Sponsorship Import System with Person Classification

## Overview

This system implements intelligent person classification during Excel sponsorship import, automatically identifying whether individuals should be stored in `data`, `re_people`, or `dead_people` tables based on their person type.

## Key Features

### 1. **Person Type Classification**
Excel file now includes a "Person Type" column (نوع الشخص) with these categories:
- **Guardian** (معيل / معيل أسرة): Primary family provider
- **Family Member** (فرد عائلة): Orphan or family member under sponsorship
- **Deceased Father** (أب متوفي): Deceased father
- **Deceased Mother** (أم متوفية): Deceased mother

### 2. **Column Name Updates**
- **Old**: اسم اليتيم (Orphan Name) → **New**: اسم المكفول (Sponsored Person Name)
- **Old**: رقم هوية اليتيم (Orphan ID) → **New**: رقم هوية المكفول (Sponsored Person ID)
- **New Column Added**: نوع الشخص (Person Type)

## Workflow

### Phase 1: Validation & Pre-Check

```
1. Upload Excel file
2. System reads "Person Type" column
3. For each person:
   - Family Member → Check in re_people.person_id
   - Guardian → Check in data.data_id_number
   - Deceased Father → Check in dead_people.father_id
   - Deceased Mother → Check in dead_people.mother_id
4. Display missing persons report
```

### Phase 2: Create Missing Persons

```
If persons not found in database:
1. Show classification table to user
2. User confirms creation
3. System creates records in appropriate tables:
   - Guardian → data table
   - Family Member → re_people table
   - Deceased Parent → dead_people table
4. Assigns unified family file_id_number
```

### Phase 3: Import Sponsorships

```
After all persons exist:
1. Create sponsorship records
2. Assign file numbers:
   - Guardian: Uses existing file_id_number
   - Family Member: Generates NEW file_id_number
   - Deceased Person: Generates NEW file_id_number
3. Link family members via guardian_identity
4. Insert bank account information
```

## Database Schema

### Person Classification Logic

| Person Type | Search Table | Search Column | Target Table for Creation |
|-------------|-------------|---------------|---------------------------|
| معيل (Guardian) | data | data_id_number | data |
| فرد عائلة (Family Member) | re_people | person_id | re_people |
| أب متوفي (Deceased Father) | dead_people | father_id | dead_people |
| أم متوفية (Deceased Mother) | dead_people | mother_id | dead_people |

### File Number Assignment

```
Guardian (معيل):
  - Uses existing file_id_number from data table
  - No new file number generated
  
Family Member (فرد عائلة):
  - NEW file_id_number generated via generateUniqueReservedCode()
  - Different from guardian's file number
  
Deceased Parent (أب/أم متوفي):
  - NEW file_id_number generated via generateUniqueReservedCode()
  - Different from guardian's file number
```

### Family Linkage

**Unified Family File ID (`re_file_id`):**
- When creating persons, all family members share the same `re_file_id`
- Links guardian with family members and deceased persons
- Enables family tree reconstruction

**Sponsorship File ID (`internal_file_number`):**
- Guardian: Original file_id_number
- Others: Newly generated unique file_id_number

## API Endpoints

### 1. Import with Validation
```
POST /admin/sponsorships/import?check_only=true
```

**Request:**
```json
{
  "excel_file": "file.xlsx",
  "sponsor_id": 6,
  "sponsorship_type_id": 2,
  "sponsorship_status_id": 2,
  "check_only": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم فحص الملف بنجاح",
  "validation": {
    "total_rows": 100,
    "missing_persons": [
      {
        "row": 5,
        "type": "فرد عائلة",
        "target_table": "re_people",
        "identity": "123456789",
        "name": "أحمد محمد",
        "guardian_identity": "987654321",
        "guardian_name": "محمد علي"
      }
    ],
    "missing_banks": ["بنك فلسطين"]
  }
}
```

### 2. Create Missing Persons
```
POST /admin/sponsorships/create-missing-persons
```

**Request:**
```json
{
  "persons": [
    {
      "type": "فرد عائلة",
      "identity": "123456789",
      "name": "أحمد محمد علي",
      "guardian_identity": "987654321",
      "guardian_name": "محمد علي حسن"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم إنشاء الأشخاص بنجاح",
  "created_persons": [
    {
      "type": "family_member",
      "identity": "123456789",
      "name": "أحمد محمد علي",
      "file_id": "002541"
    }
  ],
  "family_file_ids": {
    "987654321": "002541"
  }
}
```

### 3. Full Import
```
POST /admin/sponsorships/import
```

**Request:**
```json
{
  "excel_file": "file.xlsx",
  "sponsor_id": 6,
  "sponsorship_type_id": 2,
  "sponsorship_status_id": 2
}
```

## Excel File Structure

### Required Columns

| Column Name | Arabic Name | Required | Type | Description |
|-------------|-------------|----------|------|-------------|
| ID | ID | Yes | Number | External file number |
| Person Type | نوع الشخص | Yes | Text | معيل / فرد عائلة / أب متوفي / أم متوفية |
| Sponsored Name | اسم المكفول | Yes | Text | Full name of sponsored person |
| Sponsored Identity | رقم هوية المكفول | Yes | Number | National ID of sponsored person |
| Guardian Name | اسم المعيل | Yes | Text | Guardian full name |
| Guardian Identity | هوية المعيل | Yes | Number | Guardian national ID |
| Phone | الهاتف | No | Number | Primary phone |
| Alt Phone | جوال بديل | No | Number | Alternative phone |
| Sponsor Name | اسم الكافل | No | Text | Sponsoring organization |
| Bank Name | المحفظة | No | Text | Bank name |
| Bank Owner Identity | هوية المحفظة | No | Number | Bank account owner ID |
| Bank Owner Name | صاحب المحفظة | No | Text | Bank account owner name |
| Bank Phone | جوال المحفظة | No | Number | Bank account phone |

### Example Row

```
ID: 80004
Person Type: فرد عائلة
Sponsored Name: أحمد محمد علي حسن
Sponsored Identity: 123456789
Guardian Name: محمد علي حسن
Guardian Identity: 987654321
Phone: 0599123456
Bank Name: بنك فلسطين
```

## Code Flow

### SponsorshipController.php

#### import() Method
```php
1. Validate file and reference data
2. Read Excel headers and rows
3. Extract person data with types
4. Check existence in appropriate tables:
   - Family member → re_people.person_id
   - Guardian → data.data_id_number
   - Deceased → dead_people.father_id/mother_id
5. Return missing persons list (if check_only)
6. Otherwise, proceed with sponsorship import
```

#### createMissingPersons() Method
```php
1. Receive persons array from frontend
2. For each person:
   a. Determine family file_id (unified)
   b. Create record in appropriate table
   c. Store with proper name splitting
3. Track family_file_ids mapping
4. Return created persons summary
```

### Key Functions

**generateUniqueReservedCode('data', 'file_id_number')**
- Generates unique 6-digit file numbers
- Ensures no duplicates across system
- Used for family members and deceased persons

**normalizeArabicText($text)**
- Normalizes Arabic text for comparison
- Handles different letter forms (أ، إ، آ → ا)
- Removes diacritics and extra spaces

## Security & Data Integrity

### Transaction Safety
```php
DB::beginTransaction();
try {
    // Create person records
    // Create sponsorship
    // Create bank accounts
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    Log::error($e);
}
```

### Validation Rules
- All identity numbers must be numeric
- Person types must match predefined list
- Family linkage verified via guardian_identity
- Duplicate prevention via database constraints

## Logging

System logs all operations:
```
✅ Person found in database
🆕 New file number generated
📊 Row processing status
❌ Errors with details
```

## Error Handling

### Common Errors

1. **Missing Person**: Person not in database
   - **Solution**: Use createMissingPersons endpoint

2. **Invalid Person Type**: Unrecognized type
   - **Solution**: Use exact Arabic text from list

3. **Missing Guardian**: Guardian not found
   - **Solution**: Create guardian record first

4. **Duplicate File Number**: Rare collision
   - **Solution**: Auto-retry with new number

## Testing

### Test Scenarios

1. **New family with guardian and 2 children**
2. **Existing guardian, new family members**
3. **Deceased parent records**
4. **Mixed person types in single file**
5. **Large import (1000+ rows)**

## Maintenance

### Regular Tasks

- Monitor file_id_number sequence
- Review error logs weekly
- Update person type normalization list
- Backup before large imports

## Future Enhancements

1. Bulk person creation UI
2. Excel template generator
3. Import history tracking
4. Automated duplicate detection
5. Family tree visualization

---

**Last Updated**: December 9, 2025  
**Version**: 2.0  
**Status**: ✅ Production Ready
