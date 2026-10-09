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
     * Scopes nested directly under the given one via {@see SubtenantScope::dependsOn()}.
     *
     * @return array<SubtenantScope>
     */
    public function getChildScopes(string $parentKey): array
    {
        return array_values(array_filter(
            $this->scopes,
            fn (SubtenantScope $scope): bool => $scope->getParentKey() === $parentKey,
        ));
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
        $selectedId = $this->resolveStoredSelectedId($scopeKey);
        $scope = $this->getScope($scopeKey);
        $user = $this->getUser();

        // A nested selection only counts while it sits inside the parent's
        // current selection: a stale pair would otherwise match nothing.
        if ($selectedId !== null && $scope?->hasParent() && $user
            && ! $this->optionsContain($scope, $user, $selectedId)) {
            return null;
        }

        return $selectedId;
    }

    protected function resolveStoredSelectedId(string $scopeKey): ?int
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
            // A nested scope is exempt: its one option is still a narrower filter
            // than its parent alone (a service area's only clinic).
            $scope = $this->getScope($scopeKey);
            if ($scope && ! $scope->hasParent()) {
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

        $this->releaseChildSelections($scopeKey, $id, $user);
    }

    /**
     * A child selection survives a parent change only when it is still inside
     * the newly selected parent. Clearing the parent ("All") clears the child
     * too: keeping one clinic selected under "All areas" would leave the user
     * looking at a single clinic while the topbar reads "All".
     */
    protected function releaseChildSelections(string $parentKey, ?int $parentId, ?Authenticatable $user): void
    {
        foreach ($this->getChildScopes($parentKey) as $child) {
            $this->forgetOptions($child->key);
            unset($this->selectedIdCache[$child->key]);

            $childId = $this->resolveStoredSelectedId($child->key);

            if ($childId === null) {
                continue;
            }

            if ($parentId === null || ! $user || ! $this->optionsContain($child, $user, $childId)) {
                $this->setSelectedId($child->key, null);
            }
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

        $query = $scope->resolveOptionsQuery($user);

        if ($scope->hasParent()) {
            $parentId = $this->getSelectedId((string) $scope->getParentKey());

            if ($parentId !== null) {
                $query->where($query->qualifyColumn((string) $scope->getParentColumn()), $parentId);
            }
        }

        $result = $query->get();
        $this->optionsCache[$scope->key] = $result;

        return $result;
    }

    protected function optionsContain(SubtenantScope $scope, Authenticatable $user, int $id): bool
    {
        return $this->getOptionsForScope($scope, $user)
            ->contains(fn (Model $option): bool => (int) $option->getKey() === $id);
    }

    protected function forgetOptions(string $scopeKey): void
    {
        unset($this->optionsCache[$scopeKey]);

        foreach ($this->getChildScopes($scopeKey) as $child) {
            $this->forgetOptions($child->key);
        }
    }

    protected function getUser(): ?Authenticatable
    {
        return auth()->user();
    }
}
