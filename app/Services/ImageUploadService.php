<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Process, convert to .webp, and store an image file on public disk.
     *
     * @param  UploadedFile  $file
     * @param  string  $folder
     * @param  int  $quality  (1-100)
     * @param  int|null  $maxWidth  Optional max width resize while preserving aspect ratio
     * @return string Relative storage path (e.g. 'brands/uuid.webp')
     */
    public function upload(UploadedFile $file, string $folder = 'images', int $quality = 85, ?int $maxWidth = 1600): string
    {
        $filename = Str::uuid() . '.webp';
        $relativePath = $folder . '/' . $filename;

        try {
            $source = $file->getRealPath() ?: $file->getPathname();
            $binary = file_get_contents($source);

            // Read and process image using Intervention Image
            $image = $this->manager->decodeBinary($binary);

            // Optional auto-downscale if image is larger than maxWidth
            if ($maxWidth && $image->width() > $maxWidth) {
                $image->scale(width: $maxWidth);
            }

            // Encode to .webp format
            $encodedWebp = $image->encodeUsingFileExtension('webp', $quality);

            // Store encoded binary on public disk
            Storage::disk('public')->put($relativePath, (string) $encodedWebp);
        } catch (\Throwable $e) {
            // Fallback to native upload if image processing encounters an exception
            $extension = $file->getClientOriginalExtension() ?: 'webp';
            $fallbackFilename = Str::uuid() . '.' . strtolower($extension);
            $relativePath = $file->storeAs($folder, $fallbackFilename, 'public');
        }

        return $relativePath;
    }

    /**
     * Delete an existing stored image from public disk.
     */
    public function delete(?string $pathOrUrl): void
    {
        if (empty($pathOrUrl)) {
            return;
        }

        // Extract relative path from URL or direct path
        $publicUrl = Storage::disk('public')->url('');
        $relative = str_replace([$publicUrl, '/storage/'], '', $pathOrUrl);
        $relative = ltrim($relative, '/');

        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
