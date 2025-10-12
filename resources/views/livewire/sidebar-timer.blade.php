<div x-data="sidebarTimer()" x-init="init()" 
     x-pomodoro-session='@json($pomodoroStats["active_session"])'
     x-show="isActive"
     class="px-3 text-xs text-[var(--ui-primary)] font-medium"
     @if($pomodoroStats['active_session'])
         wire:poll.30s="loadPomodoroStats"
     @endif
     @pomodoro-started.window="loadFromServer()"
     @pomodoro-stopped.window="loadFromServer()">
    <span x-text="formatTime(timeLeft)"></span>
</div>

<script>
function sidebarTimer() {
    return {
        timeLeft: 0,
        isActive: false,
        
        init() {
            this.loadFromServer();
            
            // Listen for Livewire updates
            this.$el.addEventListener('livewire:updated', () => {
                this.loadFromServer();
            });
            
            // Listen for timer expiration
            this.$el.addEventListener('timer-expired', () => {
                this.isActive = false;
            });
        },
        
        loadFromServer() {
            const sessionData = this.$el.getAttribute('x-pomodoro-session');
            
            if (sessionData && sessionData !== 'null') {
                const session = JSON.parse(sessionData);
                this.timeLeft = (session.remaining_minutes || 0) * 60;
                this.isActive = session.is_active && this.timeLeft > 0;
            } else {
                this.isActive = false;
            }
        },
        
        formatTime(seconds) {
            const minutes = Math.ceil(seconds / 60);
            return `${minutes}m`;
        }
    }
}
</script>
