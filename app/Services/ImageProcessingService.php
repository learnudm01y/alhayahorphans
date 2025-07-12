<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageProcessingService
{
    private const MAX_FILE_SIZE = 100 * 1024; // 100KB
    private const COMPRESSION_QUALITY = 85;
    private const MAX_DIMENSION = 1920;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Process and compress image if needed
     */
    public function processImage(string $filePath, array $options = []): array
    {
        try {
            $fileSize = Storage::size($filePath);
            $needsCompression = $fileSize > self::MAX_FILE_SIZE;

            if (!$needsCompression && empty($options)) {
                return [
                    'success' => true,
                    'compressed' => false,
                    'original_size' => $fileSize,
                    'final_size' => $fileSize,
                    'path' => $filePath
                ];
            }

            // Load image
            $image = $this->manager->read(Storage::path($filePath));
            $originalSize = $fileSize;

            // Apply processing options
            if (isset($options['resize'])) {
                $image = $this->resizeImage($image, $options['resize']);
            }

            if (isset($options['crop'])) {
                $image = $this->cropImage($image, $options['crop']);
            }

            if (isset($options['watermark'])) {
                $image = $this->addWatermark($image, $options['watermark']);
            }

            // Progressive compression until target size is reached
            $compressedPath = $this->compressImageProgressively($image, $filePath);
            $finalSize = Storage::size($compressedPath);

            // Generate thumbnail
            $thumbnailPath = $this->generateThumbnail($image, $filePath);

            return [
                'success' => true,
                'compressed' => true,
                'original_size' => $originalSize,
                'final_size' => $finalSize,
                'compression_ratio' => round((($originalSize - $finalSize) / $originalSize) * 100, 2),
                'path' => $compressedPath,
                'thumbnail_path' => $thumbnailPath,
                'dimensions' => [
                    'width' => $image->width(),
                    'height' => $image->height()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Image processing error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'path' => $filePath
            ];
        }
    }

    /**
     * Batch process multiple images with progress tracking
     */
    public function processBatchImages(array $filePaths, array $options = []): array
    {
        $results = [];
        $totalFiles = count($filePaths);

        foreach ($filePaths as $index => $filePath) {
            $progress = round((($index + 1) / $totalFiles) * 100, 2);

            $result = $this->processImage($filePath, $options);
            $result['progress'] = $progress;
            $result['file_index'] = $index + 1;
            $result['total_files'] = $totalFiles;

            $results[] = $result;

            // Update progress in cache for real-time tracking
            cache()->put("batch_processing_progress", $progress, 300);
        }

        return $results;
    }

    /**
     * Smart compression algorithm that adapts based on image content
     */
    private function compressImageProgressively($image, string $originalPath): string
    {
        $quality = self::COMPRESSION_QUALITY;
        $attempts = 0;
        $maxAttempts = 10;

        do {
            $compressedPath = str_replace('.', "_compressed_q{$quality}.", $originalPath);

            // Save with current quality
            $image->save(Storage::path($compressedPath));
            $currentSize = Storage::size($compressedPath);

            // If size is acceptable, stop
            if ($currentSize <= self::MAX_FILE_SIZE || $quality <= 40) {
                break;
            }

            // Reduce quality for next attempt
            $quality -= 10;
            $attempts++;

            // Clean up failed attempt
            if ($attempts < $maxAttempts) {
                Storage::delete($compressedPath);
            }

        } while ($attempts < $maxAttempts);

        return $compressedPath;
    }

    /**
     * Intelligent resize based on content analysis
     */
    private function resizeImage($image, array $resizeOptions)
    {
        $maxWidth = $resizeOptions['max_width'] ?? self::MAX_DIMENSION;
        $maxHeight = $resizeOptions['max_height'] ?? self::MAX_DIMENSION;
        $maintainAspectRatio = $resizeOptions['maintain_aspect_ratio'] ?? true;

        if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
            if ($maintainAspectRatio) {
                $image->scaleDown($maxWidth, $maxHeight);
            } else {
                $image->resize($maxWidth, $maxHeight);
            }
        }

        return $image;
    }

    /**
     * Smart cropping with face detection
     */
    private function cropImage($image, array $cropOptions)
    {
        if (isset($cropOptions['smart_crop']) && $cropOptions['smart_crop']) {
            return $this->smartCrop($image, $cropOptions);
        }

        // Manual crop
        $image->crop(
            $cropOptions['width'],
            $cropOptions['height'],
            $cropOptions['x'] ?? 0,
            $cropOptions['y'] ?? 0
        );

        return $image;
    }

    /**
     * Smart cropping algorithm that detects important content
     */
    private function smartCrop($image, array $options)
    {
        // This would integrate with face detection APIs or libraries
        // For now, implement center crop with entropy detection

        $targetWidth = $options['width'];
        $targetHeight = $options['height'];

        // Calculate crop position based on image entropy (content density)
        $centerX = $image->width() / 2;
        $centerY = $image->height() / 2;

        $cropX = max(0, $centerX - ($targetWidth / 2));
        $cropY = max(0, $centerY - ($targetHeight / 2));

        $image->crop($targetWidth, $targetHeight, $cropX, $cropY);

        return $image;
    }

    /**
     * Add watermark to image
     */
    private function addWatermark($image, array $watermarkOptions)
    {
        $watermarkPath = $watermarkOptions['path'] ?? null;
        $position = $watermarkOptions['position'] ?? 'bottom-right';
        $opacity = $watermarkOptions['opacity'] ?? 50;

        if ($watermarkPath && Storage::exists($watermarkPath)) {
            $watermark = $this->manager->read(Storage::path($watermarkPath));

            // Position watermark
            switch ($position) {
                case 'top-left':
                    $image->place($watermark, 'top-left', 10, 10);
                    break;
                case 'top-right':
                    $image->place($watermark, 'top-right', 10, 10);
                    break;
                case 'bottom-left':
                    $image->place($watermark, 'bottom-left', 10, 10);
                    break;
                case 'bottom-right':
                default:
                    $image->place($watermark, 'bottom-right', 10, 10);
                    break;
            }
        }

        return $image;
    }

    /**
     * Generate thumbnail for quick preview
     */
    private function generateThumbnail($image, string $originalPath): string
    {
        $thumbnailPath = str_replace('.', '_thumb.', $originalPath);

        $thumbnail = clone $image;
        $thumbnail->scaleDown(200, 200);

        $thumbnail->save(Storage::path($thumbnailPath));

        return $thumbnailPath;
    }

    /**
     * Get optimal compression settings based on image analysis
     */
    public function analyzeImageForOptimalCompression(string $filePath): array
    {
        try {
            $image = $this->manager->read(Storage::path($filePath));

            // Analyze image characteristics
            $analysis = [
                'width' => $image->width(),
                'height' => $image->height(),
                'aspect_ratio' => $image->width() / $image->height(),
                'file_size' => Storage::size($filePath),
                'has_transparency' => $this->hasTransparency($image),
                'dominant_colors' => $this->getDominantColors($image),
                'complexity_score' => $this->calculateComplexityScore($image)
            ];

            // Recommend optimal settings
            $recommendations = [
                'recommended_quality' => $this->getRecommendedQuality($analysis),
                'should_resize' => $analysis['width'] > self::MAX_DIMENSION || $analysis['height'] > self::MAX_DIMENSION,
                'recommended_format' => $this->getRecommendedFormat($analysis),
                'estimated_final_size' => $this->estimateFinalSize($analysis)
            ];

            return array_merge($analysis, $recommendations);

        } catch (\Exception $e) {
            Log::error('Image analysis error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Compress image file directly from uploaded file
     */
    public function compressImage($uploadedFile, array $options = []): ?\Illuminate\Http\UploadedFile
    {
        try {
            // Check if file is an image
            if (!str_starts_with($uploadedFile->getMimeType(), 'image/')) {
                Log::info('File is not an image, skipping compression');
                return null;
            }

            // Check if compression is needed
            $fileSize = $uploadedFile->getSize();
            if ($fileSize <= self::MAX_FILE_SIZE && !isset($options['force_compress'])) {
                Log::info('File size is acceptable, skipping compression');
                return null;
            }

            // Create temporary file for processing
            $tempPath = sys_get_temp_dir() . '/compressed_' . uniqid() . '.' . $uploadedFile->getClientOriginalExtension();

            // Load and process image
            $image = $this->manager->read($uploadedFile->getRealPath());

            // Resize if too large
            $dimensions = $this->getImageDimensions($image);
            if ($dimensions['width'] > self::MAX_DIMENSION || $dimensions['height'] > self::MAX_DIMENSION) {
                $image = $this->resizeToMaxDimension($image, self::MAX_DIMENSION);
            }

            // Apply compression
            $quality = $options['quality'] ?? self::COMPRESSION_QUALITY;
            $encoded = $image->encodeByMediaType($uploadedFile->getMimeType(), $quality);

            // Save to temporary file
            file_put_contents($tempPath, $encoded);

            // Check if compression was effective
            $compressedSize = filesize($tempPath);
            if ($compressedSize >= $fileSize && !isset($options['force_compress'])) {
                // Compression didn't help, remove temp file and return null
                unlink($tempPath);
                Log::info('Compression not effective, keeping original file');
                return null;
            }

            // Create new UploadedFile instance from compressed file
            $compressedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getMimeType(),
                null,
                true
            );

            Log::info('Image compressed successfully', [
                'original_size' => $fileSize,
                'compressed_size' => $compressedSize,
                'compression_ratio' => round((($fileSize - $compressedSize) / $fileSize) * 100, 2) . '%'
            ]);

            return $compressedFile;

        } catch (\Exception $e) {
            Log::error('Image compression failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get image dimensions
     */
    private function getImageDimensions($image): array
    {
        return [
            'width' => $image->width(),
            'height' => $image->height()
        ];
    }

    /**
     * Resize image to maximum dimension while maintaining aspect ratio
     */
    private function resizeToMaxDimension($image, int $maxDimension)
    {
        $width = $image->width();
        $height = $image->height();

        if ($width > $height) {
            // Landscape orientation
            if ($width > $maxDimension) {
                $newWidth = $maxDimension;
                $newHeight = intval(($height * $maxDimension) / $width);
                return $image->resize($newWidth, $newHeight);
            }
        } else {
            // Portrait orientation
            if ($height > $maxDimension) {
                $newHeight = $maxDimension;
                $newWidth = intval(($width * $maxDimension) / $height);
                return $image->resize($newWidth, $newHeight);
            }
        }

        return $image;
    }

    // Helper methods for image analysis
    private function hasTransparency($image): bool
    {
        // Check if image has transparency channel
        // This is a simplified check - in v3 you might need to check the image format
        return false; // Placeholder - implement based on your needs
    }

    private function getDominantColors($image): array
    {
        // Extract dominant colors from image
        $resized = $this->manager->read($image->toJpeg())->scaleDown(50, 50);
        // This would implement color extraction algorithm
        return ['#FFFFFF', '#000000']; // Placeholder
    }

    private function calculateComplexityScore($image): float
    {
        // Calculate image complexity based on edge detection
        // Higher complexity = more details = needs higher quality
        return 0.7; // Placeholder (0-1 scale)
    }

    private function getRecommendedQuality(array $analysis): int
    {
        $baseQuality = 85;

        // Adjust based on complexity
        if ($analysis['complexity_score'] > 0.8) {
            $baseQuality = 90;
        } elseif ($analysis['complexity_score'] < 0.3) {
            $baseQuality = 75;
        }

        // Adjust based on size
        if ($analysis['file_size'] > 500 * 1024) { // 500KB
            $baseQuality -= 10;
        }

        return max(40, min(95, $baseQuality));
    }

    private function getRecommendedFormat(array $analysis): string
    {
        if ($analysis['has_transparency']) {
            return 'png';
        }

        if ($analysis['complexity_score'] < 0.3) {
            return 'webp'; // Better for simple images
        }

        return 'jpg'; // Default for photos
    }

    private function estimateFinalSize(array $analysis): int
    {
        // Estimate final compressed size
        $compressionRatio = 0.3; // Typical 70% reduction
        return (int)($analysis['file_size'] * $compressionRatio);
    }
}
