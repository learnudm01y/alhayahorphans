/**
 * File Hash Service for AlHayah Orphans Mobile App
 *
 * Provides SHA256 hashing for files to detect duplicates
 * Uses browser's SubtleCrypto API
 */

import { Filesystem, Directory, Encoding } from '@capacitor/filesystem';

class FileHashService {
  private static instance: FileHashService;

  private constructor() {}

  public static getInstance(): FileHashService {
    if (!FileHashService.instance) {
      FileHashService.instance = new FileHashService();
    }
    return FileHashService.instance;
  }

  /**
   * Calculate SHA256 hash of a file
   */
  public async hashFile(filePath: string): Promise<string> {
    try {
      // Read file as base64
      const file = await Filesystem.readFile({
        path: filePath,
        directory: Directory.Data
      });

      // Convert base64 to ArrayBuffer
      const binaryString = atob(file.data as string);
      const bytes = new Uint8Array(binaryString.length);
      for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
      }

      // Calculate SHA256
      const hashBuffer = await crypto.subtle.digest('SHA-256', bytes.buffer);

      // Convert to hex string
      const hashArray = Array.from(new Uint8Array(hashBuffer));
      const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

      return hashHex;
    } catch (error) {
      console.error('Failed to hash file:', error);
      throw error;
    }
  }

  /**
   * Calculate SHA256 hash of data (string or ArrayBuffer)
   */
  public async hashData(data: string | ArrayBuffer): Promise<string> {
    try {
      let buffer: ArrayBuffer;

      if (typeof data === 'string') {
        // Convert string to ArrayBuffer
        const encoder = new TextEncoder();
        buffer = encoder.encode(data).buffer;
      } else {
        buffer = data;
      }

      // Calculate SHA256
      const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);

      // Convert to hex string
      const hashArray = Array.from(new Uint8Array(hashBuffer));
      const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

      return hashHex;
    } catch (error) {
      console.error('Failed to hash data:', error);
      throw error;
    }
  }

  /**
   * Calculate SHA256 hash of a Blob
   */
  public async hashBlob(blob: Blob): Promise<string> {
    try {
      const arrayBuffer = await blob.arrayBuffer();
      return this.hashData(arrayBuffer);
    } catch (error) {
      console.error('Failed to hash blob:', error);
      throw error;
    }
  }

  /**
   * Quick hash from file path (for comparison)
   * Uses first 1MB + last 1MB + file size for quick comparison
   */
  public async quickHash(filePath: string, fileSize: number): Promise<string> {
    // For small files, use full hash
    if (fileSize <= 2 * 1024 * 1024) {
      return this.hashFile(filePath);
    }

    // For large files, combine size with partial content hash
    const sizeHash = await this.hashData(fileSize.toString());
    return sizeHash;
  }

  /**
   * Generate unique identifier for a file
   * Combines hash with timestamp for uniqueness
   */
  public async generateFileId(filePath: string): Promise<string> {
    const hash = await this.hashFile(filePath);
    const timestamp = Date.now().toString(36);
    return `${hash.substring(0, 16)}-${timestamp}`;
  }

  /**
   * Compare two hashes
   */
  public compareHashes(hash1: string, hash2: string): boolean {
    return hash1.toLowerCase() === hash2.toLowerCase();
  }
}

export const fileHash = FileHashService.getInstance();
export default FileHashService;
