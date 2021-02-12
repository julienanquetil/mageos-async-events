<?php

namespace Aligent\Webhooks\Service\Webhook;

interface NotifierFactoryInterface
{
    /**
     * Creates a custom notifier implementation instructed by what's stored in the db
     *
     * @param string $type
     * @return NotifierInterface
     */
    public function create(string $type): NotifierInterface;
}
