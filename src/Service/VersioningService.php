<?php

namespace App\Service;

// Création d'un service Symfony pour pouvoir récupérer la version contenue dans le champ "accept" de la requête HTTP.
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class VersioningService
{

    private RequestStack $requestStack;
    private string $defaultVersion;

    /**
     * Constructeur permettant de récupérer la requête courante (pour extraire le champ "accept" du header)
     * ainsi que le ParameterBagInterface pour récupérer la version par défaut dans le fichier de configuration
     *
     * @param RequestStack $requestStack
     * @param ParameterBagInterface $params
     */
    public function __construct(RequestStack $requestStack, ParameterBagInterface $params)
    {
        $this->requestStack = $requestStack;
        $this->defaultVersion = $params->get('default_api_version');
    }

    /**
     * Récupération de la version qui a été envoyée dans le header "accept" de la requête HTTP
     *
     * @return string : le numéro de la version. Par défaut, la version retournée est celle définie dans le fichier de configuration services.yaml : "default_api_version"
     */
    public final function getVersion(): string
    {
        $version = $this->defaultVersion;
        $request = $this->requestStack->getCurrentRequest();
        $accept = $request->headers->get('Accept');
        $entete = explode(';', $accept);

        foreach ($entete as $value) {
            if (str_contains($value, 'version')) {
                $version = explode('=', $value);
                $version = $version[1];
                break;
            }
        }

        return $version;
    }

}