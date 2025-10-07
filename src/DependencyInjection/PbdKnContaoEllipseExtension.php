<?php

namespace PbdKn\ContaoEllipseBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * L�dt und registriert alle Service-Definitionen f�r das Bundle.
 * Wird von Symfony automatisch erkannt, sobald die Klasse
 * {BundleName}Extension hei�t und im Namespace DependencyInjection liegt.
 */
class PbdKnContaoEllipseExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // YAML-Loader initialisieren, Pfad: src/Resources/config/
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        // Haupt-Service-Definitionen laden
        $loader->load('services.yaml');

        // Beispiel: sp�ter kannst du hier weitere Dateien laden
        // $loader->load('commands.yaml');
        // $loader->load('parameters.yaml');
    }
}
