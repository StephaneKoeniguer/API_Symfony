<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{


    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {}


    public function load(ObjectManager $manager): void
    {

        // Création user normal
        $user = new User();
        $user->setEmail("user@bookapi.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $manager->persist($user);

        $userAdmin = new User();
        $userAdmin->setEmail("admin@bookapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->passwordHasher->hashPassword($userAdmin, 'password'));
        $manager->persist($userAdmin);



        $listAuthor = [];
        for ($j = 0; $j < 10 ; $j++) {
            $author = new Author();
            $author->setFirstName('Prénom '. $j);
            $author->setLastName('Nom '. $j);
            $manager->persist($author);
            $listAuthor[] = $author;
        }


        for ($i = 0; $i < 20 ; $i++) {
            $livre = new Book();
            $livre->setTitle('Livre '.($i));
            $livre->setCoverText('Texte du couverture' . $i);
            $livre->setAuthor($listAuthor[array_rand($listAuthor)]);
            $manager->persist($livre);

        }


        $manager->flush();
    }
}
