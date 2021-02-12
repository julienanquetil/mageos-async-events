<?php


namespace Aligent\Webhooks\Model;

use Aligent\Webhooks\Service\Webhook\EventDispatcher;

class WebhookTriggerHandler
{
    /**
     * @var EventDispatcher
     */
    private EventDispatcher $dispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param array $messages
     */
    public function process(array $messages)
    {
        $this->dispatcher->dispatch($messages[0], $messages[1]);
    }
}
