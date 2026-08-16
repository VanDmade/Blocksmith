<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use VanDmade\Blocksmith\Anchoring\OpenTimestampsAnchorProvider;
use VanDmade\Blocksmith\Tests\Support\InteractsWithOpenTimestamps;
use VanDmade\Blocksmith\Tests\TestCase;

class OpenTimestampsAnchorProviderTest extends TestCase
{

    use InteractsWithOpenTimestamps;

    const OPEN_TIMESTAMPS_CALENDAR_URL = 'alice.btc.calendar.opentimestamps.org';

    public function test_send_posts_the_raw_root_bytes_and_returns_the_hex_encoded_response(): void
    {
        Http::fake([self::OPEN_TIMESTAMPS_CALENDAR_URL.'/digest' => Http::response('a-pending-proof')]);
        $provider = new OpenTimestampsAnchorProvider();
        $root = hash('sha256', 'a-merkle-root');
        $result = $provider->send($root);
        $this->assertSame(bin2hex('a-pending-proof'), $result);
        Http::assertSent(function ($request) use ($root) {
            return $request->url() === 'https://'.self::OPEN_TIMESTAMPS_CALENDAR_URL.'/digest'
                && $request->body() === hex2bin($root);
        });
    }

    public function test_send_throws_for_an_empty_root(): void
    {
        $provider = new OpenTimestampsAnchorProvider();
        $this->expectException(InvalidArgumentException::class);
        $provider->send('');
    }

    public function test_check_returns_null_when_the_calendar_has_no_record_of_the_digest(): void
    {
        Http::fake([
            self::OPEN_TIMESTAMPS_CALENDAR_URL.'/*' => Http::response('', 404),
        ]);
        $provider = new OpenTimestampsAnchorProvider();
        $this->assertNull($provider->check(hash('sha256', 'unknown-root')));
    }

    public function test_check_returns_null_when_the_digest_is_still_pending(): void
    {
        Http::fake([
            self::OPEN_TIMESTAMPS_CALENDAR_URL.'/*' => Http::response($this->pendingCalendarResponseBody()),
        ]);
        $provider = new OpenTimestampsAnchorProvider();
        $this->assertNull($provider->check(hash('sha256', 'pending-root')));
    }

    public function test_check_returns_the_hex_proof_when_the_digest_is_confirmed(): void
    {
        Http::fake([
            self::OPEN_TIMESTAMPS_CALENDAR_URL.'/*' => Http::response($this->confirmedCalendarResponseBody()),
        ]);
        $provider = new OpenTimestampsAnchorProvider();
        $result = $provider->check(hash('sha256', 'confirmed-root'));
        $this->assertNotNull($result);
        $this->assertSame(bin2hex($this->confirmedCalendarResponseBody()), $result);
    }

    public function test_check_throws_for_an_empty_root(): void
    {
        $provider = new OpenTimestampsAnchorProvider();
        $this->expectException(InvalidArgumentException::class);
        $provider->check('');
    }

    public function test_get_provider_returns_the_namespaced_identifier(): void
    {
        $provider = new OpenTimestampsAnchorProvider();
        // The method is used to allow the user to have multiple providers and allow the system to know
        $this->assertSame('blocksmith_opentimestamps', $provider->getProvider());
    }

}
