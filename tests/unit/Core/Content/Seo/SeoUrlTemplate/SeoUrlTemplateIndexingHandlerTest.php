<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingHandler;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateIndexingHandler::class)]
class SeoUrlTemplateIndexingHandlerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private IteratorFactory&MockObject $iteratorFactory;

    private DefinitionInstanceRegistry&MockObject $definitionRegistry;

    private SeoUrlRouteRegistry&MockObject $seoUrlRouteRegistry;

    private SeoUrlTemplateIndexingHandler $handler;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->iteratorFactory = $this->createMock(IteratorFactory::class);
        $this->definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->seoUrlRouteRegistry = $this->createMock(SeoUrlRouteRegistry::class);
        $this->handler = new SeoUrlTemplateIndexingHandler(
            $this->seoUrlUpdater,
            $this->iteratorFactory,
            $this->definitionRegistry,
            $this->seoUrlRouteRegistry
        );
    }

    public function testIteratesEntitiesInBatchesAndUpdatesSeoUrls(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $this->definitionRegistry->method('has')->with('category')->willReturn(true);

        $definition = $this->createMock(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);
        $this->definitionRegistry->method('getByEntityName')->with('category')->willReturn($definition);

        $this->seoUrlRouteRegistry->method('findByRouteName')
            ->with('frontend.navigation.page')
            ->willReturn($this->createMock(SeoUrlRouteInterface::class));

        // fetch() returns [binaryId => hexId] batches and an empty array stops iteration.
        $iterator = $this->createMock(IterableQuery::class);
        $iterator->method('fetch')->willReturnOnConsecutiveCalls(
            ['binA' => $id1, 'binB' => $id2],
            ['binC' => $id3],
            []
        );

        $this->iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with($definition, null, 250, Defaults::LIVE_VERSION)
            ->willReturn($iterator);

        $captured = [];
        $this->seoUrlUpdater->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (string $route, array $ids) use (&$captured): void {
                $captured[] = [$route, $ids];
            });

        $this->handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'category'));

        static::assertSame([
            ['frontend.navigation.page', [$id1, $id2]],
            ['frontend.navigation.page', [$id3]],
        ], $captured);
    }

    public function testPassesNullVersionForNonVersionAwareDefinition(): void
    {
        $this->definitionRegistry->method('has')->willReturn(true);

        $definition = $this->createMock(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(false);
        $this->definitionRegistry->method('getByEntityName')->willReturn($definition);

        $this->seoUrlRouteRegistry->method('findByRouteName')
            ->willReturn($this->createMock(SeoUrlRouteInterface::class));

        $iterator = $this->createMock(IterableQuery::class);
        $iterator->method('fetch')->willReturn([]);

        $this->iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with($definition, null, 250, null)
            ->willReturn($iterator);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.detail.page', 'product'));
    }

    public function testReturnsEarlyOnEmptyRouteName(): void
    {
        $this->definitionRegistry->expects($this->never())->method('has');
        $this->iteratorFactory->expects($this->never())->method('createIterator');
        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->handler->__invoke(new SeoUrlTemplateIndexingMessage('', 'category'));
    }

    public function testReturnsEarlyOnEmptyEntityName(): void
    {
        $this->definitionRegistry->expects($this->never())->method('has');
        $this->iteratorFactory->expects($this->never())->method('createIterator');
        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', ''));
    }

    public function testSkipsUnknownEntity(): void
    {
        $this->definitionRegistry->method('has')->with('unknown')->willReturn(false);

        $this->iteratorFactory->expects($this->never())->method('createIterator');
        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'unknown'));
    }

    public function testSkipsUnregisteredRoute(): void
    {
        $this->definitionRegistry->method('has')->willReturn(true);
        $this->seoUrlRouteRegistry->method('findByRouteName')->willReturn(null);

        $this->definitionRegistry->expects($this->never())->method('getByEntityName');
        $this->iteratorFactory->expects($this->never())->method('createIterator');
        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->handler->__invoke(new SeoUrlTemplateIndexingMessage('unregistered.route', 'category'));
    }
}
