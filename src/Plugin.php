<?php

namespace thomasvantuycom\craftbunny;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\errors\FileException;
use craft\events\AssetEvent;
use craft\events\AssetPreviewEvent;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineRulesEvent;
use craft\events\DefineUrlEvent;
use craft\events\GenerateTransformEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\App;
use craft\models\Volume;
use craft\services\Assets;
use craft\services\Fs;
use craft\services\ImageTransforms;
use thomasvantuycom\craftbunny\assetpreviews\Stream;
use thomasvantuycom\craftbunny\fs\BunnyCdnFs;
use thomasvantuycom\craftbunny\fs\BunnyStorageFs;
use thomasvantuycom\craftbunny\fs\BunnyStreamFs;
use thomasvantuycom\craftbunny\imagetransforms\BunnyDynamicImageApiTransformer;
use thomasvantuycom\craftbunny\validators\FsValidator;
use yii\base\Event;
use yii\base\ModelEvent;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        $this->registerFilesystemTypes();
        $this->applyFilesystemFileKindRestrictions();
        $this->applyFilesystemWorkarounds();
        $this->registerImageTransformers();
        $this->defineVolumeRules();
        $this->setImageTransformer();
    }

    private function registerFilesystemTypes(): void
    {
        Event::on(Fs::class, Fs::EVENT_REGISTER_FILESYSTEM_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = BunnyCdnFs::class;
            $event->types[] = BunnyStorageFs::class;
            $event->types[] = BunnyStreamFs::class;
        });
    }

    private function applyFilesystemFileKindRestrictions(): void
    {
        Event::on(Asset::class, Asset::EVENT_BEFORE_HANDLE_FILE, function(AssetEvent $event) {
            $asset = $event->asset;
            $fs = $asset->getVolume()->getFs();

            if ($fs instanceof BunnyStreamFs && $event->isNew) {
                if (!in_array($asset->extension, $fs::SUPPORTED_VIDEO_EXTENSIONS) && !in_array($asset->extension, $fs::SUPPORTED_AUDIO_EXTENSIONS)) {
                    throw new FileException(Craft::t('app', '“{extension}” is not an allowed file extension.', [
                        'extension' => $asset->extension,
                    ]));
                }
            }
        });
    }

    private function applyFilesystemWorkarounds(): void
    {
        Event::on(Asset::class, Asset::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
            $asset = $event->sender;
            $fs = $asset->getVolume()->getFs();

            if ($fs instanceof BunnyStreamFs && $event->isNew) {
                if ($asset->getScenario() === Asset::SCENARIO_CREATE) {
                    $filename = $asset->getFilename();
                    $guid = $fs->createFile($filename);

                    $event->sender->title = $filename;
                    $event->sender->newLocation = preg_replace('/}(.+)/', "}$guid", $asset->newLocation);
                }

                if ($asset->getScenario() === Asset::SCENARIO_INDEX) {
                    $filename = $asset->getFilename();
                    $event->sender->title = $fs->getFileTitle($filename);
                }
            }
        });

        Event::on(Asset::class, Asset::EVENT_DEFINE_URL, function(DefineUrlEvent $event) {
            $asset = $event->sender;
            $fs = $asset->getVolume()->getFs();

            if ($fs instanceof BunnyStreamFs) {
                $libraryId = App::parseEnv($fs->libraryId);
                $videoId = $asset->getFilename();

                $event->url = "https://iframe.mediadelivery.net/play/$libraryId/$videoId";
            }
        });

        Event::on(Assets::class, Assets::EVENT_DEFINE_THUMB_URL, function(DefineAssetThumbUrlEvent $event) {
            $asset = $event->asset;
            $fs = $asset->getVolume()->getFs();

            if ($fs instanceof BunnyStreamFs) {
                $cdnHostname = App::parseEnv($fs->cdnHostname);
                $event->url = 'https://' . $cdnHostname . '/' . $asset->getFilename() . '/thumbnail.jpg';
            }
        });

        Event::on(Assets::class, Assets::EVENT_REGISTER_PREVIEW_HANDLER, function(AssetPreviewEvent $event) {
            $asset = $event->asset;
            $fs = $asset->getVolume()->getFs();

            if ($fs instanceof BunnyStreamFs) {
                $event->previewHandler = new Stream($asset);
            }
        });
    }

    private function registerImageTransformers(): void
    {
        Event::on(ImageTransforms::class, ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS, function(RegisterComponentTypesEvent $event) {
            $event->types[] = BunnyDynamicImageApiTransformer::class;
        });
    }

    private function defineVolumeRules(): void
    {
        Event::on(Volume::class, Volume::EVENT_DEFINE_RULES, function(DefineRulesEvent $event) {
            $event->rules[] = [['fsHandle'], FsValidator::class];
        });
    }

    private function setImageTransformer(): void
    {
        Event::on(Asset::class, Asset::EVENT_BEFORE_GENERATE_TRANSFORM, function(GenerateTransformEvent $event) {
            if ($event->asset->getVolume()->getTransformFs() instanceof BunnyCdnFs) {
                $event->transform->setTransformer(BunnyDynamicImageApiTransformer::class);
            }
        });
    }
}
