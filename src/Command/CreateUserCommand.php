<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée un compte utilisateur, administrateur par défaut, en demandant le mot de passe de façon masquée.
 */
#[AsCommand(name: 'app:user:create', description: 'Crée un utilisateur (administrateur par défaut).')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail de connexion')
            ->addOption('no-admin', null, InputOption::VALUE_NONE, 'Crée un simple utilisateur, sans ROLE_ADMIN')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if ($this->userRepository->findOneBy(['email' => mb_strtolower(trim($email))])) {
            $io->error(sprintf('Un compte existe déjà avec l\'e-mail « %s ».', $email));

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles($input->getOption('no-admin') ? [] : [User::ROLE_ADMIN])
        ;

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error((string) $violation->getMessage());
            }

            return Command::FAILURE;
        }

        $password = $io->askHidden('Mot de passe (12 caractères minimum)', static function (?string $value): string {
            if (null === $value || mb_strlen($value) < 12) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins 12 caractères.');
            }

            return $value;
        });

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur « %s » créé%s.', $user->getEmail(), $input->getOption('no-admin') ? '' : ' avec ROLE_ADMIN'));

        return Command::SUCCESS;
    }
}
