<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExcelManagementService
{
    /**
     * Process Excel file with options for database insertion or archival
     */
    public function processExcelFile(string $filePath, string $processingMode = 'analyze', array $options = []): array
    {
        try {
            $analysisResult = $this->analyzeExcelFile($filePath);

            return match($processingMode) {
                'analyze' => $analysisResult,
                'import_to_database' => $this->importExcelToDatabase($filePath, $options),
                'archive' => $this->archiveExcelFile($filePath, $options),
                'extract_data' => $this->extractDataFromExcel($filePath, $options),
                default => throw new \InvalidArgumentException('Invalid processing mode')
            };

        } catch (\Exception $e) {
            Log::error('Excel processing error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyze Excel file structure and content
     */
    public function analyzeExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load(Storage::path($filePath));
            $analysis = [
                'success' => true,
                'file_info' => [
                    'path' => $filePath,
                    'size' => Storage::size($filePath),
                    'last_modified' => Storage::lastModified($filePath)
                ],
                'sheets' => []
            ];

            foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
                $sheetAnalysis = [
                    'index' => $sheetIndex,
                    'name' => $sheet->getTitle(),
                    'dimensions' => [
                        'highest_row' => $sheet->getHighestRow(),
                        'highest_column' => $sheet->getHighestColumn(),
                        'total_cells' => $sheet->getHighestRow() * $this->columnLetterToNumber($sheet->getHighestColumn())
                    ],
                    'data_types' => $this->analyzeDataTypes($sheet),
                    'has_headers' => $this->detectHeaders($sheet),
                    'empty_rows' => $this->countEmptyRows($sheet),
                    'sample_data' => $this->getSampleData($sheet, 5)
                ];

                // Detect potential database tables
                if ($sheetAnalysis['has_headers'] && $sheetAnalysis['dimensions']['highest_row'] > 1) {
                    $sheetAnalysis['database_suggestions'] = $this->suggestDatabaseStructure($sheet);
                }

                $analysis['sheets'][] = $sheetAnalysis;
            }

            // Overall file recommendations
            $analysis['recommendations'] = $this->generateProcessingRecommendations($analysis);

            return $analysis;

        } catch (\Exception $e) {
            Log::error('Excel analysis error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import Excel data to database with smart mapping
     */
    public function importExcelToDatabase(string $filePath, array $options): array
    {
        try {
            DB::beginTransaction();

            $spreadsheet = IOFactory::load(Storage::path($filePath));
            $results = [];

            foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
                if (isset($options['sheets']) && !in_array($sheetIndex, $options['sheets'])) {
                    continue; // Skip sheets not selected for import
                }

                $sheetResult = $this->importSheetToDatabase($sheet, $options, $sheetIndex);
                $results[] = $sheetResult;
            }

            DB::commit();

            // Archive the original file after successful import
            $archiveResult = $this->archiveExcelFile($filePath, [
                'reason' => 'imported_to_database',
                'import_results' => $results
            ]);

            return [
                'success' => true,
                'imported_sheets' => count($results),
                'total_rows_imported' => array_sum(array_column($results, 'rows_imported')),
                'details' => $results,
                'archive_info' => $archiveResult
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Excel import error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Archive Excel file with metadata and indexing
     */
    public function archiveExcelFile(string $filePath, array $options = []): array
    {
        try {
            $recordNumber = $options['record_number'] ?? 'general';
            $archiveFolder = "archives/excel/" . str_pad($recordNumber, 6, '0', STR_PAD_LEFT);

            // Generate archive filename with metadata
            $timestamp = now()->format('Ymd_His');
            $originalName = pathinfo($filePath, PATHINFO_FILENAME);
            $archiveName = "{$originalName}_archived_{$timestamp}.xlsx";

            // Copy to archive location
            $archivePath = "{$archiveFolder}/{$archiveName}";
            Storage::copy($filePath, $archivePath);

            // Extract and save metadata
            $metadata = $this->extractExcelMetadata($filePath);
            $metadataPath = "{$archiveFolder}/{$originalName}_metadata_{$timestamp}.json";
            Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));

            // Create searchable index
            $indexData = $this->createSearchableIndex($filePath, $metadata);
            $indexPath = "{$archiveFolder}/{$originalName}_index_{$timestamp}.json";
            Storage::put($indexPath, json_encode($indexData, JSON_PRETTY_PRINT));

            // Register in archive database
            $archiveRecord = $this->registerArchivedFile([
                'original_path' => $filePath,
                'archive_path' => $archivePath,
                'metadata_path' => $metadataPath,
                'index_path' => $indexPath,
                'record_number' => $recordNumber,
                'archive_reason' => $options['reason'] ?? 'manual_archive',
                'file_hash' => hash_file('md5', Storage::path($filePath)),
                'metadata' => $metadata
            ]);

            return [
                'success' => true,
                'archive_id' => $archiveRecord['id'],
                'archive_path' => $archivePath,
                'metadata_path' => $metadataPath,
                'index_path' => $indexPath,
                'searchable_content' => count($indexData['searchable_content'])
            ];

        } catch (\Exception $e) {
            Log::error('Excel archive error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract specific data from Excel based on criteria
     */
    public function extractDataFromExcel(string $filePath, array $criteria): array
    {
        try {
            $spreadsheet = IOFactory::load(Storage::path($filePath));
            $extractedData = [];

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetData = $this->extractSheetData($sheet, $criteria);
                if (!empty($sheetData)) {
                    $extractedData[$sheet->getTitle()] = $sheetData;
                }
            }

            return [
                'success' => true,
                'extracted_data' => $extractedData,
                'total_records' => array_sum(array_map('count', $extractedData))
            ];

        } catch (\Exception $e) {
            Log::error('Excel extraction error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export data to Excel with advanced formatting
     */
    public function exportToExcel(array $data, array $options = []): array
    {
        try {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0); // Remove default sheet

            foreach ($data as $sheetName => $sheetData) {
                $worksheet = $spreadsheet->createSheet();
                $worksheet->setTitle($sheetName);

                $this->populateWorksheet($worksheet, $sheetData, $options);
                $this->applyFormatting($worksheet, $options);
            }

            // Generate filename
            $filename = $options['filename'] ?? 'export_' . now()->format('Ymd_His') . '.xlsx';
            $exportPath = "exports/excel/{$filename}";

            // Save file
            $writer = new Xlsx($spreadsheet);
            $writer->save(Storage::path($exportPath));

            return [
                'success' => true,
                'file_path' => $exportPath,
                'download_url' => Storage::url($exportPath),
                'file_size' => Storage::size($exportPath)
            ];

        } catch (\Exception $e) {
            Log::error('Excel export error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Search through archived Excel files
     */
    public function searchArchivedExcelFiles(string $query, array $filters = []): array
    {
        try {
            // Search in indexed content
            $searchResults = DB::table('archived_excel_files')
                ->where(function($q) use ($query) {
                    $q->where('metadata->filename', 'LIKE', "%{$query}%")
                      ->orWhere('searchable_content', 'LIKE', "%{$query}%");
                });

            // Apply filters
            if (isset($filters['record_number'])) {
                $searchResults->where('record_number', $filters['record_number']);
            }

            if (isset($filters['date_from'])) {
                $searchResults->where('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $searchResults->where('created_at', '<=', $filters['date_to']);
            }

            $results = $searchResults->get();

            return [
                'success' => true,
                'total_results' => $results->count(),
                'results' => $results->map(function($result) {
                    return [
                        'id' => $result->id,
                        'filename' => $result->metadata['filename'] ?? 'Unknown',
                        'record_number' => $result->record_number,
                        'archive_date' => $result->created_at,
                        'file_size' => $result->metadata['file_size'] ?? 0,
                        'sheets_count' => count($result->metadata['sheets'] ?? []),
                        'download_url' => Storage::url($result->archive_path)
                    ];
                })
            ];

        } catch (\Exception $e) {
            Log::error('Excel search error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Private helper methods
    private function analyzeDataTypes($sheet): array
    {
        $types = ['text' => 0, 'number' => 0, 'date' => 0, 'formula' => 0, 'empty' => 0];

        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();

                if (empty($value)) {
                    $types['empty']++;
                } elseif (is_numeric($value)) {
                    $types['number']++;
                } elseif ($cell->getDataType() === 'f') {
                    $types['formula']++;
                } else {
                    $types['text']++;
                }
            }
        }

        return $types;
    }

    private function detectHeaders($sheet): bool
    {
        $firstRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1')[0];

        // Check if first row contains mostly text and appears to be headers
        $textCount = 0;
        $totalCells = count($firstRow);

        foreach ($firstRow as $cell) {
            if (!empty($cell) && !is_numeric($cell)) {
                $textCount++;
            }
        }

        return ($textCount / $totalCells) > 0.7; // 70% text indicates headers
    }

    private function countEmptyRows($sheet): int
    {
        $emptyRows = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $isEmpty = true;
            foreach ($row->getCellIterator() as $cell) {
                if (!empty($cell->getValue())) {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) {
                $emptyRows++;
            }
        }

        return $emptyRows;
    }

    private function getSampleData($sheet, int $rows = 5): array
    {
        $sampleData = [];
        $rowCount = 0;

        foreach ($sheet->getRowIterator() as $row) {
            if ($rowCount >= $rows) break;

            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $rowData[] = $cell->getValue();
            }
            $sampleData[] = $rowData;
            $rowCount++;
        }

        return $sampleData;
    }

    private function suggestDatabaseStructure($sheet): array
    {
        $headers = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1')[0];
        $sampleData = $this->getSampleData($sheet, 10);

        $suggestions = [
            'table_name' => $this->generateTableName($sheet->getTitle()),
            'columns' => []
        ];

        foreach ($headers as $index => $header) {
            if (empty($header)) continue;

            $columnData = array_column($sampleData, $index);
            $columnData = array_filter($columnData); // Remove empty values

            $suggestions['columns'][] = [
                'name' => $this->sanitizeColumnName($header),
                'type' => $this->suggestColumnType($columnData),
                'nullable' => count($columnData) < count($sampleData),
                'original_header' => $header
            ];
        }

        return $suggestions;
    }

    private function generateTableName(string $sheetTitle): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $sheetTitle));
    }

    private function sanitizeColumnName(string $header): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $header));
    }

    private function suggestColumnType(array $data): string
    {
        if (empty($data)) return 'text';

        $numericCount = 0;
        $dateCount = 0;
        $maxLength = 0;

        foreach ($data as $value) {
            if (is_numeric($value)) {
                $numericCount++;
            }

            if (strtotime($value) !== false) {
                $dateCount++;
            }

            $maxLength = max($maxLength, strlen($value));
        }

        $totalCount = count($data);

        if ($numericCount / $totalCount > 0.8) {
            return strpos(implode('', $data), '.') !== false ? 'decimal' : 'integer';
        }

        if ($dateCount / $totalCount > 0.8) {
            return 'date';
        }

        return $maxLength > 255 ? 'text' : 'varchar';
    }

    private function generateProcessingRecommendations(array $analysis): array
    {
        $recommendations = [];

        $totalSheets = count($analysis['sheets']);
        $totalRows = array_sum(array_column($analysis['sheets'], 'dimensions.highest_row'));

        if ($totalSheets > 1) {
            $recommendations[] = 'File contains multiple sheets - consider processing each sheet separately';
        }

        if ($totalRows > 10000) {
            $recommendations[] = 'Large dataset detected - consider batch processing for better performance';
        }

        foreach ($analysis['sheets'] as $sheet) {
            if ($sheet['has_headers'] && $sheet['dimensions']['highest_row'] > 1) {
                $recommendations[] = "Sheet '{$sheet['name']}' appears suitable for database import";
            }
        }

        return $recommendations;
    }

    private function columnLetterToNumber(string $columnLetter): int
    {
        $columnNumber = 0;
        for ($i = 0; $i < strlen($columnLetter); $i++) {
            $columnNumber = $columnNumber * 26 + (ord($columnLetter[$i]) - ord('A') + 1);
        }
        return $columnNumber;
    }

    private function importSheetToDatabase($sheet, array $options, int $sheetIndex): array
    {
        // Implementation for importing sheet data to database
        // This would contain the actual database insertion logic
        return [
            'sheet_index' => $sheetIndex,
            'sheet_name' => $sheet->getTitle(),
            'rows_imported' => 0,
            'errors' => []
        ];
    }

    private function extractExcelMetadata(string $filePath): array
    {
        // Implementation for extracting comprehensive metadata
        return [
            'filename' => basename($filePath),
            'file_size' => Storage::size($filePath),
            'created_at' => now()->toISOString()
        ];
    }

    private function createSearchableIndex(string $filePath, array $metadata): array
    {
        // Implementation for creating searchable index
        return [
            'searchable_content' => [],
            'keywords' => []
        ];
    }

    private function registerArchivedFile(array $data): array
    {
        // Implementation for registering archived file in database
        return ['id' => 1];
    }

    private function extractSheetData($sheet, array $criteria): array
    {
        // Implementation for extracting data based on criteria
        return [];
    }

    private function populateWorksheet($worksheet, array $data, array $options): void
    {
        // Implementation for populating worksheet with data
    }

    private function applyFormatting($worksheet, array $options): void
    {
        // Implementation for applying Excel formatting
    }

    /**
     * Extract compressed file (ZIP, RAR, 7Z)
     */
    private function extractCompressedFile(string $compressedFilePath): string
    {
        $extractPath = sys_get_temp_dir() . '/excel_extract_' . uniqid();
        mkdir($extractPath, 0755, true);

        $extension = strtolower(pathinfo($compressedFilePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'zip':
                $zip = new \ZipArchive();
                if ($zip->open($compressedFilePath) === TRUE) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                } else {
                    throw new \Exception('Failed to open ZIP file');
                }
                break;

            default:
                throw new \Exception("Unsupported archive format: {$extension}");
        }

        return $extractPath;
    }

    /**
     * Find all Excel files in directory
     */
    private function findExcelFiles(string $directory): array
    {
        $excelFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
                    $excelFiles[] = $file->getPathname();
                }
            }
        }

        return $excelFiles;
    }

    /**
     * Find all image files in directory
     */
    private function findImageFiles(string $directory): array
    {
        $imageFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $imageFiles[] = $file->getPathname();
                }
            }
        }

        return $imageFiles;
    }

    /**
     * Organize extracted image based on filename pattern
     */
    private function organizeExtractedImage(string $imagePath, array $options): void
    {
        $filename = basename($imagePath);

        // Extract identity number from filename (D_80456670_1.jpg -> 80456670)
        if (preg_match('/D_(\d+)_\d+\.\w+/', $filename, $matches)) {
            $identityNumber = $matches[1];
            $recordNumber = $options['record_number'] ?? 'imported_' . date('Ymd');

            // Create organized path
            $organizePath = "images/{$recordNumber}";
            $targetPath = storage_path("app/public/{$organizePath}");

            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            // Copy and rename
            $newFilename = time() . '_' . $filename;
            $finalPath = "{$targetPath}/{$newFilename}";
            copy($imagePath, $finalPath);

            // Save to attachments table
            DB::table('attachments')->insert([
                'record_number' => $recordNumber,
                'data_id_number' => $identityNumber,
                'original_name' => $filename,
                'file_path' => "{$organizePath}/{$newFilename}",
                'file_size' => filesize($imagePath),
                'mime_type' => mime_content_type($imagePath),
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info("Organized extracted image", [
                'data_id_number' => $identityNumber,
                'record_number' => $recordNumber,
                'original_file' => $filename
            ]);
        }
    }

    /**
     * Clean up extracted temporary folder
     */
    private function cleanupExtractedFolder(string $extractPath): void
    {
        if (is_dir($extractPath)) {
            $this->deleteDirectory($extractPath);
        }
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateValue): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        try {
            // Handle Excel serial date
            if (is_numeric($dateValue)) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                return $date->format('Y-m-d');
            }

            // Handle string dates
            $date = new \DateTime($dateValue);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$dateValue}");
            return null;
        }
    }

    /**
     * Parse float value safely
     */
    private function parseFloat($value): ?float
    {
        if (empty($value)) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
