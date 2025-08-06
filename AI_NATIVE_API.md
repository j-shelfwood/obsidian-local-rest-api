# AI-Native Obsidian REST API

This API has been redesigned with **AI agents in mind**, providing powerful, intelligent endpoints that reduce cognitive load and eliminate complex multi-step workflows.

## 🤖 AI-Native Features

### Core Philosophy
- **Single Action, Multiple Outcomes**: One endpoint handles create/update logic 
- **Context-Aware**: API handles file system complexity and ambiguity
- **Efficient**: Paginated responses prevent context overflow
- **Human-Like**: Endpoints map to natural language intentions

## 🚀 AI-Native Endpoints

### Vault Operations (`/api/vault/`)

#### List Directory (Paginated)
```http
GET /api/vault/directory?path=.&recursive=false&limit=50&offset=0
```
Safely explore vault structure without overwhelming context.

**Response:**
```json
{
  "items": [
    {"path": "note.md", "type": "file", "size": 1024, "modified": 1645123456}
  ],
  "total_items": 150,
  "has_more": true,
  "offset": 0,
  "limit": 50
}
```

#### Smart Search
```http
GET /api/vault/search?query=project&scope[]=content&scope[]=filename&scope[]=tags
```
Multi-scope search with relevance scoring.

**Response:**
```json
{
  "results": [
    {
      "note": {"path": "project-notes.md", "content": "...", "front_matter": {...}},
      "matches": ["filename", "content"],
      "relevance": 2
    }
  ],
  "query": "project",
  "total_results": 5
}
```

#### Recent Notes
```http
GET /api/vault/notes/recent?limit=5
```
Get most recently modified notes.

#### Daily Note Detection
```http
GET /api/vault/notes/daily?date=today
```
Intelligently finds daily notes regardless of naming convention or location.
- Supports: `today`, `yesterday`, `tomorrow`, or `YYYY-MM-DD`
- Searches common patterns: `YYYY-MM-DD.md`, `Daily Notes/YYYY-MM-DD.md`, etc.

#### Related Notes Discovery
```http
GET /api/vault/notes/related/my-note.md?on[]=tags&on[]=links&limit=10
```
Find notes related by tags and wikilinks with similarity scoring.

### Enhanced File Operations

#### Intelligent File Writing
```http
POST /api/files/write
Content-Type: application/json

{
  "path": "log.md",
  "content": "New entry\n",
  "mode": "append"  // overwrite|append|prepend
}
```
One endpoint for all file writing scenarios.

### Enhanced Note Operations

#### Upsert (Create or Update)
```http
POST /api/notes/upsert
Content-Type: application/json

{
  "path": "my-note",
  "content": "Note content",
  "front_matter": {
    "title": "My Note",
    "tags": ["important"]
  }
}
```
**The most important AI-native feature**: Eliminates existence checks and branching logic.

## 📊 Before vs After: AI Workflows

| Task | Old Workflow | New Workflow |
|------|-------------|-------------|
| **Create/Update Note** | 1. Check if exists<br>2. Branch logic<br>3. POST or PUT | 1. `POST /notes/upsert` |
| **Find Recent Work** | 1. List all files<br>2. Parse timestamps<br>3. Sort in memory | 1. `GET /vault/notes/recent` |
| **Append to Log** | 1. Read current content<br>2. Concatenate<br>3. Write back | 1. `POST /files/write` with `mode:append` |
| **Search Content** | 1. Get all notes<br>2. Parse each file<br>3. Filter in memory | 1. `GET /vault/search?query=...` |

## 🔄 Backward Compatibility

All legacy CRUD endpoints remain available:
- `GET|POST|PUT|DELETE /api/files/{path}`
- `GET|POST|PUT|PATCH|DELETE /api/notes/{path}`
- `POST /api/bulk/notes/delete`
- `PATCH /api/bulk/notes/update`

## 🧪 Testing

The API includes comprehensive tests for all AI-native functionality:

```bash
# Run all tests
php artisan test

# Run only AI-native endpoint tests  
php artisan test --filter=VaultController
php artisan test --filter=EnhancedEndpoints
```

## 🎯 Usage Examples

### For n8n Workflows
```javascript
// Old way - Multiple steps with error handling
const exists = await checkNoteExists(path);
if (exists) {
  await updateNote(path, content);
} else {
  await createNote(path, content);
}

// New way - Single step
await upsertNote(path, content);
```

### For AI Agent Scripts
```python
# Old way - Complex search
files = api.get('/files')
results = []
for file in files:
  content = api.get(f'/files/{file.path}')
  if 'search_term' in content:
    results.append(file)

# New way - Direct search
results = api.get('/vault/search', params={'query': 'search_term'})
```

## 🔧 Configuration

Set your vault path in `.env`:
```
OBSIDIAN_VAULT_PATH=/path/to/your/vault
```

## 📝 API Documentation

For complete API documentation including request/response schemas, see the OpenAPI specification at `/docs` when running the server.