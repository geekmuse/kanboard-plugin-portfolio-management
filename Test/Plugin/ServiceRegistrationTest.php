<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Test\Plugin;

use PHPUnit\Framework\TestCase;

class ServiceRegistrationTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function getExpectedServiceMap(): array
    {
        return [
            'portfolioModel' => '\\Kanboard\\Plugin\\Portfolio\\Model\\PortfolioModel',
            'portfolioProjectModel' => '\\Kanboard\\Plugin\\Portfolio\\Model\\PortfolioProjectModel',
            'milestoneModel' => '\\Kanboard\\Plugin\\Portfolio\\Model\\MilestoneModel',
            'milestoneTaskModel' => '\\Kanboard\\Plugin\\Portfolio\\Model\\MilestoneTaskModel',
            'dependencyModel' => '\\Kanboard\\Plugin\\Portfolio\\Model\\DependencyModel',
            'portfolioTaskModel' => '\\Kanboard\\Plugin\\Portfolio\\Model\\PortfolioTaskModel',
            'portfolioHelper' => '\\Kanboard\\Plugin\\Portfolio\\Helper\\PortfolioHelper',
            'portfolioValidator' => '\\Kanboard\\Plugin\\Portfolio\\Validator\\PortfolioValidator',
        ];
    }

    public function testPluginRegistersEachCoreContainerServiceExactlyOnce(): void
    {
        $content = file_get_contents(__DIR__ . '/../../Plugin.php');
        $this->assertNotFalse($content, 'Unable to read Plugin.php');

        foreach ($this->getExpectedServiceMap() as $serviceKey => $className) {
            $assignment = sprintf("\$this->container['%s']", $serviceKey);
            $resolution = sprintf('return new %s($c);', $className);

            $this->assertSame(
                1,
                substr_count($content, $assignment),
                sprintf('Expected exactly one DI assignment for service key "%s"', $serviceKey)
            );

            $this->assertStringContainsString(
                $resolution,
                $content,
                sprintf('Expected service key "%s" to resolve class %s', $serviceKey, $className)
            );
        }
    }
}
