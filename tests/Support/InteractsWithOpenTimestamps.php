<?php

namespace VanDmade\Blocksmith\Tests\Support;

trait InteractsWithOpenTimestamps
{

    /**
     * A fake calendar response body whose hex encoding contains the real
     * BITCOIN_ATTESTATION_TAG bytes OpenTimestampsAnchorProvider::check() looks
     * for, so it reads back as "confirmed".
     */
    protected function confirmedCalendarResponseBody(): string
    {
        return hex2bin('deadbeef').hex2bin('0588960d73d71901').hex2bin('cafebabe');
    }

    /**
     * A fake calendar response body with no attestation tag, so it reads back
     * as "still pending".
     */
    protected function pendingCalendarResponseBody(): string
    {
        return hex2bin('deadbeefcafebabe');
    }

}
