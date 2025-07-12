<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PdfManagementService
{
    /**
     * Process PDF file with comprehensive analysis and optimization
     */
    public function processPdfFile(string $filePath, array $options = []): array
    {
        try {
            $analysis = $this->analyzePdfFile($filePath);

            $result = [
                'success' => true,
                'original_path' => $filePath,
                'analysis' => $analysis,
                'optimizations' => []
            ];

            // Apply optimizations based on analysis and options
            if ($options['compress'] ?? true) {
                $compressionResult = $this->compressPdf($filePath, $options);
                $result['optimizations']['compression'] = $compressionResult;

                if ($compressionResult['success']) {
                    $result['optimized_path'] = $compressionResult['compressed_path'];
                }
            }

            if ($options['extract_text'] ?? true) {
                $textExtractionResult = $this->extractTextFromPdf($filePath);
                $result['optimizations']['text_extraction'] = $textExtractionResult;
            }

            if ($options['generate_thumbnails'] ?? true) {
                $thumbnailResult = $this->generatePdfThumbnails($filePath);
                $result['optimizations']['thumbnails'] = $thumbnailResult;
            }

            if ($options['ocr'] ?? false && $analysis['is_scanned']) {
                $ocrResult = $this->performOcrOnPdf($filePath);
                $result['optimizations']['ocr'] = $ocrResult;
            }

            // Archive with metadata
            if ($options['archive'] ?? true) {
                $archiveResult = $this->archivePdfWithMetadata($filePath, $result, $options);
                $result['archive_info'] = $archiveResult;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('PDF processing error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyze PDF structure and properties
     */
    public function analyzePdfFile(string $filePath): array
    {
        try {
            $fullPath = Storage::path($filePath);

            // Basic file information
            $analysis = [
                'file_size' => Storage::size($filePath),
                'last_modified' => Storage::lastModified($filePath),
                'pages' => $this->getPdfPageCount($fullPath),
                'version' => $this->getPdfVersion($fullPath),
                'is_encrypted' => $this->isPdfEncrypted($fullPath),
                'has_forms' => $this->pdfHasForms($fullPath),
                'is_scanned' => $this->isPdfScanned($fullPath),
                'text_extractable' => false,
                'images_count' => 0,
                'fonts_used' => [],
                'security_features' => [],
                'optimization_potential' => [],
                'quality_assessment' => []
            ];

            // Text extraction test
            $textSample = $this->extractTextSample($fullPath);
            $analysis['text_extractable'] = !empty(trim($textSample));
            $analysis['text_sample'] = substr($textSample, 0, 500);
            $analysis['estimated_word_count'] = str_word_count($textSample) * $analysis['pages'];

            // Image analysis
            $analysis['images_count'] = $this->countPdfImages($fullPath);
            $analysis['has_images'] = $analysis['images_count'] > 0;

            // Security analysis
            $analysis['security_features'] = $this->analyzePdfSecurity($fullPath);

            // Quality assessment
            $analysis['quality_assessment'] = $this->assessPdfQuality($fullPath, $analysis);

            // Optimization recommendations
            $analysis['optimization_potential'] = $this->generateOptimizationRecommendations($analysis);

            return $analysis;

        } catch (\Exception $e) {
            Log::error('PDF analysis error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Compress PDF with intelligent optimization
     */
    public function compressPdf(string $filePath, array $options = []): array
    {
        try {
            $originalSize = Storage::size($filePath);
            $compressionLevel = $options['compression_level'] ?? 'medium';

            // Define compression settings
            $compressionSettings = $this->getCompressionSettings($compressionLevel);

            $inputPath = Storage::path($filePath);
            $outputPath = str_replace('.pdf', '_compressed.pdf', $inputPath);

            // Use Ghostscript for PDF compression
            $gsCommand = sprintf(
                'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -dColorImageResolution=%d -dGrayImageResolution=%d -dMonoImageResolution=%d -sOutputFile="%s" "%s"',
                $compressionSettings['pdf_settings'],
                $compressionSettings['color_resolution'],
                $compressionSettings['gray_resolution'],
                $compressionSettings['mono_resolution'],
                $outputPath,
                $inputPath
            );

            $result = Process::run($gsCommand);

            if ($result->successful() && file_exists($outputPath)) {
                $compressedSize = filesize($outputPath);
                $compressionRatio = (($originalSize - $compressedSize) / $originalSize) * 100;

                // Move compressed file to proper storage location
                $compressedStoragePath = str_replace('.pdf', '_compressed.pdf', $filePath);
                Storage::put($compressedStoragePath, file_get_contents($outputPath));
                unlink($outputPath); // Clean up temp file

                return [
                    'success' => true,
                    'compressed_path' => $compressedStoragePath,
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'compression_ratio' => round($compressionRatio, 2),
                    'size_reduction' => $originalSize - $compressedSize
                ];
            }

            throw new \Exception('PDF compression failed: ' . $result->errorOutput());

        } catch (\Exception $e) {
            Log::error('PDF compression error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract text content from PDF
     */
    public function extractTextFromPdf(string $filePath): array
    {
        try {
            $inputPath = Storage::path($filePath);

            // Try multiple methods for text extraction
            $extractedText = '';

            // Method 1: pdftotext (if available)
            $pdftotextCommand = "pdftotext \"$inputPath\" -";
            $result = Process::run($pdftotextCommand);

            if ($result->successful()) {
                $extractedText = $result->output();
            } else {
                // Method 2: Fallback to basic extraction
                $extractedText = $this->basicPdfTextExtraction($inputPath);
            }

            // Clean and structure the text
            $cleanedText = $this->cleanExtractedText($extractedText);

            // Extract metadata from text
            $textMetadata = $this->analyzeExtractedText($cleanedText);

            return [
                'success' => true,
                'text_content' => $cleanedText,
                'word_count' => str_word_count($cleanedText),
                'character_count' => strlen($cleanedText),
                'languages_detected' => $textMetadata['languages'] ?? [],
                'keywords' => $textMetadata['keywords'] ?? [],
                'confidence' => $textMetadata['confidence'] ?? 0.8
            ];

        } catch (\Exception $e) {
            Log::error('PDF text extraction error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate thumbnails from PDF pages
     */
    public function generatePdfThumbnails(string $filePath, array $options = []): array
    {
        try {
            $inputPath = Storage::path($filePath);
            $thumbnailsFolder = dirname($filePath) . '/thumbnails/' . pathinfo($filePath, PATHINFO_FILENAME);

            // Create thumbnails directory
            Storage::makeDirectory($thumbnailsFolder);

            $resolution = $options['resolution'] ?? 150;
            $format = $options['format'] ?? 'png';
            $maxPages = $options['max_pages'] ?? 5; // Limit thumbnails for large PDFs

            $thumbnails = [];

            // Generate thumbnails using ImageMagick
            for ($page = 0; $page < $maxPages; $page++) {
                $outputPath = Storage::path($thumbnailsFolder) . "/page_{$page}.{$format}";

                $convertCommand = sprintf(
                    'convert -density %d "%s[%d]" -quality 90 -resize 200x300 "%s"',
                    $resolution,
                    $inputPath,
                    $page,
                    $outputPath
                );

                $result = Process::run($convertCommand);

                if ($result->successful() && file_exists($outputPath)) {
                    $thumbnailStoragePath = $thumbnailsFolder . "/page_{$page}.{$format}";
                    $thumbnails[] = [
                        'page' => $page + 1,
                        'path' => $thumbnailStoragePath,
                        'url' => Storage::url($thumbnailStoragePath),
                        'size' => filesize($outputPath)
                    ];
                }
            }

            return [
                'success' => true,
                'thumbnails' => $thumbnails,
                'total_generated' => count($thumbnails),
                'thumbnails_folder' => $thumbnailsFolder
            ];

        } catch (\Exception $e) {
            Log::error('PDF thumbnail generation error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Perform OCR on scanned PDF
     */
    public function performOcrOnPdf(string $filePath, array $options = []): array
    {
        try {
            $inputPath = Storage::path($filePath);
            $language = $options['language'] ?? 'ara+eng'; // Arabic + English
            $outputPath = str_replace('.pdf', '_ocr.pdf', $inputPath);

            // Use Tesseract OCR with PDF output
            $ocrCommand = sprintf(
                'ocrmypdf --language %s --pdf-renderer hocr --optimize 1 "%s" "%s"',
                $language,
                $inputPath,
                $outputPath
            );

            $result = Process::run($ocrCommand);

            if ($result->successful() && file_exists($outputPath)) {
                // Extract text from OCR'd PDF
                $textResult = $this->extractTextFromPdf(str_replace(Storage::path(''), '', $outputPath));

                $ocrStoragePath = str_replace('.pdf', '_ocr.pdf', $filePath);
                Storage::put($ocrStoragePath, file_get_contents($outputPath));
                unlink($outputPath); // Clean up temp file

                return [
                    'success' => true,
                    'ocr_pdf_path' => $ocrStoragePath,
                    'extracted_text' => $textResult['text_content'] ?? '',
                    'confidence' => $this->calculateOcrConfidence($textResult),
                    'language' => $language
                ];
            }

            throw new \Exception('OCR processing failed: ' . $result->errorOutput());

        } catch (\Exception $e) {
            Log::error('PDF OCR error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Archive PDF with comprehensive metadata
     */
    public function archivePdfWithMetadata(string $filePath, array $processingResults, array $options = []): array
    {
        try {
            $recordNumber = $options['record_number'] ?? 'general';
            $archiveFolder = "archives/pdf/" . str_pad($recordNumber, 6, '0', STR_PAD_LEFT);

            $timestamp = now()->format('Ymd_His');
            $originalName = pathinfo($filePath, PATHINFO_FILENAME);

            // Archive original and processed files
            $archivedFiles = [];

            // Original file
            $originalArchivePath = "{$archiveFolder}/original_{$originalName}_{$timestamp}.pdf";
            Storage::copy($filePath, $originalArchivePath);
            $archivedFiles['original'] = $originalArchivePath;

            // Compressed version if available
            if (isset($processingResults['optimizations']['compression']['compressed_path'])) {
                $compressedArchivePath = "{$archiveFolder}/compressed_{$originalName}_{$timestamp}.pdf";
                Storage::copy($processingResults['optimizations']['compression']['compressed_path'], $compressedArchivePath);
                $archivedFiles['compressed'] = $compressedArchivePath;
            }

            // OCR version if available
            if (isset($processingResults['optimizations']['ocr']['ocr_pdf_path'])) {
                $ocrArchivePath = "{$archiveFolder}/ocr_{$originalName}_{$timestamp}.pdf";
                Storage::copy($processingResults['optimizations']['ocr']['ocr_pdf_path'], $ocrArchivePath);
                $archivedFiles['ocr'] = $ocrArchivePath;
            }

            // Create comprehensive metadata file
            $metadata = [
                'file_info' => [
                    'original_name' => basename($filePath),
                    'archive_date' => now()->toISOString(),
                    'record_number' => $recordNumber,
                    'file_hash' => hash_file('md5', Storage::path($filePath))
                ],
                'processing_results' => $processingResults,
                'archived_files' => $archivedFiles,
                'searchable_content' => $processingResults['optimizations']['text_extraction']['text_content'] ?? '',
                'keywords' => $this->extractKeywords($processingResults),
                'archive_settings' => $options
            ];

            $metadataPath = "{$archiveFolder}/metadata_{$originalName}_{$timestamp}.json";
            Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));

            // Register in archive database
            $archiveRecord = $this->registerArchivedPdf($metadata, $archivedFiles, $metadataPath);

            return [
                'success' => true,
                'archive_id' => $archiveRecord['id'],
                'archived_files' => $archivedFiles,
                'metadata_path' => $metadataPath,
                'searchable_content_length' => strlen($metadata['searchable_content'])
            ];

        } catch (\Exception $e) {
            Log::error('PDF archive error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Search archived PDFs with advanced filters
     */
    public function searchArchivedPdfs(string $query, array $filters = []): array
    {
        try {
            // Implementation would search through archived PDF metadata and content
            // This is a placeholder for the actual search implementation

            return [
                'success' => true,
                'total_results' => 0,
                'results' => [],
                'search_time' => 0,
                'query' => $query,
                'filters_applied' => $filters
            ];

        } catch (\Exception $e) {
            Log::error('PDF search error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Private helper methods
    private function getPdfPageCount(string $filePath): int
    {
        $command = "pdfinfo \"$filePath\" | grep Pages";
        $result = Process::run($command);

        if ($result->successful()) {
            preg_match('/Pages:\s*(\d+)/', $result->output(), $matches);
            return (int)($matches[1] ?? 0);
        }

        return 0;
    }

    private function getPdfVersion(string $filePath): string
    {
        $command = "pdfinfo \"$filePath\" | grep 'PDF version'";
        $result = Process::run($command);

        if ($result->successful()) {
            preg_match('/PDF version:\s*(.+)/', $result->output(), $matches);
            return trim($matches[1] ?? 'Unknown');
        }

        return 'Unknown';
    }

    private function isPdfEncrypted(string $filePath): bool
    {
        $command = "pdfinfo \"$filePath\" | grep Encrypted";
        $result = Process::run($command);

        return $result->successful() && strpos($result->output(), 'yes') !== false;
    }

    private function pdfHasForms(string $filePath): bool
    {
        // Implementation to check for PDF forms
        return false; // Placeholder
    }

    private function isPdfScanned(string $filePath): bool
    {
        // Heuristic: if text extraction yields very little text but file is large,
        // it's likely a scanned document
        $textSample = $this->extractTextSample($filePath);
        $wordCount = str_word_count($textSample);
        $fileSize = filesize($filePath);

        // If less than 10 words per MB, likely scanned
        return ($wordCount / ($fileSize / 1048576)) < 10;
    }

    private function extractTextSample(string $filePath): string
    {
        $command = "pdftotext \"$filePath\" - | head -n 50";
        $result = Process::run($command);

        return $result->successful() ? $result->output() : '';
    }

    private function countPdfImages(string $filePath): int
    {
        $command = "pdfimages -list \"$filePath\" | wc -l";
        $result = Process::run($command);

        if ($result->successful()) {
            return max(0, (int)$result->output() - 2); // Subtract header lines
        }

        return 0;
    }

    private function analyzePdfSecurity(string $filePath): array
    {
        // Analyze PDF security features
        return [
            'is_encrypted' => $this->isPdfEncrypted($filePath),
            'has_passwords' => false,
            'printing_allowed' => true,
            'copying_allowed' => true,
            'modification_allowed' => true
        ];
    }

    private function assessPdfQuality(string $filePath, array $analysis): array
    {
        $quality = ['score' => 0, 'issues' => [], 'recommendations' => []];

        // Check file size vs page count ratio
        $sizePerPage = $analysis['file_size'] / max(1, $analysis['pages']);
        if ($sizePerPage > 1048576) { // > 1MB per page
            $quality['issues'][] = 'Large file size per page';
            $quality['recommendations'][] = 'Consider compression';
        }

        // Check text extractability
        if (!$analysis['text_extractable'] && !$analysis['is_scanned']) {
            $quality['issues'][] = 'Text not extractable';
            $quality['recommendations'][] = 'May need OCR processing';
        }

        // Calculate overall quality score
        $quality['score'] = max(0, 100 - (count($quality['issues']) * 20));

        return $quality;
    }

    private function generateOptimizationRecommendations(array $analysis): array
    {
        $recommendations = [];

        if ($analysis['file_size'] > 5242880) { // > 5MB
            $recommendations[] = 'File size is large - compression recommended';
        }

        if ($analysis['is_scanned'] && !$analysis['text_extractable']) {
            $recommendations[] = 'Scanned document detected - OCR processing recommended';
        }

        if ($analysis['images_count'] > 10) {
            $recommendations[] = 'Many images detected - image compression may help';
        }

        return $recommendations;
    }

    private function getCompressionSettings(string $level): array
    {
        return match($level) {
            'low' => [
                'pdf_settings' => 'prepress',
                'color_resolution' => 300,
                'gray_resolution' => 300,
                'mono_resolution' => 1200
            ],
            'medium' => [
                'pdf_settings' => 'printer',
                'color_resolution' => 150,
                'gray_resolution' => 150,
                'mono_resolution' => 600
            ],
            'high' => [
                'pdf_settings' => 'ebook',
                'color_resolution' => 72,
                'gray_resolution' => 72,
                'mono_resolution' => 300
            ],
            default => [
                'pdf_settings' => 'default',
                'color_resolution' => 150,
                'gray_resolution' => 150,
                'mono_resolution' => 600
            ]
        };
    }

    private function cleanExtractedText(string $text): string
    {
        // Clean up extracted text
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $text = trim($text);
        return $text;
    }

    private function analyzeExtractedText(string $text): array
    {
        // Analyze extracted text for metadata
        return [
            'languages' => ['ar', 'en'], // Placeholder
            'keywords' => $this->extractKeywords(['optimizations' => ['text_extraction' => ['text_content' => $text]]]),
            'confidence' => 0.9
        ];
    }

    private function basicPdfTextExtraction(string $filePath): string
    {
        // Fallback text extraction method
        return '';
    }

    private function calculateOcrConfidence(array $textResult): float
    {
        // Calculate OCR confidence based on various factors
        return $textResult['confidence'] ?? 0.8;
    }

    private function extractKeywords(array $processingResults): array
    {
        $text = $processingResults['optimizations']['text_extraction']['text_content'] ?? '';

        if (empty($text)) {
            return [];
        }

        // Simple keyword extraction (in practice, you'd use more sophisticated NLP)
        $words = str_word_count(strtolower($text), 1);
        $wordCounts = array_count_values($words);

        // Filter out common words and short words
        $commonWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $filteredWords = array_filter($wordCounts, function($word) use ($commonWords) {
            return strlen($word) > 3 && !in_array($word, $commonWords);
        }, ARRAY_FILTER_USE_KEY);

        // Get top 10 keywords
        arsort($filteredWords);
        return array_keys(array_slice($filteredWords, 0, 10));
    }

    private function registerArchivedPdf(array $metadata, array $archivedFiles, string $metadataPath): array
    {
        // Register archived PDF in database
        // This would insert into your archived_pdfs table
        return ['id' => 1]; // Placeholder
    }
}
