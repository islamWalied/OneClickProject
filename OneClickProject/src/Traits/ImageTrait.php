<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait ImageTrait
{
    /**
     * Compress images only (handles JPEG, PNG, WebP)
     */
    private function compressImage($sourcePath, $destinationPath, $quality = 75): bool
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $mime = $imageInfo['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                imagejpeg($image, $destinationPath, $quality);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                imagepng($image, $destinationPath, round(9 * $quality / 100));
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($sourcePath);
                imagewebp($image, $destinationPath, $quality);
                break;
            default:
                return false; // Unsupported image type
        }

        if (isset($image)) {
            imagedestroy($image);
        }

        return file_exists($destinationPath);
    }

    /**
     * Handle file storage (images with compression, other files without)
     */
    private function storeFile($file, $directoryName, $location = 'public'): string
    {
        $path = $file->store($directoryName, $location);
        $fullPath = Storage::disk($location)->path($path);

        // Check if it's an image and compress if it is
        if (in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
            $this->compressImage($fullPath, $fullPath, 75);
        }

        return $path;
    }

    public function saveImage($request, $property, $directoryName, $location = 'public')
    {
        if ($request->hasFile($property)) {
            $file = $request->file($property);
            return $this->storeFile($file, $directoryName, $location);
        }
        return null;
    }

    public function updateImage($request, $property, $directoryName, $model, $location = 'public')
    {
        if ($request->hasFile($property)) {
            if ($model) {
                Storage::disk($location)->delete($model);
            }
            $file = $request->file($property);
            return $this->storeFile($file, $directoryName, $location);
        }
        return null;
    }

    public function deleteImage($model, $location = 'public'): void
    {
        if ($model) {
            Storage::disk($location)->delete($model);
        }
    }

    public function saveManyImages($request, $property, $directoryName, $model, $relationFunction, $foreignKey, $location = 'public'): void
    {
        if ($request->hasFile($property)) {
            foreach ($request->file($property) as $file) {
                $path = $this->storeFile($file, $directoryName, $location);

                $model->$relationFunction()->create([
                    $property => $path,
                    $foreignKey => $model->id,
                ]);
            }
        }
    }

    public function updateManyImages($request, $property, $directoryName, $model, $relationFunction, $foreignKey, $location = 'public'): void
    {
        if ($request->hasFile($property)) {
            // Delete existing files
            foreach ($model->$relationFunction as $file) {
                Storage::disk($location)->delete($file->$property);
                $file->delete();
            }

            // Save new files
            foreach ($request->file($property) as $file) {
                $path = $this->storeFile($file, $directoryName, $location);

                $model->$relationFunction()->create([
                    $property => $path,
                    $foreignKey => $model->id,
                ]);
            }
        }
    }

    public function deleteManyImage($property, $model, $relationFunction, $location = 'public'): void
    {
        foreach ($model->$relationFunction as $file) {
            Storage::disk($location)->delete($file->$property);
            $file->delete();
        }
    }
}
