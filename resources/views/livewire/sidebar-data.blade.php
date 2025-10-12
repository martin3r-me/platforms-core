<div x-data="sidebarData()" x-init="init()" 
     x-pomodoro-session='@json($pomodoroStats["active_session"])'
     x-show="false"
     wire:poll.30s="loadPomodoroStats">
    <!-- Hidden component to provide data to sidebar -->
</div>

<script>
function sidebarData() {
    return {
        pomodoroSession: null,
        
        init() {
            this.loadFromServer();
            
            // Listen for Livewire updates
            this.$el.addEventListener('livewire:updated', () => {
                this.loadFromServer();
            });
            
            // Listen for timer expiration
            this.$el.addEventListener('timer-expired', () => {
                this.pomodoroSession = null;
            });
        },
        
        loadFromServer() {
            const sessionData = this.$el.getAttribute('x-pomodoro-session');
            
            if (sessionData && sessionData !== 'null') {
                this.pomodoroSession = JSON.parse(sessionData);
            } else {
                this.pomodoroSession = null;
            }
        }
    }
}
</script>
