<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use App\Service\SettingsService;

class UploadFileService
{
    private $baseUrl;

    public function __construct(
        private SluggerInterface $slugger,
        private SettingsService $settingsService
    ) {
        $env = $_ENV['APP_ENV'] ?? 'dev';
        if ($env == 'dev') {
            $base = $this->settingsService->get('SHARED_FOLDER');
            $this->baseUrl = $base;
        } else {
            $src = dirname(__DIR__);
            $arr = explode('/', $src);
            $parr = array_pop($arr);
            $arr[] = "public";
            $arr[] = "shared";
            $fpath = implode('/', $arr);
            $this->baseUrl = $fpath;


        }
    }

    public function index(UploadedFile $file, ?string $path): string
    {
        // Validate path

        if (!$path) {
            return json_encode(['error' => 'Invalid path']);
        }

        //$targetDir = "/srv/projects/grande_royal/app/shared/$path";
        $targetDir = $this->baseUrl . '/' . $path;

        // Create directory if doesn't exist
        if (!is_dir($targetDir)) {
            //mkdir($targetDir, 0777, true);
        }

        // Detect file type
        $mimeType = $file->getMimeType();
        $isImage = str_starts_with($mimeType, 'image/');
        $isVideo = str_starts_with($mimeType, 'video/');

        // Validate file type
        $allowedImageTypes = ['jpg', 'png', 'jpeg', 'gif','avif', 'webp', 'bmp'];
        $allowedVideoTypes = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $fileExtension = strtolower($file->guessExtension());

        // Validate image
        // if (!getimagesize($file->getPathname())) {
        //     return json_encode(['error' => 'File is not an image']);
        // }

        if ($isImage && !in_array($fileExtension, $allowedImageTypes)) {
            return json_encode(['error' => 'Unsupported image format']);
        }

        if ($isVideo && !in_array($fileExtension, $allowedVideoTypes)) {
            return json_encode(['error' => 'Unsupported video format']);
        }


        // // Validate file type
        // $allowedTypes = ['jpg', 'png', 'jpeg', 'gif', 'webp', 'bmp'];
        // $fileExtension = strtolower($file->guessExtension());

        // if (!in_array($fileExtension, $allowedTypes)) {
        //     return json_encode(['error' => 'Only JPG, JPEG, PNG, GIF, WEBP, BMP files are allowed']);
        // }

        // Validate file size
        $maxSize = $isImage ? 8000000 : 800000000;
        // if ($file->getSize() > 8000000) {
        //     return json_encode(['error' => 'File is too large (max 8MB)']);
        // }

        if ($file->getSize() > $maxSize) {
            return json_encode(['error' => 'File is too large (max ' . ($maxSize / 1000000) . 'MB)']);
        }

        // Generate safe filename - changed will keep the original file name
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '.' . $fileExtension;  //.'-'.uniqid().'.'.$fileExtension;

        try {
            // Move the file first
            $file->move($targetDir, $newFilename);
            $fullPath = $targetDir . '/' . $newFilename;

            if ($isImage) {
                return $this->resizeImage($fullPath, $fileExtension);
            } elseif ($isVideo) {
                return $this->processVideo($fullPath, $fileExtension);
            }

        } catch (\Exception $e) {
            return json_encode(['error' => 'Upload failed: ' . $e->getMessage()]);
        }
    }

    private function resizeImage(string $imagePath, string $extension): string
    {
        try {
            // Create image resource based on type
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($imagePath);
                    break;
                case 'webp':
                    $image = imagecreatefromwebp($imagePath);
                    break;
                case 'bmp':
                    $image = imagecreatefrombmp($imagePath);
                    break;
                default:
                    throw new \Exception("Unsupported image type");
            }

            if (!$image) {
                throw new \Exception("Failed to create image resource");
            }

            $maxWidth = 500;
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            if ($originalWidth <= $maxWidth) {
                // Keep original dimensions if smaller
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            } else {
                $newWidth = $maxWidth;
                $newHeight = (int) ($originalHeight * ($maxWidth / $originalWidth));
            }

            // Create new image with optimized settings
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG/GIF/WebP
            if ($extension === 'png' || $extension === 'gif') {
                imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            // Resample with sharpening
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // Always save as WebP (change extension)
            $outputPath = preg_replace('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', '.webp', $imagePath);
            
            // Try different quality levels to achieve 70KB max size
            $quality = 85; // Start with high quality
            $minQuality = 30; // Don't go below 30
            $maxSize = 70 * 1024; // 70KB in bytes
            
            do {
                // Save as WebP with current quality
                imagewebp($resized, $outputPath, $quality);
                
                $currentSize = filesize($outputPath);
                
                // If still too large and quality can be reduced
                if ($currentSize > $maxSize && $quality > $minQuality) {
                    $quality -= 5; // Reduce quality by 5%
                    
                    // If quality is getting very low, try reducing dimensions
                    if ($quality <= 40 && $newWidth > 300) {
                        // Scale down further
                        $newWidth = (int)($newWidth * 0.9);
                        $newHeight = (int)($newHeight * 0.9);
                        
                        // Create new smaller resized image
                        $smallerResized = imagecreatetruecolor($newWidth, $newHeight);
                        imagecopyresampled($smallerResized, $resized, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($resized), imagesy($resized));
                        imagedestroy($resized);
                        $resized = $smallerResized;
                        
                        $quality = 70; // Reset quality for smaller size
                    }
                } else {
                    break; // Size is acceptable
                }
                
            } while ($currentSize > $maxSize && $quality > $minQuality);
            
            // Final check: if still too large after all attempts
            if (filesize($outputPath) > $maxSize) {
                // One last attempt with aggressive compression
                imagewebp($resized, $outputPath, 30);
                
                // If still too large, delete and return error
                if (filesize($outputPath) > $maxSize) {
                    unlink($outputPath);
                    imagedestroy($image);
                    imagedestroy($resized);
                    return json_encode(['error' => 'Unable to compress image to under 70KB even with maximum compression']);
                }
            }
            
            // Get final file size for logging/debugging
            $finalSize = round(filesize($outputPath) / 1024, 2);
            
            // Clean up
            imagedestroy($image);
            imagedestroy($resized);
            
            return json_encode([
                'success' => true, 
                'path' => $outputPath,
                'size_kb' => $finalSize,
                'quality_used' => $quality,
                'dimensions' => "{$newWidth}x{$newHeight}"
            ]);

        } catch (\Exception $e) {
            return json_encode(['error' => 'Image processing failed: ' . $e->getMessage()]);
        }
    }

    private function processVideo(string $videoPath, string $extension): string
    {
        try {
            // Generate thumbnail (requires ffmpeg)
            $thumbnailPath = str_replace('.' . $extension, '_thumb.jpg', $videoPath);

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => '/usr/bin/ffmpeg', // Update path if needed
                // 'ffprobe.binaries' => '/usr/bin/ffprobe'
            ]);

            $video = $ffmpeg->open($videoPath);

            // Capture frame at 5 seconds
            $video
                ->frame(TimeCode::fromSeconds(5))
                ->save($thumbnailPath);

            // Optional: Resize thumbnail
            $this->resizeImage($thumbnailPath, 'jpg');

            return json_encode([
                'success' => true,
                'video_path' => $videoPath,
                'thumbnail_path' => $thumbnailPath
            ]);

        } catch (\Exception $e) {
            return json_encode(['error' => 'Video processing failed: ' . $e->getMessage()]);
        }
    }

    public function folderContents($list, $targetFolder)
    {
        $arrFolders = [];
        $arrFiles = [];
        foreach ($list as $item) {
            if ($item != "." && $item != "..") {
                if (!is_dir($targetFolder . '/' . $item)) {
                    $arrFiles[] = $item;
                } else {
                    $arrFolders[] = $item;
                }

            }
        }
        return $arr = array('arrFolders' => $arrFolders, 'arrFiles' => $arrFiles);
    }

}