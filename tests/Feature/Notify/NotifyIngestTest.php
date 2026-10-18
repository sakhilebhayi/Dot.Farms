<?php

namespace Tests\Feature\Notify;

use App\Jobs\SendNotifyEvent;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farm;
use App\Models\Field;
use App\Models\HarvestRecord;
use App\Models\Team;
use App\Models\User;
use App\Notifications\HarvestRecordedNotification;
use App\Services\Notify\NotifyClient;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/**
 * Dot.Farms tells Dot.Notify about the harvests it already announces in-app.
 * Nothing here may slow, break or change recording a harvest or the in-app
 * notification, so most of these tests prove what does NOT happen.
 */
class NotifyIngestTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://notify.test/api/ingest/events';

    private const SECRET = 'notify-ingest-secret-for-farms-tests-0001';

    private const TEAM_UUID = '11111111-1111-4111-8111-111111111111';

    private const OTHER_UUID = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        Cache::flush();
        config([
            'notify' => ['url' => self::URL, 'secret' => self::SECRET, 'platform' => 'farms', 'timeout' => 5,
                'circuit' => ['threshold' => 5, 'pause_seconds' => 60]],
        ]);
        url()->forceRootUrl('https://farms.test');
        $this->fakeNotify(Http::response(['id' => 1, 'status' => 'routed'], 202));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Replace the fake (a second Http::fake() would sit behind the first). */
    private function fakeNotify(mixed $stub = null): void
    {
        Http::swap(new HttpFactory);
        Http::fake($stub === null ? null : ['*' => $stub]);
    }

    /** @return array{owner: User, member: User, team: Team} */
    private function linkedTeam(?string $uuid = self::TEAM_UUID): array
    {
        $owner = User::factory()->withPersonalTeam()->create(['name' => 'Farm Owner']);
        $team = $owner->currentTeam;
        $team->forceFill(['infodot_team_uuid' => $uuid ?? (string) Str::uuid()])->save();
        $member = User::factory()->create(['name' => 'Farm Hand']);
        $team->users()->attach($member, ['role' => 'admin']);

        return ['owner' => $owner->fresh(), 'member' => $member, 'team' => $team->fresh()];
    }

    private function harvest(int $teamId, array $extra = []): HarvestRecord
    {
        $farm = Farm::factory()->create(['team_id' => $teamId, 'name' => 'Karoo Kloof Farm']);
        $field = Field::factory()->create(['farm_id' => $farm->id, 'name' => 'Lower Orchard']);
        $crop = Crop::factory()->create(['team_id' => $teamId, 'name' => 'Navel Oranges']);
        $cycle = CropCycle::factory()->create(['field_id' => $field->id, 'crop_id' => $crop->id]);

        return HarvestRecord::factory()->create($extra + [
            'crop_cycle_id' => $cycle->id, 'quantity_harvested' => 1234.5, 'unit' => 'kg',
            'harvested_at' => '2026-11-04 07:30:00',
        ]);
    }

    /** @return list<array{request: Request, json: array<string, mixed>}> */
    private function sent(?string $event = null): array
    {
        $out = [];
        foreach (Http::recorded() as [$request]) {
            $json = json_decode($request->body(), true);
            if ($event === null || $json['event'] === $event) {
                $out[] = ['request' => $request, 'json' => $json];
            }
        }

        return $out;
    }

    // ---- the signed request --------------------------------------------------------------

    public function test_recording_a_harvest_posts_a_signed_event_to_each_team_member(): void
    {
        ['owner' => $owner, 'member' => $member, 'team' => $team] = $this->linkedTeam();

        $record = $this->harvest($team->id);

        $events = $this->sent('harvest.recorded');
        $this->assertCount(2, $events);
        $this->assertEqualsCanonicalizing([$owner->email, $member->email], array_map(fn ($e) => $e['json']['recipient']['email'], $events));

        $event = collect($events)->first(fn ($e) => $e['json']['recipient']['email'] === $owner->email);
        $request = $event['request'];
        $json = $event['json'];
        $body = $request->body();
        $timestamp = $request->header('X-InfoDot-Timestamp')[0] ?? '';

        $this->assertSame(self::URL, $request->url());
        $this->assertSame('POST', $request->method());
        $this->assertSame('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET), $request->header('X-InfoDot-Signature')[0] ?? '');
        $this->assertLessThanOrEqual(5, abs((int) $timestamp - now()->timestamp));
        $this->assertSame(['platform', 'company_uuid', 'event', 'recipient', 'data', 'idempotency_key'], array_keys($json));
        $this->assertSame('farms', $json['platform']);
        $this->assertSame(self::TEAM_UUID, $json['company_uuid']);
        $this->assertSame(['email' => $owner->email], $json['recipient']);
        $this->assertSame(['farm_name', 'field_name', 'crop', 'quantity', 'unit', 'harvested_at', 'url'], array_keys($json['data']));
        $this->assertSame('Karoo Kloof Farm', $json['data']['farm_name']);
        $this->assertSame('Lower Orchard', $json['data']['field_name']);
        $this->assertSame('Navel Oranges', $json['data']['crop']);
        $this->assertSame('1234.50', $json['data']['quantity']);
        $this->assertSame('kg', $json['data']['unit']);
        $this->assertSame($record->fresh()->harvested_at->toIso8601String(), $json['data']['harvested_at']);
        $this->assertSame('https://farms.test/harvests/'.$record->id, $json['data']['url']);
        $this->assertSame('harvest.recorded:'.$record->id.':'.sha1($owner->email).':recorded', $json['idempotency_key']);
        $this->assertStringNotContainsString($member->email, $body, 'one person per event; nobody sees the other recipients');
        $this->assertStringNotContainsString(self::SECRET, $body);
    }

    public function test_the_email_recipients_are_exactly_the_in_app_recipients(): void
    {
        ['owner' => $owner, 'member' => $member, 'team' => $team] = $this->linkedTeam();
        $stranger = User::factory()->withPersonalTeam()->create();

        $this->harvest($team->id);

        $inApp = DB::table('notifications')->pluck('notifiable_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame([$owner->id, $member->id], $inApp);
        $emailed = collect($this->sent('harvest.recorded'))->map(fn ($e) => $e['json']['recipient']['email'])->sort()->values()->all();
        $this->assertSame(collect([$owner->email, $member->email])->sort()->values()->all(), $emailed);
        $this->assertNotContains($stranger->email, $emailed);
    }

    public function test_the_company_is_the_harvests_team_not_each_recipients_current_team(): void
    {
        ['owner' => $owner, 'member' => $member, 'team' => $team] = $this->linkedTeam();
        $elsewhere = $this->linkedTeam(self::OTHER_UUID);
        $member->forceFill(['current_team_id' => $elsewhere['team']->id])->save();
        $elsewhere['team']->users()->attach($member, ['role' => 'admin']);

        $this->harvest($team->id);

        $uuids = array_unique(array_map(fn ($e) => $e['json']['company_uuid'], $this->sent('harvest.recorded')));
        $this->assertSame([self::TEAM_UUID], $uuids, 'a farm harvest is never announced on behalf of another company');
    }

    public function test_the_request_uses_a_five_second_timeout(): void
    {
        $seen = null;
        $this->fakeNotify(function (Request $request, array $options) use (&$seen) {
            $seen = $options;

            return Http::response([], 202);
        });

        $this->harvest($this->linkedTeam()['team']->id);

        $this->assertSame(5, (int) ($seen['timeout'] ?? 0));
        $this->assertSame(5, (int) ($seen['connect_timeout'] ?? 0));
    }

    public function test_recording_a_harvest_through_the_controller_also_sends_it(): void
    {
        ['owner' => $owner, 'team' => $team] = $this->linkedTeam();
        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cycle = CropCycle::factory()->create(['field_id' => $field->id, 'crop_id' => $crop->id]);

        $this->actingAs($owner)->post(route('crop-cycles.harvest.store', [$farm, $field, $cycle]), [
            'harvested_at' => now()->toDateString(), 'quantity_harvested' => 500, 'unit' => 'kg',
        ])->assertRedirect();

        $this->assertCount(2, $this->sent('harvest.recorded'));
        $this->assertSame('500.00', $this->sent('harvest.recorded')[0]['json']['data']['quantity']);
    }

    // ---- nothing is sent unless it can be routed ------------------------------------------

    public function test_every_url_is_https_on_the_app_host_even_when_the_app_url_is_http(): void
    {
        url()->forceRootUrl('http://farms.test');

        $this->harvest($this->linkedTeam()['team']->id);

        $url = $this->sent('harvest.recorded')[0]['json']['data']['url'];
        $this->assertStringStartsWith('https://farms.test/harvests/', $url);
        $this->assertSame('farms.test', parse_url($url, PHP_URL_HOST));
    }

    public function test_it_is_disabled_when_the_url_or_the_secret_is_blank(): void
    {
        foreach ([['url' => ''], ['secret' => ''], ['url' => null, 'secret' => null]] as $blank) {
            config(['notify' => array_merge(config('notify'), $blank)]);
            $this->assertFalse(app(NotifyClient::class)->enabled());
            $this->harvest($this->linkedTeam(null)['team']->id);
        }

        Http::assertNothingSent();
        Queue::fake();
        $this->harvest($this->linkedTeam(null)['team']->id);
        Queue::assertNotPushed(SendNotifyEvent::class);
    }

    public function test_a_team_without_an_infodot_company_uuid_is_skipped_and_the_in_app_notice_still_goes(): void
    {
        $owner = User::factory()->withPersonalTeam()->create(); // never linked to InfoDot

        $this->harvest($owner->currentTeam->id);

        Http::assertNothingSent();
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_a_recipient_without_an_email_is_skipped(): void
    {
        ['owner' => $owner, 'team' => $team] = $this->linkedTeam();
        $record = $this->harvest($team->id);
        Http::swap(new HttpFactory);
        Http::fake();
        $owner->email = '';

        $owner->notify(new HarvestRecordedNotification($record));

        Http::assertNothingSent();
    }

    // ---- Notify can never hurt the user -------------------------------------------------

    public function test_a_failing_notify_leaves_the_harvest_and_the_in_app_notifications_untouched(): void
    {
        foreach ([
            fn () => Http::response('boom', 500),
            fn () => Http::response('slow down', 429),
            fn () => Http::response('nope', 401),
            fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
            fn () => throw new \RuntimeException('something unexpected'),
        ] as $failure) {
            Cache::flush();
            $calls = 0;
            $this->fakeNotify(function () use ($failure, &$calls) {
                $calls++;

                return $failure();
            });
            ['owner' => $owner, 'member' => $member, 'team' => $team] = $this->linkedTeam(null);

            $record = $this->harvest($team->id);

            $this->assertNotNull(HarvestRecord::find($record->id));
            $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
            $this->assertSame(1, $member->fresh()->unreadNotifications()->count());
            $this->assertGreaterThanOrEqual(1, $calls, 'Notify was attempted and its failure stayed contained');
        }
    }

    public function test_a_failing_notify_does_not_break_the_controller_request(): void
    {
        $this->fakeNotify(Http::response('boom', 503));
        ['owner' => $owner, 'team' => $team] = $this->linkedTeam();
        $farm = Farm::factory()->create(['team_id' => $team->id]);
        $field = Field::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['team_id' => $team->id]);
        $cycle = CropCycle::factory()->create(['field_id' => $field->id, 'crop_id' => $crop->id]);

        $this->actingAs($owner)->post(route('crop-cycles.harvest.store', [$farm, $field, $cycle]), [
            'harvested_at' => now()->toDateString(), 'quantity_harvested' => 500, 'unit' => 'kg',
        ])->assertRedirect();

        $this->assertSame(1, HarvestRecord::count());
        $this->assertSame(2, DB::table('notifications')->count());
    }

    public function test_a_failure_to_even_queue_the_event_never_reaches_the_caller(): void
    {
        ['owner' => $owner, 'team' => $team] = $this->linkedTeam();
        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldIgnoreMissing();
            $mock->shouldReceive('dispatch')->andThrow(new \RuntimeException('queue backend down'));
        });

        $record = $this->harvest($team->id);

        $this->assertNotNull(HarvestRecord::find($record->id));
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_a_transient_failure_asks_the_queue_to_retry_and_a_permanent_one_does_not(): void
    {
        $payload = $this->payload();
        $client = app(NotifyClient::class);
        $this->fakeNotify(Http::sequence()->push('x', 503)->push('x', 429)->push('x', 422)->push([], 200)->pushFailedConnection());

        $this->assertSame(NotifyClient::RETRY, $client->deliver($payload));
        $this->assertSame(NotifyClient::RETRY, $client->deliver($payload));
        $this->assertSame(NotifyClient::GIVE_UP, $client->deliver($payload), '422: retrying cannot fix a bad payload');
        $this->assertSame(NotifyClient::DONE, $client->deliver($payload));
        $this->assertSame(NotifyClient::RETRY, $client->deliver($payload), 'connection failure');
    }

    public function test_the_job_retries_three_times_with_backoff_and_never_throws(): void
    {
        $job = new SendNotifyEvent($this->payload());

        $this->assertSame(3, $job->tries);
        $this->assertTrue($job->afterCommit, 'dispatched only once the surrounding transaction has committed');
        $this->assertCount(2, $job->backoff());
        $this->assertSame($job->backoff(), array_values(array_unique($job->backoff())));

        $this->fakeNotify(fn () => throw new \RuntimeException('unexpected'));
        $job->handle(app(NotifyClient::class));
        $this->assertTrue(true, 'no exception escaped');
    }

    public function test_a_retry_is_signed_afresh_with_a_new_timestamp(): void
    {
        $payload = $this->payload();

        app(NotifyClient::class)->deliver($payload);
        Carbon::setTestNow(now()->addMinutes(2));
        app(NotifyClient::class)->deliver($payload);

        $stamps = array_map(fn ($e) => $e['request']->header('X-InfoDot-Timestamp')[0], $this->sent());
        $this->assertCount(2, array_unique($stamps));
    }

    public function test_the_idempotency_key_is_stable_across_retries_and_double_dispatch(): void
    {
        ['owner' => $owner, 'team' => $team] = $this->linkedTeam();
        $record = $this->harvest($team->id);
        $this->fakeNotify(Http::response([], 202));

        // The same notification built again (a double dispatch) yields the same key.
        $owner->notify(new HarvestRecordedNotification($record->fresh()));
        Carbon::setTestNow(now()->addHour());
        $owner->notify(new HarvestRecordedNotification($record->fresh()));

        $keys = array_map(fn ($e) => $e['json']['idempotency_key'], $this->sent('harvest.recorded'));
        $this->assertCount(2, $keys);
        $this->assertCount(1, array_unique($keys));
    }

    // ---- circuit breaker ------------------------------------------------------------------

    public function test_five_consecutive_failures_pause_calls_for_sixty_seconds_then_it_recovers(): void
    {
        $client = app(NotifyClient::class);
        $payload = $this->payload();
        $this->fakeNotify(Http::response('boom', 500));

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(NotifyClient::RETRY, $client->deliver($payload));
        }
        Http::assertSentCount(5);

        $this->assertSame(NotifyClient::PAUSED, $client->deliver($payload));
        $this->assertSame(NotifyClient::PAUSED, $client->deliver($payload));
        Http::assertSentCount(5);

        Carbon::setTestNow(now()->addSeconds(59));
        $this->assertSame(NotifyClient::PAUSED, $client->deliver($payload));
        Http::assertSentCount(5);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->fakeNotify(Http::response([], 202));
        $this->assertSame(NotifyClient::DONE, $client->deliver($payload), 'the probe after the pause is allowed through');
        $this->assertSame(NotifyClient::DONE, $client->deliver($payload));
        Http::assertSentCount(2);
    }

    public function test_a_success_resets_the_failure_count(): void
    {
        $client = app(NotifyClient::class);
        $payload = $this->payload();

        $this->fakeNotify(Http::response('boom', 500));
        for ($i = 0; $i < 4; $i++) {
            $client->deliver($payload);
        }
        $this->fakeNotify(Http::response([], 202));
        $client->deliver($payload);
        $this->fakeNotify(Http::response('boom', 500));
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(NotifyClient::RETRY, $client->deliver($payload), 'four more failures are still below the threshold');
        }
    }

    public function test_a_refusal_does_not_count_towards_the_breaker(): void
    {
        $client = app(NotifyClient::class);
        $payload = $this->payload();
        $this->fakeNotify(Http::response('no', 422));

        for ($i = 0; $i < 8; $i++) {
            $this->assertSame(NotifyClient::GIVE_UP, $client->deliver($payload));
        }
        Http::assertSentCount(8);
    }

    public function test_a_paused_job_is_released_without_calling_notify(): void
    {
        Cache::put('notify.circuit.open_until', now()->timestamp + 40, 60);
        $delay = null;
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->andReturnUsing(function ($seconds) use (&$delay) {
            $delay = $seconds;
        });
        $job = new SendNotifyEvent($this->payload());
        $job->setJob($queueJob);

        $job->handle(app(NotifyClient::class));

        Http::assertNothingSent();
        $this->assertGreaterThanOrEqual(40, $delay);
    }

    // ---- after commit ---------------------------------------------------------------------

    public function test_nothing_is_queued_for_a_harvest_that_rolls_back(): void
    {
        ['team' => $team] = $this->linkedTeam();
        Queue::fake();
        $this->fakeNotify();

        try {
            DB::transaction(function () use ($team) {
                $this->harvest($team->id);
                throw new \RuntimeException('a later step failed');
            });
        } catch (\RuntimeException) {
        }

        Queue::assertNotPushed(SendNotifyEvent::class);
        Http::assertNothingSent();
        $this->assertSame(0, HarvestRecord::count());
    }

    public function test_events_are_queued_after_the_commit_and_not_sent_in_the_request(): void
    {
        ['team' => $team] = $this->linkedTeam();
        Queue::fake();
        $this->fakeNotify();

        DB::transaction(function () use ($team) {
            $this->harvest($team->id);
            Queue::assertNotPushed(SendNotifyEvent::class);
        });

        Queue::assertPushed(SendNotifyEvent::class, 2);
        Queue::assertPushed(SendNotifyEvent::class, fn (SendNotifyEvent $job) => $job->payload['event'] === 'harvest.recorded');
        Http::assertNothingSent();
    }

    // ---- privacy of logs ------------------------------------------------------------------

    public function test_failures_are_logged_without_emails_or_secrets(): void
    {
        config([
            'logging.default' => 'notifytest',
            'logging.channels.notifytest' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'level' => 'debug'],
        ]);
        Log::forgetChannel('notifytest');
        $this->fakeNotify(Http::response('{"error":"a@b.test leaked"}', 500));
        ['owner' => $owner, 'member' => $member, 'team' => $team] = $this->linkedTeam();

        $this->harvest($team->id);

        $handler = Log::channel('notifytest')->getLogger()->getHandlers()[0];
        $logged = json_encode(array_map(fn ($r) => $r->message.json_encode($r->context), $handler->getRecords()));
        $this->assertNotSame('[]', $logged, 'the failure is logged');
        foreach ([$owner->email, $member->email, self::SECRET, 'leaked'] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'platform' => 'farms', 'company_uuid' => self::TEAM_UUID, 'event' => 'harvest.recorded',
            'recipient' => ['email' => 'someone@example.test'], 'data' => ['crop' => 'X'],
            'idempotency_key' => 'harvest.recorded:1:abc:recorded',
        ];
    }
}
