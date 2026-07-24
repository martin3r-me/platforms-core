<?php

namespace Platform\Core\Terminal\Contracts;

use Platform\Core\Terminal\TerminalContext;

/**
 * A Terminal App is a self-contained panel that plugs into the Terminal shell.
 *
 * The shell owns the frame (open/close, tab rail, presence, context). An app
 * contributes one tab + one panel, rendered as a nested Livewire component so
 * its state and methods live entirely in the app's own class — which is what
 * lets a module ship its own apps without the shell (platform-terminal)
 * depending on that module.
 *
 * Native apps (chat, agenda) implement this from inside the terminal package.
 * Foreign apps (extrafields, comms, time, okr, activity, …) implement it from
 * their owning package and register into the shell — the dependency arrow
 * always points at this contract, never from the shell into the module.
 */
interface TerminalApp
{
    /** Stable machine key, kebab-case (e.g. 'extrafields'). Also the $activeApp value. */
    public function key(): string;

    /** Human label for the tab. */
    public function label(): string;

    /** Icon identifier (heroicon name or registered svg key) for the tab rail. */
    public function icon(): string;

    /**
     * Grouping bucket for the rail ordering:
     *  'native'  — belongs to the terminal itself (chat, agenda)
     *  'context' — a view over the current context entity (files, tags, extrafields)
     *  'module'  — owned by a feature module (comms, time, okr, activity)
     */
    public function group(): string;

    /** Sort weight within the rail (ascending). */
    public function order(): int;

    /** Livewire component alias to render in the panel (e.g. 'core.terminal.apps.extra-fields'). */
    public function livewireComponent(): string;

    /**
     * Whether this app should be offered for the given context.
     * Replaces the old scattered `$availableApps[...] = true` toggles and the
     * `terminal:app:*` unlock events — availability becomes declarative.
     */
    public function isAvailable(TerminalContext $context): bool;
}
