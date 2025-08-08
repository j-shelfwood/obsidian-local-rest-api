<?php

use Illuminate\Support\Facades\Storage;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

$validator = null; // This global variable is no longer strictly necessary but can be kept or removed.

beforeEach(function () { // Removed: use (&$validator)
    Storage::fake('vault');

    $openapiPath = base_path('openapi.json');
    if (! file_exists($openapiPath)) {
        test()->fail("OpenAPI spec not found at: {$openapiPath}");
    }

    $openapiContent = file_get_contents($openapiPath);
    $schemaData = json_decode($openapiContent, true);

    // Remove global security definition
    if (isset($schemaData['security'])) {
        unset($schemaData['security']);
    }

    // Remove security definitions from individual operations
    if (isset($schemaData['paths'])) {
        foreach ($schemaData['paths'] as &$pathItem) { // Iterate by reference
            if (is_array($pathItem)) {
                foreach ($pathItem as &$operation) { // Iterate by reference
                    if (is_array($operation) && isset($operation['security'])) {
                        unset($operation['security']);
                    }
                }
            }
        }
        unset($pathItem); // Unset reference
        unset($operation); // Unset reference
    }

    $modifiedOpenapiJson = json_encode($schemaData);

    $this->validator = (new ValidatorBuilder) // Assign to $this->validator
        ->fromJson($modifiedOpenapiJson)
        ->getServerRequestValidator();
});

function toPsrRequest($laravelRequest)
{
    $psr17Factory = new Psr17Factory;
    $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

    return $psrHttpFactory->createRequest($laravelRequest);
}

function toPsrResponse($laravelResponse)
{
    $psr17Factory = new Psr17Factory;
    $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

    return $psrHttpFactory->createResponse($laravelResponse->baseResponse);
}

test('API responses conform to OpenAPI schema', function ($method, $uri, $data = [], $setupCallback = null) { // Removed: use (&$validator)
    if ($setupCallback) {
        $setupCallback();
    }

    $response = match (strtoupper($method)) {
        'GET' => getJson($uri),
        'POST' => postJson($uri, $data),
        'PUT' => putJson($uri, $data),
        'DELETE' => deleteJson($uri),
        default => test()->fail("Unsupported HTTP method: {$method}"),
    };

    $psrRequest = toPsrRequest(request()); // Captures the Laravel request
    $psrResponse = toPsrResponse($response);

    try {
        $this->validator->validate($psrRequest, $psrResponse); // Access via $this->validator
        expect(true)->toBeTrue(); // Assertion to ensure test runs
    } catch (\League\OpenAPIValidation\PSR7\Exception\ValidationFailed $e) {
        test()->fail("OpenAPI schema validation failed for {$method} {$uri}:\n".$e->getMessage()."\n".$e->getPrevious());
    }
})->with([
    'listFiles' => ['GET', '/api/files', [], function () {
        Storage::disk('vault')->put('a.txt', 'A');
        Storage::disk('vault')->put('b.md', 'B');
    }],
    'createFile' => ['POST', '/api/files', ['path' => 'newfile.txt', 'content' => 'Hello']],
    'getFile' => ['GET', '/api/files/'.urlencode('testfile.md'), [], function () {
        Storage::disk('vault')->put('testfile.md', 'hello content');
    }],
    'updateFile' => ['PUT', '/api/files/'.urlencode('updatefile.txt'), ['content' => 'New Content'], function () {
        Storage::disk('vault')->put('updatefile.txt', 'Old Content');
    }],
    'deleteFile' => ['DELETE', '/api/files/'.urlencode('deletefile.txt'), [], function () {
        Storage::disk('vault')->put('deletefile.txt', 'To be deleted');
    }],
    // Add more routes and operations here
    // 'listNotes' => ['GET', '/api/notes'],
    // 'createNote' => ['POST', '/api/notes', ['path' => 'newnote.md', 'content' => '# Hello Note']],
]);
