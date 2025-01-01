<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookController extends AbstractController
{
    /**
     * Permet de récupérer tous les livres
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/books', name: 'book', methods: ['GET'])]
    public final function getBookList(BookRepository $bookRepository, SerializerInterface $serializer,
                                      Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        // Récupération des paramètres de pagination de la requête
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // Utilisation d'un cache pour stocker les résultats des requêtes sur les livres
        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
            // Permet de taguer le cache avec le nom du groupe des livres pour faciliter la purge des données
            $item->tag("booksCache");
            // Permet de contourner le lazy Loading
            $bookList = $bookRepository->findAllWidthPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($bookList, 'json', $context);
        });


        return new JsonResponse(
            $jsonBookList,
            Response::HTTP_OK,
            [],
        true);
    }

    /**
     * Méthode temporaire pour vider le cache.
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/books/clearCache', name:"clearCache", methods:['GET'])]
    public final function clearCache(TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["booksCache"]);
        return new JsonResponse("Cache Vidé", Response::HTTP_OK);
    }


    /**
     * Permet de récupérer le détail d'un livre
     * @param Book $book
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public final function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        return new JsonResponse(
            $jsonBook,
            Response::HTTP_OK,
            [],
            true);
    }

    /**
     * Permet de supprimer un livre
     * @param Book $book
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public final function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        // Vider la cache lors de la suppression
        $cache->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT
       );
    }

    /**
     * Permet de créer un livre
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param AuthorRepository $authorRepository
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public final function createBook(Request $request, SerializerInterface $serializer,
                                     EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
                                     AuthorRepository $authorRepository, ValidatorInterface $validator,
                                     TagAwareCacheInterface $cache): JsonResponse
    {

        $book = $serializer->deserialize($request->getContent(),Book::class, 'json');

        // Vérification des erreurs
        $errorReponse = $this->handleErrors($book, $validator, $serializer);

        if ($errorReponse) {
            return $errorReponse;
        }

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'id Author
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        // Vider la cache lors de la création
        $cache->invalidateTags(["booksCache"]);

        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(
            $jsonBook,
            Response::HTTP_CREATED,
            ['Location' => $location],
            true
        );

    }

    /**
     * Permet de modifier un livre
     * @param Book $book
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param AuthorRepository $authorRepository
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre')]
    public final function updateBook(Book $book,Request $request, SerializerInterface $serializer,
                                     EntityManagerInterface $em, AuthorRepository $authorRepository,
                                     ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {

        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $book->setTitle($newBook->getTitle());
        $book->setCoverText($newBook->getCoverText());


        // Vérification des erreurs
        $errorReponse = $this->handleErrors($book, $validator, $serializer);

        if ($errorReponse) {
            return $errorReponse;
        }

        $content = $request->toArray();

        // Récupération de l'id Author
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        // Vider la cache lors de la modification
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);

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
