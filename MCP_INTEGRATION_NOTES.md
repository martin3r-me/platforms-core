# MCP (Model Context Protocol) Integration Notes

## Was ist MCP?

MCP (Model Context Protocol) ist ein Standard für die Kommunikation zwischen LLMs und externen Tools/Services. OpenAI unterstützt MCP-Events im Responses API.

## Aktuelle Implementierung

Wir nutzen aktuell das **Standard Function-Calling Format**:
```php
[
    'type' => 'function',
    'name' => 'planner_projects_GET',
    'description' => '...',
    'parameters' => [...]
]
```

## MCP-Events (Streaming)

OpenAI Responses API unterstützt MCP-Events für Streaming:

1. **`response.mcp_call_arguments.delta`** - Partielle Tool-Argumente während des Streamings
2. **`response.mcp_call_arguments.done`** - Finalisierte Tool-Argumente
3. **`response.mcp_call.completed`** - Tool-Aufruf erfolgreich abgeschlossen
4. **`response.mcp_call.failed`** - Tool-Aufruf fehlgeschlagen
5. **`response.mcp_list_tools`** - Tool-Liste (wenn MCP-Format verwendet wird)

## Aktueller Status

✅ **Was wir nutzen (seit 2026-01-03):**
- **Standard `tools` Array** - OpenAI Responses API akzeptiert `mcp_servers` nicht als Payload-Parameter
- **MCP-Events werden während des Streams unterstützt** - Events kommen von OpenAI, nicht im Payload
- **Auto-Injection Best Practice:**
  - Wenn Tool fehlt → Versuche Modul-Tools nachzuladen (via `tools.GET`)
  - Tools werden in `dynamicallyLoadedTools` gespeichert
  - Tool ist für nächste Iteration verfügbar
- **MCP-Events vollständig unterstützt:**
  - `response.mcp_call_arguments.delta` - Partielle Tool-Argumente
  - `response.mcp_call_arguments.done` - Finalisierte Tool-Argumente
  - `response.mcp_call.completed` - Tool-Aufruf erfolgreich
  - `response.mcp_call.failed` - Tool-Aufruf fehlgeschlagen
  - `response.mcp_list_tools.*` - Tool-Liste Events (in_progress, completed, failed)
  - `response.output_item.added` mit `type: 'mcp_call'` - MCP-Tool-Erstellung

✅ **Best Practice:**
- Tools werden nicht während des Streams nachgeladen (technisch nicht möglich)
- Auto-Injection: Wenn Tool fehlt → nachladen → Fehler zurückgeben → Tool für nächste Iteration verfügbar
- Discovery-Layer: Starte mit Discovery-Tools, LLM kann `tools.GET` aufrufen

## MCP vs. Standard Function-Calling

### Standard Function-Calling (veraltet)
```json
{
  "tools": [
    {
      "type": "function",
      "name": "planner_projects_GET",
      "description": "...",
      "parameters": {...}
    }
  ]
}
```

### MCP-Format (aktuell aktiv)
```json
{
  "mcp_servers": {
    "planner": {
      "tools": [
        {
          "name": "projects.GET",
          "description": "...",
          "inputSchema": {...}
        }
      ]
    },
    "core": {
      "tools": [
        {
          "name": "teams.GET",
          "description": "...",
          "inputSchema": {...}
        }
      ]
    }
  }
}
```

## Implementierung

**Standard `tools` Array (seit 2026-01-03):**
- ✅ Tools werden als Standard `tools` Array gesendet (OpenAI akzeptiert `mcp_servers` nicht)
- ✅ MCP-Events werden während des Streams verarbeitet (kommen von OpenAI)
- ✅ Auto-Injection: Wenn Tool fehlt → Modul-Tools nachladen → für nächste Iteration verfügbar
- ✅ Tool-Denormalisierung: Standard Function-Calling Format
- ✅ `mcp_list_tools` Events werden verarbeitet (aber Tools können nicht während Stream nachgeladen werden)

## Referenzen

- [OpenAI Responses API - MCP Events](https://platform.openai.com/docs/api-reference/responses-streaming/response/mcp_call_arguments)
- [OpenAI Responses API - MCP List Tools](https://platform.openai.com/docs/api-reference/responses-streaming/response/mcp_list_tools)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/)

