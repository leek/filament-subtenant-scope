<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope\Livewire;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Leek\FilamentSubtenantScope\SubtenantScope;
use Leek\FilamentSubtenantScope\SubtenantScopeManager;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SubtenantScopeSelector extends Component
{
    /** @var array<string, int|null> */
    public array $selectedValues = [];

    public function mount(): void
    {
        $manager = resolve(SubtenantScopeManager::class);

        foreach ($manager->getScopes() as $scope) {
            $this->selectedValues[$scope->key] = $manager->getSelectedId($scope->key);
        }
    }

    public function updateScope(string $scopeKey, ?int $value): void
    {
        $selectedId = $value ?: null;

        $manager = resolve(SubtenantScopeManager::class);
        $manager->setSelectedId($scopeKey, $selectedId);
        $this->selectedValues[$scopeKey] = $selectedId;

        $this->dispatch('sub-scope-changed', scopeKey: $scopeKey, value: $selectedId);
        $this->js($this->reloadWithoutScopeQueryParametersScript());
    }

    /**
     * @return Collection<int, SubtenantScope>
     */
    #[Computed]
    public function scopes(): Collection
    {
        return collect(resolve(SubtenantScopeManager::class)->getScopes());
    }

    /**
     * @return Collection<int, Model>
     */
    public function getOptionsForScope(SubtenantScope $scope): Collection
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return resolve(SubtenantScopeManager::class)->getOptionsForScope($scope, $user);
    }

    public function render(): View
    {
        return view('filament-subtenant-scope::livewire.selector');
    }

    private function reloadWithoutScopeQueryParametersScript(): string
    {
        $queryParameters = $this->scopes
            ->map(fn (SubtenantScope $scope): string => "scope_{$scope->key}")
            ->values()
            ->toJson();

        return <<<JS
            const url = new URL(window.location.href);

            for (const queryParameter of {$queryParameters}) {
                url.searchParams.delete(queryParameter);
            }

            window.history.replaceState({}, '', url);
            window.location.reload();
        JS;
    }
}
