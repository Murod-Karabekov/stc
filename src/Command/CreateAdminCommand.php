<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates or resets an administrator account securely without committing passwords.'
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Administrator username / identifier')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Administrator password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $username = $input->getOption('username');
        if (!$username) {
            $question = new Question('Enter administrator username (e.g. admin): ', 'admin');
            $username = $helper->ask($input, $output, $question);
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Enter administrator password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        if (empty($password) || strlen((string) $password) < 8) {
            $io->error('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        $user = $this->userRepository->findByIdentifier($username);
        $isNew = false;

        if (!$user) {
            $user = new User();
            $user->setIdentifier($username);
            $user->setFullName('System Administrator');
            $isNew = true;
        }

        $user->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, (string) $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($isNew) {
            $io->success(sprintf('Administrator "%s" created successfully with ROLE_ADMIN.', $username));
        } else {
            $io->success(sprintf('Password and ROLE_ADMIN reset successfully for administrator "%s".', $username));
        }

        return Command::SUCCESS;
    }
}
