<div class="flex-1 min-h-0 flex flex-col items-center justify-center gap-2 p-6 text-center">
    <div class="text-[13px] font-bold text-[var(--t-text)]">🧪 Demo-App via TerminalAppRegistry</div>
    <div class="text-[11px] text-[var(--t-text-muted)]">
        Diese App wurde nicht in der Shell hartcodiert, sondern über die Registry beigesteuert.
    </div>
    <div class="mt-2 text-[10px] font-mono text-[var(--t-text-muted)] space-y-0.5">
        <div>context_type: {{ $contextType ?? '—' }}</div>
        <div>context_id: {{ $contextId ?? '—' }}</div>
        <div>subject: {{ $contextSubject ?? '—' }}</div>
    </div>
</div>
