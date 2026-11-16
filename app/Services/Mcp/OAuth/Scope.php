<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\{EntityTrait, ScopeTrait};
final class Scope implements ScopeEntityInterface
{
    use EntityTrait, ScopeTrait;
    public function __construct(string $id) { $this->identifier = $id; }
}
