@extends('platform::layouts.embedded')

@section('content')
<div class="min-h-screen w-full bg-white">
  <div class="max-w-4xl mx-auto p-6 space-y-6">
    <h1 class="text-xl font-semibold text-[var(--ui-secondary)]">Teams – Zentrale Konfiguration</h1>
    <p class="text-sm text-[var(--ui-muted)]">Wähle ein Modul und konfiguriere den Tab.</p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <button type="button" id="tilePlanner" class="block p-4 rounded-lg border border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)] transition bg-[var(--ui-muted-5)]">
        <div class="flex items-center gap-3">
          @svg('heroicon-o-clipboard-document-list','w-6 h-6 text-[var(--ui-secondary)]')
          <div>
            <div class="font-medium text-[var(--ui-secondary)]">Planner</div>
            <div class="text-xs text-[var(--ui-muted)]">Projekt auswählen und als Tab hinzufügen</div>
          </div>
        </div>
      </button>
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

    <!-- Planner Inline Config -->
    <div id="plannerConfig" class="hidden space-y-4">
      <div class="text-sm font-semibold text-[var(--ui-secondary)]">Planner konfigurieren</div>

      <!-- Teams Auswahl -->
      <div class="bg-white rounded-lg border p-4">
        <div class="mb-2">
          <div class="text-sm text-[var(--ui-secondary)]">Team auswählen</div>
          <div class="text-xs text-[var(--ui-muted)]">Nur Teams, denen du angehörst</div>
        </div>
        <div id="teamGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          @forelse(($teams ?? collect()) as $team)
            <button type="button" class="team-tile flex items-center justify-center p-3 rounded-lg border border-[var(--ui-border)] bg-white hover:border-[var(--ui-primary)] text-sm"
                    data-team-id="{{ $team->id }}" data-team-name="{{ $team->name }}">
              <span class="truncate">{{ $team->name }}</span>
            </button>
          @empty
            <div class="text-xs text-[var(--ui-muted)]">Keine Teams gefunden.</div>
          @endforelse
        </div>
      </div>

      <!-- Projekte -->
      <div class="bg-white rounded-lg border p-4">
        <div class="flex items-center justify-between mb-2">
          <div>
            <div class="text-sm text-[var(--ui-secondary)]">Projekt auswählen</div>
            <div class="text-xs text-[var(--ui-muted)]">Nur Projekte aus dem gewählten Team</div>
          </div>
          <button id="newProjectBtn" type="button" class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-[var(--ui-border)] text-[var(--ui-secondary)] text-xs rounded hover:bg-[var(--ui-muted-5)]">
            @svg('heroicon-o-plus','w-4 h-4') Neues Projekt
          </button>
        </div>
        <select id="projectSelect" class="w-full rounded border border-[var(--ui-border)] px-3 py-2 text-sm bg-white">
          <option value="">– Bitte erst ein Team wählen –</option>
        </select>
        <div class="text-xs text-[var(--ui-muted)] mt-2"><span id="projectCount">0</span> Projekte verfügbar</div>
      </div>

      <div class="flex items-center justify-end">
        <button id="plannerSave" type="button" class="px-4 py-2 rounded bg-[var(--ui-primary)] text-white text-sm disabled:opacity-50" disabled>Als Tab hinzufügen</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const tilePlanner = document.getElementById('tilePlanner');
  const plannerConfig = document.getElementById('plannerConfig');
  const teamTiles = document.querySelectorAll('.team-tile');
  const projectSelect = document.getElementById('projectSelect');
  const projectCountEl = document.getElementById('projectCount');
  const saveBtn = document.getElementById('plannerSave');

  const allProjects = @json($plannerProjects ?? []);
  let selectedTeamId = null;

  tilePlanner?.addEventListener('click', function(){
    plannerConfig.classList.remove('hidden');
  });

  function renderProjects(){
    projectSelect.innerHTML = '';
    let filtered = allProjects.filter(p => String(p.team_id) === String(selectedTeamId));
    if (!selectedTeamId) {
      projectSelect.innerHTML = '<option value="">– Bitte erst ein Team wählen –</option>';
      projectCountEl.textContent = '0';
      saveBtn.disabled = true;
      if (window.microsoftTeams?.pages?.config) window.microsoftTeams.pages.config.setValidityState(false);
      return;
    }
    if (filtered.length === 0) {
      projectSelect.innerHTML = '<option value="">Keine Projekte im Team</option>';
      saveBtn.disabled = true;
      if (window.microsoftTeams?.pages?.config) window.microsoftTeams.pages.config.setValidityState(false);
    } else {
      const placeholder = document.createElement('option');
      placeholder.value=''; placeholder.textContent='– Bitte wählen –';
      projectSelect.appendChild(placeholder);
      filtered.forEach(p => {
        const opt = document.createElement('option'); opt.value = p.id; opt.textContent = p.name; projectSelect.appendChild(opt);
      });
      saveBtn.disabled = true;
      if (window.microsoftTeams?.pages?.config) window.microsoftTeams.pages.config.setValidityState(false);
    }
    projectCountEl.textContent = String(filtered.length);
  }

  teamTiles.forEach(tile => {
    tile.addEventListener('click', function(){
      teamTiles.forEach(t => t.classList.remove('ring-2','ring-[var(--ui-primary)]'));
      this.classList.add('ring-2','ring-[var(--ui-primary)]');
      selectedTeamId = this.getAttribute('data-team-id');
      renderProjects();
    });
  });

  projectSelect?.addEventListener('change', function(){
    const ok = !!projectSelect.value;
    saveBtn.disabled = !ok;
    if (window.microsoftTeams?.pages?.config) window.microsoftTeams.pages.config.setValidityState(ok);
  });

  // Neues Projekt (einfacher Dialog)
  const btnOpen = document.getElementById('newProjectBtn');
  btnOpen?.addEventListener('click', async function(){
    const name = prompt('Projektname?');
    if (!name) return;
    if (!selectedTeamId) { alert('Bitte zuerst ein Team wählen'); return; }
    try {
      const res = await fetch('/planner/embedded/planner/projects', {
        method: 'POST', credentials: 'include', headers: {
          'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }, body: JSON.stringify({ name: name, team_id: selectedTeamId })
      });
      if (!res.ok) { alert('Fehler beim Anlegen'); return; }
      const data = await res.json();
      allProjects.push(data.project);
      renderProjects();
      projectSelect.value = data.project.id;
      saveBtn.disabled = false;
      if (window.microsoftTeams?.pages?.config) window.microsoftTeams.pages.config.setValidityState(true);
    } catch(e) { alert('Request-Fehler: ' + e.message); }
  });

  // Save in Teams
  if (window.microsoftTeams?.pages?.config) {
    window.microsoftTeams.pages.config.setValidityState(false);
    window.microsoftTeams.pages.config.registerOnSaveHandler(async function (saveEvent) {
      try {
        const projectId = projectSelect?.value || '';
        if (!projectId) { saveEvent.notifyFailure('Bitte Projekt wählen'); return; }
        let projectName = 'Projekt ' + projectId;
        const selected = allProjects.find(p => String(p.id) === String(projectId));
        if (selected) projectName = selected.name;
        const contentUrl = 'https://office.martin3r.me/planner/embedded/planner/projects/' + encodeURIComponent(projectId) + '?name=' + encodeURIComponent(projectName);
        await window.microsoftTeams.pages.config.setConfig({
          contentUrl: contentUrl, websiteUrl: contentUrl, entityId: 'planner-project-' + projectId, suggestedDisplayName: 'PLANNER - ' + projectName
        });
        saveEvent.notifySuccess();
      } catch(e) {
        saveEvent.notifyFailure('Speichern fehlgeschlagen');
      }
    });
  }
})();
</script>
@endsection
