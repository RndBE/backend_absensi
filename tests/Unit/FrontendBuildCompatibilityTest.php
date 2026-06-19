<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontendBuildCompatibilityTest extends TestCase
{
    public function test_frontend_build_uses_tailwind_three_compatible_pipeline(): void
    {
        $root = dirname(__DIR__, 2);
        $package = json_decode(file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
        $viteConfig = file_get_contents($root.'/vite.config.js');
        $cssEntry = file_get_contents($root.'/resources/css/app.css');

        $this->assertSame('^3.4.17', $package['devDependencies']['tailwindcss'] ?? null);
        $this->assertArrayHasKey('postcss', $package['devDependencies']);
        $this->assertArrayHasKey('autoprefixer', $package['devDependencies']);
        $this->assertArrayNotHasKey('@tailwindcss/vite', $package['devDependencies']);

        $this->assertFileExists($root.'/tailwind.config.js');
        $this->assertFileExists($root.'/postcss.config.js');
        $this->assertStringNotContainsString('@tailwindcss/vite', $viteConfig);
        $this->assertStringContainsString('@tailwind base;', $cssEntry);
        $this->assertStringContainsString('@tailwind components;', $cssEntry);
        $this->assertStringContainsString('@tailwind utilities;', $cssEntry);
        $this->assertStringNotContainsString('@theme', $cssEntry);
        $this->assertStringNotContainsString('@utility', $cssEntry);
    }
}
