<?php

declare(strict_types=1);

namespace App\DTO;

use App\Service\PhoneNormalizer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CandidateRegistrationDto
{
    #[Assert\NotBlank(message: 'Iltimos, to\'liq ism-familiyangizni kiriting.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Ism-familiya kamida 3 ta belgidan iborat bo\'lishi kerak.',
        maxMessage: 'Ism-familiya 100 ta belgidan oshmasligi kerak.'
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}\s'`ʻʼ-]+$/u",
        message: 'Ism-familiya faqat harflar, bo\'sh joy va apostrof belgilaridan iborat bo\'lishi kerak.'
    )]
    public ?string $fullName = null;

    #[Assert\NotBlank(message: 'Iltimos, telefon raqamingizni kiriting.')]
    public ?string $phone = null;

    #[Assert\IsTrue(message: 'Saralashda qatnashish uchun shaxsiy ma\'lumotlarni qayta ishlashga rozilik bildirish shart.')]
    public bool $consent = false;

    #[Assert\Callback]
    public function validatePhone(ExecutionContextInterface $context): void
    {
        if ($this->phone !== null && !PhoneNormalizer::isValid($this->phone)) {
            $context->buildViolation('Telefon raqami yaroqsiz. Masalan: +998901234567')
                ->atPath('phone')
                ->addViolation();
        }
    }
}
