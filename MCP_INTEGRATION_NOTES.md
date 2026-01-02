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

✅ **Was wir bereits nutzen:**
- Standard Function-Calling Format (funktioniert)
- Tool-Name-Normalisierung (Punkte → Unterstriche)
- Tool-Result-Formatierung als User-Messages
- Streaming mit `response.function_call_arguments.delta` und `.done`
- **MCP-Events unterstützt** (seit 2026-01-02):
  - `response.mcp_call_arguments.delta` - Partielle Tool-Argumente
  - `response.mcp_call_arguments.done` - Finalisierte Tool-Argumente
  - `response.mcp_call.completed` - Tool-Aufruf erfolgreich
  - `response.mcp_call.failed` - Tool-Aufruf fehlgeschlagen
  - `response.mcp_list_tools` - Tool-Liste (Debugging)
  - `response.output_item.added` mit `type: 'mcp_call'` - MCP-Tool-Erstellung

❓ **Was wir prüfen sollten:**
- Sollten wir das MCP-Format für Tools verwenden (statt Standard Function-Calling)?
- Gibt es Vorteile durch `mcp_list_tools`?
- Können wir MCP-Events für bessere Tool-Discovery nutzen?

## MCP vs. Standard Function-Calling

### Standard Function-Calling (aktuell)
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

### MCP-Format (mögliche Alternative)
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
    }
  }
}
```

## Empfehlung

**Aktuell: Standard Function-Calling beibehalten**
- Funktioniert zuverlässig
- Gut dokumentiert
- Keine Breaking Changes nötig

**Zukünftig: MCP-Format evaluieren**
- Wenn OpenAI MCP als Standard empfiehlt
- Wenn es bessere Tool-Gruppierung ermöglicht
- Wenn es Discovery verbessert

## Referenzen

- [OpenAI Responses API - MCP Events](https://platform.openai.com/docs/api-reference/responses-streaming/response/mcp_call_arguments)
- [OpenAI Responses API - MCP List Tools](https://platform.openai.com/docs/api-reference/responses-streaming/response/mcp_list_tools)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/)

