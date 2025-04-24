<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function tree()
    {
        $paths = Storage::disk('vault')->allFiles();
        $tree = $this->buildTree($paths);

        return response()->json($tree);
    }

    protected function buildTree(array $paths): array
    {
        $tree = [];
        foreach ($paths as $path) {
            $parts = explode('/', $path);
            $this->insertNode($tree, $parts);
        }

        return $tree;
    }

    protected function insertNode(array &$nodes, array $parts): void
    {
        $segment = array_shift($parts);
        foreach ($nodes as &$node) {
            if ($node['name'] === $segment) {
                if ($parts) {
                    $this->insertNode($node['children'], $parts);
                }

                return;
            }
        }
        $newNode = [
            'name' => $segment,
            'type' => empty($parts) ? 'file' : 'directory',
            'children' => [],
        ];
        if ($parts) {
            $this->insertNode($newNode['children'], $parts);
        }
        $nodes[] = $newNode;
    }

    public function index()
    {
        $files = Storage::disk('vault')->allFiles();

        return response()->json($files);
    }

    public function raw(Request $request)
    {
        $path = $request->query('path');
        if (! $path || ! Storage::disk('vault')->exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }
        $content = Storage::disk('vault')->get($path);

        return response($content, 200)->header('Content-Type', 'text/plain');
    }
}
