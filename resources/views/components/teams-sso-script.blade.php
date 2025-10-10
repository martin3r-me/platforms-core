<script>
// Teams SSO Integration
(function() {
    'use strict';
    
    // Prüfen ob wir in Teams sind
    if (typeof microsoftTeams === 'undefined') {
        console.log('Teams SSO: Not running in Teams context');
        return;
    }
    
    console.log('Teams SSO: Initializing...');
    
    // Teams SDK initialisieren
    microsoftTeams.app.initialize().then(() => {
        console.log('Teams SSO: SDK initialized');
        
        // SSO Token anfordern
        microsoftTeams.authentication.getAuthToken({
            resources: ['https://graph.microsoft.com/User.Read'],
            silent: true
        }).then((accessToken) => {
            console.log('Teams SSO: Token received');
            
            // Token an Backend senden
            fetch('/teams/sso/authenticate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    access_token: accessToken,
                    redirect_url: window.location.href
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Teams SSO: Authentication successful', data.user);
                    // Seite neu laden um authentifizierten Zustand zu aktivieren
                    window.location.reload();
                } else {
                    console.error('Teams SSO: Authentication failed', data.error);
                    // Fallback zu normaler Azure SSO
                    window.location.href = data.auth_url;
                }
            })
            .catch(error => {
                console.error('Teams SSO: Request failed', error);
                // Fallback zu normaler Azure SSO
                window.location.href = '/sso/login';
            });
            
        }).catch((error) => {
            console.error('Teams SSO: Token request failed', error);
            // Fallback zu normaler Azure SSO
            window.location.href = '/sso/login';
        });
        
    }).catch((error) => {
        console.error('Teams SSO: SDK initialization failed', error);
        // Fallback zu normaler Azure SSO
        window.location.href = '/sso/login';
    });
    
})();
</script>
