# External Anchoring (OpenTimestamps)

Hash chaining and Merkle batching only prove things are INTERNALLY consistent. Someone with database admin credentials and full access who rewrites an entire chain, and recomputes every hash to match, leaves no trace... The chain still checks out against itself. External anchoring, Sherlock Holmes, is the one piece that actually removes trust in whoever's running the app, because it's backed by something they don't control: the Bitcoin blockchain.

## `AnchorProviderInterface`

```php
interface AnchorProviderInterface
{
    public function send(string $root): string;
    public function check(string $root): ?string;
    public function getProvider(): string;
}
```

You can create your own interface is OpenTimestamp is not your cup of tea! Check out the AnchorProviderInterface and just update the config file's `blocksmith.anchor_provider` to your new provider and make sure to implement the interface!

## `OpenTimestampsAnchorProvider`

The built-in one, talking to the free [OpenTimestamps](https://opentimestamps.org) calendar server.

- **`send($root)`** posts the raw digest to the calendar and gets back a hex string - that's a *pending* proof, not a confirmation yet.
- **`check($root)`** asks the calendar if it's confirmed. Returns `null` if there's no record, or if there is one but Bitcoin hasn't confirmed it yet. Only returns the real proof once it's actually confirmed.
- **`getProvider()`** just returns an identifier string for this provider.

Parsing the full binary proof format is out of scope on purpose - proofs get stored and passed around as opaque hex blobs. (Not really tamper proof so no real reason to continuously check them as the provider can be absolutely sure it's not tampered with)

## `CheckAnchorConfirmationJob`

The job that polls. It grabs every batch still sitting at `submitted`, checks each one against the calendar, and confirms any that come back positive. This is the cheap, local half of confirmation - it just flips a status column, it doesn't re-verify anything about the chain itself.

If a batch has been sitting at `submitted` too long (`blocksmith.anchoring.max_time_until_give_up`, 48 hours by default) and still hasn't confirmed, this job gives up on it and marks it `failed` instead of checking it forever and ever!

## The expensive check, and why it's not here

Actually re-fetching the real root from OpenTimestamps to catch *local* tampering on an already-confirmed batch - not just confirming a pending one - is a separate, more expensive check. See [Verification](06-verification.md#why-the-expensive-check-isnt-in-here) for why it doesn't run on every single `verify()` call. `VerifyAnchorBatchJob` checks each anchor on a schedule to verify that nothing has changed, if it did Blocksmith will let you know through an event dispatch. `blocksmith:verify-document` does the same live check on demand, since someone manually asking to verify a document is exactly the case where paying for it makes sense - see [Artisan Commands](08-artisan-commands.md).

## See also

- [Merkle Batching & Anchoring](02-merkle-batching.md)
- [Verification](06-verification.md)
