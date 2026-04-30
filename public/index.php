<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::createFromGlobals();

$routes = new RouteCollection();
$routes->add('health', new Route('/health'));

$context = (new RequestContext())->fromRequest($request);
$matcher = new UrlMatcher($routes, $context);

try {
    $parameters = $matcher->match($request->getPathInfo());

    if (($parameters['_route'] ?? null) === 'health') {
        (new JsonResponse([
            'status' => 'ok',
            'service' => 'php-ical-filter-proxy',
        ]))->send();
        return;
    }

    (new JsonResponse(['error' => 'Route not implemented.'], 501))->send();
} catch (Throwable) {
    (new JsonResponse(['error' => 'Not found.'], 404))->send();
}
