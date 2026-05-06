<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class UploadController
{
    private const MAX_SIZE = 50 * 1024 * 1024; // 50 MB
    private const UPLOADS_DIR = '/var/www/uploads';

    #[Route('/api/upload', name: 'upload', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'file is required'], 400);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            return new JsonResponse(['error' => 'file too large (max 50 MB)'], 400);
        }

        $mime = $file->getMimeType() ?? $file->getClientMimeType();
        $attachmentType = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };

        $ext = $file->guessExtension() ?? pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'bin';
        $filename = (string) Uuid::v7() . '.' . $ext;

        try {
            $file->move(self::UPLOADS_DIR, $filename);
        } catch (FileException) {
            return new JsonResponse(['error' => 'upload failed'], 500);
        }

        return new JsonResponse([
            'url'  => '/uploads/' . $filename,
            'type' => $attachmentType,
            'name' => $file->getClientOriginalName(),
        ], 201);
    }
}
