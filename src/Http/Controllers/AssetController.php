<?php

namespace DigitalCardKit\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{
    public function __invoke(Request $request, string $file): Response
    {
        $assets = [
            'card.css' => [__DIR__.'/../../../resources/css/card.css', 'text/css; charset=utf-8'],
            'card.js' => [__DIR__.'/../../../resources/js/card.js', 'text/javascript; charset=utf-8'],
        ];

        abort_unless(isset($assets[$file]), 404);

        [$path, $contentType] = $assets[$file];
        $content = file_get_contents($path);
        $response = response($content, 200, ['Content-Type' => $contentType]);
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag(hash('sha256', $content));
        $response->setLastModified((new \DateTimeImmutable)->setTimestamp(filemtime($path)));
        $response->isNotModified($request);

        return $response;
    }
}
