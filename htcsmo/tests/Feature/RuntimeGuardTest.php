<?php

namespace Tests\Feature;

use Tests\TestCase;

class RuntimeGuardTest extends TestCase
{
    public function test_runtime_loader_blocks_interaction_and_guards_duplicate_submissions(): void
    {
        $html = view('layouts.partials.runtime-guard')->render();

        $this->assertStringContainsString('id="runtimeLoader"', $html);
        $this->assertStringContainsString('pointer-events: auto', $html);
        $this->assertStringContainsString("form.dataset.runtimeSubmitting === 'true'", $html);
        $this->assertStringContainsString("document.addEventListener('keydown'", $html);
        $this->assertStringContainsString("window.addEventListener('beforeunload'", $html);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $html);
    }
}
