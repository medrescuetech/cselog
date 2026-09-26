<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

class MapPackagePublisher
{
    public function publish(string $currentLink, string $candidate): string
    {
        if (! is_link($currentLink)) {
            throw new RuntimeException('The runtime map current path must be a symbolic link for atomic refresh.');
        }

        $previousTarget = readlink($currentLink);
        $previousPath = realpath($currentLink);
        if ($previousTarget === false || $previousPath === false || ! is_dir($previousPath)) {
            throw new RuntimeException('The current runtime map link is missing or invalid.');
        }

        $parent = realpath(dirname($currentLink));
        $candidatePath = realpath($candidate);
        if ($parent === false || $candidatePath === false || ! is_dir($candidatePath)) {
            throw new RuntimeException('The staged runtime map package is missing.');
        }

        $prefix = rtrim($parent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($candidatePath, $prefix)) {
            throw new RuntimeException('The staged map package must be inside the runtime map directory.');
        }

        $this->replaceLink($currentLink, substr($candidatePath, strlen($prefix)));

        return $previousTarget;
    }

    public function restore(string $currentLink, string $previousTarget): void
    {
        if (! is_dir(realpath(dirname($currentLink)))) {
            throw new RuntimeException('The runtime map directory is missing; cannot restore the previous package.');
        }

        $this->replaceLink($currentLink, $previousTarget);
    }

    private function replaceLink(string $currentLink, string $target): void
    {
        $temporaryLink = dirname($currentLink).'/.'.basename($currentLink).'-'.Str::uuid();

        if (! symlink($target, $temporaryLink)) {
            throw new RuntimeException('Could not create the staged runtime map link.');
        }

        try {
            if (! rename($temporaryLink, $currentLink)) {
                throw new RuntimeException('Could not atomically switch the runtime map package.');
            }
        } finally {
            if (is_link($temporaryLink)) {
                unlink($temporaryLink);
            }
        }
    }
}
