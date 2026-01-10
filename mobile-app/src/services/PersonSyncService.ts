/**
 * Person Sync Service for AlHayah Orphans Mobile App
 *
 * Handles person-related sync operations:
 * - Check if person exists
 * - Generate file IDs
 * - Create new person entries
 * - Sync person data with server
 */

import { api } from './ApiService';
import { database, PersonRecord } from './DatabaseService';
import { syncProgress, SyncTask } from './SyncProgressTracker';
import { config } from './ConfigService';

export interface PersonData {
  identityNumber: string;
  personType: 'guardian' | 'orphan' | 'deceased';
  fullName: string;
  birthDate?: string;
  province?: string;
  city?: string;
  additionalData?: Record<string, any>;
}

export interface PersonCheckResult {
  exists: boolean;
  foundIn?: string;
  fileId?: string;
  personData?: any;
  isLocal: boolean;
}

export interface FileIdResult {
  success: boolean;
  fileId: string;
  handshakeToken: string;
}

class PersonSyncService {
  private static instance: PersonSyncService;

  private constructor() {}

  public static getInstance(): PersonSyncService {
    if (!PersonSyncService.instance) {
      PersonSyncService.instance = new PersonSyncService();
    }
    return PersonSyncService.instance;
  }

  /**
   * Check if person exists (local first, then server)
   */
  public async checkPersonExists(identityNumber: string): Promise<PersonCheckResult> {
    // First check local database
    const localPerson = await database.getOne<PersonRecord>(
      'persons',
      'identity_number = ?',
      [identityNumber]
    );

    if (localPerson) {
      return {
        exists: true,
        foundIn: localPerson.table_name,
        fileId: localPerson.file_id,
        personData: JSON.parse(localPerson.local_data || '{}'),
        isLocal: true
      };
    }

    // Then check server
    try {
      const response = await api.checkPersonInAllTables(identityNumber);

      if (response.success && response.data?.exists) {
        // Cache locally
        await this.cachePersonLocally({
          identityNumber,
          personType: response.data.person_data?.person_type || 'guardian',
          fullName: response.data.person_data?.full_name || '',
          additionalData: response.data.person_data
        }, response.data.found_in || 'data', response.data.file_id);

        return {
          exists: true,
          foundIn: response.data.found_in,
          fileId: response.data.file_id,
          personData: response.data.person_data,
          isLocal: false
        };
      }

      return { exists: false, isLocal: false };
    } catch (error) {
      console.warn('Failed to check server:', error);
      return { exists: false, isLocal: true };
    }
  }

  /**
   * Generate new file ID for person
   */
  public async generateFileId(
    tableName: string,
    personType: string,
    identityNumber?: string
  ): Promise<FileIdResult> {
    // Queue task for progress tracking
    const taskId = await syncProgress.queueTask({
      operationType: 'file_id_generation',
      entityType: 'person',
      entityId: identityNumber || 'new'
    });

    try {
      await syncProgress.updateProgress(taskId, 25, 'in_progress', 'Requesting file ID...');

      const response = await api.generateFileId(tableName, personType, identityNumber);

      if (!response.success || !response.data?.file_id) {
        await syncProgress.failTask(taskId, response.message || 'Failed to generate file ID');
        throw new Error(response.message || 'Failed to generate file ID');
      }

      await syncProgress.updateProgress(taskId, 50, 'in_progress', 'Saving locally...');

      // Save to local file_id_registry
      await database.insert('file_id_registry', {
        file_id: response.data.file_id,
        table_name: tableName,
        person_type: personType,
        identity_number: identityNumber || null,
        handshake_token: response.data.handshake_token,
        status: 'pending'
      });

      await syncProgress.completeTask(taskId, 'File ID generated successfully');

      return {
        success: true,
        fileId: response.data.file_id!,
        handshakeToken: response.data.handshake_token!
      };
    } catch (error: any) {
      await syncProgress.failTask(taskId, error.message);
      throw error;
    }
  }

  /**
   * Activate file ID after successful local save
   */
  public async activateFileId(handshakeToken: string): Promise<boolean> {
    try {
      const response = await api.activateFileId(handshakeToken);

      if (response.success) {
        // Update local registry
        await database.update(
          'file_id_registry',
          { status: 'activated', activated_at: new Date().toISOString() },
          'handshake_token = ?',
          [handshakeToken]
        );
        return true;
      }

      return false;
    } catch (error) {
      console.error('Failed to activate file ID:', error);
      return false;
    }
  }

  /**
   * Create new person entry
   */
  public async createNewPerson(
    data: PersonData,
    tableName: string
  ): Promise<{
    success: boolean;
    fileId?: string;
    message?: string;
  }> {
    const taskId = await syncProgress.queueTask({
      operationType: 'person_creation',
      entityType: 'person',
      entityId: data.identityNumber
    });

    try {
      await syncProgress.updateProgress(taskId, 10, 'in_progress', 'Checking for duplicates...');

      // Check if already exists
      const existsCheck = await this.checkPersonExists(data.identityNumber);
      if (existsCheck.exists) {
        await syncProgress.failTask(taskId, 'Person already exists');
        return {
          success: false,
          fileId: existsCheck.fileId,
          message: `الشخص موجود مسبقاً في ${existsCheck.foundIn}`
        };
      }

      await syncProgress.updateProgress(taskId, 30, 'in_progress', 'Generating file ID...');

      // Generate file ID
      const fileIdResult = await this.generateFileId(
        tableName,
        data.personType,
        data.identityNumber
      );

      await syncProgress.updateProgress(taskId, 50, 'in_progress', 'Saving locally...');

      // Save locally first
      await database.insert('persons', {
        file_id: fileIdResult.fileId,
        identity_number: data.identityNumber,
        person_type: data.personType,
        full_name: data.fullName,
        birth_date: data.birthDate,
        province: data.province,
        city: data.city,
        table_name: tableName,
        local_data: JSON.stringify(data.additionalData || {}),
        sync_status: 'pending'
      });

      await syncProgress.updateProgress(taskId, 70, 'in_progress', 'Syncing with server...');

      // Sync with server
      const serverResponse = await api.createNewPersonEntry({
        file_id: fileIdResult.fileId,
        handshake_token: fileIdResult.handshakeToken,
        identity_number: data.identityNumber,
        person_type: data.personType,
        full_name: data.fullName,
        birth_date: data.birthDate,
        province: data.province,
        city: data.city,
        table_name: tableName,
        ...data.additionalData
      });

      if (serverResponse.success) {
        // Activate file ID
        await this.activateFileId(fileIdResult.handshakeToken);

        // Update local record
        await database.update(
          'persons',
          {
            sync_status: 'synced',
            synced_at: new Date().toISOString()
          },
          'file_id = ?',
          [fileIdResult.fileId]
        );

        await syncProgress.completeTask(taskId, 'Person created successfully');

        return {
          success: true,
          fileId: fileIdResult.fileId,
          message: 'تم إنشاء الشخص بنجاح'
        };
      } else {
        // Keep local record for later sync
        await syncProgress.failTask(taskId, serverResponse.message || 'Server sync failed');

        return {
          success: true, // Local save succeeded
          fileId: fileIdResult.fileId,
          message: 'تم الحفظ محلياً، سيتم المزامنة لاحقاً'
        };
      }
    } catch (error: any) {
      await syncProgress.failTask(taskId, error.message);
      throw error;
    }
  }

  /**
   * Cache person data locally
   */
  private async cachePersonLocally(
    data: PersonData,
    tableName: string,
    fileId?: string
  ): Promise<void> {
    // Check if already cached
    const existing = await database.getOne<PersonRecord>(
      'persons',
      'identity_number = ?',
      [data.identityNumber]
    );

    if (existing) {
      // Update
      await database.update(
        'persons',
        {
          full_name: data.fullName,
          person_type: data.personType,
          birth_date: data.birthDate,
          province: data.province,
          city: data.city,
          local_data: JSON.stringify(data.additionalData || {}),
          sync_status: 'synced',
          synced_at: new Date().toISOString()
        },
        'identity_number = ?',
        [data.identityNumber]
      );
    } else {
      // Insert
      await database.insert('persons', {
        file_id: fileId || '',
        identity_number: data.identityNumber,
        person_type: data.personType,
        full_name: data.fullName,
        birth_date: data.birthDate,
        province: data.province,
        city: data.city,
        table_name: tableName,
        local_data: JSON.stringify(data.additionalData || {}),
        sync_status: 'synced',
        synced_at: new Date().toISOString()
      });
    }
  }

  /**
   * Sync all unsynced persons with server
   */
  public async syncUnsyncedPersons(): Promise<{
    synced: number;
    failed: number;
  }> {
    const unsynced = await database.getAll<PersonRecord>(
      'persons',
      "sync_status = 'pending'"
    );

    let synced = 0;
    let failed = 0;

    for (const person of unsynced) {
      try {
        const response = await api.createNewPersonEntry({
          file_id: person.file_id,
          identity_number: person.identity_number,
          person_type: person.person_type,
          full_name: person.full_name,
          birth_date: person.birth_date,
          province: person.province,
          city: person.city,
          table_name: person.table_name,
          ...JSON.parse(person.local_data || '{}')
        });

        if (response.success) {
          await database.update(
            'persons',
            {
              sync_status: 'synced',
              synced_at: new Date().toISOString()
            },
            'id = ?',
            [person.id]
          );
          synced++;
        } else {
          failed++;
        }
      } catch (error) {
        console.error('Failed to sync person:', error);
        failed++;
      }
    }

    return { synced, failed };
  }

  /**
   * Get local person by file ID
   */
  public async getPersonByFileId(fileId: string): Promise<PersonRecord | null> {
    return database.getOne<PersonRecord>(
      'persons',
      'file_id = ?',
      [fileId]
    );
  }

  /**
   * Get local person by identity number
   */
  public async getPersonByIdentity(identityNumber: string): Promise<PersonRecord | null> {
    return database.getOne<PersonRecord>(
      'persons',
      'identity_number = ?',
      [identityNumber]
    );
  }

  /**
   * Search local persons
   */
  public async searchLocalPersons(query: string): Promise<PersonRecord[]> {
    return database.getAll<PersonRecord>(
      'persons',
      "full_name LIKE ? OR identity_number LIKE ?",
      [`%${query}%`, `%${query}%`],
      'full_name ASC'
    );
  }
}

export const personSync = PersonSyncService.getInstance();
export default PersonSyncService;
