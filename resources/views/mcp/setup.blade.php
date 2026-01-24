<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP Server Setup - {{ $serverName ?? 'Platform' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .config-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            position: relative;
        }
        
        .config-box pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }
        
        .config-box code {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: #f1f3f5;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .alert {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        .step {
            background: #fff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .copy-feedback {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }
        
        .copy-feedback.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 {{ $serverName ?? 'Platform' }} MCP Server Setup</h1>
            <p>Konfiguration für ChatGPT Desktop & Web Clients</p>
        </div>
        
        <div class="content">
            <div class="section">
                <h2>📋 Schritt 1: Token erstellen</h2>
                <div class="step">
                    <span class="step-number">1</span>
                    Führe diesen Befehl im Terminal aus:
                </div>
                <div class="config-box">
                    <code>php artisan api:token:create --email=your@email.com --name="MCP Token" --show</code>
                    <button class="btn" onclick="copyText(this.previousElementSibling.textContent)">📋 Kopieren</button>
                </div>
                <div class="alert alert-info">
                    <strong>⚠️ Wichtig:</strong> Speichere den Token sofort – er wird nur einmal angezeigt!
                </div>
            </div>
            
            <div class="section">
                <h2>💻 Schritt 2: ChatGPT Desktop Konfiguration</h2>
                <div class="step">
                    <span class="step-number">2</span>
                    Öffne ChatGPT Desktop → Settings → Features → Model Context Protocol
                </div>
                <div class="config-box">
                    <pre id="chatgpt-config">{
  "mcpServers": {
    "{{ $serverNameKey ?? 'platform' }}": {
      "command": "php",
      "args": [
        "{{ $artisanPath }}",
        "mcp:start",
        "{{ $serverNameKey ?? 'platform' }}"
      ]
    }
  }
}</pre>
                    <button class="btn btn-success" onclick="copyConfig('chatgpt-config')">📋 Konfiguration kopieren</button>
                </div>
            </div>
            
            <div class="section">
                <h2>🤖 Schritt 2b: Claude Desktop Konfiguration</h2>
                <div class="step">
                    <span class="step-number">2b</span>
                    Öffne Claude Desktop → Settings → Developer → Model Context Protocol
                </div>
                <div class="config-box">
                    <p><strong>Claude Desktop MCP Server Konfiguration (OAuth):</strong></p>
                    <p style="margin-bottom: 10px; color: #666; font-size: 14px;">Claude Desktop bevorzugt OAuth. Füge diese Konfiguration ein:</p>
                    <pre id="claude-oauth-config">{
  "mcpServers": {
    "{{ $serverNameKey ?? 'platform' }}": {
      "url": "{{ $serverUrl }}",
      "oauth": {
        "authorizationServer": "{{ $baseUrl }}/.well-known/oauth-authorization-server",
        "protectedResource": "{{ $baseUrl }}/.well-known/oauth-protected-resource"
      }
    }
  }
}</pre>
                    <button class="btn btn-success" onclick="copyConfig('claude-oauth-config')">📋 OAuth Konfiguration kopieren</button>
                </div>
                <div class="alert alert-info">
                    <strong>💡 OAuth-Anleitung:</strong>
                    <ol style="margin-top: 10px; padding-left: 20px;">
                        <li>Öffne Claude Desktop → Settings → Developer → Model Context Protocol</li>
                        <li>Klicke auf "Benutzerdefinierten Connector hinzufügen"</li>
                        <li>Füge die obige OAuth-Konfiguration ein</li>
                        <li>Claude Desktop wird automatisch den OAuth-Flow starten</li>
                        <li>Du wirst zur Autorisierung weitergeleitet und musst dich anmelden</li>
                        <li>Nach erfolgreicher Autorisierung ist der Connector verbunden</li>
                    </ol>
                </div>
                <div class="config-box" style="margin-top: 20px;">
                    <p><strong>Alternative: Bearer Token (falls OAuth nicht funktioniert):</strong></p>
                    <pre id="claude-bearer-config">{
  "mcpServers": {
    "{{ $serverNameKey ?? 'platform' }}": {
      "url": "{{ $serverUrl }}",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN_HERE"
      }
    }
  }
}</pre>
                    <button class="btn btn-success" onclick="copyConfig('claude-bearer-config')">📋 Bearer Token Konfiguration kopieren</button>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                        <strong>Hinweis:</strong> Ersetze <code>YOUR_TOKEN_HERE</code> mit deinem Sanctum Token (aus Schritt 1)
                    </p>
                </div>
            </div>
            
            <div class="section">
                <h2>🌐 Schritt 3: Web Client Konfiguration</h2>
                <div class="step">
                    <span class="step-number">3</span>
                    Für HTTP-basierte Clients (Custom GPTs, etc.)
                </div>
                <div class="config-box">
                    <pre id="web-config">{
  "url": "{{ $serverUrl }}",
  "headers": {
    "Authorization": "Bearer YOUR_TOKEN_HERE",
    "Content-Type": "application/json"
  }
}</pre>
                    <button class="btn btn-success" onclick="copyConfig('web-config')">📋 Konfiguration kopieren</button>
                </div>
                <div class="alert alert-info">
                    <strong>💡 Tipp:</strong> Ersetze <code>YOUR_TOKEN_HERE</code> mit dem Token aus Schritt 1.
                </div>
            </div>
            
            <div class="section">
                <h2>📝 Schritt 4: Cursor IDE Konfiguration</h2>
                <div class="step">
                    <span class="step-number">4</span>
                    Für Cursor IDE MCP Server Integration (HTTP-basiert mit SSE)
                </div>
                <div class="config-box">
                    <p><strong>Cursor MCP Server Konfiguration (HTTP mit SSE):</strong></p>
                    <p style="margin-bottom: 10px; color: #666; font-size: 14px;">Füge diese Konfiguration in deine Cursor-Einstellungen ein (Settings → Features → Model Context Protocol):</p>
                    <pre id="cursor-config">{
  "mcpServers": {
    "{{ $serverNameKey ?? 'platform' }}": {
      "url": "{{ $serverUrl }}",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN_HERE",
        "Accept": "text/event-stream",
        "Content-Type": "application/json"
      }
    }
  }
}</pre>
                    <button class="btn btn-success" onclick="copyConfig('cursor-config')">📋 Cursor Konfiguration kopieren</button>
                    <p style="margin-top: 15px; color: #666; font-size: 14px;">
                        <strong>Oder direkt als JSON laden:</strong> 
                        <a href="{{ $baseUrl }}/mcp/cursor-config.json" target="_blank" style="color: #667eea; text-decoration: underline;">{{ $baseUrl }}/mcp/cursor-config.json</a>
                    </p>
                </div>
                <div class="alert alert-info">
                    <strong>💡 Anleitung:</strong>
                    <ol style="margin-top: 10px; padding-left: 20px;">
                        <li>Öffne Cursor → Settings → Features → Model Context Protocol</li>
                        <li>Füge die obige Konfiguration ein</li>
                        <li><strong>Wichtig:</strong> Ersetze <code>YOUR_TOKEN_HERE</code> mit deinem Sanctum Token (aus Schritt 1)</li>
                        <li>Speichere die Einstellungen</li>
                        <li><strong>Hinweis:</strong> Cursor verwendet HTTP mit Server-Sent Events (SSE) für die Kommunikation. Der Server konvertiert automatisch JSON-Responses in SSE-Format.</li>
                        <li><strong>Technisch:</strong> Der Server unterstützt GET-Requests (Cursor sendet GET) und konvertiert sie automatisch in POST-Requests mit MCP Initialize.</li>
                    </ol>
                </div>
            </div>
            
            <div class="section">
                <h2>🤖 Schritt 5: Custom GPT (Actions) - ChatGPT GPT Builder</h2>
                <div class="step">
                    <span class="step-number">5</span>
                    Für Custom GPTs im GPT Builder (chat.openai.com → Create GPT → Actions)
                </div>
                <div class="config-box">
                    <p><strong>OpenAPI Schema URL:</strong></p>
                    <code id="openapi-url">{{ $baseUrl }}/mcp/openapi.json</code>
                    <button class="btn btn-success" onclick="copyText('{{ $baseUrl }}/mcp/openapi.json')">📋 OpenAPI URL kopieren</button>
                </div>
                <div class="alert alert-info">
                    <strong>💡 Anleitung:</strong>
                    <ol style="margin-top: 10px; padding-left: 20px;">
                        <li>Gehe zu <code>chat.openai.com</code> → Create GPT</li>
                        <li>Klicke auf "Configure" → "Actions"</li>
                        <li>Klicke auf "Import from URL"</li>
                        <li>Füge die OpenAPI URL ein: <code>{{ $baseUrl }}/mcp/openapi.json</code></li>
                        <li>Authentication: Bearer Token</li>
                        <li>Token: Dein Sanctum Token (aus Schritt 1)</li>
                    </ol>
                </div>
            </div>
            
            <div class="section">
                <h2>🔗 Server URLs</h2>
                <div class="config-box">
                    <p><strong>Web Server URL:</strong> <code>{{ $serverUrl }}</code></p>
                    <p><strong>Info/Discovery URL:</strong> <code>{{ $baseUrl }}/mcp/info</code></p>
                    <p><strong>OpenAPI Schema URL:</strong> <code>{{ $baseUrl }}/mcp/openapi.json</code></p>
                    <button class="btn" onclick="copyText('{{ $serverUrl }}')">📋 Server URL kopieren</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="copy-feedback" id="copyFeedback">
        ✅ Kopiert!
    </div>
    
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                showFeedback();
            }).catch(err => {
                console.error('Fehler beim Kopieren:', err);
                alert('Fehler beim Kopieren. Bitte manuell kopieren.');
            });
        }
        
        function copyConfig(id) {
            const text = document.getElementById(id).textContent;
            copyText(text);
        }
        
        function showFeedback() {
            const feedback = document.getElementById('copyFeedback');
            feedback.classList.add('show');
            setTimeout(() => {
                feedback.classList.remove('show');
            }, 2000);
        }
    </script>
</body>
</html>
