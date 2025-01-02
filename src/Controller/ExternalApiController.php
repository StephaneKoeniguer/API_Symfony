<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalApiController extends AbstractController
{
    /**
     * Cette méthode fait appel à la route https://api.github.com/repos/symfony/symfony-docs
     *  récupère les données et les transmets telles quelles.
     *
     *  Pour plus d'information sur le client http:
     *  https://symfony.com/doc/current/http_client.html
     *
     * @param HttpClientInterface $httpClient
     * @return JsonResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    #[Route('/api/external/getSfDoc', name: 'external_api', methods: 'GET')]
    public final function getSymfonyDoc(HttpClientInterface $httpClient): JsonResponse
    {
        $response = $httpClient->request(
            'GET',
            'https://api.github.com/repos/symfony/symfony-docs'
        );

        return new JsonResponse($response->getContent(), $response->getStatusCode(), [], true);
    }

}
