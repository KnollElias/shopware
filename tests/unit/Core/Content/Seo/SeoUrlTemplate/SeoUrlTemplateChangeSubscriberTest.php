<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateChangeSubscriber;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateChangeSubscriber::class)]
class SeoUrlTemplateChangeSubscriberTest extends TestCase
{
    private Connection&MockObject $connection;

    private MessageBusInterface&MockObject $messageBus;

    private SeoUrlTemplateChangeSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->subscriber = new SeoUrlTemplateChangeSubscriber($this->connection, $this->messageBus);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            ['seo_url_template.written' => 'onSeoUrlTemplateWritten'],
            SeoUrlTemplateChangeSubscriber::getSubscribedEvents()
        );
    }

    public function testDispatchesIndexingMessageWhenTemplateChanged(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
        ]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (SeoUrlTemplateIndexingMessage $message): bool => $message->routeName === 'frontend.navigation.page'
                && $message->entityName === 'category'))
            ->willReturn(new Envelope(new \stdClass()));

        $this->subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['template' => 'custom-prefix/{{ category.name }}']),
        ]));
    }

    public function testDispatchesOneMessagePerResolvedRoute(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
            ['routeName' => 'frontend.detail.page', 'entityName' => 'product'],
        ]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['template' => 'a']),
            $this->writeResult(Uuid::randomHex(), ['template' => 'b']),
        ]));
    }

    public function testIgnoresWritesWithoutTemplatePayload(): void
    {
        // Partial writes such as custom-fields-only saves must not trigger the
        // expensive reindexing pass.
        $this->connection->expects($this->never())->method('fetchAllAssociative');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['customFields' => ['foo' => 'bar']]),
        ]));
    }

    public function testIgnoresWriteResultsWithNonStringPrimaryKey(): void
    {
        $this->connection->expects($this->never())->method('fetchAllAssociative');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(['id' => Uuid::randomHex()], ['template' => 'x']),
        ]));
    }

    public function testIgnoresRoutesWithEmptyRouteOrEntityName(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => '', 'entityName' => 'category'],
            ['routeName' => 'frontend.navigation.page', 'entityName' => ''],
        ]);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['template' => 'x']),
        ]));
    }

    /**
     * @param list<EntityWriteResult<string|array<string, string>>> $writeResults
     */
    private function createEvent(array $writeResults): EntityWrittenEvent
    {
        return new EntityWrittenEvent('seo_url_template', $writeResults, Context::createDefaultContext());
    }

    /**
     * @param array<string, string>|string $primaryKey
     * @param array<string, mixed> $payload
     *
     * @return EntityWriteResult<string|array<string, string>>
     */
    private function writeResult(array|string $primaryKey, array $payload): EntityWriteResult
    {
        return new EntityWriteResult($primaryKey, $payload, 'seo_url_template', EntityWriteResult::OPERATION_UPDATE);
    }
}
