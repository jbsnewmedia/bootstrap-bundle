<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\DependencyInjection;

use JBSNewMedia\BootstrapBundle\Command\CompileCommand;
use JBSNewMedia\BootstrapBundle\Command\PurgeCommand;
use JBSNewMedia\BootstrapBundle\DependencyInjection\BootstrapExtension;
use JBSNewMedia\BootstrapBundle\Service\PurgeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BootstrapExtensionTest extends TestCase
{
    public function testServicesAreLoaded(): void
    {
        $container = new ContainerBuilder();
        $extension = new BootstrapExtension();
        $extension->load([], $container);

        $this->assertTrue($container->hasDefinition(PurgeService::class));
        $this->assertTrue($container->hasDefinition(PurgeCommand::class));
        $this->assertTrue($container->hasDefinition(CompileCommand::class));

        $this->assertTrue($container->getDefinition(PurgeService::class)->isPrivate());
        $this->assertTrue($container->getDefinition(PurgeCommand::class)->isPrivate());
        $this->assertTrue($container->getDefinition(CompileCommand::class)->isPrivate());
    }
}
