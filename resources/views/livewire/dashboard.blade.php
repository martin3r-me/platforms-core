<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Platform Dashboard" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Team-Info</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <div class="text-sm text-[var(--ui-muted)]">Aktuelles Team</div>
                            <div class="font-semibold text-[var(--ui-secondary)]">{{ $currentTeam?->name ?? 'Kein Team' }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <div class="text-sm text-[var(--ui-muted)]">Mitglieder</div>
                            <div class="font-semibold text-[var(--ui-secondary)]">{{ count($teamMembers) }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <div class="text-sm text-[var(--ui-muted)]">Module</div>
                            <div class="font-semibold text-[var(--ui-secondary)]">{{ count($modules) }}</div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="primary" size="sm" x-data @click="$dispatch('open-modal-team')" class="w-full">
                            Team verwalten
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" x-data @click="$dispatch('open-modal-modules')" class="w-full">
                            Module verwalten
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Verfügbare Module</h3>
                    <div class="space-y-2">
                        @foreach($modules as $key => $module)
                            @php
                                $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                                $routeName = $module['navigation']['route'] ?? null;
                                $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                    ? route($routeName)
                                    : ($module['url'] ?? '#');
                            @endphp
                            <a href="{{ $finalUrl }}" class="block p-2 rounded-lg hover:bg-[var(--ui-muted-5)] transition">
                                <div class="font-medium text-[var(--ui-secondary)] text-sm">{{ $title }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Letzte Aktivitäten</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <div class="text-sm text-[var(--ui-muted)]">Team gewechselt</div>
                            <div class="font-semibold text-[var(--ui-secondary)]">{{ $currentTeam?->name ?? 'Kein Team' }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">vor 2 Minuten</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg">
                            <div class="text-sm text-[var(--ui-muted)]">Dashboard aufgerufen</div>
                            <div class="font-semibold text-[var(--ui-secondary)]">Platform Dashboard</div>
                            <div class="text-xs text-[var(--ui-muted)]">vor 5 Minuten</div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellzugriff</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" x-data @click="$dispatch('open-modal-user')" class="w-full">
                            Benutzer-Einstellungen
                        </x-ui-button>
                        <x-ui-button variant="secondary" size="sm" x-data @click="$dispatch('open-modal-checkin')" class="w-full">
                            Täglicher Check-in
                        </x-ui-button>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <!-- Platform Stats Section -->
        <div class="bg-[var(--ui-surface)] py-16 sm:py-24 rounded-xl border border-[var(--ui-border)]/60 mb-8">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:mx-0">
                    <h2 class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)] sm:text-4xl">Wir gestalten die Zukunft der Arbeit</h2>
                    <p class="mt-6 text-lg text-[var(--ui-muted)]">Unsere Plattform verbindet Teams, optimiert Prozesse und schafft neue Möglichkeiten für produktive Zusammenarbeit.</p>
                </div>
                    <div class="mx-auto mt-16 flex max-w-2xl flex-col gap-8 lg:mx-0 lg:mt-20 lg:max-w-none lg:flex-row lg:items-end">
                        <div class="flex flex-col-reverse justify-between gap-x-16 gap-y-8 rounded-2xl bg-[var(--ui-muted-5)] p-8 sm:w-3/4 sm:max-w-md sm:flex-row-reverse sm:items-end lg:w-72 lg:max-w-none lg:flex-none lg:flex-col lg:items-start">
                            <p class="flex-none text-3xl font-bold tracking-tight text-[var(--ui-secondary)]">{{ count($teamMembers) }}</p>
                            <div class="sm:w-80 sm:shrink lg:w-auto lg:flex-none">
                                <p class="text-lg font-semibold tracking-tight text-[var(--ui-secondary)]">Team-Mitglieder</p>
                                <p class="mt-2 text-base/7 text-[var(--ui-muted)]">Aktive Nutzer in {{ $currentTeam?->name ?? 'Ihrem Team' }}.</p>
                            </div>
                        </div>
                        <div class="flex flex-col-reverse justify-between gap-x-16 gap-y-8 rounded-2xl bg-[var(--ui-primary)] p-8 sm:flex-row-reverse sm:items-end lg:w-full lg:max-w-sm lg:flex-auto lg:flex-col lg:items-start lg:gap-y-44">
                            <p class="flex-none text-3xl font-bold tracking-tight text-white">€{{ number_format($monthlyTotal, 2) }}</p>
                            <div class="sm:w-80 sm:shrink lg:w-auto lg:flex-none">
                                <p class="text-lg font-semibold tracking-tight text-white">Monatliche Kosten</p>
                                <p class="mt-2 text-base/7 text-[var(--ui-primary-200)]">Aktuelle Ausgaben für Module und Services.</p>
                            </div>
                        </div>
                        <div class="flex flex-col-reverse justify-between gap-x-16 gap-y-8 rounded-2xl bg-[var(--ui-success)] p-8 sm:w-11/12 sm:max-w-xl sm:flex-row-reverse sm:items-end lg:w-full lg:max-w-none lg:flex-auto lg:flex-col lg:items-start lg:gap-y-28">
                            <p class="flex-none text-3xl font-bold tracking-tight text-white">{{ count($modules) }}</p>
                            <div class="sm:w-80 sm:shrink lg:w-auto lg:flex-none">
                                <p class="text-lg font-semibold tracking-tight text-white">Verfügbare Module</p>
                                <p class="mt-2 text-base/7 text-[var(--ui-success-200)]">Tools und Services für Ihr Team.</p>
                            </div>
                        </div>
                    </div>
            </div>
        </div>

        <!-- Platform Features Section -->
        <div class="bg-[var(--ui-surface)] py-16 sm:py-24 rounded-xl border border-[var(--ui-border)]/60 mb-8">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:mx-0 lg:max-w-none">
                    <p class="text-base/7 font-semibold text-[var(--ui-primary)]">Effizienter arbeiten</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[var(--ui-secondary)] sm:text-4xl">Ein besseres Arbeitsumfeld</h1>
                    <div class="mt-10 grid max-w-xl grid-cols-1 gap-8 text-base/7 text-[var(--ui-muted)] lg:max-w-none lg:grid-cols-2">
                        <div>
                            <p>Unsere Plattform vereint alle wichtigen Tools an einem Ort. Teams können nahtlos zusammenarbeiten, ohne zwischen verschiedenen Anwendungen wechseln zu müssen. Die intuitive Benutzeroberfläche macht komplexe Prozesse einfach und übersichtlich.</p>
                            <p class="mt-8">Mit intelligenten Automatisierungen und KI-gestützten Workflows optimieren wir tägliche Arbeitsabläufe. Jede Minute wird effizienter genutzt, jede Aufgabe wird präziser ausgeführt.</p>
                        </div>
                        <div>
                            <p>Die modulare Architektur ermöglicht es, die Plattform genau an die Bedürfnisse Ihres Teams anzupassen. Ob Projektmanagement, CRM oder Dokumentenverwaltung – alles ist integriert und synchronisiert.</p>
                            <p class="mt-8">Durch kontinuierliche Weiterentwicklung und Feedback-Integration stellen wir sicher, dass unsere Plattform immer den aktuellen Anforderungen entspricht und neue Möglichkeiten eröffnet.</p>
                        </div>
                    </div>
                    <dl class="mt-16 grid grid-cols-1 gap-x-8 gap-y-12 sm:mt-20 sm:grid-cols-2 sm:gap-y-16 lg:mt-28 lg:grid-cols-4">
                        <div class="flex flex-col-reverse gap-y-3 border-l border-[var(--ui-border)] pl-6">
                            <dt class="text-base/7 text-[var(--ui-muted)]">Gegründet</dt>
                            <dd class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)]">2024</dd>
                        </div>
                        <div class="flex flex-col-reverse gap-y-3 border-l border-[var(--ui-border)] pl-6">
                            <dt class="text-base/7 text-[var(--ui-muted)]">Aktive Teams</dt>
                            <dd class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)]">{{ count($teamMembers) }}</dd>
                        </div>
                        <div class="flex flex-col-reverse gap-y-3 border-l border-[var(--ui-border)] pl-6">
                            <dt class="text-base/7 text-[var(--ui-muted)]">Module</dt>
                            <dd class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)]">{{ count($modules) }}</dd>
                        </div>
                        <div class="flex flex-col-reverse gap-y-3 border-l border-[var(--ui-border)] pl-6">
                            <dt class="text-base/7 text-[var(--ui-muted)]">Uptime</dt>
                            <dd class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)]">99.9%</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Available Tools Section -->
        <div class="bg-[var(--ui-surface)] py-16 sm:py-24 rounded-xl border border-[var(--ui-border)]/60 mb-8">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:mx-0">
                    <h2 class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)] sm:text-4xl">Verfügbare Tools</h2>
                    <p class="mt-6 text-lg text-[var(--ui-muted)]">Alle Module und Services, auf die Sie Zugriff haben, um Ihre Arbeit zu optimieren.</p>
                </div>
                <div class="mx-auto mt-16 grid max-w-2xl grid-cols-1 gap-8 lg:mx-0 lg:max-w-none lg:grid-cols-2 xl:grid-cols-3">
                    @foreach($modules as $key => $module)
                        @php
                            $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                            $description = $module['description'] ?? 'Ein leistungsstarkes Tool für Ihr Team.';
                            $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                ? route($routeName)
                                : ($module['url'] ?? '#');
                        @endphp
                        <div class="group relative flex flex-col gap-6 rounded-2xl bg-[var(--ui-muted-5)] p-8 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0">
                                    @if(!empty($icon))
                                        <x-dynamic-component :component="$icon" class="w-8 h-8 text-[var(--ui-primary)]" />
                                    @else
                                        @svg('heroicon-o-cube', 'w-8 h-8 text-[var(--ui-primary)]')
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] transition-colors">{{ $title }}</h3>
                                </div>
                            </div>
                            <p class="text-[var(--ui-muted)] text-sm leading-relaxed">{{ $description }}</p>
                            <div class="mt-auto">
                                <a href="{{ $finalUrl }}" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition-colors">
                                    Tool öffnen
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Team Section -->
        <div class="bg-[var(--ui-surface)] py-16 sm:py-24 rounded-xl border border-[var(--ui-border)]/60">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:mx-0">
                    <h2 class="text-3xl font-semibold tracking-tight text-[var(--ui-secondary)] sm:text-4xl">Unser Team</h2>
                    <p class="mt-6 text-lg text-[var(--ui-muted)]">Wir sind ein dynamisches Team von Menschen, die leidenschaftlich an dem arbeiten, was wir tun, und bestrebt sind, die besten Ergebnisse für unsere Kunden zu liefern.</p>
                </div>
                <ul role="list" class="mx-auto mt-16 grid max-w-2xl grid-cols-2 gap-x-8 gap-y-16 text-center sm:grid-cols-3 md:grid-cols-4 lg:mx-0 lg:max-w-none lg:grid-cols-5 xl:grid-cols-6">
                    @foreach($teamMembers as $member)
                        <li>
                            <div class="mx-auto size-24 rounded-full bg-[var(--ui-primary-5)] flex items-center justify-center outline-1 -outline-offset-1 outline-[var(--ui-border)]">
                                <span class="text-2xl font-semibold text-[var(--ui-primary)]">
                                    {{ strtoupper(substr($member->name, 0, 2)) }}
                                </span>
                            </div>
                            <h3 class="mt-6 text-base/7 font-semibold tracking-tight text-[var(--ui-secondary)]">{{ $member->name }}</h3>
                            <p class="text-sm/6 text-[var(--ui-muted)]">
                                @if($member->id === $currentTeam?->user_id)
                                    Team-Leiter
                                @else
                                    Team-Mitglied
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>