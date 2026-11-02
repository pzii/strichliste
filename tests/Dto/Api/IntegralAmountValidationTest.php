<?php

namespace App\Tests\Dto\Api;

use App\Dto\Api\CreateTransactionDto;
use App\Dto\Api\WriteArticleDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

class IntegralAmountValidationTest extends TestCase
{
    public function testFractionalJsonAmountsAreRejected(): void
    {
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $transaction = $serializer->deserialize('{"amount":10.5}', CreateTransactionDto::class, 'json');
        $article = $serializer->deserialize('{"name":"Mate","amount":10.5}', WriteArticleDto::class, 'json');

        self::assertSame(10.5, $transaction->amount);
        self::assertSame(10.5, $article->amount);
        self::assertSame('money.integral_amount', $validator->validate($transaction)[0]->getMessageTemplate());
        self::assertSame('money.integral_amount', $validator->validate($article)[0]->getMessageTemplate());
    }

    public function testFractionalFormEncodedAmountsAreRejected(): void
    {
        $normalizer = new ObjectNormalizer();
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $transaction = $normalizer->denormalize(['amount' => '10.5'], CreateTransactionDto::class);

        self::assertSame(10.5, $transaction->amount);
        self::assertSame('money.integral_amount', $validator->validate($transaction)[0]->getMessageTemplate());
    }

    public function testWholeJsonAmountsAreAccepted(): void
    {
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $transaction = $serializer->deserialize('{"amount":10}', CreateTransactionDto::class, 'json');
        $wholeDecimal = $serializer->deserialize('{"amount":10.0}', CreateTransactionDto::class, 'json');
        $article = $serializer->deserialize('{"name":"Mate","amount":10}', WriteArticleDto::class, 'json');

        self::assertCount(0, $validator->validate($transaction));
        self::assertCount(0, $validator->validate($wholeDecimal));
        self::assertCount(0, $validator->validate($article));
    }

    public function testAmountsOutsideIntegerRangeAreRejected(): void
    {
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $transaction = $serializer->deserialize('{"amount":1e100}', CreateTransactionDto::class, 'json');
        $article = $serializer->deserialize('{"name":"Mate","amount":1e100}', WriteArticleDto::class, 'json');

        self::assertSame('money.amount_out_of_range', $validator->validate($transaction)[0]->getMessageTemplate());
        self::assertSame('money.amount_out_of_range', $validator->validate($article)[0]->getMessageTemplate());
    }
}
