/**
 * Configuration Service for AlHayah Orphans Mobile App
 *
 * Central configuration for API endpoints, sync settings, and app constants
 */

export interface AppConfig {
  // API Configuration
  apiBaseUrl: string;
  apiTimeout: number;

  // Sync Configuration
  syncInterval: number; // milliseconds
  maxRetries: number;
  retryDelay: number; // milliseconds

  // Google Drive / Rclone Configuration
  rcloneRemote: string;
  googleDriveFolderId: string;
  uploadChunkSize: number; // bytes

  // SQLCipher Configuration
  dbName: string;
  dbEncryptionKey: string;

  // File ID Generation
  fileIdPrefix: string;
  fileIdYear: number;
}

// Default configuration
const defaultConfig: AppConfig = {
  // API
  apiBaseUrl: 'https://your-laravel-server.com/api',
  apiTimeout: 30000,

  // Sync
  syncInterval: 60000, // 1 minute
  maxRetries: 3,
  retryDelay: 5000, // 5 seconds

  // Google Drive
  rcloneRemote: 'gdrive',
  googleDriveFolderId: 'YOUR_GOOGLE_DRIVE_FOLDER_ID',
  uploadChunkSize: 8 * 1024 * 1024, // 8MB

  // Database
  dbName: 'alhayah_orphans.db',
  dbEncryptionKey: 'CHANGE_THIS_ENCRYPTION_KEY_IN_PRODUCTION',

  // File ID
  fileIdPrefix: 'G',
  fileIdYear: new Date().getFullYear()
};

class ConfigService {
  private config: AppConfig;
  private static instance: ConfigService;

  private constructor() {
    this.config = { ...defaultConfig };
    this.loadFromStorage();
  }

  public static getInstance(): ConfigService {
    if (!ConfigService.instance) {
      ConfigService.instance = new ConfigService();
    }
    return ConfigService.instance;
  }

  /**
   * Get configuration value
   */
  public get<K extends keyof AppConfig>(key: K): AppConfig[K] {
    return this.config[key];
  }

  /**
   * Set configuration value
   */
  public set<K extends keyof AppConfig>(key: K, value: AppConfig[K]): void {
    this.config[key] = value;
    this.saveToStorage();
  }

  /**
   * Get all configuration
   */
  public getAll(): AppConfig {
    return { ...this.config };
  }

  /**
   * Update multiple configuration values
   */
  public update(updates: Partial<AppConfig>): void {
    this.config = { ...this.config, ...updates };
    this.saveToStorage();
  }

  /**
   * Load configuration from storage
   */
  private loadFromStorage(): void {
    try {
      const stored = localStorage.getItem('app_config');
      if (stored) {
        const parsed = JSON.parse(stored);
        this.config = { ...this.config, ...parsed };
      }
    } catch (error) {
      console.warn('Failed to load config from storage:', error);
    }
  }

  /**
   * Save configuration to storage
   */
  private saveToStorage(): void {
    try {
      localStorage.setItem('app_config', JSON.stringify(this.config));
    } catch (error) {
      console.warn('Failed to save config to storage:', error);
    }
  }

  /**
   * Reset to default configuration
   */
  public reset(): void {
    this.config = { ...defaultConfig };
    this.saveToStorage();
  }
}

export const config = ConfigService.getInstance();
export default ConfigService;
