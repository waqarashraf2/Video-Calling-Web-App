<?php

use App\Enums\GuestStatus;
use App\Enums\RoomStatus;
use App\Events\MatchFound;
use App\Models\CallRoom;
use App\Models\GuestSession;
use App\Services\GuestSessionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

function createGuest(array $overrides = []): GuestSession
{
    return GuestSession::query()->create(array_merge([
        'public_uuid' => (string) Str::uuid(),
        'display_name' => 'Guest',
        'status' => GuestStatus::Idle,
        'abuse_fingerprint' => hash('sha256', Str::random()),
        'last_seen_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ], $overrides));
}

function asGuest(GuestSession $guest): void
{
    Session::put(GuestSessionService::SESSION_KEY, $guest->public_uuid);
}

it('creates and validates guest sessions', function () {
    $this->postJson('/api/guest-sessions', [
        'display_name' => 'Ada',
        'adult' => true,
        'terms' => true,
    ])->assertCreated()->assertJsonPath('session.display_name', 'Ada');

    $this->assertDatabaseHas('guest_sessions', ['display_name' => 'Ada']);
});

it('rejects unsafe display names and missing adult consent', function () {
    $this->postJson('/api/guest-sessions', [
        'display_name' => '<script>alert(1)</script>',
        'adult' => false,
        'terms' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['display_name', 'adult']);
});

it('prevents duplicate queue entries', function () {
    $guest = createGuest();
    asGuest($guest);

    $this->postJson('/api/matchmaking/join')->assertOk();
    $this->postJson('/api/matchmaking/join')->assertOk();

    $guest->refresh();
    expect($guest->status)->toBe(GuestStatus::Queued);
    $this->assertDatabaseCount('call_rooms', 0);
});

it('keeps guests available until someone manually calls them', function () {
    Event::fake([MatchFound::class]);
    $first = createGuest(['display_name' => 'Ada']);
    $second = createGuest(['display_name' => 'Grace']);

    asGuest($first);
    $this->postJson('/api/matchmaking/join')
        ->assertOk()
        ->assertJsonPath('matched', false);

    asGuest($second);
    $this->postJson('/api/matchmaking/join')
        ->assertOk()
        ->assertJsonPath('matched', false)
        ->assertJsonPath('room', null);

    expect($first->refresh()->status)->toBe(GuestStatus::Queued)
        ->and($second->refresh()->status)->toBe(GuestStatus::Queued);
    $this->assertDatabaseCount('call_rooms', 0);
    Event::assertNotDispatched(MatchFound::class);
});

it('lists available users and safely calls one selected user', function () {
    Event::fake([MatchFound::class]);
    $first = createGuest();
    $second = createGuest(['display_name' => 'Grace']);

    asGuest($first);
    $this->postJson('/api/matchmaking/join')->assertOk()->assertJsonPath('matched', false);
    asGuest($second);
    $second->forceFill(['status' => GuestStatus::Queued])->save();

    $this->getJson('/api/matchmaking/available')
        ->assertOk()
        ->assertJsonPath('participants.0.display_name', 'Guest')
        ->assertJsonMissingPath('participants.0.id');

    $this->postJson('/api/matchmaking/call', ['target_uuid' => $first->public_uuid])
        ->assertOk()
        ->assertJsonPath('matched', true);

    $this->assertDatabaseCount('call_rooms', 1);
    Event::assertNotDispatched(MatchFound::class);
});

it('rejects unauthorized signals and expired rooms', function () {
    $first = createGuest();
    $second = createGuest();
    $room = CallRoom::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'first_guest_session_id' => $first->id,
        'second_guest_session_id' => $second->id,
        'initiator_guest_session_id' => $first->id,
        'status' => RoomStatus::Expired,
        'started_at' => now(),
        'ended_at' => now(),
    ]);
    asGuest($first);

    $this->postJson('/api/signals', [
        'room_uuid' => $room->public_uuid,
        'sequence' => 1,
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ])->assertUnprocessable();

    asGuest(createGuest());
    $room->forceFill(['status' => RoomStatus::Active])->save();
    $this->postJson('/api/signals', [
        'room_uuid' => $room->public_uuid,
        'sequence' => 1,
        'type' => 'answer',
        'payload' => ['sdp' => 'v=0'],
    ])->assertUnprocessable();
});

it('validates signal payload shape and size', function () {
    asGuest(createGuest());

    $this->postJson('/api/signals', [
        'room_uuid' => 'not-a-uuid',
        'sequence' => 0,
        'type' => 'bad',
        'payload' => ['sdp' => str_repeat('x', 65536)],
    ])->assertUnprocessable()->assertJsonValidationErrors(['room_uuid', 'sequence', 'type', 'payload.sdp']);
});

it('accepts real sized webrtc sdp payloads for polling signals', function () {
    $first = createGuest();
    $second = createGuest();
    $room = CallRoom::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'first_guest_session_id' => $first->id,
        'second_guest_session_id' => $second->id,
        'initiator_guest_session_id' => $first->id,
        'status' => RoomStatus::Active,
        'started_at' => now(),
    ]);
    asGuest($first);

    $sdp = 'v=0'.PHP_EOL.implode(PHP_EOL, array_fill(0, 300, 'a=candidate:'.Str::random(80)));

    $this->postJson('/api/signals', [
        'room_uuid' => $room->public_uuid,
        'sequence' => 1,
        'type' => 'offer',
        'payload' => ['sdp' => $sdp],
    ])->assertOk();

    asGuest($second);
    $this->getJson('/api/signals?room_uuid='.$room->public_uuid.'&after=0')
        ->assertOk()
        ->assertJsonPath('signals.0.signal.type', 'offer')
        ->assertJsonPath('signals.0.signal.payload.sdp', $sdp);
});

it('returns an ended marker instead of not found for stale signal polling', function () {
    $first = createGuest();
    $second = createGuest();
    $room = CallRoom::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'first_guest_session_id' => $first->id,
        'second_guest_session_id' => $second->id,
        'initiator_guest_session_id' => $first->id,
        'status' => RoomStatus::Ended,
        'started_at' => now(),
        'ended_at' => now(),
    ]);
    asGuest($first);

    $this->getJson('/api/signals?room_uuid='.$room->public_uuid.'&after=0')
        ->assertSuccessful()
        ->assertJsonPath('room_ended', true)
        ->assertJsonPath('signals', []);
});

it('stores reports and blocks peers without media evidence', function () {
    $first = createGuest();
    $second = createGuest();
    $room = CallRoom::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'first_guest_session_id' => $first->id,
        'second_guest_session_id' => $second->id,
        'initiator_guest_session_id' => $first->id,
        'status' => RoomStatus::Active,
        'started_at' => now(),
    ]);
    asGuest($first);

    $this->postJson('/api/reports', [
        'room_uuid' => $room->public_uuid,
        'reason' => 'spam_or_scam',
        'description' => '<b>spam</b>',
    ])->assertOk();

    $this->postJson('/api/blocks', ['room_uuid' => $room->public_uuid])->assertOk();
    $this->assertDatabaseHas('reports', ['reason' => 'spam_or_scam', 'description' => 'spam']);
    $this->assertDatabaseHas('blocks', ['blocker_session_id' => $first->id, 'blocked_session_id' => $second->id]);
});

it('authorizes only own private guest channel', function () {
    putenv('REVERB_APP_KEY=test-key');
    putenv('REVERB_APP_SECRET=test-secret');
    $guest = createGuest();
    asGuest($guest);

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-guest.'.$guest->public_uuid,
    ])->assertOk()->assertJsonStructure(['auth']);

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-guest.'.Str::uuid(),
    ])->assertForbidden();
});

it('cleans stale sessions rooms queue reports and blocks', function () {
    $guest = createGuest(['status' => GuestStatus::Queued, 'last_seen_at' => now()->subMinutes(10), 'expires_at' => now()->subMinute()]);

    $this->artisan('videochat:cleanup')->assertSuccessful();

    $this->assertDatabaseHas('guest_sessions', ['id' => $guest->id, 'status' => GuestStatus::Expired->value]);
});
