<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use RuntimeException;
use VanDmade\Blocksmith\Services\MerkleTreeService;
use VanDmade\Blocksmith\Tests\TestCase;

class MerkleTreeServiceTest extends TestCase
{

    public function test_compute_root_is_deterministic_for_the_same_leaves(): void
    {
        $leaves = ['leaf-a', 'leaf-b', 'leaf-c', 'leaf-d'];
        $first = new MerkleTreeService();
        $first->build($leaves);
        $second = new MerkleTreeService();
        $second->build($leaves);
        $this->assertSame($first->computeRoot(), $second->computeRoot());
    }

    public function test_compute_root_differs_when_a_leaf_changes(): void
    {
        $original = new MerkleTreeService();
        $original->build(['leaf-a', 'leaf-b', 'leaf-c', 'leaf-d']);
        $tampered = new MerkleTreeService();
        $tampered->build(['leaf-a', 'leaf-b', 'leaf-c', 'leaf-tampered']);
        // TAmpering is bad and we will CATCH IT
        $this->assertNotSame($original->computeRoot(), $tampered->computeRoot());
    }

    public function test_generate_proof_and_verify_round_trips_for_every_leaf_with_an_even_count(): void
    {
        $leaves = ['leaf-a', 'leaf-b', 'leaf-c', 'leaf-d'];
        $tree = new MerkleTreeService();
        $tree->build($leaves);
        $root = $tree->computeRoot();
        foreach ($leaves as $index => $leaf) {
            $proof = $tree->generateProof($index);
            $this->assertTrue(
                $tree->verify($leaf, $proof, $root),
                'Leaf at index '.$index.' failed to verify against the root.'
            );
        }
    }

    public function test_generate_proof_and_verify_round_trips_with_an_odd_number_of_leaves(): void
    {
        $leaves = ['leaf-a', 'leaf-b', 'leaf-c', 'leaf-d', 'leaf-e'];
        $tree = new MerkleTreeService();
        $tree->build($leaves);
        $root = $tree->computeRoot();
        /* A   B C   D  E
         *  \ /   \ /   |
         *   AB    CD   EE
         *    \    /    |
         *     ABCD    EEEE
         *       \     /
         *      ABCDEEEEE
         */
        foreach ($leaves as $index => $leaf) {
            $proof = $tree->generateProof($index);
            $this->assertTrue(
                $tree->verify($leaf, $proof, $root),
                'Leaf at index '.$index.' failed to verify against the root.'
            );
        }
    }

    public function test_verify_fails_when_the_proof_has_been_tampered_with(): void
    {
        $leaves = ['leaf-a', 'leaf-b', 'leaf-c', 'leaf-d'];
        $tree = new MerkleTreeService();
        $tree->build($leaves);
        $root = $tree->computeRoot();
        $proof = $tree->generateProof(0);
        // Flips the first sibling hash in the proof - simulates an attacker rewriting
        // a neighboring proof entry to try to keep a forged leaf internally consistent.
        $proof[0][0] = str_repeat('f', strlen($proof[0][0]));
        // The order DOES matter so if the proof is altered it will fail
        $this->assertFalse($tree->verify('leaf-a', $proof, $root));
    }

    public function test_verify_fails_for_the_wrong_leaf(): void
    {
        $leaves = ['leaf-a', 'leaf-b', 'leaf-c', 'leaf-d'];
        $tree = new MerkleTreeService();
        $tree->build($leaves);
        $root = $tree->computeRoot();
        $proof = $tree->generateProof(0);
        $this->assertFalse($tree->verify('leaf-not-in-tree', $proof, $root));
    }

    public function test_generate_proof_throws_for_an_out_of_range_leaf_index(): void
    {
        $tree = new MerkleTreeService();
        $tree->build(['leaf-a', 'leaf-b']);
        $this->expectException(RuntimeException::class);
        // Proofs cannot be generated for indexes that don't exist
        $tree->generateProof(5);
    }

    public function test_compute_root_throws_when_the_tree_has_not_been_built(): void
    {
        $tree = new MerkleTreeService();
        $this->expectException(RuntimeException::class);
        // The compure root throws a runtime exception if the tree wasn't built yet
        $root = $tree->computeRoot();
    }

    public function test_generate_proof_and_verify_round_trips_for_a_single_leaf_tree(): void
    {
        $tree = new MerkleTreeService();
        $tree->build(['only-leaf']);
        $root = $tree->computeRoot();
        $proof = $tree->generateProof(0);
        $this->assertSame([], $proof);
        $this->assertTrue($tree->verify('only-leaf', $proof, $root));
    }

}
