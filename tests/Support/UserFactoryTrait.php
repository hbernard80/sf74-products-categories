<?php

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Création d'utilisateurs de test (à utiliser dans un WebTestCase/KernelTestCase).
 */
trait UserFactoryTrait
{
    private function removeAllUsers(EntityManagerInterface $manager): void
    {
        foreach ($manager->getRepository(User::class)->findAll() as $user) {
            $manager->remove($user);
        }

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(EntityManagerInterface $manager, string $email, string $plainPassword = 'test-password-123', array $roles = []): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles)
        ;

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $manager->persist($user);
        $manager->flush();

        return $user;
    }
}
