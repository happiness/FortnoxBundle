<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\FortnoxBundle\Tests;

use App\Plugin\PluginInterface;
use App\Plugin\PluginMetadata;
use KimaiPlugin\FortnoxBundle\Controller\FortnoxController;
use KimaiPlugin\FortnoxBundle\DependencyInjection\FortnoxExtension;
use KimaiPlugin\FortnoxBundle\FortnoxBundle;
use KimaiPlugin\FortnoxBundle\Service\PdfGenerator;
use KimaiPlugin\FortnoxBundle\Service\TimeReportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FortnoxBundleTest extends TestCase
{
    private string $bundleDir;

    protected function setUp(): void
    {
        $this->bundleDir = \dirname(__DIR__);
    }

    public function testBundleMetadata(): void
    {
        self::assertDirectoryExists($this->bundleDir);
        self::assertFileExists($this->bundleDir . '/composer.json');

        $meta = PluginMetadata::createFromPath($this->bundleDir);
        self::assertEquals('FortnoxBundle', $meta->getName());
        self::assertEquals('1.0.0', $meta->getVersion());
        self::assertEquals(20000, $meta->getKimaiVersion());
        self::assertEquals('happiness/fortnox-bundle', $meta->getPackage());
        self::assertEquals('Kimai plugin for generating Fortnox customer invoice time-report attachments.', $meta->getDescription());
    }

    public function testBundleInstantiation(): void
    {
        $bundle = new FortnoxBundle();
        self::assertInstanceOf(Bundle::class, $bundle);
        self::assertInstanceOf(PluginInterface::class, $bundle);
        self::assertEquals('FortnoxBundle', $bundle->getName());
        self::assertEquals(realpath($this->bundleDir), realpath($bundle->getPath()));
    }

    public function testExtensionPrepend(): void
    {
        $extension = new FortnoxExtension();
        $container = new ContainerBuilder();

        $extension->prepend($container);

        $kimaiConfigs = $container->getExtensionConfig('kimai');
        self::assertNotEmpty($kimaiConfigs);
        self::assertEquals([
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => [
                        'fortnox_export',
                    ],
                ],
            ],
        ], $kimaiConfigs[0]);
    }

    public function testExtensionLoad(): void
    {
        $extension = new FortnoxExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        self::assertTrue($container->hasDefinition(FortnoxBundle::class));
        self::assertTrue($container->hasDefinition(FortnoxController::class));
        self::assertTrue($container->hasDefinition(TimeReportService::class));
        self::assertTrue($container->hasDefinition(PdfGenerator::class));
    }
}
