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

class BunnyStorageFs extends Fs
{
    public string $region = 'de';

    public ?string $zone = null;

    public ?string $accessKey = null;

    private ?Client $client = null;

    public static function displayName(): string
    {
        return Craft::t('bunny', 'Bunny Storage');
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'zone',
                'accessKey',
            ],
        ];

        return $behaviors;
    }

    public function attributeLabels(): array
    {
        $attributeLabels = parent::attributeLabels();
        $attributeLabels['region'] = Craft::t('bunny', 'Primary Region');
        $attributeLabels['zone'] = Craft::t('bunny', 'Zone');
        $attributeLabels['accessKey'] = Craft::t('bunny', 'Access Key');

        return $attributeLabels;
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['region', 'zone', 'accessKey'], 'trim'];
        $rules[] = [['region', 'zone', 'accessKey'], 'required'];
        $rules[] = [['region', 'zone', 'accessKey'], 'string'];

        return $rules;
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('bunny/_components/fs/BunnyStorage/settings.twig', [
            'fs' => $this,
        ]);
    }

    public function getFileList(string $directory = '', bool $recursive = true): \Generator
    {
        try {
            $response = $this->getClient()->request('GET', $directory === '' ? '' : "$directory/");
            $body = Json::decode($response->getBody()->getContents());

            foreach ($body as $fileMetadata) {
                yield new FsListing([
                    'dirname' => $directory,
                    'basename' => $fileMetadata['ObjectName'],
                    'type' => $fileMetadata['IsDirectory'] === true ? 'dir' : 'file',
                    'fileSize' => $fileMetadata['Length'],
                    'dateModified' => strtotime($fileMetadata['LastChanged']),
                ]);

                if ($fileMetadata['IsDirectory'] && $recursive) {
                    $nextDirectory = $fileMetadata['ObjectName'];

                    if ($directory !== '') {
                        $nextDirectory = "$directory/$nextDirectory";
                    }

                    yield from $this->getFileList($nextDirectory, $recursive);
                }
            }
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function getFileSize(string $uri): int
    {
        try {
            $response = $this->getClient()->request('DESCRIBE', $uri);
            $body = Json::decode($response->getBody()->getContents());
            
            return $body['Length'];
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function getDateModified(string $uri): int
    {
        try {
            $response = $this->getClient()->request('DESCRIBE', $uri);
            $body = Json::decode($response->getBody()->getContents());

            return strtotime($body['LastChanged']);
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $response = $this->getClient()->request('GET', $path);
            $body = $response->getBody()->getContents();

            return $body;
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        try {
            $this->getClient()->request('PUT', $path, [
                'body' => $contents,
            ]);
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
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
            $this->getClient()->request('DESCRIBE', $path);

            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }

            throw new FsException($e->getMessage(), 0, $e);
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
        $this->writeFileFromStream($newPath, $this->getFileStream($path), $config);
        $this->deleteFile($path);
    }

    public function copyFile(string $path, string $newPath, array $config = []): void
    {
        $this->writeFileFromStream($newPath, $this->getFileStream($path), $config);
    }

    public function getFileStream(string $uriPath)
    {
        try {
            $response = $this->getClient()->request('GET', $uriPath);
            $stream = $response->getBody()->detach();

            return $stream;
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        $parentDirectory = dirname($path);

        if ($parentDirectory === '.') {
            $parentDirectory = '';
        }

        foreach ($this->getFileList($parentDirectory, false) as $listing) {
            if ($listing->getIsDir() && $listing->getUri() === $path) {
                return true;
            }
        }

        return false;
    }

    public function createDirectory(string $path, array $config = []): void
    {
        try {
            $this->getClient()->request('PUT', "$path/");
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->getClient()->request('DELETE', "$path/");
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return;
            }

            throw new FsException($e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new FsException($e->getMessage(), 0, $e);
        }
    }

    public function renameDirectory(string $path, string $newName): void
    {
        $parentDirectory = dirname($path);
        $newPath = $parentDirectory === '.' ? $newName : "$parentDirectory/$newName";

        foreach ($this->getFileList($path) as $listing) {
            $uri = $listing->getUri();
            $newUri = substr_replace($listing->getUri(), $newPath, 0, strlen($path));

            if ($listing->getIsDir()) {
                $this->createDirectory($newUri);
            } else {
                $this->renameFile($uri, $newUri);
            }
        }

        $this->deleteDirectory($path);
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $region = App::parseEnv($this->region);
            $zone = App::parseEnv($this->zone);

            $baseUri = $region === 'de'
                ? "https://storage.bunnycdn.com/{$zone}/"
                : "https://{$region}.storage.bunnycdn.com/{$zone}/";

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
