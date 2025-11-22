# Content Piece Upgrade Implementation Plan

## Overview

This plan details the upgrade of the "Create Content Piece" and "Edit Content Piece" pages to introduce a tabbed interface with Research and Editing tabs, integrate Tiptap rich text editor, and add media gallery support.

## Phase 1: Database & Backend (Foundation)

### 1.1 Database Migration

**Migration 1: Rename field and add new field**
- Rename `full_text` → `research_text` in `content_pieces` table
- Add `edited_text` column (longText, nullable) to `content_pieces` table

**Migration 2: Create pivot table**
- Create `content_piece_media` pivot table with columns:
  - `content_piece_id` (foreign key)
  - `media_id` (foreign key)
  - `sort_order` (integer, for ordering attachments)
  - `timestamps`

### 1.2 Model Updates

**ContentPiece Model** (`app/Models/ContentPiece.php`)
- Add `edited_text` to `$fillable` array
- Add `media()` relationship method:
  ```php
  public function media(): BelongsToMany
  {
      return $this->belongsToMany(Media::class)
          ->withTimestamps()
          ->orderBy('sort_order');
  }
  ```

**Media Model** (`app/Models/Media.php`)
- Add `contentPieces()` relationship method:
  ```php
  public function contentPieces(): BelongsToMany
  {
      return $this->belongsToMany(ContentPiece::class)
          ->withTimestamps();
  }
  ```

### 1.3 Form Request Validation

**StoreContentPieceRequest**
- Add validation rules:
  - `edited_text` → `nullable|string`
  - `media_ids` → `nullable|array`
  - `media_ids.*` → `exists:media,id`

**UpdateContentPieceRequest**
- Rename `full_text` → `research_text` in validation rules
- Add validation rules:
  - `edited_text` → `nullable|string`
  - `media_ids` → `nullable|array`
  - `media_ids.*` → `exists:media,id`

### 1.4 Controller Updates

**ContentPieceController** (`app/Http/Controllers/ContentPieceController.php`)

**`store()` method:**
- After creating content piece, sync media relationship:
  ```php
  $contentPiece->media()->sync($request->input('media_ids', []));
  ```

**`update()` method:**
- After updating content piece, sync media relationship:
  ```php
  $contentPiece->media()->sync($request->input('media_ids', []));
  ```

**`status()` endpoint:**
- Ensure response includes both `research_text` and `edited_text` fields
- Eager load media relationship when needed

**`create()` and `edit()` methods:**
- Eager load media relationship for edit page
- Return media collection to frontend

---

## Phase 2: Frontend Dependencies & Shared Components

### 2.1 Install NPM Dependencies

```bash
npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-image @tiptap/extension-link @tiptap/extension-placeholder @tiptap/pm @tiptap/extension-markdown
```

### 2.2 Create Shared Components

All components created in `resources/js/components/ContentPiece/`

#### **GeneralInfoHeader.vue**
Displays general information above tabs.

**Props:**
- `contentPiece` (object, optional) - Existing content piece data
- `form` (object) - Inertia form object
- `channels` (array) - Available channel options
- `statuses` (array) - Available status options

**Features:**
- Shows `internal_name` as heading (or "New Content Piece" for create page)
- Displays channel badge, published_at field, and status selector inline
- Responsive layout
- Dark mode support

#### **ResearchTab.vue**
Contains all research-related form fields.

**Props:**
- `form` (object) - Inertia form object
- `prompts` (array) - Available prompts
- `posts` (array) - Available source posts
- `generationStatus` (object) - Current generation status
- `onCopyToEditor` (function) - Callback when copy button clicked

**Features:**
- Prompt selector dropdown
- Briefing text textarea
- Source posts checkboxes with search/filter
- Research text textarea (read-only after generation)
- Generation polling UI with status indicators
- "Copy to Editing Tab" button
- Real-time generation status updates (QUEUED, PROCESSING, COMPLETED, FAILED)

#### **EditingTab.vue**
Rich text editor and media gallery integration.

**Props:**
- `form` (object) - Inertia form object
- `selectedMedia` (array) - Currently attached media files
- `onMediaSelect` (function) - Callback when media selected
- `onMediaRemove` (function) - Callback when media removed

**Features:**
- TiptapEditor component integration
- "Add Media" button to open gallery picker
- Attached media preview cards with remove option
- Drag-and-drop reordering of attached media (future enhancement)

#### **TiptapEditor.vue**
Reusable Tiptap rich text editor wrapper.

**Props:**
- `modelValue` (string) - Editor content
- `placeholder` (string, optional) - Placeholder text

**Emits:**
- `update:modelValue` - When content changes

**Features:**
- Tiptap configuration with extensions:
  - StarterKit (basic formatting)
  - Markdown (markdown support)
  - Image (image insertion)
  - Link (link handling)
  - Placeholder
- Toolbar with formatting buttons:
  - Bold, italic, strike, code
  - Headings (H1, H2, H3)
  - Bullet list, ordered list
  - Blockquote, code block
  - Link insertion
  - Image insertion from gallery
- Markdown/WYSIWYG mode toggle
- Dark mode support
- Accessible keyboard shortcuts

#### **MediaGalleryPicker.vue**
Dialog for selecting media from gallery.

**Props:**
- `open` (boolean) - Dialog open state
- `selectedIds` (array) - Currently selected media IDs
- `multiSelect` (boolean, default: true) - Allow multiple selection
- `onSelect` (function) - Callback with selected media
- `onOpenChange` (function) - Dialog open/close callback

**Features:**
- Reuses existing MediaCard and MediaFilters components
- Multi-select mode with checkboxes
- Search and filter functionality
- Pagination
- Preview on click
- "Select" button to confirm selection

#### **CopyContentDialog.vue**
Confirmation dialog for copying research text to editor.

**Props:**
- `open` (boolean) - Dialog open state
- `onConfirm` (function) - Callback with user choice ('replace' or 'append')
- `onCancel` (function) - Callback when cancelled

**Features:**
- Shows warning when edited_text already has content
- Two action buttons: "Replace" and "Append"
- Cancel button
- Clear explanation of each option

---

## Phase 3: Refactor Create & Edit Pages

### 3.1 Refactor `Create.vue`

**Structure:**
```vue
<AppLayout>
  <template #header>
    <!-- Breadcrumbs -->
  </template>

  <GeneralInfoHeader
    :form="form"
    :channels="channels"
    :statuses="statuses"
  />

  <Tabs v-model="activeTab" default-value="research">
    <TabsList>
      <TabsTrigger value="research">Research</TabsTrigger>
      <TabsTrigger value="editing">Editing</TabsTrigger>
    </TabsList>

    <TabsContent value="research">
      <ResearchTab
        :form="form"
        :prompts="prompts"
        :posts="posts"
        :generation-status="generationStatus"
        @copy-to-editor="handleCopyToEditor"
      />
    </TabsContent>

    <TabsContent value="editing">
      <EditingTab
        :form="form"
        :selected-media="selectedMedia"
        @media-select="handleMediaSelect"
        @media-remove="handleMediaRemove"
      />
    </TabsContent>
  </Tabs>

  <!-- Action buttons -->
  <div class="flex justify-end gap-4 mt-6">
    <Button @click="handleSaveDraft">Save Draft</Button>
    <Button @click="handleGenerate">Generate</Button>
  </div>

  <CopyContentDialog
    :open="showCopyDialog"
    @confirm="confirmCopy"
    @cancel="cancelCopy"
  />
</AppLayout>
```

**Form Data:**
```typescript
const form = useForm({
  internal_name: '',
  prompt_id: null,
  briefing_text: '',
  channel: 'BLOG_POST',
  target_language: 'ENGLISH',
  status: 'NOT_STARTED',
  research_text: '',
  edited_text: '',
  post_ids: [],
  media_ids: [],
  published_at: null,
});
```

**Key Methods:**
- `handleCopyToEditor()` - Opens dialog if edited_text has content, otherwise copies directly
- `confirmCopy(action)` - Replaces or appends based on user choice
- `handleMediaSelect(media)` - Adds media IDs to form
- `handleMediaRemove(mediaId)` - Removes media ID from form
- `handleSaveDraft()` - Submits form
- `handleGenerate()` - Triggers AI generation
- Polling logic for generation status (keep existing implementation)

### 3.2 Refactor `Edit.vue`

**Same structure as Create.vue** with these differences:
- Pre-populate form with existing data:
  - `research_text` from `contentPiece.research_text`
  - `edited_text` from `contentPiece.edited_text`
  - `media_ids` from `contentPiece.media.map(m => m.id)`
- Pass `contentPiece` to GeneralInfoHeader for title display
- Update form submission to use PATCH method

---

## Phase 4: Media Integration

### 4.1 Media Selection Flow

1. User clicks "Add Media" button in EditingTab
2. MediaGalleryPicker dialog opens, user can search for media files, e.g. by name
3. User selects media files (multi-select)
4. User clicks "Select" button
5. Dialog closes and emits selected media
6. Parent component adds media IDs to `form.media_ids`
7. EditingTab displays attached media as preview cards

### 4.2 Media Display

**Attached Media Preview:**
- Display media cards below Tiptap editor
- Show thumbnail, filename, file size
- "Remove" button on each card
- Responsive grid layout

### 4.3 Tiptap Image Insertion

**"Insert from Gallery" feature:**
1. Add custom button to Tiptap toolbar
2. Button opens MediaGalleryPicker in single-select mode
3. User selects one image
4. Image inserted at cursor position using Tiptap Image extension
5. Image stored as markdown/HTML in `edited_text` content
6. Image URL points to S3 storage

**Note:** Images inserted into editor are embedded in content, separate from attached media files tracked in database relationship.

---

## Phase 5: Testing & Polish

### 5.1 Create/Update Tests

**Feature Tests:**
- `tests/Feature/ContentPieceTest.php`:
  - Test creating content piece with `research_text` and `edited_text`
  - Test updating content piece with new fields
  - Test media attachment via `media_ids`
  - Test media sync (add, remove, replace)
  - Test validation rules for all fields

**Browser Tests:**
- `tests/Browser/ContentPieceTest.php`:
  - Test tab navigation (Research ↔ Editing)
  - Test copying research text to editor (replace and append)
  - Test media gallery picker integration
  - Test Tiptap editor interaction
  - Test form submission with all fields
  - Test generation polling UI

### 5.2 Code Quality

**PHP:**
```bash
vendor/bin/pint
```

**Frontend:**
```bash
npm run build
```

**TypeScript:**
- Fix any type errors in new components
- Add proper interfaces for props and emits

**Accessibility:**
- Ensure all interactive elements have proper ARIA labels
- Test keyboard navigation through tabs and editor
- Verify screen reader compatibility

**Dark Mode:**
- Test all new components in dark mode
- Ensure proper contrast ratios
- Use consistent dark mode classes

### 5.3 Migration Safety

**Before running migrations:**
1. Backup production database
2. Test migrations on staging environment
3. Test rollback functionality

**Data Migration (if needed):**
- If existing `content_pieces` have `full_text` data:
  - Migration automatically renames column, preserving data
  - No manual data migration needed

**Rollback Plan:**
```bash
php artisan migrate:rollback --step=2
```

---

## Implementation Order

### Step-by-Step Execution

1. **Phase 1.1:** Create and run database migrations
2. **Phase 1.2:** Update ContentPiece and Media models
3. **Phase 1.3:** Update form request validation
4. **Phase 1.4:** Update controller methods
5. **Phase 2.1:** Install Tiptap npm dependencies
6. **Phase 2.2:** Build shared components (bottom-up):
   - TiptapEditor.vue
   - MediaGalleryPicker.vue
   - CopyContentDialog.vue
   - ResearchTab.vue
   - EditingTab.vue
   - GeneralInfoHeader.vue
7. **Phase 3.1:** Refactor Create.vue using new components
8. **Phase 3.2:** Refactor Edit.vue using new components
9. **Phase 4:** Test media integration end-to-end
10. **Phase 5.1:** Write and run tests
11. **Phase 5.2:** Run Pint, build frontend, fix TypeScript errors
12. **Phase 5.3:** Final QA and testing

---

## Key Design Decisions

✓ **Tiptap** for rich text editing (Vue 3 friendly, excellent markdown support)
✓ **Many-to-many relationship** for media attachments (reusable, trackable)
✓ **Keep polling** on Research tab during generation
✓ **Prompt user** when copying research_text to edited_text (replace vs append)
✓ **Component-based architecture** eliminates duplication between Create/Edit pages
✓ **Tab state** persisted in component (not URL) for simpler implementation
✓ **General info header** shows context above tabs (title, status, channel, date)

---

## Files to Create/Modify

### Create

**Migrations:**
- `database/migrations/YYYY_MM_DD_rename_full_text_add_edited_text.php`
- `database/migrations/YYYY_MM_DD_create_content_piece_media_table.php`

**Components:**
- `resources/js/components/ContentPiece/GeneralInfoHeader.vue`
- `resources/js/components/ContentPiece/ResearchTab.vue`
- `resources/js/components/ContentPiece/EditingTab.vue`
- `resources/js/components/ContentPiece/TiptapEditor.vue`
- `resources/js/components/ContentPiece/MediaGalleryPicker.vue`
- `resources/js/components/ContentPiece/CopyContentDialog.vue`

**Tests:**
- `tests/Feature/ContentPieceMediaTest.php` (new tests for media integration)
- `tests/Browser/ContentPieceTabsTest.php` (new browser tests)

### Modify

**Backend:**
- `app/Models/ContentPiece.php`
- `app/Models/Media.php`
- `app/Http/Requests/StoreContentPieceRequest.php`
- `app/Http/Requests/UpdateContentPieceRequest.php`
- `app/Http/Controllers/ContentPieceController.php`

**Frontend:**
- `resources/js/Pages/ContentPieces/Create.vue`
- `resources/js/Pages/ContentPieces/Edit.vue`

**Tests:**
- `tests/Feature/ContentPieceTest.php` (update existing tests for new fields)

---

## Risk Mitigation

### Potential Issues

1. **Field rename breaking existing code:**
   - Search codebase for `full_text` references before migration
   - Update any API consumers or exports

2. **Tiptap bundle size:**
   - Only import needed extensions
   - Consider code splitting for editor component

3. **Media gallery performance:**
   - Implement pagination in picker
   - Lazy load thumbnails

4. **Migration rollback complexity:**
   - Test rollback thoroughly on staging
   - Document manual rollback steps if needed

### Validation

- Test with large content pieces (>10,000 characters)
- Test with many attached media files (>20 files)
- Test concurrent editing scenarios
- Test generation polling with network delays
- Test on different browsers and devices

---

## Future Enhancements

*Not in scope for initial implementation, but documented for future reference:*

- Drag-and-drop reordering of attached media
- Auto-save draft functionality
- Version history for edited_text
- Collaborative editing support
- AI-powered editing suggestions
- Export to different formats (PDF, DOCX)
- Template library for common content structures
- Image optimization and resizing in editor

---

## Success Criteria

- [ ] Database migrations run successfully without data loss
- [ ] All existing tests pass
- [ ] New tests for research_text/edited_text pass
- [ ] Tabs navigate correctly
- [ ] Research tab maintains existing generation functionality
- [ ] Editing tab supports markdown and WYSIWYG modes
- [ ] Media can be attached and removed
- [ ] Copy to editor works with replace/append options
- [ ] Form validation works for all fields
- [ ] No TypeScript errors
- [ ] Dark mode works throughout
- [ ] Code passes Pint formatting
- [ ] Frontend builds without warnings
- [ ] Browser tests pass in Chrome, Firefox, Safari
- [ ] No N+1 query issues with media relationship
- [ ] Page load time remains under 2 seconds

---

This plan maintains your existing architecture patterns, follows Laravel/Vue/Inertia best practices, and eliminates code duplication through proper component extraction.
