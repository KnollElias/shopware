<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGeneration;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportRendererInterface;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportPartialGenerationHandler::class)]
class ProductExportPartialGenerationHandlerTest extends TestCase
{
    public function testMarksRunningEveryBatchAndDispatchesNextBatch(): void
    {
        $productExportId = 'product-export-id';
        $salesChannelId = 'sales-channel-id';

        $productExport = new ProductExportEntity();
        $productExport->setId($productExportId);
        $productExport->setUniqueIdentifier($productExportId);

        // Resuming from a later batch (offset > 0): the running heartbeat must still fire so
        // ProductExportGenerateTaskHandler::isStale() does not treat a long export as stuck.
        $message = new ProductExportPartialGeneration($productExportId, $salesChannelId, 100);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: Context::createDefaultContext(),
            salesChannel: $salesChannel
        );

        $contextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'product_export',
            1,
            new ProductExportCollection([$productExport]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(static fn (array $payload): bool => ($payload[0]['isRunning'] ?? null) === true));

        $generator = static::createStub(ProductExportGeneratorInterface::class);
        $generator->method('generate')->willReturn(new ProductExportResult('content', [], 0, 250, true));

        $fileHandler = $this->createMock(ProductExportFileHandlerInterface::class);
        $fileHandler->method('getFilePath')->willReturn('/tmp/export.csv');
        $fileHandler->expects($this->once())
            ->method('writeProductExportContent')
            ->with('content', '/tmp/export.csv', true);
        $fileHandler->expects($this->never())->method('finalizePartialProductExport');

        $dispatched = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        $handler = new ProductExportPartialGenerationHandler(
            $generator,
            $contextFactory,
            $repository,
            $fileHandler,
            $messageBus,
            static::createStub(ProductExportRendererInterface::class),
            static::createStub(AbstractTranslator::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(SalesChannelContextPersister::class),
            static::createStub(Connection::class),
            static::createStub(LanguageLocaleCodeProvider::class),
            static::createStub(ClockInterface::class),
        );

        $handler($message);

        static::assertInstanceOf(ProductExportPartialGeneration::class, $dispatched);
        static::assertSame(250, $dispatched->getOffset());
        static::assertSame($productExportId, $dispatched->getProductExportId());
        static::assertSame($salesChannelId, $dispatched->getSalesChannelId());
    }
}
