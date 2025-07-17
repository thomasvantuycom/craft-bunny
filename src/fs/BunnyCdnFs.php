<?php

namespace thomasvantuycom\craftbunny\fs;

use Craft;
use craft\base\Fs;
use yii\base\NotSupportedException;

class BunnyCdnFs extends Fs
{
    public bool $hasUrls = true;

    public static function displayName(): string
    {
        return Craft::t('bunny', 'Bunny CDN');
    }

    public function getFileList(string $directory = '', bool $recursive = true): \Generator
    {
        throw new NotSupportedException('getFileList() is not implemented.');
    }

    public function getFileSize(string $uri): int
    {
        throw new NotSupportedException('getFileSize() is not implemented.');
    }

    public function getDateModified(string $uri): int
    {
        throw new NotSupportedException('getDateModified() is not implemented.');
    }

    public function read(string $path): string
    {
        throw new NotSupportedException('read() is not implemented.');
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        throw new NotSupportedException('write() is not implemented.');
    }

    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        throw new NotSupportedException('writeFileFromStream() is not implemented.');
    }

    public function fileExists(string $path): bool
    {
        throw new NotSupportedException('fileExists() is not implemented.');
    }

    public function deleteFile(string $path): void
    {
        throw new NotSupportedException('deleteFile() is not implemented.');
    }

    public function renameFile(string $path, string $newPath, array $config = []): void
    {
        throw new NotSupportedException('renameFile() is not implemented.');
    }

    public function copyFile(string $path, string $newPath, array $config = []): void
    {
        throw new NotSupportedException('copyFile() is not implemented.');
    }

    public function getFileStream(string $uriPath)
    {
        throw new NotSupportedException('getFileStream() is not implemented.');
    }

    public function directoryExists(string $path): bool
    {
        throw new NotSupportedException('directoryExists() is not implemented.');
    }

    public function createDirectory(string $path, array $config = []): void
    {
        throw new NotSupportedException('createDirectory() is not implemented.');
    }

    public function deleteDirectory(string $path): void
    {
        throw new NotSupportedException('deleteDirectory() is not implemented.');
    }

    public function renameDirectory(string $path, string $newName): void
    {
        throw new NotSupportedException('renameDirectory() is not implemented.');
    }
}
