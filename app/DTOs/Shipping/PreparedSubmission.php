<?php

namespace App\DTOs\Shipping;

final readonly class PreparedSubmission
{
    public function __construct(
        public int $providerSubmissionId,
        public Request $providerRequest,
    ) {}
}
