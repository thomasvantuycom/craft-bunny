<?php

namespace thomasvantuycom\craftbunny\assetpreviews;

use Craft;
use craft\base\AssetPreviewHandler;
use yii\base\NotSupportedException;

class Stream extends AssetPreviewHandler
{
    public function getPreviewHtml(array $variables = []): string
    {
        $url = $this->asset->getUrl();

        if ($url === null) {
            throw new NotSupportedException('Preview not supported.');
        }

        return Craft::$app->getView()->renderTemplate('bunny/assets/_previews/stream.twig',
            array_merge([
                'asset' => $this->asset,
                'url' => $url,
            ], $variables)
        );
    }
}
