<?php

namespace VanDmade\Blocksmith\Services;

use HashContext;
use InvalidArgumentException;
use RuntimeException;

class HasherService
{

    private ?HashContext $context = null;
    private string $hashAlgorithm;
    private int $chunkSize;

    public function __construct()
    {
        $this->hashAlgorithm = config('blocksmith.hash_algorithm', 'sha256');
        $this->chunkSize = config('blocksmith.chunk_size', 8192);
    }
    
    public function hashContent(mixed $content): string
    {
        $this->initialize();
        if (is_string($content)) {
            hash_update($this->context, $content);
        } elseif (is_resource($content)) {
            // Makes it easier on the system to read a larger file (Obviously... But wanted to write something for green text   )
            while (!feof($content)) {
                $chunk = fread($content, $this->chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException('Failed to read from resource.');
                }
                hash_update($this->context, $chunk);
            }
        } else {
            throw new InvalidArgumentException('Content must be a string or a resource.');
        }
        return $this->finalize();
    }

    public function computeHash(?string $previous, string $current): string
    {
        return hash($this->hashAlgorithm, ($previous ?? '') . $current);
    }

    private function initialize(): void
    {
        if (empty($this->context)) {
            $this->context = hash_init($this->hashAlgorithm);
        }
    }

    private function finalize(): ?string
    {
        if (!empty($this->context)) {
            $result = hash_final($this->context);
            // Cleans the context just incase it's ran again on the same instance of this class
            $this->context = null;
            return $result;
        }
        return null;
    }

}
