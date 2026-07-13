<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Payment\AppPaymentMethodCriteriaSubscriber;
use Shopware\Core\Framework\App\Privileges\AppCapability;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\System\SalesChannel\Event\SalesChannelProcessCriteriaEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(AppPaymentMethodCriteriaSubscriber::class)]
class AppPaymentMethodCriteriaSubscriberTest extends TestCase
{
    public function testExcludesUngrantedAppPaymentMethods(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['app-granted', 'app-denied']);

        $appCapability = static::createStub(AppCapability::class);
        $appCapability->method('can')->willReturnMap([
            ['app-granted', Payments::PERMISSION, true],
            ['app-denied', Payments::PERMISSION, false],
        ]);

        $criteria = new Criteria();
        (new AppPaymentMethodCriteriaSubscriber($connection, $appCapability))->hideUngrantedAppPaymentMethods(
            new SalesChannelProcessCriteriaEvent($criteria, static::createStub(SalesChannelContext::class))
        );

        $filters = $criteria->getFilters();
        static::assertCount(1, $filters);
        static::assertInstanceOf(NandFilter::class, $filters[0]);

        $inner = $filters[0]->getQueries();
        static::assertCount(1, $inner);
        static::assertInstanceOf(EqualsAnyFilter::class, $inner[0]);
        static::assertSame('appPaymentMethod.appId', $inner[0]->getField());
        static::assertSame(['app-denied'], $inner[0]->getValue());
    }

    public function testAddsNoFilterWhenAllAppsGranted(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['app-granted']);

        $appCapability = static::createStub(AppCapability::class);
        $appCapability->method('can')->willReturn(true);

        $criteria = new Criteria();
        (new AppPaymentMethodCriteriaSubscriber($connection, $appCapability))->hideUngrantedAppPaymentMethods(
            new SalesChannelProcessCriteriaEvent($criteria, static::createStub(SalesChannelContext::class))
        );

        static::assertSame([], $criteria->getFilters());
    }
}
