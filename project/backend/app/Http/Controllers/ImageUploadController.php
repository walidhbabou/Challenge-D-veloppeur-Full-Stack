<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ImageUploadController extends Controller
{
    // Image optimization settings
    const MAX_WIDTH = 1200;
    const MAX_HEIGHT = 1200;
    const QUALITY = 80;
    const THUMBNAIL_SIZE = 300;
    const MEDIUM_SIZE = 600;

    /**
     * Handle image upload with optimization.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No image provided'], 400);
        }

        $uploadedFile = $request->file('image');
        $originalSize = $uploadedFile->getSize();
        $baseFilename = Str::random(20);
        $extension = $uploadedFile->getClientOriginalExtension();
        
        // Load and optimize the image
        $image = Image::make($uploadedFile);
        
        // Get original dimensions
        $originalWidth = $image->width();
        $originalHeight = $image->height();
        
        // Resize if larger than max dimensions (maintains aspect ratio)
        if ($originalWidth > self::MAX_WIDTH || $originalHeight > self::MAX_HEIGHT) {
            $image->resize(self::MAX_WIDTH, self::MAX_HEIGHT, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }
        
        // Create storage paths
        $storagePath = storage_path('app/public/images');
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        // Save optimized main image
        $mainFilename = $baseFilename . '.' . $extension;
        $mainPath = $storagePath . '/' . $mainFilename;
        $image->save($mainPath, self::QUALITY);
        
        // Generate WebP version for modern browsers
        $webpFilename = $baseFilename . '.webp';
        $webpPath = $storagePath . '/' . $webpFilename;
        $image->save($webpPath, self::QUALITY);
        
        // Generate thumbnail (300x300)
        $thumbnailFilename = $baseFilename . '_thumb.' . $extension;
        $thumbnailPath = $storagePath . '/' . $thumbnailFilename;
        $thumbnail = clone $image;
        $thumbnail->fit(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);
        $thumbnail->save($thumbnailPath, self::QUALITY);
        
        // Generate medium size (600x600)
        $mediumFilename = $baseFilename . '_medium.' . $extension;
        $mediumPath = $storagePath . '/' . $mediumFilename;
        $medium = clone $image;
        $medium->resize(self::MEDIUM_SIZE, self::MEDIUM_SIZE, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $medium->save($mediumPath, self::QUALITY);
        
        // Calculate optimized size
        $optimizedSize = filesize($mainPath);
        $compressionRatio = round((1 - ($optimizedSize / $originalSize)) * 100, 1);
        
        return response()->json([
            'message' => 'Image uploaded and optimized successfully',
            'path' => 'images/' . $mainFilename,
            'url' => '/storage/images/' . $mainFilename,
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
            'compression_ratio' => $compressionRatio . '%',
            'dimensions' => [
                'width' => $image->width(),
                'height' => $image->height(),
            ],
            'variants' => [
                'webp' => '/storage/images/' . $webpFilename,
                'thumbnail' => '/storage/images/' . $thumbnailFilename,
                'medium' => '/storage/images/' . $mediumFilename,
            ],
        ], 201);
    }

    /**
     * Delete an uploaded image and all its variants.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');
        
        // Extract base filename
        $pathInfo = pathinfo($path);
        $baseFilename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        // Delete main image
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        
        // Delete variants
        $variants = [
            'images/' . $baseFilename . '.webp',
            'images/' . $baseFilename . '_thumb.' . $extension,
            'images/' . $baseFilename . '_medium.' . $extension,
        ];
        
        foreach ($variants as $variant) {
            if (Storage::disk('public')->exists($variant)) {
                Storage::disk('public')->delete($variant);
            }
        }

        return response()->json(['message' => 'Image and variants deleted successfully']);
    }
}

