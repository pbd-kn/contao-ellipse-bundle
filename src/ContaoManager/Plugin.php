<?php

namespace PbdKn\ContaoEllipseBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PbdKn\ContaoEllipseBundle\PbdKnContaoEllipseBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(PbdKnContaoEllipseBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
