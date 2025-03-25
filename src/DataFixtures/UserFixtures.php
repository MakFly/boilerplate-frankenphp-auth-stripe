<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Enum\AuthProvider;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setProvider([AuthProvider::CREDENTIALS]);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin12345');
        $user->setPassword($hashedPassword);

        $manager->persist($user);
        $this->addReference('admin_user', $user);

        // Créer un utilisateur standard
        $standardUser = new User();
        $standardUser->setUsername('user');
        $standardUser->setEmail('user@example.com');
        $standardUser->setProvider([AuthProvider::CREDENTIALS]);
        // Le rôle ROLE_USER est déjà ajouté par défaut dans l'entité
        
        $hashedPassword = $this->passwordHasher->hashPassword($standardUser, 'user123456@');
        $standardUser->setPassword($hashedPassword);

        $manager->persist($standardUser);
        $this->addReference('standard_user', $standardUser);

        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $user->setUsername('user' . $i);
            $user->setEmail('user' . $i . '@example.com');
            $user->setPassword($this->passwordHasher->hashPassword($user, 'user123456@'));
            $user->setProvider([AuthProvider::CREDENTIALS]);
            
            $manager->persist($user);
            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }
}