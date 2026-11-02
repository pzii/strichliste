<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Request payload for POST /api/article and POST /api/article/{articleId}.
 *
 * `amount` is the price in the configured currency's integer minor units and must be non-zero (a missing or
 * zero amount was a "parameter missing" error in the legacy contract).
 */
#[OA\Schema(required: ['name', 'amount'])]
final class WriteArticleDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name;

    public function __construct(
        string $name = '',
        #[Assert\NotEqualTo(0)]
        #[OA\Property(type: 'integer', description: 'Price in the configured currency\'s smallest unit. Must be non-zero — a missing or 0 amount is rejected (422).')]
        public int|float $amount = 0,
    ) {
        $this->name = trim($name);
    }

    #[Assert\Callback]
    public function validateIntegralAmount(ExecutionContextInterface $context): void
    {
        $amountAsFloat = (float) $this->amount;
        if (!is_finite($amountAsFloat) || floor($amountAsFloat) !== $amountAsFloat) {
            $context->buildViolation('money.integral_amount')
                ->atPath('amount')
                ->addViolation();

            return;
        }

        if (is_float($this->amount) && ($this->amount < PHP_INT_MIN || $this->amount >= PHP_INT_MAX + 1)) {
            $context->buildViolation('money.amount_out_of_range')
                ->atPath('amount')
                ->addViolation();
        }
    }
}
