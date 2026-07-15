<?php

declare(strict_types=1);

final class ImageStore
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly WallpaperRepository $repository)
    {
    }

    /** @return array{created: bool, record: array} */
    public function accept(array $upload, string $caption, string $author): array
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $tmp = (string) ($upload['tmp_name'] ?? '');
        $originalName = fs_text_limit($upload['name'] ?? 'wallpaper', 180);
        $size = (int) ($upload['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('The uploaded file could not be verified.');
        }
        if ($size <= 0 || $size > (int) fs_config('max_upload_bytes')) {
            throw new RuntimeException('The image is empty or exceeds the configured upload limit.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, and WebP images are accepted.');
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a readable image.');
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        $detectedMime = (string) ($info['mime'] ?? '');
        if ($detectedMime !== $mime) {
            throw new RuntimeException('The image type does not match its contents.');
        }
        if ($width < (int) fs_config('min_width') || $height < (int) fs_config('min_height')) {
            throw new RuntimeException(sprintf(
                'Wallpapers must be at least %d × %d pixels.',
                (int) fs_config('min_width'),
                (int) fs_config('min_height')
            ));
        }
        if ($width <= $height) {
            throw new RuntimeException('FreshSplash currently accepts landscape wallpapers only.');
        }
        if ($width * $height > (int) fs_config('max_pixels')) {
            throw new RuntimeException('The image dimensions are too large to process safely.');
        }

        $sha256 = hash_file('sha256', $tmp);
        if ($sha256 === false) {
            throw new RuntimeException('Unable to fingerprint the uploaded image.');
        }
        $existing = $this->repository->findByHash($sha256);
        if ($existing !== null) {
            return ['created' => false, 'record' => $existing];
        }

        $id = bin2hex(random_bytes(16));
        $extension = self::ALLOWED_MIME[$mime];
        $directory = gmdate('Y/m');
        $relativeDirectory = 'uploads/' . $directory;
        $targetDirectory = rtrim((string) fs_config('public_path'), '/') . '/' . $relativeDirectory;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Unable to create the upload directory.');
        }

        $relativeImage = $relativeDirectory . '/' . $id . '.' . $extension;
        $absoluteImage = rtrim((string) fs_config('public_path'), '/') . '/' . $relativeImage;
        $relativeThumbnail = null;
        $absoluteThumbnail = null;

        try {
            if ($this->gdAvailable()) {
                $source = @imagecreatefromstring((string) file_get_contents($tmp));
                if ($source === false) {
                    throw new RuntimeException('GD could not decode the image.');
                }
                try {
                    if ($mime === 'image/jpeg') {
                        $source = $this->orientJpeg($source, $tmp);
                    }
                    $this->writeImage($source, $absoluteImage, $mime);
                    $relativeThumbnail = $relativeDirectory . '/' . $id . '.thumb.webp';
                    $absoluteThumbnail = rtrim((string) fs_config('public_path'), '/') . '/' . $relativeThumbnail;
                    $this->writeThumbnail($source, $absoluteThumbnail);
                    $width = imagesx($source);
                    $height = imagesy($source);
                } finally {
                    imagedestroy($source);
                }
            } elseif (!move_uploaded_file($tmp, $absoluteImage)) {
                throw new RuntimeException('Unable to save the uploaded image.');
            }

            @chmod($absoluteImage, 0644);
            if ($absoluteThumbnail !== null) {
                @chmod($absoluteThumbnail, 0644);
            }
            $storedSize = filesize($absoluteImage);
            if ($storedSize === false) {
                throw new RuntimeException('Unable to inspect the saved image.');
            }

            $record = [
                'id' => $id,
                'filename' => $relativeImage,
                'thumbnail' => $relativeThumbnail,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'width' => $width,
                'height' => $height,
                'size_bytes' => $storedSize,
                'sha256' => $sha256,
                'caption' => fs_text_limit($caption, 160),
                'author' => fs_text_limit($author, 80),
                'created_at' => gmdate('c'),
            ];

            $result = $this->repository->insert($record);
            if (!$result['created']) {
                @unlink($absoluteImage);
                if ($absoluteThumbnail !== null) {
                    @unlink($absoluteThumbnail);
                }
            }
            return $result;
        } catch (Throwable $throwable) {
            @unlink($absoluteImage);
            if ($absoluteThumbnail !== null) {
                @unlink($absoluteThumbnail);
            }
            throw $throwable;
        }
    }

    public function removeFiles(array $record): void
    {
        foreach (['filename', 'thumbnail'] as $field) {
            $relative = $record[$field] ?? null;
            if (!is_string($relative) || $relative === '') {
                continue;
            }
            $absolute = realpath((string) fs_config('public_path') . '/' . $relative);
            $uploadRoot = realpath((string) fs_config('upload_path'));
            if ($absolute !== false && $uploadRoot !== false && str_starts_with($absolute, $uploadRoot . DIRECTORY_SEPARATOR)) {
                @unlink($absolute);
            }
        }
    }

    private function gdAvailable(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagewebp');
    }

    private function writeImage(GdImage $image, string $path, string $mime): void
    {
        $temporary = $path . '.tmp';
        $success = match ($mime) {
            'image/jpeg' => imagejpeg($image, $temporary, (int) fs_config('jpeg_quality')),
            'image/png' => imagepng($image, $temporary, 7),
            'image/webp' => imagewebp($image, $temporary, (int) fs_config('webp_quality')),
            default => false,
        };
        if (!$success || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to encode the uploaded image.');
        }
    }

    private function writeThumbnail(GdImage $source, string $path): void
    {
        $targetWidth = 640;
        $targetHeight = 360;
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = (int) round(($sourceWidth - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($thumbnail === false) {
            throw new RuntimeException('Unable to create a thumbnail.');
        }
        try {
            imagecopyresampled(
                $thumbnail,
                $source,
                0,
                0,
                $sourceX,
                $sourceY,
                $targetWidth,
                $targetHeight,
                $cropWidth,
                $cropHeight
            );
            $temporary = $path . '.tmp';
            if (!imagewebp($thumbnail, $temporary, 82) || !rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to create the thumbnail.');
            }
        } finally {
            imagedestroy($thumbnail);
        }
    }

    private function orientJpeg(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 270),
            6 => $this->rotate($image, 270),
            7 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);
        return $image;
    }

    private function rotate(GdImage $image, int $angle): GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);
        return $rotated;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
            default => 'The upload failed unexpectedly.',
        };
    }
}
