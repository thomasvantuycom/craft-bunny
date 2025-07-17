<?php

namespace thomasvantuycom\craftbunny;

use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineRulesEvent;
use craft\events\GenerateTransformEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\models\Volume;
use craft\services\Fs;
use craft\services\ImageTransforms;
use thomasvantuycom\craftbunny\fs\BunnyCdnFs;
use thomasvantuycom\craftbunny\fs\BunnyStorageFs;
use thomasvantuycom\craftbunny\imagetransforms\BunnyDynamicImageApiTransformer;
use thomasvantuycom\craftbunny\validators\FsValidator;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        $this->registerFilesystemTypes();
        $this->registerImageTransformers();
        $this->defineVolumeRules();
        $this->setImageTransformer();
    }

    private function registerFilesystemTypes(): void
    {
        Event::on(Fs::class, Fs::EVENT_REGISTER_FILESYSTEM_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = BunnyCdnFs::class;
            $event->types[] = BunnyStorageFs::class;
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
