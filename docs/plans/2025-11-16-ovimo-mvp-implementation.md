# Ovimo MVP Implementation Plan

**Date:** 2025-11-16
**Status:** In Progress

## Implementation Order

### Phase 1: Foundation
1. Database config (MariaDB)
2. All migrations in dependency order
3. All models with relationships and casts
4. Team system integrated into user registration

### Phase 2: Core Features
5. Sources CRUD (controller, form request, Vue pages)
6. Posts listing with filters
7. CheckSourceJob for RSS/Sitemap parsing
8. SummarizePostJob with OpenAI integration

### Phase 3: Content Creation
9. Prompts CRUD with placeholder validation
10. Content Pieces CRUD
11. ContentGeneratorService with AI

### Phase 4: Infrastructure
12. Webhook notifications
13. Token usage tracking and limits
14. Team settings page
15. Dashboard with usage stats

### Key Decisions
- Custom team system (not Jetstream)
- One team per user for MVP
- MariaDB as database
- OpenAI GPT-5.1 (configurable via .env)
- Redis for queues (database fallback)
