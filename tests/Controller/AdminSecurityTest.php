<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Question;
use App\Entity\User;
use App\Repository\QuestionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminSecurityTest extends WebTestCase
{
    public function testUnauthenticatedUserCannotAccessAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        // Should redirect to login
        self::assertResponseRedirects('/admin/login');
    }

    public function testNonAdminRoleUserCannotAccessAdmin(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $candidate = $userRepo->findByIdentifier('+998901234567');
        if (!$candidate) {
            $candidate = new User();
            $candidate->setIdentifier('+998901234567');
            $candidate->setPhone('+998901234567');
            $candidate->setFullName('Test Candidate');
            $candidate->setRoles(['ROLE_USER']);
            $em->persist($candidate);
            $em->flush();
        }

        $client->loginUser($candidate);
        $client->request('GET', '/admin');

        // Access denied (403)
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanLoginAndAccessDashboard(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $admin = $userRepo->findByIdentifier('test_admin');
        if (!$admin) {
            $admin = new User();
            $admin->setIdentifier('test_admin');
            $admin->setFullName('Admin Tester');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($hasher->hashPassword($admin, 'StrongAdminPass123!'));
            $em->persist($admin);
            $em->flush();
        }

        $crawler = $client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Kirish ↵')->form([
            '_username' => 'test_admin',
            '_password' => 'StrongAdminPass123!',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/admin');

        $client->followRedirect();
        // Redirects to EasyAdmin exam CRUD
        self::assertResponseStatusCodeSame(302);
    }

    public function testQuestionPreviewRendersMarkdownSafely(): void
    {
        $client = static::createClient();
        $userRepo = static::getContainer()->get(UserRepository::class);
        $qRepo = static::getContainer()->get(QuestionRepository::class);

        $admin = $userRepo->findByIdentifier('test_admin');
        $client->loginUser($admin);

        $question = $qRepo->findOneBy(['questionNumber' => 21]);
        self::assertNotNull($question, 'Question 21 should exist from seed');

        $crawler = $client->request('GET', sprintf('/admin/question/%d/preview', $question->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Savol №21');
        self::assertSelectorTextContains('body', 'Python');
        self::assertSelectorTextContains('body', '4 ta');
    }
}
