<?php

namespace App\Services;

use App\Models\Visitor;

class VisitorService
{
    public function generateFingerprintHash(array $data): string
    {
        ksort($data);

        return md5(serialize($data));
    }

    public function getOrCreateFromFingerprint(array $fingerprint): Visitor
    {
        $fingerprintHash = $this->generateFingerprintHash($fingerprint);

        return Visitor::query()->firstOrCreate(
            ['fingerprint' => $fingerprintHash],
            ['language' => 'fr', 'in_europe' => false]
        );
    }
}
