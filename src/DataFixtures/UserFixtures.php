<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée les comptes de démonstration (environnement de développement uniquement).
 */
class UserFixtures extends Fixture
{
    /**
     * Identifiants de démonstration : à ne jamais réutiliser en production.
     */
    public const ADMIN_EMAIL = 'admin@sf74.test';
    public const ADMIN_PASSWORD = 'admin-demo-sf74';
    public const USER_EMAIL = 'user@sf74.test';
    public const USER_PASSWORD = 'user-demo-sf74';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Crée un administrateur et un utilisateur sans droit d'écriture.
     *
     * @param ObjectManager $manager Gestionnaire d'objets Doctrine.
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $accounts = [
            [self::ADMIN_EMAIL, self::ADMIN_PASSWORD, [User::ROLE_ADMIN]],
            [self::USER_EMAIL, self::USER_PASSWORD, []],
        ];

        foreach ($accounts as [$email, $password, $roles]) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles($roles)
            ;
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));

            $manager->persist($user);
        }

        $manager->flush();
    }
}
