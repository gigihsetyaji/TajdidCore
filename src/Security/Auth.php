<?php
declare(strict_types=1);
namespace Tajdid\Core\Security;
final class Auth
{
    public function __construct(private Session $session, private UserProvider $users) {}
    public function attempt(array $credentials, #[\SensitiveParameter] string $password): bool
    {
        if (strlen($password) > 4096 || str_contains($password, "\0")) { return false; }
        $user = $this->users->findByCredentials($credentials);
        // A dummy hash keeps a missing account on the password verification path.
        $hash = $user['password'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (str_starts_with($hash, '$2') && strlen($password) > 72) { return false; }
        $verified = password_verify($password, $hash);
        if (!$verified || $user === null || !isset($user['id'])) { return false; }
        $this->session->invalidate();
        $this->session->put('_auth_id', (string) $user['id']);
        return true;
    }
    public function user(): ?array
    {
        $id = $this->session->get('_auth_id');
        $user = is_string($id) ? $this->users->findById($id) : null;
        if ($user !== null) { unset($user['password']); }
        return $user;
    }
    public function logout(): void { $this->session->invalidate(); }
}
