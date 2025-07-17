<?php

namespace thomasvantuycom\craftbunny\imagetransforms;

use Craft;
use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\helpers\UrlHelper;
use craft\models\ImageTransform;

class BunnyDynamicImageApiTransformer extends Component implements ImageTransformerInterface
{
    public const SUPPORTED_IMAGE_FORMATS = ['jpg', 'jpeg', 'webp', 'png', 'gif'];

    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $transformWidth = $imageTransform->width;
        $transformHeight = $imageTransform->height;

        $params = [
            'width' => $transformWidth,
            'height' => $transformHeight,
            'quality' => $imageTransform->quality ?? Craft::$app->getConfig()->getGeneral()->defaultImageQuality,
        ];

        if ($imageTransform->mode === 'crop' && $transformWidth !== null && $transformHeight !== null) {
            $assetWidth = $asset->width;
            $assetHeight = $asset->height;

            $transformAspectRatio = $transformWidth / $transformHeight;
            $assetAspectRatio = $assetWidth / $assetHeight;

            if ($transformAspectRatio > $assetAspectRatio) {
                $cropWidth = $assetWidth;
                $cropHeight = ceil($assetWidth / $transformAspectRatio);
            } else {
                $cropWidth = ceil($assetHeight * $transformAspectRatio);
                $cropHeight = $assetHeight;
            }

            if ($asset->getHasFocalPoint()) {
                ['x' => $focalPointRelativeX, 'y' => $focalPointRelativeY] = $asset->getFocalPoint();

                $params['focus_crop'] = "$cropWidth,$cropHeight,$focalPointRelativeX,$focalPointRelativeY";
            } else {
                $params['crop'] = "$cropWidth,$cropHeight";
    
                $params['crop_gravity'] = match ($imageTransform->position) {
                    'top-left' => 'northwest',
                    'top-center' => 'north',
                    'top-right' => 'northeast',
                    'center-left' => 'west',
                    'center-center' => 'center',
                    'center-right' => 'east',
                    'bottom-left' => 'southwest',
                    'bottom-center' => 'south',
                    'bottom-right' => 'southeast',
                };
            }
        }

        $format = $imageTransform->format;

        if (in_array($format, self::SUPPORTED_IMAGE_FORMATS, true)) {
            $params['format'] = $format;
        }

        $url = $asset->getVolume()->getTransformFs()->getRootUrl() . $asset->getPath();

        return UrlHelper::urlWithParams($url, $params);
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
    }
}
