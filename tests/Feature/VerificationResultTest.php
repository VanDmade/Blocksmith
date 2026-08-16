<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Tests\TestCase;
use VanDmade\Blocksmith\Verification\VerificationResult;

class VerificationResultTest extends TestCase
{

    public function test_passed_is_true_when_nothing_has_failed(): void
    {
        $result = new VerificationResult();
        $this->assertTrue($result->passed());
        $this->assertSame([], $result->getErrors());
    }

    public function test_fail_marks_the_check_failed_and_records_the_message(): void
    {
        $result = new VerificationResult();
        $result->fail('chain', 'Revision chain verification failed.');
        $this->assertTrue($result->checkFailed('chain'));
        $this->assertSame(['Revision chain verification failed.'], $result->getErrors());
        $this->assertFalse($result->passed());
    }

    public function test_check_failed_is_false_for_a_check_that_never_ran(): void
    {
        $result = new VerificationResult();
        $result->fail('chain', 'Revision chain verification failed.');
        $this->assertFalse($result->checkFailed('signature'));
    }

    public function test_multiple_failures_accumulate_independently(): void
    {
        $result = new VerificationResult();
        $result->fail('chain', 'Revision chain verification failed.');
        $result->fail('signature', 'Signature verification failed.');
        $this->assertTrue($result->checkFailed('chain'));
        $this->assertTrue($result->checkFailed('signature'));
        $this->assertFalse($result->checkFailed('merkle_root'));
        $this->assertCount(2, $result->getErrors());
        $this->assertFalse($result->passed());
    }

}
