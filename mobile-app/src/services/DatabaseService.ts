/**
 * Database Service for AlHayah Orphans Mobile App
 *
 * Handles SQLCipher encrypted local database operations
 * Provides offline-first data storage with sync capabilities
 */

import { Preferences } from '@capacitor/preferences';
import { config } from './ConfigService';

export interface DatabaseRecord {
  id?: number;
  created_at?: string;
  updated_at?: string;
  synced_at?: string | null;
  sync_status?: 'pending' | 'syncing' | 'synced' | 'failed';
}

export interface PersonRecord extends DatabaseRecord {
  file_id: string;
  identity_number: string;
  person_type: 'guardian' | 'orphan' | 'deceased';
  full_name: string;
  birth_date?: string;
  province?: string;
  city?: string;
  table_name: 'data' | 're_people' | 'dead_people';
  local_data: string; // JSON string of all person data
}

export interface SyncProgressRecord extends DatabaseRecord {
  operation_type: 'upload' | 'download' | 'sync';
  entity_type: string;
  entity_id: string;
  progress: number;
  status: 'pending' | 'in_progress' | 'completed' | 'failed';
  error_message?: string;
}

export interface GoogleDriveUploadRecord extends DatabaseRecord {
  local_file_path: string;
  local_file_hash: string;
  google_drive_file_id?: string;
  upload_status: 'pending' | 'uploading' | 'completed' | 'failed';
  entity_type: 'photo' | 'document' | 'attachment';
  entity_id: string;
  file_size: number;
  uploaded_bytes: number;
  retry_count: number;
  last_error?: string;
  synced_to_server: boolean;
}

class DatabaseService {
  private db: any = null;
  private isInitialized: boolean = false;
  private static instance: DatabaseService;

  private constructor() {}

  public static getInstance(): DatabaseService {
    if (!DatabaseService.instance) {
      DatabaseService.instance = new DatabaseService();
    }
    return DatabaseService.instance;
  }

  /**
   * Initialize the encrypted database
   */
  public async init(): Promise<void> {
    if (this.isInitialized) return;

    try {
      // In a real app, use @nicepkg/capacitor-sqlite or similar
      // For now, we'll use a mock implementation
      console.log('Initializing encrypted database...');

      await this.createTables();
      this.isInitialized = true;

      console.log('Database initialized successfully');
    } catch (error) {
      console.error('Failed to initialize database:', error);
      throw error;
    }
  }

  /**
   * Create all required tables
   */
  private async createTables(): Promise<void> {
    const tables = [
      // Persons table (local cache)
      `CREATE TABLE IF NOT EXISTS persons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id TEXT UNIQUE NOT NULL,
        identity_number TEXT,
        person_type TEXT NOT NULL,
        full_name TEXT,
        birth_date TEXT,
        province TEXT,
        city TEXT,
        table_name TEXT NOT NULL,
        local_data TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        synced_at TEXT,
        sync_status TEXT DEFAULT 'pending'
      )`,

      // Google Drive uploads tracking
      `CREATE TABLE IF NOT EXISTS google_drive_uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        local_file_path TEXT NOT NULL,
        local_file_hash TEXT NOT NULL,
        google_drive_file_id TEXT,
        upload_status TEXT DEFAULT 'pending',
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        file_size INTEGER DEFAULT 0,
        uploaded_bytes INTEGER DEFAULT 0,
        retry_count INTEGER DEFAULT 0,
        last_error TEXT,
        synced_to_server INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
      )`,

      // Sync progress tracking
      `CREATE TABLE IF NOT EXISTS sync_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        operation_type TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        progress REAL DEFAULT 0,
        status TEXT DEFAULT 'pending',
        error_message TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
      )`,

      // File ID registry (local)
      `CREATE TABLE IF NOT EXISTS file_id_registry (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id TEXT UNIQUE NOT NULL,
        table_name TEXT NOT NULL,
        person_type TEXT,
        identity_number TEXT,
        handshake_token TEXT UNIQUE,
        status TEXT DEFAULT 'pending',
        created_at TEXT DEFAULT (datetime('now')),
        activated_at TEXT
      )`,

      // Attachments metadata
      `CREATE TABLE IF NOT EXISTS attachments_metadata (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        local_path TEXT NOT NULL,
        remote_path TEXT,
        file_hash TEXT NOT NULL,
        file_name TEXT NOT NULL,
        file_type TEXT,
        file_size INTEGER,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        upload_status TEXT DEFAULT 'pending',
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
      )`
    ];

    for (const sql of tables) {
      await this.execute(sql);
    }

    // Create indexes
    await this.execute(`CREATE INDEX IF NOT EXISTS idx_persons_file_id ON persons(file_id)`);
    await this.execute(`CREATE INDEX IF NOT EXISTS idx_persons_identity ON persons(identity_number)`);
    await this.execute(`CREATE INDEX IF NOT EXISTS idx_uploads_hash ON google_drive_uploads(local_file_hash)`);
    await this.execute(`CREATE INDEX IF NOT EXISTS idx_uploads_status ON google_drive_uploads(upload_status)`);
    await this.execute(`CREATE INDEX IF NOT EXISTS idx_sync_progress_status ON sync_progress(status)`);
  }

  /**
   * Execute SQL query
   */
  public async execute(sql: string, params: any[] = []): Promise<any> {
    // This is a placeholder - in real app, use actual SQLite plugin
    console.log('Executing SQL:', sql.substring(0, 100) + '...');

    // Store in Preferences for demo purposes
    // In production, use actual SQLite with SQLCipher
    return { changes: 0, lastInsertRowId: 0 };
  }

  /**
   * Query database
   */
  public async query<T>(sql: string, params: any[] = []): Promise<T[]> {
    console.log('Querying:', sql.substring(0, 100) + '...');
    return [];
  }

  /**
   * Insert record
   */
  public async insert(table: string, data: Record<string, any>): Promise<number> {
    const keys = Object.keys(data);
    const values = Object.values(data);
    const placeholders = keys.map(() => '?').join(', ');

    const sql = `INSERT INTO ${table} (${keys.join(', ')}) VALUES (${placeholders})`;
    const result = await this.execute(sql, values);

    return result.lastInsertRowId || 0;
  }

  /**
   * Update record
   */
  public async update(
    table: string,
    data: Record<string, any>,
    where: string,
    whereParams: any[] = []
  ): Promise<number> {
    const sets = Object.keys(data).map(k => `${k} = ?`).join(', ');
    const values = [...Object.values(data), ...whereParams];

    const sql = `UPDATE ${table} SET ${sets}, updated_at = datetime('now') WHERE ${where}`;
    const result = await this.execute(sql, values);

    return result.changes || 0;
  }

  /**
   * Delete record
   */
  public async delete(table: string, where: string, whereParams: any[] = []): Promise<number> {
    const sql = `DELETE FROM ${table} WHERE ${where}`;
    const result = await this.execute(sql, whereParams);

    return result.changes || 0;
  }

  /**
   * Get single record
   */
  public async getOne<T>(table: string, where: string, whereParams: any[] = []): Promise<T | null> {
    const sql = `SELECT * FROM ${table} WHERE ${where} LIMIT 1`;
    const results = await this.query<T>(sql, whereParams);
    return results.length > 0 ? results[0] : null;
  }

  /**
   * Get all records matching criteria
   */
  public async getAll<T>(
    table: string,
    where?: string,
    whereParams: any[] = [],
    orderBy?: string
  ): Promise<T[]> {
    let sql = `SELECT * FROM ${table}`;
    if (where) sql += ` WHERE ${where}`;
    if (orderBy) sql += ` ORDER BY ${orderBy}`;

    return this.query<T>(sql, whereParams);
  }

  /**
   * Count records
   */
  public async count(table: string, where?: string, whereParams: any[] = []): Promise<number> {
    let sql = `SELECT COUNT(*) as count FROM ${table}`;
    if (where) sql += ` WHERE ${where}`;

    const result = await this.query<{ count: number }>(sql, whereParams);
    return result.length > 0 ? result[0].count : 0;
  }

  /**
   * Begin transaction
   */
  public async beginTransaction(): Promise<void> {
    await this.execute('BEGIN TRANSACTION');
  }

  /**
   * Commit transaction
   */
  public async commit(): Promise<void> {
    await this.execute('COMMIT');
  }

  /**
   * Rollback transaction
   */
  public async rollback(): Promise<void> {
    await this.execute('ROLLBACK');
  }
}

export const database = DatabaseService.getInstance();
export default DatabaseService;
