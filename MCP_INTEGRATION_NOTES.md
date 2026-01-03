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
- **MCP-Format aktiv** - Tools werden als `mcp_servers` gruppiert nach Modulen gesendet
- Tool-Gruppierung nach Modulen (z.B. `planner`, `core`)
- MCP-Tool-Format: `name`, `description`, `inputSchema` (statt `function`, `parameters`)
- Tool-Name ohne Modul-Präfix im MCP-Format (z.B. `projects.GET` statt `planner.projects.GET`)
- Modul kommt aus Server-Name (z.B. `planner` → `planner.projects.GET`)
- **MCP-Events vollständig unterstützt:**
  - `response.mcp_call_arguments.delta` - Partielle Tool-Argumente
  - `response.mcp_call_arguments.done` - Finalisierte Tool-Argumente
  - `response.mcp_call.completed` - Tool-Aufruf erfolgreich
  - `response.mcp_call.failed` - Tool-Aufruf fehlgeschlagen
  - `response.mcp_list_tools.*` - Tool-Liste Events (in_progress, completed, failed)
  - `response.output_item.added` mit `type: 'mcp_call'` - MCP-Tool-Erstellung

✅ **Vorteile:**
- Tools können während des Streams nachgeladen werden (via `mcp_list_tools` Events)
- Bessere Gruppierung nach Modulen
- Standardisiertes Format
- Nahtlose Tool-Discovery während des Streams

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

**MCP-Format ist aktiv seit 2026-01-03:**
- ✅ Tools werden nach Modulen gruppiert (`buildMcpServers()`)
- ✅ Tool-Namen ohne Modul-Präfix (z.B. `projects.GET` statt `planner.projects.GET`)
- ✅ Modul kommt aus Server-Name
- ✅ Tool-Denormalisierung: `server.tool` → `planner.projects.GET`
- ✅ `mcp_list_tools` Events für Tool-Nachladen während des Streams

## Referenzen

- [OpenAI Responses API - MCP Events](https://platform.openai.com/docs/api-reference/responses-streaming/response/mcp_call_arguments)
- [OpenAI Responses API - MCP List Tools](https://platform.openai.com/docs/api-reference/responses-streaming/response/mcp_list_tools)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/)

