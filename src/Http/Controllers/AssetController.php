<?php

namespace DigitalCardKit\Laravel\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{
    /** The only files this controller will ever serve. */
    public const FILES = ['card.css', 'card.js', 'alpine.js'];

    private const CONTENT_TYPES = [
        'card.css' => 'text/css; charset=utf-8',
        'card.js' => 'text/javascript; charset=utf-8',
        'alpine.js' => 'text/javascript; charset=utf-8',
    ];

    public function __invoke(Request $request, string $file): Response
    {
        abort_unless(in_array($file, self::FILES, true), 404);

        $path = $this->path($file);
        $content = (string) file_get_contents($path);

        $response = response($content, 200, ['Content-Type' => self::CONTENT_TYPES[$file]]);
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag(hash('sha256', $content));
        $response->setLastModified((new DateTimeImmutable)->setTimestamp((int) filemtime($path)));
        $response->isNotModified($request);

        return $response;
    }

    private function path(string $file): string
    {
        $directory = str_ends_with($file, '.css') ? 'css' : 'js';

        return __DIR__.'/../../../resources/'.$directory.'/'.$file;
    }
}
