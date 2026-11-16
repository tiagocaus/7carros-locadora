<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
final class User implements UserEntityInterface
{
    use EntityTrait;
    public function __construct(string $connectionId) { $this->identifier = $connectionId; }
}
