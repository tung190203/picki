<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;

class ImageOptimizationService
{
    protected $manager;

    public function __construct()
    {
        // Khởi tạo ImageManager với GD driver
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Tối ưu và lưu ảnh với nhiều kích thước
     */
    public function optimizeAndSave($file, $path, $sizes = [])
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $image = $this->manager->read($file);

        // Nếu không có sizes, chỉ lưu ảnh gốc đã tối ưu
        if (empty($sizes)) {
            $optimized = $image->scaleDown(width: 1920);
            $encoded = $optimized->toJpeg(quality: 80);
            Storage::put($path . '/' . $filename, $encoded);

            return [
                'original' => $path . '/' . $filename
            ];
        }

        // Lưu nhiều kích thước
        $paths = [];
        foreach ($sizes as $sizeName => $dimensions) {
            $sizeFilename = $sizeName . '_' . $filename;

            if (isset($dimensions['width']) && isset($dimensions['height'])) {
                // Resize và crop
                $resized = $image->cover($dimensions['width'], $dimensions['height']);
            } else {
                // Chỉ scale theo chiều rộng
                $resized = $image->scaleDown(width: $dimensions['width']);
            }

            $encoded = $resized->toJpeg(quality: $dimensions['quality'] ?? 80);
            Storage::put($path . '/' . $sizeFilename, $encoded);

            $paths[$sizeName] = $path . '/' . $sizeFilename;
        }

        return $paths;
    }

    /**
     * Tối ưu ảnh đơn giản
     */
    public function optimize($file, $path, $maxWidth = 1920, $quality = 80)
    {
        if ($file === null || ! $file->isValid()) {
            throw new \InvalidArgumentException('File ảnh không hợp lệ hoặc không tồn tại.');
        }

        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $image = $this->manager->read($file);

        // Resize nếu ảnh lớn hơn maxWidth
        $optimized = $image->scaleDown(width: $maxWidth);

        // Encode với quality được chỉ định
        $encoded = $optimized->toJpeg(quality: $quality);

        // Lưu vào storage
        Storage::disk('public')->put($path . '/' . $filename, $encoded);

        return $path . '/' . $filename;
    }

    /**
     * Chuyển đổi sang WebP
     */
    public function convertToWebP($file, $path, $maxWidth = 1920, $quality = 80)
    {
        $filename = time() . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.webp';
        $image = $this->manager->read($file);

        $optimized = $image->scaleDown(width: $maxWidth);
        $encoded = $optimized->toWebp(quality: $quality);

        Storage::put($path . '/' . $filename, $encoded);

        return $path . '/' . $filename;
    }

    /**
     * Xử lý ảnh upload: resize + convert sang WebP + lưu vào storage.
     * Dùng cho poster và QR code trong tournament/mini-tournament.
     *
     * @param mixed $file  UploadedFile hoặc file object
     * @param string $folder  Thư mục lưu (e.g. 'posters', 'qr_codes', 'tournaments/posters')
     * @param string $prefix  Prefix tên file (e.g. 'poster_', 'qr_')
     * @param int $maxWidth  Chiều rộng tối đa (poster=1920, qr=800)
     * @param int $quality  Chất lượng WebP (poster=80, qr=75)
     * @return string  Relative path đã lưu (e.g. 'posters/poster_1234567890_abc.webp')
     */
    public function processAndSaveImage(
        $file,
        string $folder,
        string $prefix = '',
        int $maxWidth = 1920,
        int $quality = 80
    ): string {
        $filename = $prefix . time() . '_' . uniqid() . '.webp';
        $image = $this->manager->read($file);
        $optimized = $image->scaleDown(width: $maxWidth);
        $encoded = $optimized->toWebp(quality: $quality);
        Storage::disk('public')->put($folder . '/' . $filename, $encoded);
        return $folder . '/' . $filename;
    }

    /**
     * Tạo thumbnail
     */
    public function createThumbnail($file, $path, $width = 300, $height = 300)
    {
        $filename = 'thumb_' . time() . '_' . $file->getClientOriginalName();
        $image = $this->manager->read($file);

        // Cover sẽ crop ảnh theo tỉ lệ
        $thumbnail = $image->cover($width, $height);
        $encoded = $thumbnail->toJpeg(quality: 85);

        Storage::put($path . '/' . $filename, $encoded);

        return $path . '/' . $filename;
    }

    /**
     * Xóa ảnh cũ từ storage
     */
    public function deleteOldImage($url)
    {
        if (empty($url)) {
            return;
        }

        // Extract relative path: handle both full URL and relative path
        $path = $url;
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = str_replace(asset('storage/') . '/', '', $url);
        }

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
    /**
     * Tối ưu avatar với nhiều size
     */
    public function optimizeAvatar($file, $folder = 'avatars')
    {
        $filename = time() . '_' . uniqid() . '.jpg';
        $image = $this->manager->read($file);

        $sizes = [
            'original' => ['size' => 800, 'quality' => 90],
            'medium' => ['size' => 400, 'quality' => 85],
            'thumbnail' => ['size' => 150, 'quality' => 85],
        ];

        $paths = [];
        foreach ($sizes as $key => $config) {
            $sizeFilename = $key === 'original' ? $filename : "{$key}_{$filename}";
            $resized = $image->cover($config['size'], $config['size']);
            $encoded = $resized->toJpeg(quality: $config['quality']);

            $fullPath = "{$folder}/{$sizeFilename}";
            Storage::disk('public')->put($fullPath, $encoded);
            $paths[$key] = asset('storage/' . $fullPath);
        }

        return $paths;
    }

    public function optimizeThumbnail($file, $folder = 'thumbnails', $quality = 85)
    {
        // Tạo tên file duy nhất
        $filename = time() . '_' . uniqid() . '.jpg';

        // Đọc ảnh
        $image = $this->manager->read($file);

        // Encode JPEG với chất lượng
        $encoded = $image->toJpeg(quality: $quality);

        // Lưu vào storage public
        $fullPath = "{$folder}/{$filename}";
        Storage::disk('public')->put($fullPath, $encoded);

        // Trả về URL public
        return asset('storage/' . $fullPath);
    }

    /**
     * Tối ưu ảnh từ đường dẫn file (dùng cho queue job - vì UploadedFile không serialize được)
     */
    public function optimizeFromPath(string $filePath, string $path, int $maxWidth = 1920, int $quality = 80): string
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File ảnh không tồn tại: ' . $filePath);
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $extension;

        $image = $this->manager->read($filePath);
        $optimized = $image->scaleDown(width: $maxWidth);
        $encoded = $optimized->toJpeg(quality: $quality);

        Storage::disk('public')->put($path . '/' . $filename, $encoded);

        return $path . '/' . $filename;
    }

}
