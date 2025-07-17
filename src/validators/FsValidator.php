<?php

namespace thomasvantuycom\craftbunny\validators;

use Craft;
use thomasvantuycom\craftbunny\fs\BunnyCdnFs;
use yii\validators\Validator;

class FsValidator extends Validator
{
    public function init(): void
    {
        parent::init();

        if ($this->message === null) {
            $this->message = Craft::t('bunny', 'This filesystem can only be used as a Transform Filesystem.');
        }
    }

    protected function validateValue($value): ?array
    {
        $fs = Craft::$app->getFs()->getFilesystemByHandle($value);

        if ($fs instanceof BunnyCdnFs) {
            return [$this->message, []];
        }

        return null;
    }
}
