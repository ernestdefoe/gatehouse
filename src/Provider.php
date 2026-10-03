<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\User\AccountActivationMailer;

class Provider extends AbstractServiceProvider
{
    public function register(): void
    {
        // Core listens with the class name and resolves it from the container,
        // so binding it here swaps in the version that knows about the queue.
        $this->container->bind(AccountActivationMailer::class, ActivationMailer::class);
    }
}
