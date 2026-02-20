# Plan: AI Chat Widget pentru Marketplace-uri (AmBilet & TICS)

## 1. Rezumat Executiv

Implementarea unui chat widget AI pe marketplace-urile AmBilet și TICS care:
- Răspunde la întrebări despre evenimente, bilete, comenzi, rambursări
- Oferă suport automat 24/7 bazat pe Knowledge Base-ul existent
- Identifică utilizatorii autentificați și accesează datele lor (comenzi, bilete)
- Escaladează la suport uman când nu poate rezolva
- Se integrează nativ în frontend-ul existent fără dependențe externe

---

## 2. Recomandare AI Provider

### Opțiunea Recomandată: **Claude API (Anthropic)**

| Criteriu | Claude API | OpenAI GPT | Gemini |
|----------|-----------|------------|--------|
| Calitate răspunsuri RO | Excelentă | Bună | Bună |
| Cost per 1M tokens (input) | $3 (Haiku) | $2.50 (GPT-4o-mini) | $0.15 (Flash) |
| Cost per 1M tokens (output) | $15 (Haiku) | $10 (GPT-4o-mini) | $0.60 (Flash) |
| Latență medie | ~1-2s | ~1-2s | ~0.5-1s |
| Context window | 200K | 128K | 1M |
| Tool use / Function calling | Da | Da | Da |
| Suport limba română | Foarte bun | Foarte bun | Bun |

**De ce Claude API:**
- Context window de 200K permite încărcarea întregului KB + istoric conversație
- Excelent la instrucțiuni complexe (system prompt cu reguli de business)
- Tool use nativ pentru a interoga baza de date (comenzi, bilete, etc.)
- Ton natural în română

**Alternativă buget redus:** Google Gemini Flash — cost de 10x mai mic, dar calitate inferioară la instrucțiuni complexe.

### Estimare Costuri Lunare (Claude Haiku)

| Scenariu | Conversații/lună | Cost estimat |
|----------|-----------------|--------------|
| Low traffic | 500 | ~$15-25 |
| Medium traffic | 2,000 | ~$60-100 |
| High traffic | 10,000 | ~$300-500 |

*Bazat pe ~4 mesaje/conversație, ~1500 tokens input + 500 tokens output per mesaj.*

---

## 3. Arhitectură Tehnică

### 3.1 Diagrama de Flux

```
┌─────────────────────────────────────────────────────┐
│  FRONTEND (Browser)                                 │
│  ┌───────────────────────────────────────────────┐  │
│  │  Chat Widget (JS Module)                      │  │
│  │  - Bubble button (fixed bottom-right, z-50)   │  │
│  │  - Chat window (messages, input)              │  │
│  │  - Typing indicator, suggestions              │  │
│  │  - Integrat cu AmbiletAuth + AmbiletAPI       │  │
│  └──────────────────┬────────────────────────────┘  │
│                     │ HTTP (via proxy.php)           │
└─────────────────────┼───────────────────────────────┘
                      │
┌─────────────────────┼───────────────────────────────┐
│  BACKEND (Laravel)  │                               │
│  ┌──────────────────▼────────────────────────────┐  │
│  │  ChatController                               │  │
│  │  POST /api/v1/chat/messages                   │  │
│  │  GET  /api/v1/chat/conversations              │  │
│  │  GET  /api/v1/chat/conversations/{id}         │  │
│  └──────────────────┬────────────────────────────┘  │
│                     │                               │
│  ┌──────────────────▼────────────────────────────┐  │
│  │  ChatService                                  │  │
│  │  - Construiește system prompt                 │  │
│  │  - Adaugă context KB (RAG)                    │  │
│  │  - Adaugă context utilizator                  │  │
│  │  - Apelează Claude API                        │  │
│  │  - Procesează tool calls                      │  │
│  │  - Salvează conversația                       │  │
│  └──────────┬───────────────┬────────────────────┘  │
│             │               │                       │
│  ┌──────────▼──────┐  ┌────▼─────────────────────┐  │
│  │  KnowledgeBase  │  │  Claude API              │  │
│  │  (RAG Search)   │  │  (Anthropic SDK)         │  │
│  │  - KB Articles  │  │  - System prompt         │  │
│  │  - FAQ          │  │  - Tool use              │  │
│  │  - Help pages   │  │  - Streaming response    │  │
│  └─────────────────┘  └──────────────────────────┘  │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │  Database (MySQL)                            │   │
│  │  - chat_conversations                        │   │
│  │  - chat_messages                             │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

### 3.2 Componente Principale

#### A. Backend (Laravel)

**Modele noi:**
```
ChatConversation
├── id (uuid)
├── marketplace_id (FK → marketplaces)
├── marketplace_customer_id (FK, nullable - guest allowed)
├── session_id (string - for anonymous users)
├── status: open | resolved | escalated
├── escalated_at (timestamp, nullable)
├── metadata (json - page URL, user agent, etc.)
├── created_at / updated_at

ChatMessage
├── id (uuid)
├── chat_conversation_id (FK)
├── role: user | assistant | system
├── content (text)
├── tool_calls (json, nullable)
├── tool_results (json, nullable)
├── tokens_used (integer, nullable)
├── created_at
```

**Services noi:**
```
App\Services\Chat\ChatService          - Orchestrare principală
App\Services\Chat\ChatContextBuilder   - Construiește context (KB + user data)
App\Services\Chat\ChatToolHandler      - Procesează tool calls de la Claude
App\Services\Chat\AnthropicClient      - Wrapper peste Claude API
```

**Controllers:**
```
App\Http\Controllers\Api\V1\ChatController
├── sendMessage(Request)    → POST /api/v1/chat/messages
├── getConversations()      → GET  /api/v1/chat/conversations
├── getConversation($id)    → GET  /api/v1/chat/conversations/{id}
└── rateMessage(Request)    → POST /api/v1/chat/messages/{id}/rate
```

#### B. Frontend (JS Module)

**Fișiere noi:**
```
resources/marketplaces/ambilet/assets/js/chat-widget.js   (~600 linii)
resources/marketplaces/ambilet/assets/css/chat-widget.css  (~350 linii)
resources/marketplaces/tics/assets/js/chat-widget.js       (symlink/copy)
resources/marketplaces/tics/assets/css/chat-widget.css     (symlink/copy)
```

**Fișiere modificate:**
```
resources/marketplaces/ambilet/includes/head.php      → Add CSS link
resources/marketplaces/ambilet/includes/scripts.php   → Add JS + init
resources/marketplaces/tics/includes/head.php         → Add CSS link
resources/marketplaces/tics/includes/scripts.php      → Add JS + init
```

#### C. Admin Panel (Filament)

**Resurse noi:**
```
App\Filament\Resources\ChatConversationResource
├── List: conversații cu filtru status, search
├── View: timeline mesaje, acțiuni (resolve, escalate)
└── Statistici: rata rezolvare, timp mediu, top întrebări
```

---

## 4. System Prompt & RAG Strategy

### 4.1 System Prompt (Template)

```
Ești asistentul virtual al {marketplace_name}. Ajuți clienții cu:
- Informații despre evenimente și bilete disponibile
- Statusul comenzilor și biletelor
- Procesul de cumpărare și plată
- Politica de rambursare și anulare
- Întrebări generale despre platformă

REGULI:
1. Răspunde DOAR în limba română
2. Fii concis și prietenos
3. Nu inventa informații - folosește doar datele disponibile
4. Pentru probleme complexe, recomandă contactarea suportului la {support_email}
5. Nu oferi informații financiare sensibile (carduri, conturi bancare)
6. Când nu știi răspunsul, spune sincer și sugerează alternative
7. Folosește tool-urile disponibile pentru a accesa date reale

CONTEXT MARKETPLACE:
- Nume: {marketplace_name}
- Monedă: {currency}
- Email suport: {support_email}
- Telefon suport: {support_phone}
```

### 4.2 RAG (Retrieval Augmented Generation)

**Sursa de cunoștințe:** Tabelul `kb_articles` existent în baza de date.

**Strategia:**
1. La fiecare mesaj, se face search în KB articles pe baza întrebării
2. Se folosește `LIKE` search pe `title` și `content` (simplu, fără vector DB)
3. Top 3 articole relevante se adaugă în context-ul conversației
4. Articolele sunt deja structurate per marketplace (`marketplace_id`)

**Exemplu flow:**
```
User: "Cum pot cere rambursare?"
→ Search KB: WHERE (title LIKE '%ramburs%' OR content LIKE '%ramburs%')
→ Găsește: "Politica de rambursare" (kb_article)
→ Adaugă în context pentru Claude
→ Claude formulează răspuns bazat pe articolul real
```

**Upgrade viitor (Faza 2):** Embeddings cu pgvector pentru search semantic.

---

## 5. Tool Use (Function Calling)

Claude va avea acces la următoarele tool-uri pentru a accesa date reale:

### 5.1 Tools Disponibile

```json
[
  {
    "name": "get_customer_orders",
    "description": "Obține comenzile clientului autentificat",
    "input_schema": {
      "type": "object",
      "properties": {
        "status": {"type": "string", "enum": ["pending", "confirmed", "cancelled"]},
        "limit": {"type": "integer", "default": 5}
      }
    }
  },
  {
    "name": "get_order_details",
    "description": "Obține detaliile unei comenzi specifice",
    "input_schema": {
      "type": "object",
      "properties": {
        "order_number": {"type": "string", "description": "Numărul comenzii"}
      },
      "required": ["order_number"]
    }
  },
  {
    "name": "get_customer_tickets",
    "description": "Obține biletele clientului autentificat",
    "input_schema": {
      "type": "object",
      "properties": {
        "upcoming_only": {"type": "boolean", "default": false}
      }
    }
  },
  {
    "name": "search_events",
    "description": "Caută evenimente disponibile",
    "input_schema": {
      "type": "object",
      "properties": {
        "query": {"type": "string"},
        "city": {"type": "string"},
        "category": {"type": "string"},
        "date_from": {"type": "string", "format": "date"}
      }
    }
  },
  {
    "name": "get_event_details",
    "description": "Obține detalii despre un eveniment specific",
    "input_schema": {
      "type": "object",
      "properties": {
        "event_slug": {"type": "string"}
      },
      "required": ["event_slug"]
    }
  },
  {
    "name": "search_knowledge_base",
    "description": "Caută în baza de cunoștințe/FAQ",
    "input_schema": {
      "type": "object",
      "properties": {
        "query": {"type": "string"}
      },
      "required": ["query"]
    }
  },
  {
    "name": "get_refund_policy",
    "description": "Obține politica de rambursare pentru un eveniment",
    "input_schema": {
      "type": "object",
      "properties": {
        "event_slug": {"type": "string"}
      },
      "required": ["event_slug"]
    }
  },
  {
    "name": "escalate_to_human",
    "description": "Transferă conversația la suport uman",
    "input_schema": {
      "type": "object",
      "properties": {
        "reason": {"type": "string", "description": "Motivul escaladării"},
        "priority": {"type": "string", "enum": ["low", "medium", "high"]}
      },
      "required": ["reason"]
    }
  }
]
```

### 5.2 Identificare Utilizator

| Stare | Comportament |
|-------|-------------|
| **Autentificat** | `AmbiletAuth.getToken()` → trimis în header → backend identifică `MarketplaceCustomer` → acces la comenzi, bilete, profil |
| **Neautentificat** | Session ID generat client-side → acces doar la: KB search, event search, informații generale |
| **Tranziție** | La login, conversațiile anonime se leagă de customer prin session_id |

---

## 6. Faze de Implementare

### Faza 1 — MVP (2-3 săptămâni)

**Scope:**
- [x] Chat widget UI (bubble + window) pe AmBilet
- [x] Backend API (send message, get conversation)
- [x] Integrare Claude API (Haiku) cu system prompt
- [x] RAG simplu din KB articles (LIKE search)
- [x] Suport utilizatori autentificați (vezi comenzi, bilete)
- [x] Salvare conversații în DB
- [x] Pagină admin Filament pentru vizualizare conversații

**Fișiere de creat:**
```
# Backend
app/Models/ChatConversation.php
app/Models/ChatMessage.php
database/migrations/xxxx_create_chat_conversations_table.php
database/migrations/xxxx_create_chat_messages_table.php
app/Services/Chat/ChatService.php
app/Services/Chat/ChatContextBuilder.php
app/Services/Chat/ChatToolHandler.php
app/Services/Chat/AnthropicClient.php
app/Http/Controllers/Api/V1/ChatController.php
config/anthropic.php

# Frontend
resources/marketplaces/ambilet/assets/js/chat-widget.js
resources/marketplaces/ambilet/assets/css/chat-widget.css

# Admin
app/Filament/Resources/ChatConversationResource.php
app/Filament/Resources/ChatConversationResource/Pages/ListChatConversations.php
app/Filament/Resources/ChatConversationResource/Pages/ViewChatConversation.php
```

**Fișiere de modificat:**
```
resources/marketplaces/ambilet/includes/head.php     → CSS link
resources/marketplaces/ambilet/includes/scripts.php  → JS init
routes/api.php                                        → Chat routes
.env                                                  → ANTHROPIC_API_KEY
composer.json                                         → anthropic SDK
```

### Faza 2 — Îmbunătățiri (2 săptămâni)

- [ ] Streaming responses (SSE - Server Sent Events)
- [ ] Chat widget pe TICS (adaptare CSS/config)
- [ ] Quick reply suggestions (butoane cu întrebări frecvente)
- [ ] Rating mesaje (thumbs up/down)
- [ ] Statistici în admin (conversații/zi, rata rezolvare, top întrebări)
- [ ] Limită rate per IP/user (anti-abuse)

### Faza 3 — Avansat (opțional)

- [ ] Vector search (pgvector) pentru RAG semantic
- [ ] Proactive chat (trigger pe pagini specifice)
- [ ] Notificări push pentru escaladări
- [ ] Export conversații
- [ ] A/B testing system prompts
- [ ] Multi-language support

---

## 7. Detalii Frontend — Chat Widget

### 7.1 UI Design

```
┌─────────────────────────────────┐
│ ✕  Asistent AmBilet        ─── │  ← Header (brand color)
├─────────────────────────────────┤
│                                 │
│  Bună! 👋 Sunt asistentul      │  ← Welcome message
│  virtual AmBilet. Cu ce te     │
│  pot ajuta?                     │
│                                 │
│  ┌─────────────────────────┐   │  ← Quick suggestions
│  │ 📋 Statusul comenzii    │   │
│  │ 🎫 Biletele mele        │   │
│  │ 💰 Politica rambursare  │   │
│  │ 🔍 Caută eveniment      │   │
│  └─────────────────────────┘   │
│                                 │
│         ┌───────────────────┐   │
│         │ Bună, cum pot     │   │  ← User message (right)
│         │ cere rambursare?  │   │
│         └───────────────────┘   │
│                                 │
│  ┌───────────────────────┐     │
│  │ Pentru a solicita o   │     │  ← AI message (left)
│  │ rambursare, accesează │     │
│  │ secțiunea "Comenzile  │     │
│  │ mele" și apasă pe...  │     │
│  └───────────────────────┘     │
│                                 │
├─────────────────────────────────┤
│ [  Scrie un mesaj...     ] [➤] │  ← Input area
└─────────────────────────────────┘

        ┌──────┐
        │ 💬   │  ← Floating bubble (bottom-right)
        └──────┘
```

### 7.2 Responsive

- **Desktop:** Window 380px × 520px, fixed bottom-right
- **Mobile:** Full-screen overlay, slide-up animation
- **Tablet:** Window 380px × 480px

### 7.3 Integrare cu Stilul Existent

- Folosește culorile din `AMBILET_CONFIG.THEME` (PRIMARY: `#A51C30`)
- Font: `Inter` (deja încărcat)
- Iconuri: SVG inline (pattern existent în codebase)
- Animații: CSS transitions (no external libs)
- Z-index: `50` (peste header z-30, sub modals)

---

## 8. Securitate

| Risc | Mitigare |
|------|----------|
| Prompt injection | System prompt strict, nu expune date interne |
| Rate limiting | Max 20 mesaje/minut per user, 5/minut per guest |
| Data leakage | Tools returnează doar datele clientului autentificat |
| Abuse | Lungime max mesaj: 1000 caractere, filtrare conținut |
| Cost control | Max 10 tool calls per conversație, timeout 30s |
| XSS | Sanitizare HTML în răspunsuri, render ca text |
| CSRF | Token existent via Laravel |

---

## 9. Configurare Admin (Filament)

### Settings disponibile în admin:
- **Enable/Disable** chat widget per marketplace
- **System prompt** editabil
- **Model selection** (Haiku/Sonnet)
- **Max messages per conversation** (default: 50)
- **Welcome message** customizabil
- **Quick suggestions** customizabile
- **Escalation email** pentru notificări

### Conversation View:
- Timeline cu mesaje user/assistant
- Metadata: pagina de unde a inițiat, browser, IP
- Status: open/resolved/escalated
- Acțiuni: resolve, escalate, delete
- Feedback rating per mesaj

---

## 10. Dependențe Tehnice

### Pachete noi (composer):
```json
{
  "anthropic/sdk": "^1.0"
}
```

### Variabile de mediu noi:
```env
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-haiku-4-5-20251001
CHAT_WIDGET_ENABLED=true
CHAT_RATE_LIMIT_AUTH=20
CHAT_RATE_LIMIT_GUEST=5
```

### Migrări DB:
```sql
-- chat_conversations
CREATE TABLE chat_conversations (
    id CHAR(36) PRIMARY KEY,
    marketplace_id BIGINT UNSIGNED NOT NULL,
    marketplace_customer_id BIGINT UNSIGNED NULL,
    session_id VARCHAR(64) NOT NULL,
    status ENUM('open', 'resolved', 'escalated') DEFAULT 'open',
    metadata JSON NULL,
    escalated_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (marketplace_id) REFERENCES marketplaces(id),
    FOREIGN KEY (marketplace_customer_id) REFERENCES marketplace_customers(id),
    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_marketplace_customer (marketplace_id, marketplace_customer_id)
);

-- chat_messages
CREATE TABLE chat_messages (
    id CHAR(36) PRIMARY KEY,
    chat_conversation_id CHAR(36) NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    tool_calls JSON NULL,
    tool_results JSON NULL,
    tokens_used INT UNSIGNED NULL,
    rating TINYINT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (chat_conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation (chat_conversation_id),
    INDEX idx_created (created_at)
);
```

---

## 11. Metrici de Succes

| Metrică | Target |
|---------|--------|
| Rata de rezolvare fără escaladare | > 70% |
| Timp mediu răspuns | < 3 secunde |
| Satisfacție utilizator (rating) | > 4/5 |
| Reducere emailuri suport | > 30% |
| Cost per conversație | < $0.05 |

---

## 12. Prioritatea Implementării

**Ordinea fișierelor de implementat (Faza 1):**

1. Migrări DB + Modele (`ChatConversation`, `ChatMessage`)
2. Config `anthropic.php` + `.env`
3. `AnthropicClient` service (wrapper API)
4. `ChatContextBuilder` (RAG + user context)
5. `ChatToolHandler` (tool call processing)
6. `ChatService` (orchestrare)
7. `ChatController` + routes
8. Frontend: `chat-widget.css`
9. Frontend: `chat-widget.js`
10. Injectare în `head.php` + `scripts.php`
11. Admin: `ChatConversationResource` (Filament)
12. Testing & ajustare system prompt
