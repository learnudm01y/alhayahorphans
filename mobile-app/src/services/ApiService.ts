/**
 * API Service for AlHayah Orphans Mobile App
 *
 * Handles all HTTP communication with Laravel backend
 */

import { config } from './ConfigService';

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

export interface PersonCheckResult {
  exists: boolean;
  found_in?: string;
  file_id?: string;
  person_data?: any;
  message?: string;
}

export interface FileIdGenerationResult {
  success: boolean;
  file_id?: string;
  handshake_token?: string;
  message?: string;
}

export interface UploadNotification {
  local_file_hash: string;
  google_drive_file_id: string;
  entity_type: string;
  entity_id: string;
  original_filename: string;
  file_size: number;
  uploaded_by_device_id: string;
}

class ApiService {
  private baseUrl: string;
  private timeout: number;
  private authToken: string | null = null;
  private static instance: ApiService;

  private constructor() {
    this.baseUrl = config.get('apiBaseUrl');
    this.timeout = config.get('apiTimeout');
    this.loadAuthToken();
  }

  public static getInstance(): ApiService {
    if (!ApiService.instance) {
      ApiService.instance = new ApiService();
    }
    return ApiService.instance;
  }

  /**
   * Load auth token from storage
   */
  private loadAuthToken(): void {
    try {
      this.authToken = localStorage.getItem('auth_token');
    } catch (error) {
      console.warn('Failed to load auth token:', error);
    }
  }

  /**
   * Set authentication token
   */
  public setAuthToken(token: string): void {
    this.authToken = token;
    localStorage.setItem('auth_token', token);
  }

  /**
   * Clear authentication token
   */
  public clearAuthToken(): void {
    this.authToken = null;
    localStorage.removeItem('auth_token');
  }

  /**
   * Make HTTP request with timeout and retry
   */
  private async request<T>(
    method: string,
    endpoint: string,
    data?: any,
    retries: number = config.get('maxRetries')
  ): Promise<ApiResponse<T>> {
    const url = `${this.baseUrl}${endpoint}`;
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };

    if (this.authToken) {
      headers['Authorization'] = `Bearer ${this.authToken}`;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
      const response = await fetch(url, {
        method,
        headers,
        body: data ? JSON.stringify(data) : undefined,
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      const responseData = await response.json();

      if (!response.ok) {
        return {
          success: false,
          message: responseData.message || `HTTP Error: ${response.status}`,
          errors: responseData.errors
        };
      }

      return {
        success: true,
        data: responseData,
        message: responseData.message
      };
    } catch (error: any) {
      clearTimeout(timeoutId);

      // Retry on network errors
      if (retries > 0 && error.name !== 'AbortError') {
        console.log(`Retrying request... (${retries} attempts left)`);
        await this.delay(config.get('retryDelay'));
        return this.request<T>(method, endpoint, data, retries - 1);
      }

      return {
        success: false,
        message: error.message || 'Network error'
      };
    }
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  // =========================================================================
  // SYNC API ENDPOINTS
  // =========================================================================

  /**
   * Check if person exists in all tables
   */
  public async checkPersonInAllTables(identityNumber: string): Promise<ApiResponse<PersonCheckResult>> {
    return this.request('POST', '/sync/check-person-all-tables', { identity_number: identityNumber });
  }

  /**
   * Check if person exists (simple check)
   */
  public async checkPersonExists(identityNumber: string, tableName?: string): Promise<ApiResponse<PersonCheckResult>> {
    return this.request('POST', '/sync/check-person-exists', {
      identity_number: identityNumber,
      table_name: tableName
    });
  }

  /**
   * Generate new file ID with handshake
   */
  public async generateFileId(
    tableName: string,
    personType: string,
    identityNumber?: string
  ): Promise<ApiResponse<FileIdGenerationResult>> {
    return this.request('POST', '/sync/generate-file-id', {
      table_name: tableName,
      person_type: personType,
      identity_number: identityNumber
    });
  }

  /**
   * Activate file ID after successful local save
   */
  public async activateFileId(handshakeToken: string): Promise<ApiResponse<any>> {
    return this.request('POST', '/sync/activate-file-id', {
      handshake_token: handshakeToken
    });
  }

  /**
   * Create new person entry
   */
  public async createNewPersonEntry(personData: any): Promise<ApiResponse<any>> {
    return this.request('POST', '/sync/new-person-entry', personData);
  }

  /**
   * Get eligible sponsorships
   */
  public async getEligibleSponsorships(): Promise<ApiResponse<any[]>> {
    return this.request('GET', '/sync/eligible-sponsorships');
  }

  /**
   * Update sponsorship relation ID
   */
  public async updateSponsorshipRelationId(
    sponsorshipId: number,
    relationId: string
  ): Promise<ApiResponse<any>> {
    return this.request('POST', '/sync/sponsorships/update-relation-id', {
      sponsorship_id: sponsorshipId,
      relation_id: relationId
    });
  }

  /**
   * Get person by relation ID
   */
  public async getPersonByRelationId(relationId: string): Promise<ApiResponse<any>> {
    return this.request('GET', `/sync/person-by-relation/${relationId}`);
  }

  // =========================================================================
  // UPLOAD NOTIFICATION ENDPOINTS
  // =========================================================================

  /**
   * Check if file already exists (duplicate check)
   */
  public async checkUploadDuplicate(fileHash: string): Promise<ApiResponse<{ exists: boolean; upload?: any }>> {
    return this.request('POST', '/uploads/check-duplicate', {
      local_file_hash: fileHash
    });
  }

  /**
   * Notify server that file upload is complete
   */
  public async notifyUploadCompleted(notification: UploadNotification): Promise<ApiResponse<any>> {
    return this.request('POST', '/uploads/notify-completed', notification);
  }

  /**
   * Notify server that upload failed
   */
  public async notifyUploadFailed(
    fileHash: string,
    errorMessage: string
  ): Promise<ApiResponse<any>> {
    return this.request('POST', '/uploads/mark-failed', {
      local_file_hash: fileHash,
      error_message: errorMessage
    });
  }

  /**
   * Get upload statistics
   */
  public async getUploadStats(): Promise<ApiResponse<any>> {
    return this.request('GET', '/uploads/stats');
  }

  /**
   * Get uploads for specific entity
   */
  public async getEntityUploads(entityType: string, entityId: string): Promise<ApiResponse<any[]>> {
    return this.request('GET', `/uploads/entity/${entityType}/${entityId}`);
  }

  // =========================================================================
  // SYNC STATUS
  // =========================================================================

  /**
   * Get pending uploads that need to be synced to server
   */
  public async getPendingUploads(): Promise<ApiResponse<any[]>> {
    return this.request('GET', '/uploads/pending');
  }

  /**
   * Batch sync upload notifications
   */
  public async batchSyncUploads(uploads: UploadNotification[]): Promise<ApiResponse<any>> {
    return this.request('POST', '/uploads/batch-sync', { uploads });
  }
}

export const api = ApiService.getInstance();
export default ApiService;
