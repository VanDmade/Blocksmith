<?php

namespace VanDmade\Blocksmith\Verification;

class VerificationResult
{

    private array $errors = [];
    private array $failedChecks = [];

    public function fail(string $check, string $message): void
    {
        $this->failedChecks[$check] = true;
        $this->errors[] = $message;
    }

    public function checkFailed(string $check): bool
    {
        return $this->failedChecks[$check] ?? false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function passed(): bool
    {
        return empty($this->errors);
    }

}
