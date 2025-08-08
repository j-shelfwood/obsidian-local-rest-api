<?php

use App\Http\Controllers\AgentToolsController;
use App\Services\LocalVaultService;

beforeEach(function () {
    $this->vaultService = Mockery::mock(LocalVaultService::class);
    $this->controller = new AgentToolsController($this->vaultService);

    // Setup test vault content
    $this->testFiles = [
        'project-a/README.md',
        'project-a/notes.md',
        'project-b/tasks.md',
        'daily/2024-01-01.md',
        'daily/2024-01-02.md',
        'reference/concepts.md',
    ];

    $this->testContent = [
        'project-a/README.md' => "---\ntags: [project, readme]\nstatus: active\ncreated: 2024-01-01\n---\n\n# Project A\n\nThis is a test project with [[concepts]] and #planning tags.\n\nSee also: [[project-b/tasks]]",

        'project-a/notes.md' => "---\ntags: [notes, project-a]\npriority: high\n---\n\n# Meeting Notes\n\nDiscussed implementation of #features and reviewed [[concepts]].\n\nAction items:\n- Review [[daily/2024-01-01]]\n- Update #planning docs",

        'project-b/tasks.md' => "---\ntags: [tasks, project-b]\nstatus: pending\n---\n\n# Task List\n\n- [ ] Complete #development work\n- [x] Review [[project-a/README]]\n- [ ] Update #documentation",

        'daily/2024-01-01.md' => "---\ntags: [daily]\ndate: 2024-01-01\nmood: productive\n---\n\n# Daily Note - Jan 1\n\nWorked on [[project-a/notes]] and reviewed #planning materials.",

        'daily/2024-01-02.md' => "---\ntags: [daily]\ndate: 2024-01-02\nmood: focused\n---\n\n# Daily Note - Jan 2\n\nContinued #development work on project features.",

        'reference/concepts.md' => "---\ntags: [reference, concepts]\ntype: permanent\n---\n\n# Core Concepts\n\nFundamental ideas for the project including #architecture and #design patterns.",
    ];

    $this->vaultService->shouldReceive('files')
        ->with('.', true)
        ->andReturn($this->testFiles);

    foreach ($this->testContent as $file => $content) {
        $this->vaultService->shouldReceive('get')
            ->with($file)
            ->andReturn($content);
    }
});

describe('grepVault', function () {
    it('searches for simple text patterns', function () {
        $request = new \Illuminate\Http\Request([
            'pattern' => 'project',
            'is_regex' => false,
            'case_sensitive' => false,
        ]);

        $response = $this->controller->grepVault($request);
        $data = $response->resource;

        expect($data['pattern'])->toBe('project');
        expect($data['files_searched'])->toBe(6);
        expect($data['files_with_matches'])->toBeGreaterThan(0);
        expect($data['total_matches'])->toBeGreaterThan(0);
    });

    it('searches with regex patterns', function () {
        $request = new \Illuminate\Http\Request([
            'pattern' => '#\w+',
            'is_regex' => true,
            'case_sensitive' => false,
        ]);

        $response = $this->controller->grepVault($request);
        $data = $response->resource;

        expect($data['is_regex'])->toBe(true);
        expect($data['total_matches'])->toBeGreaterThan(0);

        // Should find tag patterns
        $hasTagMatches = false;
        foreach ($data['results'] as $result) {
            foreach ($result['matches'] as $match) {
                if (str_contains($match['line_content'], '#')) {
                    $hasTagMatches = true;
                    break 2;
                }
            }
        }
        expect($hasTagMatches)->toBe(true);
    });
});

describe('queryFrontmatter', function () {
    it('queries all frontmatter fields', function () {
        $request = new \Illuminate\Http\Request([
            'fields' => ['*'],
        ]);

        $response = $this->controller->queryFrontmatter($request);
        $data = $response->resource;

        expect($data['total_files_scanned'])->toBe(6);
        expect($data['total_records'])->toBe(6); // All files have frontmatter

        // Check that all fields are included
        $firstRecord = $data['records'][0];
        expect($firstRecord)->toHaveKey('_file');
        expect($firstRecord)->toHaveKey('tags');
    });

    it('filters with where conditions', function () {
        $request = new \Illuminate\Http\Request([
            'fields' => ['*'],
            'where' => [
                'status' => 'active',
            ],
        ]);

        $response = $this->controller->queryFrontmatter($request);
        $data = $response->resource;

        expect($data['total_records'])->toBe(1); // Only one file has status: active
        expect($data['records'][0]['status'])->toBe('active');
    });
});

describe('getBacklinks', function () {
    it('finds backlinks to a note', function () {
        $request = new \Illuminate\Http\Request([
            'note_path' => 'reference/concepts',
            'include_mentions' => true,
        ]);

        $response = $this->controller->getBacklinks($request);
        $data = $response->resource;

        expect($data['target_note'])->toBe('reference/concepts.md');
        expect($data['backlink_count'])->toBeGreaterThan(0);

        // Should find links from project-a files
        $backlinkFiles = array_column($data['backlinks'], 'file');
        expect($backlinkFiles)->toContain('project-a/README.md');
        expect($backlinkFiles)->toContain('project-a/notes.md');
    });
});

describe('getTags', function () {
    it('extracts all tags from vault', function () {
        $request = new \Illuminate\Http\Request([
            'format' => 'flat',
        ]);

        $response = $this->controller->getTags($request);
        $data = $response->resource;

        expect($data['total_unique_tags'])->toBeGreaterThan(0);
        expect($data['total_files_scanned'])->toBe(6);

        // Should find common tags
        $tagNames = array_column($data['tags'], 'tag');
        expect($tagNames)->toContain('project');
        expect($tagNames)->toContain('daily');
        expect($tagNames)->toContain('planning');
    });
});

describe('getVaultStats', function () {
    it('calculates comprehensive vault statistics', function () {
        $request = new \Illuminate\Http\Request;

        $response = $this->controller->getVaultStats($request);
        $data = $response->resource;

        expect($data['total_files'])->toBe(6);
        expect($data['markdown_files'])->toBe(6);
        expect($data['notes_with_frontmatter'])->toBe(6);
        expect($data['notes_with_tags'])->toBeGreaterThan(0);
        expect($data['notes_with_links'])->toBeGreaterThan(0);
        expect($data)->toHaveKey('average_note_length');
        expect($data)->toHaveKey('health_score');
        expect($data['health_score'])->toBeGreaterThanOrEqual(0);
        expect($data['health_score'])->toBeLessThanOrEqual(100);
    });
});
