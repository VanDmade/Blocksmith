<?php

use Illuminate\Support\Facades\Route;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Services\MerkleTreeService;

Route::get('/blocksmith/test/merkle', function () {
    $leaves = explode(',', 'a,b,c,d,e');
    $tree = new MerkleTreeService();
    $tree->build($leaves);
    $proof = $tree->generateProof(2);
    return response()->json([
        $tree->verify('c', $proof, $tree->computeRoot())
    ]);
});

Route::get('/blocksmith/test/anchor/send', function (AnchorProviderInterface $provider) {
    // A random-looking 32-byte hex "root" for testing - real usage would be an actual Merkle root.
    $root = hash('sha256', 'test-root-'.now()->timestamp);

    $pendingProof = $provider->send($root);

    return response()->json([
        'provider' => $provider->getProvider(),
        'root' => $root,
        'pending_proof' => $pendingProof,
        'pending_proof_bytes' => strlen($pendingProof) / 2,
        'submitted_at' => now()->toDateTimeString(),
        'status' => 'submitted (pending)',
        'note' => 'Use /blocksmith/test/anchor/check?root='.$root.' to poll it. Real Bitcoin confirmation takes roughly an hour or more.',
    ]);
});

Route::get('/blocksmith/test/anchor/check', function (AnchorProviderInterface $provider) {
    $root = request('root');
    if (! $root) {
        return response()->json(['error' => 'Pass ?root=... from a previous /blocksmith/test/anchor/send call.'], 400);
    }

    $result = $provider->check($root);

    return response()->json([
        'provider' => $provider->getProvider(),
        'root' => $root,
        'checked_at' => now()->toDateTimeString(),
        'status' => $result ? 'confirmed' : 'still pending (or unknown digest)',
        'proof' => $result,
        'proof_bytes' => $result ? strlen($result) / 2 : null,
    ]);
});
