<?php

declare(strict_types=1);

namespace JBSNewMedia\BootstrapBundle\Tests\Bundle;

use JBSNewMedia\BootstrapBundle\BootstrapBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BootstrapBundleTest extends TestCase
{
    public function testBundleIsSymfonyBundle(): void
    {
        $bundle = new BootstrapBundle();
        $this->assertInstanceOf(Bundle::class, $bundle);
    }
}
