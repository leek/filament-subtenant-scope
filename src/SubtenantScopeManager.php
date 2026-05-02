<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class SubtenantScopeManager
{
    /** @var array<SubtenantScope> */
    protected array $scopes = [];

    /** @var array<string, Collection<int, Model>> */
    protected array $optionsCache = [];

    /** @var array<string, int|null|false> false = not resolved yet */
    protected array $selectedIdCache = [];

    protected ?Closure $persistGetter = null;

    protected ?Closure $persistSetter = null;

    /**
     * @param  array<SubtenantScope>  $scopes
     */
    public function registerScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    /**
     * Configure user-level persistence.
     *
     * - $get: fn (Authenticatable $user, string $scopeKey): ?int
     * - $set: fn (Authenticatable $user, string $scopeKey, ?int $id): void
     */
    public function persistUsing(?Closure $get, ?Closure $set): void
    {
        $this->persistGetter = $get;
        $this->persistSetter = $set;
    }

    /**
     * @return array<SubtenantScope>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getScope(string $key): ?SubtenantScope
    {
        foreach ($this->scopes as $scope) {
            if ($scope->key === $key) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * Resolve selected ID with priority: URL param > session > user persistence.
     * Returns null to mean "All" (no filtering).
     */
    public function getSelectedId(string $scopeKey): ?int
    {
        if (array_key_exists($scopeKey, $this->selectedIdCache)) {
            $cached = $this->selectedIdCache[$scopeKey];

            return $cached === false ? null : $cached;
        }

        $result = $this->resolveSelectedId($scopeKey);
        $this->selectedIdCache[$scopeKey] = $result;

        return $result;
    }

    protected function resolveSelectedId(string $scopeKey): ?int
    {
        // 1. URL param — persisted so bookmarked/shared scoped URLs stay sticky.
        $queryParameter = "scope_{$scopeKey}";
        if (request()->query->has($queryParameter)) {
            $urlParam = request()->query($queryParameter);
            $selectedId = filled($urlParam) ? (int) $urlParam : null;

            $this->setSelectedId($scopeKey, $selectedId);

            return $selectedId;
        }

        // 2. Session.
        $sessionKey = "sub_scope.{$scopeKey}";
        $sessionValue = Session::get($sessionKey);
        if ($sessionValue !== null) {
            return (int) $sessionValue;
        }

        // 3. User-level persistence (optional).
        $user = $this->getUser();
        if ($user) {
            // If user has exactly 1 option there is nothing to filter between, so don't.
            $scope = $this->getScope($scopeKey);
            if ($scope) {
                $options = $this->getOptionsForScope($scope, $user);
                if ($options->count() === 1) {
                    return null;
                }
            }

            if ($this->persistGetter) {
                $stored = ($this->persistGetter)($user, $scopeKey);
                if ($stored !== null) {
                    Session::put($sessionKey, $stored);

                    return (int) $stored;
                }
            }
        }

        return null;
    }

    /**
     * Persist selected ID to session and (if configured) user storage.
     * Pass null to clear (means "All").
     */
    public function setSelectedId(string $scopeKey, ?int $id): void
    {
        $this->selectedIdCache[$scopeKey] = $id;
        $sessionKey = "sub_scope.{$scopeKey}";

        if ($id === null) {
            Session::forget($sessionKey);
        } else {
            Session::put($sessionKey, $id);
        }

        $user = $this->getUser();
        if ($user && $this->persistSetter) {
            ($this->persistSetter)($user, $scopeKey, $id);
        }
    }

    /**
     * Get available options for a scope, respecting the optionsQuery.
     *
     * @return Collection<int, Model>
     */
    public function getOptionsForScope(SubtenantScope $scope, Authenticatable $user): Collection
    {
        if (array_key_exists($scope->key, $this->optionsCache)) {
            return $this->optionsCache[$scope->key];
        }

        $result = $scope->resolveOptionsQuery($user)->get();
        $this->optionsCache[$scope->key] = $result;

        return $result;
    }

    protected function getUser(): ?Authenticatable
    {
        return auth()->user();
    }
}
