<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Services\HasherService;
use VanDmade\Blocksmith\Tests\TestCase;

class HasherServiceTest extends TestCase
{

    public function test_hash_content_matches_a_known_sha256_value(): void
    {
        // The hasher service uses a hash algorithm from the config so this just verifies that the config file is correct
        config(['blocksmith.hash_algorithm' => 'sha256']);
        $hasher = new HasherService();
        // Runs the same hash through out service
        $result = $hasher->hashContent('hello world');
        $this->assertSame(hash('sha256', 'hello world'), $result);
    }

    public function test_hash_content_produces_the_same_result_for_a_string_and_an_equivalent_stream(): void
    {
        config(['blocksmith.hash_algorithm' => 'sha256']);
        // Creates a large string to ensure the streaming actual works.
        $content = str_repeat('the quick brown fox jumps over the lazy dog ', 500);
        $hasher = new HasherService();
        $stringResult = $hasher->hashContent($content);
        // Creates the same contents as a resource to test
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $hasher = new HasherService();
        $streamResult = $hasher->hashContent($stream);
        fclose($stream);
        $this->assertSame($stringResult, $streamResult);
    }

    public function test_hash_content_can_be_called_more_than_once_on_the_same_instance(): void
    {
        config(['blocksmith.hash_algorithm' => 'sha256']);
        $hasher = new HasherService();
        $first = $hasher->hashContent('one');
        $second = $hasher->hashContent('two');
        $this->assertSame(hash('sha256', 'one'), $first);
        $this->assertSame(hash('sha256', 'two'), $second);
    }

    public function test_compute_hash_is_deterministic_for_the_same_inputs(): void
    {
        $hasher = new HasherService();
        $a = $hasher->computeHash('previous-hash', 'content-hash');
        $b = $hasher->computeHash('previous-hash', 'content-hash');
        $this->assertSame($a, $b);
    }

    public function test_compute_hash_differs_when_the_previous_hash_differs(): void
    {
        $hasher = new HasherService();
        $withPrevious = $hasher->computeHash('previous-hash', 'content-hash');
        $withoutPrevious = $hasher->computeHash(null, 'content-hash');
        $this->assertNotSame($withPrevious, $withoutPrevious);
    }

}
