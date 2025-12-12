<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    private $accessToken;
    private $credentials;
    private $baseUrl = 'https://www.googleapis.com/drive/v3';
    private $uploadUrl = 'https://www.googleapis.com/upload/drive/v3';

    public function __construct()
    {
        $this->loadCredentials();
        $this->getAccessToken();
    }

    /**
     * تحميل بيانات الاعتماد من ملف JSON
     */
    private function loadCredentials()
    {
        try {
            $credentialsPath = storage_path('app/google/credentials.json');

            if (!file_exists($credentialsPath)) {
                throw new Exception('ملف credentials.json غير موجود');
            }

            $this->credentials = json_decode(file_get_contents($credentialsPath), true);

            if (!$this->credentials) {
                throw new Exception('فشل في قراءة ملف credentials.json');
            }

            return true;
        } catch (Exception $e) {
            Log::error('خطأ في تحميل credentials: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على Access Token من Google
     */
    private function getAccessToken()
    {
        try {
            // إنشاء JWT Token
            $jwt = $this->createJWT();

            // طلب Access Token (مع تعطيل SSL verification للبيئة المحلية)
            $response = Http::withOptions([
                'verify' => false, // تعطيل SSL verification للبيئة المحلية
            ])->asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

            if ($response->successful()) {
                $this->accessToken = $response->json()['access_token'];
                return $this->accessToken;
            } else {
                throw new Exception('فشل في الحصول على Access Token: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في الحصول على Access Token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * إنشاء JWT Token للمصادقة
     */
    private function createJWT()
    {
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]);

        $now = time();
        $claim = json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/drive.file',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlClaim = $this->base64UrlEncode($claim);

        $signature = '';
        $signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;

        openssl_sign(
            $signatureInput,
            $signature,
            $this->credentials['private_key'],
            'SHA256'
        );

        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $signatureInput . '.' . $base64UrlSignature;
    }

    /**
     * Base64 URL Encoding
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * رفع ملف إلى Google Drive
     * ملاحظة: يجب رفع الملفات إلى مجلد مشترك (Shared Drive) أو مجلد له صلاحيات للـ Service Account
     */
    public function uploadFile($filePath, $fileName, $folderId = null)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('الملف غير موجود: ' . $filePath);
            }

            $fileContent = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            // إعداد metadata
            $metadata = [
                'name' => $fileName,
            ];

            if ($folderId) {
                $metadata['parents'] = [$folderId];
            }

            // رفع الملف (مع دعم Shared Drives)
            $url = $this->uploadUrl . '/files?uploadType=multipart&supportsAllDrives=true';

            $response = Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'multipart/related; boundary=foo_bar_baz'
            ])->withBody(
                "--foo_bar_baz\r\n" .
                "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
                json_encode($metadata) . "\r\n" .
                "--foo_bar_baz\r\n" .
                "Content-Type: $mimeType\r\n\r\n" .
                $fileContent . "\r\n" .
                "--foo_bar_baz--",
                'multipart/related; boundary=foo_bar_baz'
            )->post($url);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('تم رفع الملف بنجاح: ' . $fileName, ['file_id' => $result['id']]);
                return $result;
            } else {
                throw new Exception('فشل رفع الملف: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في رفع الملف: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * حذف ملف من Google Drive
     */
    public function deleteFile($fileId)
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->delete($this->baseUrl . '/files/' . $fileId . '?supportsAllDrives=true');

            if ($response->successful() || $response->status() === 204) {
                Log::info('تم حذف الملف بنجاح', ['file_id' => $fileId]);
                return true;
            } else {
                throw new Exception('فشل حذف الملف: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في حذف الملف: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على معلومات ملف
     */
    public function getFile($fileId)
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->get($this->baseUrl . '/files/' . $fileId . '?fields=*&supportsAllDrives=true');

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('فشل الحصول على معلومات الملف: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في الحصول على معلومات الملف: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * سرد الملفات في مجلد معين أو Shared Drive
     */
    public function listFiles($folderId = null, $pageSize = 10)
    {
        try {
            $query = $folderId ? "'{$folderId}' in parents" : null;

            $params = [
                'pageSize' => $pageSize,
                'fields' => 'files(id, name, mimeType, size, createdTime, modifiedTime)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ];

            if ($query) {
                $params['q'] = $query;
            }

            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->get($this->baseUrl . '/files', $params);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('فشل في سرد الملفات: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في سرد الملفات: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * إنشاء مجلد جديد
     */
    public function createFolder($folderName, $parentFolderId = null)
    {
        try {
            $metadata = [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ];

            if ($parentFolderId) {
                $metadata['parents'] = [$parentFolderId];
            }

            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->post($this->baseUrl . '/files?supportsAllDrives=true', $metadata);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('تم إنشاء المجلد بنجاح: ' . $folderName, ['folder_id' => $result['id']]);
                return $result;
            } else {
                throw new Exception('فشل إنشاء المجلد: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في إنشاء المجلد: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث ملف موجود
     */
    public function updateFile($fileId, $filePath)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('الملف غير موجود: ' . $filePath);
            }

            $fileContent = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            $response = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => $mimeType
                ])->withBody($fileContent, $mimeType)
                ->patch($this->uploadUrl . '/files/' . $fileId . '?uploadType=media&supportsAllDrives=true');

            if ($response->successful()) {
                $result = $response->json();
                Log::info('تم تحديث الملف بنجاح', ['file_id' => $fileId]);
                return $result;
            } else {
                throw new Exception('فشل تحديث الملف: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في تحديث الملف: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحميل ملف من Google Drive
     */
    public function downloadFile($fileId, $savePath)
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->get($this->baseUrl . '/files/' . $fileId . '?alt=media&supportsAllDrives=true');

            if ($response->successful()) {
                file_put_contents($savePath, $response->body());
                Log::info('تم تحميل الملف بنجاح', ['file_id' => $fileId, 'save_path' => $savePath]);
                return true;
            } else {
                throw new Exception('فشل تحميل الملف: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في تحميل الملف: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * إعطاء صلاحيات للملف
     */
    public function shareFile($fileId, $email, $role = 'reader')
    {
        try {
            $permission = [
                'type' => 'user',
                'role' => $role, // reader, writer, commenter, owner
                'emailAddress' => $email
            ];

            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->post($this->baseUrl . '/files/' . $fileId . '/permissions', $permission);

            if ($response->successful()) {
                Log::info('تم مشاركة الملف بنجاح', ['file_id' => $fileId, 'email' => $email]);
                return $response->json();
            } else {
                throw new Exception('فشل مشاركة الملف: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في مشاركة الملف: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * البحث عن ملفات
     */
    public function searchFiles($query, $pageSize = 10)
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->get($this->baseUrl . '/files', [
                    'q' => "name contains '{$query}'",
                    'pageSize' => $pageSize,
                    'fields' => 'files(id, name, mimeType, size, createdTime)',
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true'
                ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('فشل البحث عن الملفات: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في البحث عن الملفات: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على Service Account Email
     */
    public function getServiceAccountEmail()
    {
        return $this->credentials['client_email'] ?? null;
    }

    /**
     * جعل المجلد عام (يمكن لأي شخص الوصول إليه)
     */
    public function makePublic($fileId)
    {
        try {
            $permission = [
                'type' => 'anyone',
                'role' => 'writer' // يسمح بالكتابة
            ];

            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->post($this->baseUrl . '/files/' . $fileId . '/permissions?supportsAllDrives=true', $permission);

            if ($response->successful()) {
                Log::info('تم جعل المجلد/الملف عام', ['file_id' => $fileId]);
                return $response->json();
            } else {
                throw new Exception('فشل جعل المجلد عام: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في جعل المجلد عام: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * سرد جميع Shared Drives المتاحة
     */
    public function listSharedDrives()
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->get('https://www.googleapis.com/drive/v3/drives', [
                    'pageSize' => 100
                ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('فشل في سرد Shared Drives: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في سرد Shared Drives: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على معلومات Shared Drive
     */
    public function getSharedDrive($driveId)
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withToken($this->accessToken)
                ->get("https://www.googleapis.com/drive/v3/drives/{$driveId}");

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('فشل في الحصول على معلومات Shared Drive: ' . $response->body());
            }
        } catch (Exception $e) {
            Log::error('خطأ في الحصول على معلومات Shared Drive: ' . $e->getMessage());
            throw $e;
        }
    }
}
