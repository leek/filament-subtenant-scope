{{--
    Teleport is required: Filament's .fi-topbar-ctn uses overflow-x-clip, which
    (via the CSS overflow pairing rules) also clips the y-axis. Without teleport
    the panel opens but is clipped to the topbar height and appears "stuck".
    Same pattern as topbar nav groups + user menu.
--}}
<div class="fi-subtenant-scope-selector flex items-center gap-1">
    @foreach ($this->scopes as $scope)
        @php
            $options = $this->getOptionsForScope($scope);
            $selectedId = $this->selectedValues[$scope->key] ?? null;
            $selectedOption = $selectedId ? $options->firstWhere('id', $selectedId) : null;
            $labelAttr = $scope->getLabelAttribute();
            // A nested scope keeps its dropdown with a single option: picking the
            // one clinic in an area is still narrower than the area alone.
            $hasMultiple = $options->count() > 1 || ($scope->hasParent() && $options->isNotEmpty());
            $manageUrl = $hasMultiple ? $scope->resolveManageUrl(auth()->user()) : null;
        @endphp

        @if ($options->count() === 1 && ! $hasMultiple)
            {{-- Single option: static indicator --}}
            <div class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-700 dark:text-white/90">
                @if ($scope->getIcon())
                    <x-filament::icon :icon="$scope->getIcon()" class="h-4 w-4 text-gray-500 dark:text-white/70" />
                @endif
                <span>{{ $options->first()->{$labelAttr} }}</span>
            </div>
        @elseif ($hasMultiple)
            {{-- Multiple options: dropdown --}}
            <x-filament::dropdown placement="bottom-start" teleport>
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg py-1.5 ps-2.5 pe-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-white/90 dark:hover:bg-white/10"
                    >
                        @if ($scope->getIcon())
                            <x-filament::icon :icon="$scope->getIcon()" class="h-4 w-4 text-gray-500 dark:text-white/70" />
                        @endif
                        <span>
                            @if ($selectedOption)
                                {{ $selectedOption->{$labelAttr} }}
                            @else
                                All<span class="hidden sm:inline"> {{ \Illuminate\Support\Str::plural($scope->label) }}</span>
                            @endif
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="h-3.5 w-3.5 text-gray-400 dark:text-white/50" />
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    {{-- "All" option --}}
                    <x-filament::dropdown.list.item
                        wire:click="updateScope('{{ $scope->key }}', null)"
                        :icon="$selectedId === null ? 'heroicon-o-check-circle' : null"
                        :class="$selectedId === null ? 'bg-primary-50 dark:bg-primary-950' : ''"
                    >
                        {{ $this->getAllOptionLabel($scope) }}
                    </x-filament::dropdown.list.item>

                    <hr class="my-1 border-gray-200 dark:border-white/10" />

                    @foreach ($options as $option)
                        @php
                            $isSelected = $selectedId === $option->getKey();
                        @endphp
                        <x-filament::dropdown.list.item
                            wire:click="updateScope('{{ $scope->key }}', {{ $option->getKey() }})"
                            :icon="$isSelected ? 'heroicon-o-check-circle' : null"
                            :class="$isSelected ? 'bg-primary-50 dark:bg-primary-950' : ''"
                        >
                            {{ $option->{$labelAttr} }}
                        </x-filament::dropdown.list.item>
                    @endforeach

                    @if ($manageUrl)
                        <hr class="my-1 border-gray-200 dark:border-white/10" />

                        <x-filament::dropdown.list.item
                            tag="a"
                            :href="$manageUrl"
                            icon="heroicon-m-cog-6-tooth"
                            class="fi-subtenant-scope-manage-link"
                        >
                            {{ $scope->getManageLabel() }}
                        </x-filament::dropdown.list.item>
                    @endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    @endforeach
</div>
