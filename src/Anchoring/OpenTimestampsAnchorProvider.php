<?php

namespace VanDmade\Blocksmith\Anchoring;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class OpenTimestampsAnchorProvider implements AnchorProviderInterface
{

    private const CALENDAR_URL = 'https://alice.btc.calendar.opentimestamps.org';
    private const DIGEST_URL = 'digest';
    private const POLLING_URL = 'timestamp/%s';
    private const ACCEPT_HEADER = 'application/vnd.opentimestamps.v1';
    // Had to Google this one as this was not something I was used to!
    private const BITCOIN_ATTESTATION_TAG = '0588960d73d71901';

    public function send(string $root): string
    {
        if (empty($root)) {
            throw new InvalidArgumentException('Merkle root cannot be empty.');
        }
        $url = self::CALENDAR_URL.'/'.self::DIGEST_URL;
        $response = Http::withHeaders(['Accept' => self::ACCEPT_HEADER])
            ->withBody(hex2bin($root), 'application/octet-stream')
            ->post($url);
        if (!$response->successful()) {
            throw new InvalidArgumentException('Failed to send Merkle root to OpenTimestamps calendar. Response: '.$response->body());
        }
        return bin2hex($response->body());
    }

    public function check(string $root): ?string
    {
        if (empty($root)) {
            throw new InvalidArgumentException('Merkle root cannot be empty.');
        }
        $url = self::CALENDAR_URL.'/'.sprintf(self::POLLING_URL, $root);
        $response = Http::withHeaders(['Accept' => self::ACCEPT_HEADER])
            ->get($url);
        if (!$response->successful()) {
            // No record of this digest at all... Time to stay it failed!
            return null;
        }
        $proof = bin2hex($response->body());
        // The ATTESTATION_TAG is a known magic number from OpenTimestamps that says "Woohoo it's confirmed"
        if (!str_contains($proof, self::BITCOIN_ATTESTATION_TAG)) {
            return null;
        }
        return $proof;
    }

    public function getProvider(): string
    {
        return 'blocksmith_opentimestamps';
    }

}
