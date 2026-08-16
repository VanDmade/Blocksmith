<?php

namespace VanDmade\Blocksmith\Services;

use RuntimeException;

class MerkleTreeService
{

    private array $tree = [];
    private string $hashAlgorithm;

    public function __construct()
    {
        $this->hashAlgorithm = config('blocksmith.hash_algorithm', 'sha256');
    }

    public function build(array $leaves): void
    {
        // Hashes the leaves to add a "domain" separation prefix to prevent accidental collisions with internal nodes
        $current = $this->tree[0] = array_map(fn($leaf) => $this->hashLeaf($leaf), $leaves);
        while (count($current) > 1) {
            $next = [];
            // Iterates through the current level of leaves and hashes pairs of leaves to create the next level
            for ($i = 0; $i < count($current); $i += 2) {
                $left  = $current[$i];
                $right = $current[$i + 1] ?? $left;
                $next[] = $this->hashPair($left, $right);
            }
            $this->tree[] = $next;
            $current = $next;
        }
    }

    public function computeRoot(): string
    {
        if (empty($this->tree)) {
            throw new RuntimeException('Merkle tree is empty. Please build the tree with leaves before computing the root.');
        }
        return $this->tree[count($this->tree) - 1][0];
    }

    public function generateProof(int $leafIndex): array
    {
        // Makes sure the leaf index is valid and within the range of the FIRST layer
        if ($leafIndex < 0 || $leafIndex >= count($this->tree[0] ?? [])) {
            throw new RuntimeException('Invalid leaf index. Please provide a valid index within the range of the leaves.');
        }
        $proof = [];
        $index = $leafIndex;
        $layers = count($this->tree);
        for ($level = 0; $level < $layers - 1; $level++) {
            $siblingIndex = ($index % 2 === 0) ? $index + 1 : $index - 1;
            if (isset($this->tree[$level][$siblingIndex])) {
                $side = ($siblingIndex > $index) ? 'right' : 'left';
                $proof[] = [$this->tree[$level][$siblingIndex], $side];
            } else {
                // No sibling for this only child
                $proof[] = [$this->tree[$level][$index], 'right'];
            }
            $index = intdiv($index, 2);
        }
        // Empty proof just means there is a single leaf (Just an FYI)
        return $proof;
    }

    public function verify(string $leaf, array $proof, string $claimedRoot): bool
    {
        $computedHash = $this->hashLeaf($leaf);
        foreach ($proof as [$siblingHash, $side]) {
            if ($side === 'right') {
                $computedHash = $this->hashPair($computedHash, $siblingHash);
            } else {
                $computedHash = $this->hashPair($siblingHash, $computedHash);
            }
        }
        // Compares the computed hash with the claimed root to verify if they match
        return $computedHash === $claimedRoot;
    }

    private function hashLeaf(string $leaf): string
    {
        // Hashses the leaf to be able to distinguish between leaves and hashed hashes
        return hash($this->hashAlgorithm, "\x00".$leaf);
    }

    private function hashPair(string $left, string $right): string
    {
        // Hashed hashes!
        return hash($this->hashAlgorithm, "\x01".$left.$right);
    }

}
