@extends('platform::layouts.embedded')

@section('content')
<div class="min-h-screen w-full bg-white">
  <div class="max-w-4xl mx-auto p-6 space-y-6">
    <h1 class="text-xl font-semibold text-[var(--ui-secondary)]">Teams – Zentrale Konfiguration</h1>
    <p class="text-sm text-[var(--ui-muted)]">Wähle, was du als Tab hinzufügen möchtest.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <a href="{{ url('/planner/embedded/teams/config') }}" class="block p-4 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)] transition bg-[var(--ui-muted-5)]">
        <div class="flex items-center gap-3">
          @svg('heroicon-o-clipboard-document-list','w-6 h-6 text-[var(--ui-secondary)]')
          <div>
            <div class="font-medium text-[var(--ui-secondary)]">Planner</div>
            <div class="text-xs text-[var(--ui-muted)]">Projekt auswählen und als Tab hinzufügen</div>
          </div>
        </div>
      </a>

      <a href="{{ url('/okr/embedded/teams/config') }}" class="block p-4 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)] transition bg-[var(--ui-muted-5)]">
        <div class="flex items-center gap-3">
          @svg('heroicon-o-flag','w-6 h-6 text-[var(--ui-secondary)]')
          <div>
            <div class="font-medium text-[var(--ui-secondary)]">OKRs</div>
            <div class="text-xs text-[var(--ui-muted)]">Ziele/Key Results als Tab einbinden</div>
          </div>
        </div>
      </a>

      <a href="{{ url('/helpdesk/embedded/teams/config') }}" class="block p-4 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)] transition bg-[var(--ui-muted-5)]">
        <div class="flex items-center gap-3">
          @svg('heroicon-o-lifebuoy','w-6 h-6 text-[var(--ui-secondary)]')
          <div>
            <div class="font-medium text-[var(--ui-secondary)]">Helpdesk</div>
            <div class="text-xs text-[var(--ui-muted)]">Ticket-Boards als Tab einbinden</div>
          </div>
        </div>
      </a>
    </div>
  </div>
</div>
@endsection
