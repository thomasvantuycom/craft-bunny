<?php

namespace thomasvantuycom\craftbunny\fs;

use Craft;
use craft\base\Fs;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\errors\FsException;
use craft\helpers\App;
use craft\helpers\Json;
use craft\models\FsListing;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use yii\base\NotSupportedException;

class BunnyStreamFs extends Fs
{
    public const SUPPORTED_VIDEO_EXTENSIONS = ['mp4', '4mv', 'mkv', 'webm', 'mov', 'avi', 'vod', 'flv', 'wmv', 'ts', 'amv', 'mpeg'];

    public const SUPPORTED_AUDIO_EXTENSIONS = ['mp3', 'ogg', 'wav', 'm4v', 'm4p'];

    protected static bool $showHasUrlSetting = false;

    public ?string $libraryId = null;

    public ?string $collectionId = null;

    public ?string $cdnHostname = null;

    public ?string $accessKey = null;

    private ?Client $client = null;

    public static function displayName(): string
    {
        return Craft::t('bunny', 'Bunny Stream');
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'libraryId',
                'collectionId',
                'cdnHostname',
                'accessKey',
            ],
        ];

        return $behaviors;
    }

    public function attributeLabels(): array
    {
        $attributeLabels = parent::attributeLabels();
        $attributeLabels['libraryId'] = Craft::t('bunny', 'Library ID');
        $attributeLabels['collectionId'] = Craft::t('bunny', 'Collection ID');
        $attributeLabels['cdnHostname'] = Craft::t('bunny', 'CDN Hostname');
        $attributeLabels['accessKey'] = Craft::t('bunny', 'Access Key');

        return $attributeLabels;
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['libraryId', 'collectionId', 'cdnHostname', 'accessKey'], 'trim'];
        $rules[] = [['libraryId', 'cdnHostname', 'accessKey'], 'required'];
        $rules[] = [['libraryId', 'collectionId', 'cdnHostname', 'accessKey'], 'string'];

        return $rules;
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('bunny/_components/fs/BunnyStream/settings.twig', [
            'fs' => $this,
        ]);
    }

    public function getFileList(string $directory = '', bool $recursive = true): \Generator
    {
        try {
            $page = 1;

            do {
                $response = $this->getClient()->request('GET', '', [
                    'query' => [
                        'page' => $page,
                        'collection' => App::parseEnv($this->collectionId),
                    ],
                ]);
                $body = Json::decode($response->getBody()->getContents());

                foreach ($body['items'] as $fileMetadata) {
                    $storageSize = $fileMetadata['storageSize'];
                    
                    if ($storageSize > 0) {
                        yield new FsListing([
                            'dirname' => '',
                            'basename' => $fileMetadata['guid'],
                            'type' => 'file',
                            'fileSize' => $fileMetadata['storageSize'],
                            'dateModified' => strtotime($fileMetadata['dateUploaded']),
                        ]);
                    }
                }

                $page++;
            } while (count($body['items']) === $body['itemsPerPage']);
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function getFileSize(string $uri): int
    {
        try {
            $response = $this->getClient()->request('GET', $uri);
            $body = Json::decode($response->getBody()->getContents());
            
            return $body['storageSize'];
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function getFileTitle(string $uri): string
    {
        try {
            $response = $this->getClient()->request('GET', $uri);
            $body = Json::decode($response->getBody()->getContents());
            
            return $body['title'];
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function getDateModified(string $uri): int
    {
        try {
            $response = $this->getClient()->request('GET', $uri);
            $body = Json::decode($response->getBody()->getContents());
            
            return $body['dateUploaded'];
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
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
        try {
            $this->getClient()->request('PUT', $path, [
                'body' => $stream,
            ]);
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function fileExists(string $path): bool
    {
        try {
            $response = $this->getClient()->request('GET', $path);
            $body = Json::decode($response->getBody()->getContents());

            return $body['storageSize'] !== 0;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }

            throw new FsException($e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function createFile(string $filename): string
    {
        try {
            $response = $this->getClient()->request('POST', '', [
                'json' => [
                    'title' => $filename,
                    'collectionId' => App::parseEnv($this->collectionId),
                ],
            ]);
            $body = Json::decode($response->getBody()->getContents());

            return $body['guid'];
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function deleteFile(string $path): void
    {
        try {
            $this->getClient()->request('DELETE', $path);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return;
            }

            Craft::info($e->getMessage(), __METHOD__);
        } catch (RequestException $e) {
            Craft::info($e->getMessage(), __METHOD__);
        }
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

    private function getClient(): Client
    {
        if ($this->client === null) {
            $libraryId = App::parseEnv($this->libraryId);

            $baseUri = "https://video.bunnycdn.com/library/{$libraryId}/videos/";

            $this->client = Craft::createGuzzleClient([
                'base_uri' => $baseUri,
                'headers' => [
                    'AccessKey' => App::parseEnv($this->accessKey),
                ],
            ]);
        }

        return $this->client;
    }
}
