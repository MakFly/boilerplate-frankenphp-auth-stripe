<?php

declare(strict_types=1);

use App\Entity\User;
use App\Repository\UserRepository;

beforeEach(function () {
    $this->entityManager = static::getContainer()->get('doctrine')->getManager();

    // Nettoyer la base de données
    $connection = $this->entityManager->getConnection();
    $platform = $connection->getDatabasePlatform();
    $connection->executeStatement($platform->getTruncateTableSQL('users', true));

    /** @var UserRepository $userRepository */
    $userRepository = static::getContainer()->get(UserRepository::class);

    $user = new User();
    $user->setUsername('testuser121212');
    $user->setEmail('test@example.com');
    $user->setPassword('$2y$10$pKEjoKXFOQe5zyG2zo663.Y5AwrEDILaHr1f.hDo.HMVh3rxDieHm'); // password = '@test123465@'
    $user->setRoles(['ROLE_USER']);

    $userRepository->save($user, true);
});

test('un utilisateur peut se connecter avec des identifiants valides', function () {
    $this->client->jsonRequest('POST', '/api/auth/login', [
        'email' => 'test@example.com',
        'password' => '@test123465@'
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
    $response = json_decode($this->client->getResponse()->getContent(), true);
    expect($response)->toHaveKey('success');
    expect($response)->toHaveKey('data');
    expect($response['data'])->toHaveKey('token');
    expect($response['data'])->toHaveKey('userId');
    expect($response['data'])->toHaveKey('username');
});

test('la connexion échoue avec des identifiants invalides', function () {
    $this->client->jsonRequest('POST', '/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'mauvais_mot_de_passe'
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(401);
});
