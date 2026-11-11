<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Util;

use Symfony\Component\Mime\MimeTypes;

final class MimeTypesFactory
{
    // Types unknown to Symfony's built-in map but accepted by the platform
    private const array ADDITIONAL_TYPES = [
        'application/x-adobe-indesign' => ['indd'],
    ];

    public static function create(): MimeTypes
    {
        return new MimeTypes(self::ADDITIONAL_TYPES);
    }
}
