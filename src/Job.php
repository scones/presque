<?php

declare(strict_types=1);

namespace Resque;

use Psr\Container\ContainerInterface;
use Resque\Dispatchers\Noop;
use Resque\Interfaces\DispatcherInterface;
use Resque\Tasks\AfterUserJobPerform;
use Resque\Tasks\BeforeUserJobPerform;
use Resque\Tasks\FailedUserJobPerform;

class Job
{
    private $queueName;
    private $payload;
    private $serviceLocator;
    private $dispatcher;
    private $failed = false;

    public function __construct(string $queueName, array $payload, ContainerInterface $serviceLocator)
    {
        $this->queueName = $queueName;
        $this->payload = $payload;
        $this->serviceLocator = $serviceLocator;
        $this->dispatcher = new Noop();
    }

    public function setDispatcher(DispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    public function getPayloadClassName(): string
    {
        return $this->payload['class'];
    }

    public function getPayloadArguments(): array
    {
        return $this->payload['args'];
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function hasFailed(): bool
    {
        return $this->failed;
    }

    public function perform(): void
    {
        $this->dispatcher->dispatch(BeforeUserJobPerform::class, $this->payload);
        try {
            $userJob = $this->serviceLocator->get($this->getPayloadClassName());
            $userJob->perform($this->getPayloadArguments());
            $this->dispatcher->dispatch(AfterUserJobPerform::class, $this->payload);
        } catch (\Exception $e) {
            $this->handleFailedJob();
        }
    }

    private function handleFailedJob()
    {
        $this->failed = true;
        $this->dispatcher->dispatch(FailedUserJobPerform::class, $this->payload);
    }
}
