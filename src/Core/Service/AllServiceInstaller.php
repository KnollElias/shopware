<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\Message\UpdateServiceMessage;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
class AllServiceInstaller
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly Client $serviceRegistryClient,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly EntityRepository $appRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * This is a low-level class that is responsible for installing all services.
     * It should only be called from a higher-level with 'state' awareness class, Specifically: Shopware\Core\Service\LifecycleManager
     *
     * @return array<string> The newly installed services
     */
    public function install(Context $context): array
    {
        return $this->installNewServices(
            $this->loadExistingServices($context),
            $this->serviceRegistryClient->getAll(),
            $context
        );
    }

    /**
     * Like install(), but also schedules an idempotent update for every already-installed service so it
     * converges to the registry's best revision.
     *
     * @return array<string> The newly installed services
     */
    public function reconcile(Context $context): array
    {
        $existingServices = $this->loadExistingServices($context);
        $registryServices = $this->serviceRegistryClient->getAll();

        $installedServices = $this->installNewServices($existingServices, $registryServices, $context);

        $this->scheduleServiceUpdates($existingServices, $registryServices);

        return $installedServices;
    }

    public function scheduleInstall(): void
    {
        $this->messageBus->dispatch(new InstallServicesMessage());
    }

    private function loadExistingServices(Context $context): AppCollection
    {
        return $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('selfManaged', true)),
            $context
        )->getEntities();
    }

    /**
     * @param array<ServiceEntry> $registryServices
     *
     * @return array<string>
     */
    private function installNewServices(AppCollection $existingServices, array $registryServices, Context $context): array
    {
        $installedServices = [];
        foreach ($this->getNewServices($existingServices, $registryServices) as $service) {
            $result = $this->serviceLifecycle->install($service, $context);

            if ($result) {
                $installedServices[] = $service->name;
            }
        }

        if ($installedServices !== []) {
            $this->eventDispatcher->dispatch(new NewServicesInstalledEvent());
        }

        return $installedServices;
    }

    /**
     * The update is idempotent (ServiceLifecycle::update no-ops when the revision already matches), so
     * enqueuing for every in-registry service is safe.
     *
     * @param array<ServiceEntry> $registryServices
     */
    private function scheduleServiceUpdates(AppCollection $existingServices, array $registryServices): void
    {
        $registryServiceNames = [];
        foreach ($registryServices as $registryService) {
            $registryServiceNames[$registryService->name] = true;
        }

        $scheduled = [];
        foreach ($existingServices as $service) {
            if (isset($registryServiceNames[$service->getName()])) {
                $this->messageBus->dispatch(new UpdateServiceMessage($service->getName()));
                $scheduled[] = $service->getName();
            }
        }

        if ($scheduled !== []) {
            $this->logger->debug('Reconcile scheduled updates for installed services', [
                'count' => \count($scheduled),
                'services' => $scheduled,
            ]);
        }
    }

    /**
     * @param array<ServiceEntry> $registryServices
     *
     * @return array<ServiceEntry>
     */
    private function getNewServices(AppCollection $installedServices, array $registryServices): array
    {
        $names = $installedServices->map(static fn (AppEntity $app) => $app->getName());

        return array_filter(
            $registryServices,
            static fn (ServiceEntry $service) => !\in_array($service->name, $names, true)
        );
    }
}
