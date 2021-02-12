<?php

namespace Aligent\Webhooks\Service\Webhook;

use Aligent\Webhooks\Api\WebhookRepositoryInterface;
use Aligent\Webhooks\Model\WebhookLogFactory;
use Aligent\Webhooks\Model\WebhookLogRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;

class EventDispatcher
{
    /**
     * @var WebhookRepositoryInterface
     */
    private WebhookRepositoryInterface $webhookRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var NotifierFactoryInterface
     */
    private NotifierFactoryInterface $notifierFactory;

    /**
     * @var WebhookLogRepository
     */
    private WebhookLogRepository $webhookLogRepository;

    /**
     * @var WebhookLogFactory
     */
    private WebhookLogFactory $webhookLogFactory;

    public function __construct(
        WebhookRepositoryInterface $webhookRepository,
        WebhookLogRepository $webhookLogRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        NotifierFactoryInterface $notifierFactory,
        WebhookLogFactory $webhookLogFactory
    ) {
        $this->webhookRepository = $webhookRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->notifierFactory = $notifierFactory;
        $this->webhookLogRepository = $webhookLogRepository;
        $this->webhookLogFactory = $webhookLogFactory;
    }

    /**
     * @param string $eventName
     * @throws AlreadyExistsException
     */
    public function dispatch(string $eventName, string $objectId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('status', 1)
            ->addFilter('event_name', $eventName)
            ->create();

        $webhooks = $this->webhookRepository->getList($searchCriteria)->getItems();

        /** @var \Aligent\Webhooks\Model\Webhook $webhook */
        foreach ($webhooks as $webhook) {

            $notifier = $this->notifierFactory->create($webhook->getMetadata());

            $response = $notifier->notify($webhook, [
                'objectId' => $objectId
            ]);

            $webhookLog = $this->webhookLogFactory->create();
            $webhookLog->setSuccess($response->getSuccess());
            $webhookLog->setSubscriptionId($response->getSubscriptionId());
            $webhookLog->setResponseData($response->getResponseData());

            $this->webhookLogRepository->save($webhookLog);
        }
    }
}
