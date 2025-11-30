<?php

namespace App\Http\Controllers;

use App\Events\TeamInvitationAccepted;
use App\Events\TeamInvitationRevoked;
use App\Events\TeamInvitationSent;
use App\Http\Requests\StoreTeamInvitationRequest;
use App\Mail\TeamInvitationMail;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    public function store(StoreTeamInvitationRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $email = $request->validated('email');

        if ($team->users()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'This user is already a member of the team.']);
        }

        if ($team->pendingInvitations()->forEmail($email)->exists()) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email address.']);
        }

        $invitation = $team->invitations()->create([
            'email' => $email,
        ]);

        Mail::to($email)->send(new TeamInvitationMail($invitation));

        TeamInvitationSent::dispatch($invitation, $request->user());

        return back()->with('success', 'Invitation sent successfully.');
    }

    public function destroy(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if (! $team || $invitation->team_id !== $team->id) {
            abort(404);
        }

        if ($team->owner_id !== $request->user()->id) {
            abort(403, 'Only the team owner can revoke invitations.');
        }

        $email = $invitation->email;
        $invitation->delete();

        TeamInvitationRevoked::dispatch($team, $request->user(), $email);

        return back()->with('success', 'Invitation revoked.');
    }

    public function accept(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = TeamInvitation::where('token', $token)->first();

        if (! $invitation) {
            return Inertia::render('Invitations/Invalid', [
                'message' => 'This invitation link is invalid.',
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Expired', [
                'teamName' => $invitation->team->name,
            ]);
        }

        if (! $request->user()) {
            session(['url.intended' => url("/invitations/{$token}/accept")]);

            return redirect()->route('login');
        }

        $user = $request->user();
        $team = $invitation->team;

        if ($team->users()->where('user_id', $user->id)->exists()) {
            $invitation->delete();

            return redirect()->route('dashboard')
                ->with('info', "You're already a member of {$team->name}.");
        }

        $invitedEmail = $invitation->email;

        $team->users()->attach($user->id, ['role' => 'member']);

        $user->update(['current_team_id' => $team->id]);

        $invitation->delete();

        TeamInvitationAccepted::dispatch($team, $user, $invitedEmail);

        return redirect()->route('dashboard')
            ->with('success', "You've joined {$team->name}!");
    }
}
