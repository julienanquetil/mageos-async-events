<?php

declare(strict_types=1);

namespace Aligent\AsyncEvents\Model\Indexer;

use Aligent\AsyncEvents\Helper\Config;
use Aligent\AsyncEvents\Model\Indexer\DataProvider\AsyncEventSubscriberLogs;
use Aligent\AsyncEvents\Model\Resolver\AsyncEvent;
use ArrayIterator;
use Magento\CatalogSearch\Model\Indexer\IndexerHandlerFactory;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Magento\Framework\Indexer\SaveHandler\IndexerInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Traversable;

class AsyncEventSubscriber implements
    IndexerActionInterface,
    MviewActionInterface,
    DimensionalIndexerInterface
{
    /**
     * Indexer ID in configuration
     */
    private const INDEXER_ID = 'asynchronous_event_subscriber_log';

    /**
     * Default batch size
     */
    private const BATCH_SIZE = 100;

    /**
     * Deployment config path
     *
     * @var string
     */
    private const DEPLOYMENT_CONFIG_INDEXER_BATCHES = 'indexer/batch_size/';

    /**
     * @var int
     */
    private int $batchSize;

    /**
     * @var DeploymentConfig|null
     */
    private $deploymentConfig;

    /**
     * @param DimensionProviderInterface $dimensionProvider
     * @param IndexerHandlerFactory $indexerHandlerFactory
     * @param AsyncEventSubscriberLogs $asyncEventSubscriberLogsDataProvider
     * @param AsyncEvent $asyncEventScopeResolver
     * @param Config $config
     * @param array $data
     * @param int|null $batchSize
     * @param DeploymentConfig|null $deploymentConfig
     */
    public function __construct(
        private readonly DimensionProviderInterface $dimensionProvider,
        private readonly IndexerHandlerFactory $indexerHandlerFactory,
        private readonly AsyncEventSubscriberLogs $asyncEventSubscriberLogsDataProvider,
        private readonly AsyncEvent $asyncEventScopeResolver,
        private readonly Config $config,
        private readonly array $data,
        int $batchSize = null,
        DeploymentConfig $deploymentConfig = null
    ) {
        $this->batchSize = $batchSize ?? self::BATCH_SIZE;
        $this->deploymentConfig = $deploymentConfig ?: ObjectManager::getInstance()->get(DeploymentConfig::class);
    }

    /**
     * @inheritDoc
     */
    public function executeFull()
    {
        $this->execute([]);
    }

    /**
     * @inheritDoc
     */
    public function execute($ids)
    {
        foreach ($this->dimensionProvider->getIterator() as $dimension) {
            $this->executeByDimensions($dimension, new ArrayIterator($ids));
        }
    }

    /**
     * @inheritDoc
     */
    public function executeByDimensions(array $dimensions, Traversable $entityIds)
    {
        if (!$this->config->isIndexingEnabled()) {
            return;
        }

        $saveHandler = $this->indexerHandlerFactory->create(
            [
                'data' => $this->data,
                'scopeResolver' => $this->asyncEventScopeResolver
            ]
        );

        $asyncEventIds = iterator_to_array($entityIds);

        $this->batchSize = $this->deploymentConfig->get(
            self::DEPLOYMENT_CONFIG_INDEXER_BATCHES . self::INDEXER_ID . '/partial_reindex'
        ) ?? $this->batchSize;

        $asyncEventBatches = array_chunk($asyncEventIds, $this->batchSize);

        foreach ($asyncEventBatches as $asyncEventBatch) {
            $this->processBatch(
                $saveHandler,
                $dimensions,
                $asyncEventBatch
            );
        }
    }

    /**
     * Process a batch of async event log entities to be indexed into Elasticsearch
     *
     * @param IndexerInterface $saveHandler
     * @param array $dimensions
     * @param array $asyncEventLogIds
     * @return void
     */
    private function processBatch(
        IndexerInterface $saveHandler,
        array $dimensions,
        array $asyncEventLogIds
    ) {
        $asyncEvent = $dimensions[0]->getValue();

        if ($saveHandler->isAvailable($dimensions)) {
            $saveHandler->deleteIndex($dimensions, new ArrayIterator($asyncEventLogIds));
            $saveHandler->saveIndex(
                $dimensions,
                $this->asyncEventSubscriberLogsDataProvider->getAsyncEventLogs(
                    $asyncEventLogIds,
                    $asyncEvent
                )
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritDoc
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }
}
