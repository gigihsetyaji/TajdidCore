<?php
declare(strict_types=1);
namespace Tajdid\Core\Security;
interface UserProvider
{
    /** Return an application user with string id and password hash, or null. */
    public function findByCredentials(array $credentials): ?array;
    public function findById(string $id): ?array;
}
