<div class="fi-subtenant-scope-selector flex items-center gap-2 [&_.fi-dropdown-panel]:z-[70]">
    @foreach ($this->scopes as $scope)
        @php
            $options = $this->getOptionsForScope($scope);
            $selectedId = $this->selectedValues[$scope->key] ?? null;
            $selectedOption = $selectedId ? $options->firstWhere('id', $selectedId) : null;
            $labelAttr = $scope->getLabelAttribute();
            $hasMultiple = $options->count() > 1;
        @endphp

        @if ($options->count() === 1)
            {{-- Single option: static indicator --}}
            <div class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-white/90">
                @if ($scope->getIcon())
                    <x-filament::icon :icon="$scope->getIcon()" class="h-4 w-4 text-white/70" />
                @endif
                <span>{{ $options->first()->{$labelAttr} }}</span>
            </div>
        @elseif ($hasMultiple)
            {{-- Multiple options: dropdown --}}
            <x-filament::dropdown placement="bottom-start">
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors hover:bg-white/10"
                    >
                        @if ($scope->getIcon())
                            <x-filament::icon :icon="$scope->getIcon()" class="h-4 w-4 text-white/70" />
                        @endif
                        <span class="text-white/90">
                            @if ($selectedOption)
                                {{ $selectedOption->{$labelAttr} }}
                            @else
                                All<span class="hidden sm:inline"> {{ $scope->label }}s</span>
                            @endif
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-down" class="h-3.5 w-3.5 text-white/50" />
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    {{-- "All" option --}}
                    <x-filament::dropdown.list.item
                        wire:click="updateScope('{{ $scope->key }}', null)"
                        :icon="$selectedId === null ? 'heroicon-o-check-circle' : null"
                        :class="$selectedId === null ? 'bg-primary-50 dark:bg-primary-950' : ''"
                    >
                        All {{ $scope->label }}s
                    </x-filament::dropdown.list.item>

                    <hr class="border-gray-200 dark:border-white/10 my-1" />

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
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    @endforeach
</div>
