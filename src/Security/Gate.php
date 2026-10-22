<?php
declare(strict_types=1);
namespace Tajdid\Core\Security;
use Tajdid\Core\Http\HttpException;
final class Gate
{
    private array $abilities = [];
    public function define(string $ability, \Closure $policy): void { $this->abilities[$ability] = $policy; }
    public function allows(string $ability, ?array $user, mixed ...$arguments): bool
    {
        return isset($this->abilities[$ability]) && ($this->abilities[$ability])($user, ...$arguments) === true;
    }
    public function authorize(string $ability, ?array $user, mixed ...$arguments): void
    {
        if (!$this->allows($ability, $user, ...$arguments)) { throw new HttpException(403, 'Forbidden'); }
    }
}
