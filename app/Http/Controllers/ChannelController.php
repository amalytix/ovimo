<?php

namespace App\Http\Controllers;

use App\Http\Requests\Channel\StoreChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Channel::class);

        $teamId = auth()->user()->current_team_id;

        $channels = Channel::query()
            ->where('team_id', $teamId)
            ->ordered()
            ->withCount('derivatives')
            ->get()
            ->map(fn (Channel $channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'icon' => $channel->icon,
                'color' => $channel->color,
                'sort_order' => $channel->sort_order,
                'is_active' => $channel->is_active,
                'derivatives_count' => $channel->derivatives_count,
            ]);

        return response()->json(['channels' => $channels]);
    }

    public function store(StoreChannelRequest $request): RedirectResponse
    {
        $this->authorize('create', Channel::class);

        $teamId = auth()->user()->current_team_id;

        $maxSortOrder = Channel::query()
            ->where('team_id', $teamId)
            ->max('sort_order') ?? -1;

        Channel::create([
            'team_id' => $teamId,
            'name' => $request->validated('name'),
            'icon' => $request->validated('icon'),
            'color' => $request->validated('color'),
            'sort_order' => $maxSortOrder + 1,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Channel created successfully.');
    }

    public function update(UpdateChannelRequest $request, Channel $channel): RedirectResponse
    {
        $this->authorize('update', $channel);

        $channel->update([
            'name' => $request->validated('name'),
            'icon' => $request->validated('icon'),
            'color' => $request->validated('color'),
            'is_active' => $request->validated('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Channel updated successfully.');
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        $this->authorize('delete', $channel);

        $channel->delete();

        return redirect()->back()->with('success', 'Channel deleted successfully.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('reorder', Channel::class);

        $validated = $request->validate([
            'channel_ids' => ['required', 'array'],
            'channel_ids.*' => ['required', 'integer', 'exists:channels,id'],
        ]);

        $teamId = auth()->user()->current_team_id;

        foreach ($validated['channel_ids'] as $index => $channelId) {
            Channel::query()
                ->where('id', $channelId)
                ->where('team_id', $teamId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
