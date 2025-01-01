<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AuthorController extends AbstractController
{
    /**
     * Récupérer tous les auteurs
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/authors', name: 'author', methods: ['GET'])]
    public final function getAllAuthor(AuthorRepository $authorRepository, SerializerInterface $serializer,
                                       Request $request, TagAwareCacheInterface $cache): JsonResponse
    {

        // Récupération des paramètres de pagination de la requête
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // Utilisation d'un cache pour stocker les résultats des requêtes sur les auteurs
        $idCache = "getAllAuthors-" . $page . "-" . $limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
            // Permet de taguer le cache avec le nom du groupe d'auteur pour faciliter la purge des données
            $item->tag("authorsCache");
            // Permet de contourner le lazy Loading
            $authorList = $authorRepository->findAllWidthPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getAuthors"]);
            return $serializer->serialize($authorList, 'json', $context);
        });


        return new JsonResponse(
            $jsonAuthorList,
            Response::HTTP_OK,
            [],
            true);
    }

    /**
     * Récupérer le détail d'un auteur
     * @param Author $author
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'detailAuthor', methods: ['GET'])]
    public final function getAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);


        return new JsonResponse(
            $jsonAuthor,
            Response::HTTP_OK,
            [],
            true);
    }

    /**
     * Suppression d'un auteur
     * @param Author $author
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    public final function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {

        $em->remove($author);
        $em->flush();

        // Vide la cache lors de la suppression
        $cache->invalidateTags(["authorsCache"]);

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
            );
    }

    /**
     * Ajouter un auteur
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/authors', name: 'createAuthor', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un auteur')]
    public final function createAuthor(Request $request, SerializerInterface $serializer,
                                       EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
                                       ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        // Vérification des erreurs
        $errorReponse = $this->handleErrors($author, $validator, $serializer);

        if ($errorReponse) {
            return $errorReponse;
        }

        $em->persist($author);
        $em->flush();

        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        // Vide la cache lors de la création
        $cache->invalidateTags(["authorsCache"]);

        return new JsonResponse(
            $jsonAuthor,
            Response::HTTP_CREATED,
            ['location' => $location],
            true
        );
    }

    /**
     * Modification d'un auteur
     * @param Author $author
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un auteur')]
    public final function updateAuthor(Author $author, Request $request, SerializerInterface $serializer,
                                       EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $author->setFirstName($newAuthor->getFirstName());
        $author->setLastName($newAuthor->getLastName());

        // Vérification des erreurs
        $errorReponse = $this->handleErrors($author, $validator, $serializer);

        if ($errorReponse) {
            return $errorReponse;
        }

        $em->persist($author);
        $em->flush();

        // Vide le cache lors de la modification
        $cache->invalidateTags(["authorsCache"]);

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
        );
    }

    /**
     * Gestion des erreurs
     * @param object $entity
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @return JsonResponse|null
     */
    private function handleErrors(object $entity, ValidatorInterface $validator, SerializerInterface $serializer): ?JsonResponse
    {
        $errors = $validator->validate($entity);
        if (count($errors) > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                Response::HTTP_BAD_REQUEST,
                [],
                true);
            //throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }
        return null;
    }




}
