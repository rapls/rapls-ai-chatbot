# Rapls AI Chatbot — MCP Server Manual

> Connect external AI agents (Claude Desktop, Cursor, custom scripts) to your WordPress site through the Model Context Protocol.

Rapls AI Chatbot ships with a built-in **MCP (Model Context Protocol) server**. Once enabled, any MCP-compatible client can read your knowledge base, search your indexed site content, browse stored conversations, and send messages to your chatbot — over a single authenticated HTTP endpoint hosted on your own WordPress install.

This manual documents the exact behavior of the server as implemented in plugin **version 1.9.1**: the transport, the endpoint, authentication, every tool with its input schema **and response shape**, the full JSON-RPC protocol, developer extension points, security, and troubleshooting.

---

## Table of contents

1. [Quick reference](#1-quick-reference)
2. [Overview & architecture](#2-overview--architecture)
3. [Requirements](#3-requirements)
4. [Step 1 — Enable the MCP server](#4-step-1--enable-the-mcp-server)
5. [Step 2 — Generate an API key](#5-step-2--generate-an-api-key)
6. [Step 3 — Find your endpoint URL](#6-step-3--find-your-endpoint-url)
7. [Step 4 — Configure your MCP client](#7-step-4--configure-your-mcp-client)
8. [A complete session walkthrough](#8-a-complete-session-walkthrough)
9. [Tool reference](#9-tool-reference)
10. [Protocol reference (JSON-RPC 2.0)](#10-protocol-reference-json-rpc-20)
11. [How tool results are wrapped](#11-how-tool-results-are-wrapped)
12. [Testing with curl](#12-testing-with-curl)
13. [Developer / extensibility](#13-developer--extensibility)
14. [Security model](#14-security-model)
15. [Troubleshooting](#15-troubleshooting)
16. [FAQ](#16-faq)

---

## 1. Quick reference

| Property | Value |
|---|---|
| Connection type | **Remote HTTP** (no local process, no `npx`/`docker`) |
| Transport | **Streamable HTTP** — a single `POST` endpoint. **No SSE / WebSocket.** |
| Message format | **JSON-RPC 2.0** |
| MCP protocol version | `2024-11-05` |
| HTTP method | `POST` only |
| Endpoint (pretty permalinks) | `https://YOUR-SITE.com/wp-json/rapls-ai-chatbot/v1/mcp` |
| Endpoint (plain permalinks) | `https://YOUR-SITE.com/?rest_route=/rapls-ai-chatbot/v1/mcp` |
| Authentication | `Authorization: Bearer <MCP_API_KEY>` |
| Server name (in `initialize`) | `rapls-ai-chatbot` |
| Default state | **Disabled** (returns `404` until enabled) |
| Tools — Free | 5: `send_message`, `search_knowledge`, `list_conversations`, `get_conversation`, `get_site_info` |
| Tools — with Pro | 7: adds `search_products`, `get_analytics` |
| Methods | `initialize`, `notifications/initialized`, `tools/list`, `tools/call`, `ping` |

---

## 2. Overview & architecture

The MCP server is exposed as **one WordPress REST route** that speaks **JSON-RPC 2.0**. It is a *remote (HTTP) MCP server*: there is nothing to install or run locally, and no stdio command. Your client opens an HTTP connection to a URL on your site and authenticates with a bearer token.

```
┌────────────────────┐        POST (JSON-RPC 2.0)         ┌──────────────────────────────┐
│   MCP client        │  ───────────────────────────────▶ │  WordPress                    │
│  (Claude Desktop,   │   Authorization: Bearer <key>      │  /wp-json/rapls-ai-chatbot/   │
│   Cursor, scripts)  │ ◀───────────────────────────────  │     v1/mcp                    │
└────────────────────┘        JSON-RPC result             │                              │
                                                           │  ├─ check_mcp_auth (Bearer)  │
                                                           │  ├─ JSON-RPC dispatch         │
                                                           │  └─ Tool registry → tools     │
                                                           └──────────────────────────────┘
```

**Why a single POST endpoint and not SSE?** The server implements the MCP "Streamable HTTP" style: every JSON-RPC request — `initialize`, `tools/list`, `tools/call`, `ping` — is a self-contained HTTP `POST`. This is simpler to host on shared WordPress infrastructure and passes cleanly through standard reverse proxies and CDNs. There is no separate event-stream URL to configure.

**Request lifecycle**

1. Client sends `POST` with a JSON-RPC body and an `Authorization: Bearer` header.
2. `check_mcp_auth()` validates the bearer token against a stored hash. On failure it returns HTTP `401` *before* any JSON-RPC handling.
3. The body is parsed and validated as JSON-RPC 2.0.
4. The method is dispatched. For `tools/call`, the named tool is looked up in the registry and executed.
5. A JSON-RPC `result` (or `error`) is returned with HTTP `200`. (Auth errors are the exception — they are `401`.)

---

## 3. Requirements

- Rapls AI Chatbot **1.5.0 or later** active on your WordPress site.
- An administrator account (capability to manage plugin settings) to enable the server and mint a key.
- **Pretty permalinks recommended.** The `/wp-json/…` route only works when **Settings → Permalinks** is *not* set to "Plain". A fallback URL is provided for plain permalinks ([Step 3](#6-step-3--find-your-endpoint-url)).
- An AI provider configured (OpenAI / Claude / Gemini / OpenRouter) — required **only** for the `send_message` tool, which calls your provider and consumes tokens.
- **HTTPS** strongly recommended: the bearer token is sent in a header on every request.

---

## 4. Step 1 — Enable the MCP server

The MCP server is **off by default**. While off, the route is not registered and the endpoint returns `404`.

1. In wp-admin, go to **AI Chatbot → Settings**.
2. Open the **MCP (Model Context Protocol)** section.
3. Tick **Enable MCP server**.
4. **Save changes.**

> ⚠️ Until this box is ticked and saved, the route does not exist. A `404` from the endpoint almost always means the server is disabled (or you are using the wrong permalink form).

---

## 5. Step 2 — Generate an API key

In the same MCP section, click **Generate API key** (or **Regenerate**). The plugin:

- generates a **40-character random key** (`wp_generate_password(40, false)`),
- stores **only a salted hash** of it (`wp_hash_password`) in `raplsaich_settings['mcp_api_key_hash']`,
- displays the **raw key exactly once**.

> 🔴 **Copy the key immediately.** It is shown a single time and is never recoverable — only its hash is stored. If you lose it, regenerate (which invalidates the previous key).

Key facts:

- The key is the **bearer token** your client sends on every request.
- **Regenerating immediately invalidates the old key.** Any client still using it begins receiving `401`.
- The key is **independent of WordPress logins, application passwords, and cookies** — it is a dedicated MCP credential.
- Authentication is checked with `wp_check_password(provided_key, stored_hash)`.

---

## 6. Step 3 — Find your endpoint URL

The MCP section shows your exact endpoint with a **Copy** button.

**Pretty permalinks (recommended):**

```
https://YOUR-SITE.com/wp-json/rapls-ai-chatbot/v1/mcp
```

**Plain permalinks (fallback):** if **Settings → Permalinks** is "Plain", use the query-string form:

```
https://YOUR-SITE.com/?rest_route=/rapls-ai-chatbot/v1/mcp
```

> The endpoint printed in the admin screen is always correct for your current permalink setting — prefer copying it from there.

---

## 7. Step 4 — Configure your MCP client

Any client that supports **remote HTTP MCP servers with header-based auth** can connect. Because there is no command to launch, you do **not** use the `command`/`args` (stdio) fields — you provide a `url` and an `Authorization` header.

### Claude Desktop / Cursor (JSON config)

```json
{
  "mcpServers": {
    "rapls-ai-chatbot": {
      "url": "https://YOUR-SITE.com/wp-json/rapls-ai-chatbot/v1/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_MCP_API_KEY"
      }
    }
  }
}
```

Replace `YOUR-SITE.com` with your domain and `YOUR_MCP_API_KEY` with the 40-character key from Step 2. **Restart the client** after editing its config so it reloads the server list.

### Clients that only support stdio (command-based) servers

Connect through a generic HTTP-MCP bridge/proxy, configured with the same URL and `Authorization: Bearer` header. The Rapls server itself never uses `npx` or `docker`.

---

## 8. A complete session walkthrough

A typical client performs this exact sequence. Every step is a `POST` to the same endpoint with the bearer header.

**(a) initialize** — handshake.

```json
{ "jsonrpc": "2.0", "id": 1, "method": "initialize",
  "params": { "protocolVersion": "2024-11-05", "capabilities": {},
              "clientInfo": { "name": "my-client", "version": "1.0.0" } } }
```

Response:

```json
{ "jsonrpc": "2.0", "id": 1,
  "result": {
    "protocolVersion": "2024-11-05",
    "serverInfo": { "name": "rapls-ai-chatbot", "version": "1.9.1" },
    "capabilities": { "tools": {} }
  } }
```

**(b) notifications/initialized** — client acknowledgement. This is a JSON-RPC *notification* (no `id`). The server replies with HTTP `202` and **no body**.

```json
{ "jsonrpc": "2.0", "method": "notifications/initialized" }
```

**(c) tools/list** — discover tools (see [Tool reference](#9-tool-reference)).

```json
{ "jsonrpc": "2.0", "id": 2, "method": "tools/list" }
```

**(d) tools/call** — invoke a tool.

```json
{ "jsonrpc": "2.0", "id": 3, "method": "tools/call",
  "params": { "name": "search_knowledge",
              "arguments": { "query": "refund policy", "limit": 3 } } }
```

**(e) ping** — optional liveness check; returns an empty result object.

```json
{ "jsonrpc": "2.0", "id": 4, "method": "ping" }
```

---

## 9. Tool reference

The Free edition registers **5 tools**. Installing **Pro** adds 2 more (total 7). Always call `tools/list` to see what a given site actually offers.

> **How responses look:** the values shown under "Returns" below are the tool's raw output. Over the wire, `tools/call` serializes that output to a JSON string and wraps it in `result.content[0].text`. See [How tool results are wrapped](#11-how-tool-results-are-wrapped).

### `send_message` — FREE

Send a message to the AI chatbot and get a response. Uses your configured AI provider and injects knowledge-base context, exactly like the on-site widget.

**Input schema**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `message` | string | **required** | The user message. Max length governed by the `raplsaich_max_message_length` filter (default **8000** characters). |
| `session_id` | string | optional | Continue an existing conversation. A new session is created if omitted. |

**Returns** — object with:

| Field | Type | Description |
|---|---|---|
| `content` | string | The AI's reply. |
| `session_id` | string | Session ID (new or echoed) for continuity. |
| `tokens_used` | integer | Total tokens for this turn. |
| `model` | string | Model that produced the reply. |
| `provider` | string | Provider name (`openai`, `claude`, `gemini`, `openrouter`, …). |
| `sources` | string[] | De-duplicated source URLs used as context (may be empty). |

> ⚠️ **This is the only tool that calls your AI provider and incurs token cost.** It also respects the `raplsaich_mcp_pre_send_check` filter (Pro can block on banned words, message limits, or budget) and applies the `raplsaich_ai_response` filter to the reply. If `save_history` is enabled, the user and assistant messages are persisted.

**Example call**

```json
{ "jsonrpc": "2.0", "id": 10, "method": "tools/call",
  "params": { "name": "send_message",
              "arguments": { "message": "What are your business hours?" } } }
```

**Example tool output (before wrapping)**

```json
{
  "content": "We're open Monday–Friday, 9:00–18:00 JST.",
  "session_id": "8f3c…",
  "tokens_used": 412,
  "model": "gpt-4o-mini",
  "provider": "openai",
  "sources": ["https://your-site.com/contact/"]
}
```

---

### `search_knowledge` — FREE

Search the knowledge base and site content index. Returns matching articles, FAQs, and indexed pages — the same retrieval layer the chatbot uses for RAG. **Does not call the AI provider** (no token cost).

**Input schema**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `query` | string | **required** | Search query text. |
| `limit` | integer | optional | Max results. Default **5**; clamped to **1–50**. |

**Returns**

| Field | Type | Description |
|---|---|---|
| `results` | object[] | Each: `type`, `title`, `content`, `score`, and `url` (when available). |
| `total` | integer | Number of results returned. |

**Example tool output**

```json
{
  "results": [
    { "type": "knowledge", "title": "Refund policy",
      "content": "Refunds are available within 30 days…",
      "score": 0.91, "url": "https://your-site.com/refunds/" }
  ],
  "total": 1
}
```

---

### `list_conversations` — FREE

List chat conversations with message counts, newest first.

**Input schema**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `limit` | integer | optional | Max conversations. Default **20**; clamped to **1–100**. |
| `status` | string | optional | Filter by status, e.g. `active` or `ended`. |

**Returns**

| Field | Type | Description |
|---|---|---|
| `conversations` | object[] | Each: `id`, `session_id`, `status`, `message_count`, `page_url`, `created_at`. |
| `total` | integer | Number of conversations returned. |

> Returns data only when conversation history saving is enabled and conversations exist.

---

### `get_conversation` — FREE

Get a single conversation together with its message history.

**Input schema**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `conversation_id` | integer | **required** | The conversation ID (from `list_conversations`). Up to **200** messages are returned. |

**Returns** — the conversation details plus a `messages` array. Each message includes `role`, `content`, `created_at`, and `tokens_used` (when recorded).

**Example tool output**

```json
{
  "id": 16,
  "messages": [
    { "role": "user", "content": "Hi", "created_at": "2026-06-12 22:13:39" },
    { "role": "assistant", "content": "Hello! How can I help?",
      "created_at": "2026-06-12 22:13:57", "tokens_used": 91 }
  ]
}
```

---

### `get_site_info` — FREE

Get WordPress site information and chatbot configuration. **Takes no parameters. API keys are never exposed.**

**Returns**

| Field | Type | Field | Type |
|---|---|---|---|
| `site_name` | string | `ai_provider` | string |
| `site_url` | string | `ai_model` | string |
| `site_language` | string | `knowledge_count` | integer |
| `wordpress_version` | string | `index_count` | integer |
| `plugin_version` | string | `conversation_count` | integer |
| `is_pro` | boolean | `today_conversations` | integer |
| `save_history` | boolean | `embedding_enabled` | boolean |

---

### `search_products` — PRO

Search WooCommerce products. Registered only when **Rapls AI Chatbot Pro** is active *and* WooCommerce is installed.

### `get_analytics` — PRO

Retrieve chatbot analytics (conversation/usage metrics). Registered only when **Rapls AI Chatbot Pro** is active.

---

## 10. Protocol reference (JSON-RPC 2.0)

Every request is a JSON-RPC 2.0 message in the body of a `POST`.

| Method | Purpose | Response |
|---|---|---|
| `initialize` | Handshake. Returns protocol version, `serverInfo`, capabilities. | result object |
| `notifications/initialized` | Client acknowledgement after initialize. | HTTP `202`, **no body** |
| `tools/list` | List registered tools and their input schemas. | `{ "tools": [ … ] }` |
| `tools/call` | Invoke a tool by `name` with `arguments`. | wrapped result (§11) |
| `ping` | Liveness check. | `{}` (empty object) |

**Success envelope**

```json
{ "jsonrpc": "2.0", "id": <id>, "result": { … } }
```

**Error envelope**

```json
{ "jsonrpc": "2.0", "id": <id>, "error": { "code": <int>, "message": "<text>" } }
```

### Error codes

| Code | Meaning | When |
|---|---|---|
| `-32700` | Parse error | Body is not valid JSON. |
| `-32600` | Invalid Request | Missing/incorrect `jsonrpc` or `method`. |
| `-32601` | Method not found | Unknown JSON-RPC method. |
| `-32602` | Invalid params | Missing tool `name`, or tool not found. |
| `-32603` | Internal error | A tool threw an exception during execution. |

> **Important:** JSON-RPC errors are returned with HTTP **200** (the error is in the body). The only HTTP-level error is **401** for authentication failures, which happens before dispatch. A **404** means the route is not registered (server disabled / wrong permalink form).

---

## 11. How tool results are wrapped

`tools/call` does **not** put the tool's raw object directly in `result`. It conforms to MCP's content model:

**Successful tool output** is JSON-encoded (pretty-printed, UTF-8 unescaped) and placed in a text content block:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [
      { "type": "text", "text": "{\n  \"results\": [ … ],\n  \"total\": 1\n}" }
    ]
  }
}
```

So a client reads `result.content[0].text` and parses that string as JSON to recover the tool's object.

**Tool-level errors** (e.g. a missing required argument detected *inside* the tool, an AI provider failure, "Conversation not found") are **not** returned as JSON-RPC errors. They come back as a normal result with `isError: true`:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [ { "type": "text", "text": "Message is required." } ],
    "isError": true
  }
}
```

This distinction matters: **protocol/registry problems** (bad JSON, unknown method, unknown tool, thrown exception) use JSON-RPC `error` codes; **expected tool failures** use `isError: true` in a normal result.

---

## 12. Testing with curl

Replace the URL and key. (For plain permalinks, swap in the `?rest_route=` URL.)

**List tools**

```bash
curl -s https://YOUR-SITE.com/wp-json/rapls-ai-chatbot/v1/mcp \
  -H "Authorization: Bearer YOUR_MCP_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

**Call a tool (no token cost)**

```bash
curl -s https://YOUR-SITE.com/wp-json/rapls-ai-chatbot/v1/mcp \
  -H "Authorization: Bearer YOUR_MCP_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call",
       "params":{"name":"get_site_info","arguments":{}}}'
```

**Send a message (calls the AI provider — incurs cost)**

```bash
curl -s https://YOUR-SITE.com/wp-json/rapls-ai-chatbot/v1/mcp \
  -H "Authorization: Bearer YOUR_MCP_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call",
       "params":{"name":"send_message",
                 "arguments":{"message":"Hello"}}}'
```

Expected outcomes:

- ✅ Valid key → JSON-RPC `result`.
- 🔑 Missing/incorrect key → HTTP `401`.
- 🚫 `404` → server disabled or wrong permalink form.

---

## 13. Developer / extensibility

The server is designed to be extended without modifying the Free plugin.

### Register custom tools

After the Free plugin builds its registry it fires:

```php
do_action( 'raplsaich_mcp_register_tools', $registry );
```

A plugin (this is exactly how Pro adds `search_products` and `get_analytics`) can hook this and register tools:

```php
add_action( 'raplsaich_mcp_register_tools', function ( $registry ) {
    $registry->register(
        'my_tool',                       // unique name
        [                                // schema returned by tools/list
            'name'        => 'my_tool',
            'description' => 'What my tool does.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'q' => [ 'type' => 'string', 'description' => 'Query.' ],
                ],
                'required'   => [ 'q' ],
            ],
        ],
        function ( array $args ) {        // handler: array -> array|WP_Error
            return [ 'echo' => $args['q'] ?? '' ];
        }
    );
} );
```

Handler contract:

- Receives the validated `arguments` array.
- Return an **array** → wrapped as a success result.
- Return an array containing `error` (and no `content`) → wrapped as `isError: true`.
- Return a `WP_Error` → JSON-RPC error (`-32602` if code is `tool_not_found`, otherwise `-32603`).
- Thrown exceptions are caught and converted to a `WP_Error` (`-32603`).

### Other relevant filters

| Hook | Type | Effect |
|---|---|---|
| `raplsaich_max_message_length` | filter | Max characters accepted by `send_message` (default 8000). |
| `raplsaich_mcp_pre_send_check` | filter | Return an array with `error` to block a `send_message` call (Pro: banned words, limits, budget). |
| `raplsaich_ai_response` | filter | Post-process the AI reply text returned by `send_message`. |

---

## 14. Security model

- **Single bearer credential, all-or-nothing.** A valid key can call **every** registered tool; there is no per-tool scoping.
- **Hash-at-rest.** Only a salted hash (`wp_hash_password`) is stored; the raw key is shown once and never persisted.
- **No secrets leaked.** `get_site_info` deliberately omits AI provider API keys and other credentials.
- **Use HTTPS.** The token rides in a header on every request — TLS is essential in production.
- **Rotate on exposure.** If a key may have leaked, regenerate it; the old key dies instantly.
- **Cost awareness.** `send_message` spends AI provider tokens. Treat the key as a billable credential and share only with trusted clients. The `raplsaich_mcp_pre_send_check` filter (Pro) can enforce budgets/limits.
- **Disable when unused.** Turning off "Enable MCP server" removes the route entirely (returns `404`).

---

## 15. Troubleshooting

| Symptom | Likely cause & fix |
|---|---|
| `404` / route not found | MCP server not enabled, or plain permalinks. Enable it in Settings, or use the `?rest_route=` URL form. |
| `401` "MCP API key is required." | No `Authorization` header. Add `Authorization: Bearer <key>`. |
| `401` "Invalid authorization format. Use: Bearer <api_key>" | Header is not `Bearer <key>` form. Include the literal `Bearer` and a space. |
| `401` "Invalid MCP API key." | Wrong or regenerated key. Generate a fresh key and update your client. |
| `-32700` Parse error | Request body is not valid JSON. Check quoting/escaping. |
| `-32600` Invalid Request | Missing `jsonrpc: "2.0"` or `method`. |
| `-32601` Method not found | Unknown method name. |
| `-32602` Invalid params | Missing tool `name`, or tool not registered. |
| `-32603` Internal error | A tool threw an exception. Check the WordPress error log. |
| `isError: true` "… is required." | A required tool argument was missing (e.g. `message`, `query`, `conversation_id`). |
| Empty conversation lists | History saving disabled, or no conversations yet. |
| `send_message` returns `isError` | AI provider not configured/invalid, message too long, or a pre-send check blocked it. |

---

## 16. FAQ

**Is this a local (stdio) or remote (HTTP) MCP server?**
Remote HTTP. You connect to a URL with a bearer token. Nothing to install or run locally.

**Does it use SSE?**
No. It is a single `POST` JSON-RPC endpoint (Streamable HTTP). There is no separate event-stream URL.

**Which MCP protocol version does it implement?**
`2024-11-05`, reported in the `initialize` response.

**How many tools are there?**
5 in Free; 7 with Pro (adds `search_products` and `get_analytics`). Always confirm with `tools/list`.

**Can I limit a client to read-only tools?**
Not currently — one key grants access to all registered tools. To avoid AI spend, simply don't have the client call `send_message`.

**What happens to the old key when I regenerate?**
It is invalidated immediately. Update every client that used it.

**Why did my tool call return HTTP 200 with `isError: true` instead of an error code?**
That's by design. Expected tool failures are reported as `isError: true` in a normal result; only protocol/registry problems use JSON-RPC error codes. See [§11](#11-how-tool-results-are-wrapped).

**Does `get_conversation` work if history saving is off?**
Conversations only exist when history saving is enabled. With it off, `list_conversations` returns an empty list and there is nothing for `get_conversation` to fetch.

---

*Rapls AI Chatbot — MCP Server Manual. Endpoint, tool names, return shapes, and protocol version reflect plugin version 1.9.1. Tool availability depends on your edition (Free/Pro) and configuration; query `tools/list` for the authoritative list on your site.*
