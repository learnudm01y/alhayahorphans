<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockSuspiciousStoragePaths;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * SecureFileSystemTest — يغطي middleware حجب المسارات المشبوهة
 * (الملفات الأخرى: FileSecurityHelper / SecureFileAccess / SecureFileController
 *  ملفات فارغة في المستودع — لا يوجد ما يُختبر منها حالياً).
 */
class SecureFileSystemTest extends TestCase
{
    private function makeRequest(string $uri): Request
    {
        return Request::create($uri, 'GET');
    }

    private function runMiddleware(Request $request)
    {
        $mw = new BlockSuspiciousStoragePaths();
        return $mw->handle($request, function ($req) {
            return response('passed', 200);
        });
    }

    public function test_normal_storage_path_passes_through(): void
    {
        $res = $this->runMiddleware($this->makeRequest('/storage/documents/file.pdf'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('passed', $res->getContent());
    }

    public function test_windows_drive_letter_pattern_is_blocked(): void
    {
        $res = $this->runMiddleware($this->makeRequest('/storage/c:/windows/system32'));
        $this->assertSame(200, $res->getStatusCode());
        // رد فارغ (octet-stream) وليس محتوى passed
        $this->assertStringNotContainsString('passed', (string) $res->getContent());
    }

    public function test_unit_test_pattern_is_blocked(): void
    {
        $res = $this->runMiddleware($this->makeRequest('/storage/foo/unit%20test/bar'));
        $this->assertStringNotContainsString('passed', (string) $res->getContent());
    }

    public function test_literal_aso_copy_pattern_is_blocked(): void
    {
        // middleware يستخدم stripos (مطابقة حرفية) وليس preg_match
        $res = $this->runMiddleware($this->makeRequest('/storage/.*ASO.*Copy.*/file'));
        $this->assertStringNotContainsString('passed', (string) $res->getContent());
    }

    public function test_plain_aso_copy_path_is_not_blocked_by_literal_pattern(): void
    {
        // '/storage/ASO Copy/...' لا يحوي النص الحرفي '/storage/.*ASO.*Copy.*/'
        $res = $this->runMiddleware($this->makeRequest('/storage/ASO Copy/secret.pdf'));
        $this->assertSame('passed', $res->getContent());
    }

    public function test_uppercase_drive_letter_is_blocked(): void
    {
        $res = $this->runMiddleware($this->makeRequest('/storage/C:/inetpub/wwwroot'));
        $this->assertStringNotContainsString('passed', (string) $res->getContent());
    }
}
