# Team Management Implementation Plan

## Overview

Implement team invitation and member management features allowing users to invite others to teams, manage team membership, view 2FA status, and switch between teams.

## Requirements Summary

- Users can invite others to join a team (email-based, supports new user registration)
- Invitations valid for 48 hours with email notification
- Team owner can manage members (view, remove, revoke invitations)
- All members can view team members and their 2FA status
- Users can switch between teams (if member of multiple)
- Users can leave teams (except if only member)
- Emails normalized to lowercase for duplicate prevention

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| UI Location | `/settings/teams` | Fits with existing settings pattern |
| Invite flow | New users allowed | More flexible onboarding |
| Last member leaving | Block with error | Prevents orphaned teams |
| Invite/revoke permissions | Owner only | Simpler authorization model |
| Email handling | Lowercase normalized | Prevents duplicate invites |

---

## Phase 1: Backend - Team Invitations

### 1.1 Migration: `create_team_invitations_table`

```php
Schema::create('team_invitations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('email')->index();
    $table->string('token', 64)->unique();
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->unique(['team_id', 'email']);
});
```

### 1.2 Model: `app/Models/TeamInvitation.php`

- Relationships: `belongsTo(Team::class)`
- Scopes: `scopeNotExpired()`, `scopeForEmail()`
- Accessors: `isExpired` computed property
- Events: Generate token on creating

### 1.3 Add relationship to Team model

```php
public function invitations(): HasMany
{
    return $this->hasMany(TeamInvitation::class);
}

public function pendingInvitations(): HasMany
{
    return $this->invitations()->where('expires_at', '>', now());
}
```

### 1.4 Controller: `app/Http/Controllers/TeamInvitationController.php`

**Routes:**
- `POST /team-invitations` → `store` (send invitation)
- `DELETE /team-invitations/{invitation}` → `destroy` (revoke)
- `GET /invitations/{token}/accept` → `accept` (accept invitation)

**store():**
1. Authorize: user is team owner
2. Validate email (lowercase normalized)
3. Check not already team member
4. Check no pending invitation exists
5. Create TeamInvitation with 48h expiry
6. Queue TeamInvitationMail
7. Redirect back with success

**destroy():**
1. Authorize: user is team owner
2. Delete invitation
3. Redirect back with success

**accept():**
1. Find invitation by token
2. If expired → show error page
3. If not authenticated → redirect to login with intended URL
4. If already team member → delete invite, show message
5. Attach user to team with 'member' role
6. Delete invitation
7. Set as current team
8. Redirect to dashboard

### 1.5 Form Request: `app/Http/Requests/StoreTeamInvitationRequest.php`

```php
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'max:255'],
    ];
}

protected function prepareForValidation(): void
{
    $this->merge([
        'email' => strtolower($this->email),
    ]);
}
```

### 1.6 Mailable: `app/Mail/TeamInvitationMail.php`

- Constructor: `TeamInvitation $invitation`
- Subject: "You've been invited to join {team name}"
- View: `emails.team-invitation`
- Contains: Team name, accept URL, expiry info

### 1.7 Email View: `resources/views/emails/team-invitation.blade.php`

Simple HTML email with:
- Greeting
- "You've been invited to join {team name} on {app name}"
- Accept button/link
- Expiry notice (48 hours)
- Note about creating account if new

---

## Phase 2: Backend - Team Member Management

### 2.1 Controller: `app/Http/Controllers/TeamMemberController.php`

**Routes:**
- `GET /settings/teams` → `index` (show team management page)
- `DELETE /team-members/{user}` → `destroy` (remove member)
- `POST /team-members/leave` → `leave` (leave current team)

**index():**
Return Inertia page with:
- Current team with users (eager load 2FA status)
- Pending invitations
- User's other teams (for switcher)
- isOwner flag

**destroy():**
1. Authorize: user is team owner
2. Validate: cannot remove self, cannot remove owner
3. Detach user from team
4. If removed user's current_team_id matches → set to their first other team or null
5. Redirect back with success

**leave():**
1. Validate: user is not only member
2. Detach from team
3. Set current_team_id to first other team or null
4. Redirect to dashboard

### 2.2 Controller: `app/Http/Controllers/TeamSwitchController.php`

**Routes:**
- `POST /teams/{team}/switch` → `__invoke`

**__invoke():**
1. Authorize: user is member of team
2. Update user's current_team_id
3. Redirect back (or to dashboard)

### 2.3 Policy: `app/Policies/TeamPolicy.php` (if needed)

Add methods:
- `invite(User $user, Team $team)` - is owner
- `removeMember(User $user, Team $team)` - is owner
- `switchTo(User $user, Team $team)` - is member

---

## Phase 3: Frontend

### 3.1 Update Settings Layout Navigation

**File:** `resources/js/layouts/settings/Layout.vue`

Add "Teams" nav item linking to `/settings/teams`

### 3.2 New Page: `resources/js/Pages/settings/Teams.vue`

**Props:**
```typescript
interface Props {
    team: {
        id: number;
        name: string;
        owner_id: number;
        users: Array<{
            id: number;
            name: string;
            email: string;
            two_factor_confirmed_at: string | null;
        }>;
    };
    pendingInvitations: Array<{
        id: number;
        email: string;
        created_at: string;
        expires_at: string;
    }>;
    userTeams: Array<{
        id: number;
        name: string;
    }>;
    isOwner: boolean;
}
```

**Sections:**

1. **Current Team Header**
   - Team name display
   - Leave Team button (if not only member and not owner, or show disabled with tooltip)

2. **Team Members Table**
   - Columns: Name, Email, 2FA (Badge: Enabled/Disabled), Actions
   - Actions: Remove button (owner only, not on self)

3. **Pending Invitations Table** (owner only)
   - Columns: Email, Sent, Expires, Actions
   - Actions: Revoke button
   - Show relative time for expiry

4. **Invite Member Form** (owner only)
   - Email input
   - Send Invitation button

### 3.3 Team Switcher in Sidebar

**File:** `resources/js/components/NavUser.vue` or `UserMenuContent.vue`

If user has multiple teams, add a "Switch Team" submenu:
- List of teams (excluding current)
- Each triggers POST to `/teams/{id}/switch`

### 3.4 Components to Use

- `Card`, `CardHeader`, `CardContent` for sections
- `Button` with variants
- `Input`, `Label` for forms
- `Badge` for 2FA status
- `DropdownMenu` for team switcher
- `Dialog` for leave/remove confirmations
- Standard HTML tables with Tailwind

---

## Phase 4: Testing

### 4.1 Feature Tests: Team Invitations

**File:** `tests/Feature/TeamInvitationTest.php`

Tests:
- Owner can send invitation
- Non-owner cannot send invitation
- Cannot invite existing team member
- Cannot invite if pending invitation exists
- Email is normalized to lowercase
- Invitation email is sent (queued)
- Owner can revoke invitation
- Non-owner cannot revoke invitation
- User can accept valid invitation
- Expired invitation shows error
- New user flow: redirect to register → accept after auth
- Existing user flow: redirect to login → accept after auth
- Already member: shows message, deletes invite

### 4.2 Feature Tests: Team Members

**File:** `tests/Feature/TeamMemberTest.php`

Tests:
- Members page shows team members with 2FA status
- Owner can remove member
- Non-owner cannot remove member
- Cannot remove self (use leave instead)
- Removed user's current_team_id is updated
- User can leave team
- Cannot leave if only member
- After leaving, current_team_id updated

### 4.3 Feature Tests: Team Switching

**File:** `tests/Feature/TeamSwitchTest.php`

Tests:
- User can switch to team they belong to
- User cannot switch to team they don't belong to
- After switch, current_team_id is updated

---

## Files to Create

| File | Type |
|------|------|
| `database/migrations/xxxx_create_team_invitations_table.php` | Migration |
| `app/Models/TeamInvitation.php` | Model |
| `app/Http/Controllers/TeamInvitationController.php` | Controller |
| `app/Http/Controllers/TeamMemberController.php` | Controller |
| `app/Http/Controllers/TeamSwitchController.php` | Controller |
| `app/Http/Requests/StoreTeamInvitationRequest.php` | Form Request |
| `app/Mail/TeamInvitationMail.php` | Mailable |
| `resources/views/emails/team-invitation.blade.php` | Email View |
| `resources/js/Pages/settings/Teams.vue` | Page Component |
| `tests/Feature/TeamInvitationTest.php` | Test |
| `tests/Feature/TeamMemberTest.php` | Test |
| `tests/Feature/TeamSwitchTest.php` | Test |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Models/Team.php` | Add `invitations()` and `pendingInvitations()` relationships |
| `routes/web.php` | Add routes for invitations, members, switching |
| `resources/js/layouts/settings/Layout.vue` | Add "Teams" nav item |
| `resources/js/components/NavUser.vue` or `UserMenuContent.vue` | Add team switcher dropdown |

## Verification

After implementation:
1. Run `php artisan test --filter=TeamInvitation`
2. Run `php artisan test --filter=TeamMember`
3. Run `php artisan test --filter=TeamSwitch`
4. Manual testing of invitation email flow
5. Run `npm run build` for frontend changes
6. Run `vendor/bin/pint --dirty` for code style
