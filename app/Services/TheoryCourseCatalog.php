<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Maps each lesson in index-cours.json to its exported HTML file. */
class TheoryCourseCatalog
{
    public function urls(): array
    {
        // Preserve the old manifest behaviour for existing test/development bundles.
        if (! File::isFile(public_path('index-cours.json'))) {
            return $this->legacyUrls();
        }

        $root = public_path('CFPAM_HTML_EXPORT');
        if (! File::isDirectory($root)) {
            throw new \RuntimeException('Dossier HTML absent : public/CFPAM_HTML_EXPORT');
        }
        $files = [];
        foreach (File::allFiles($root) as $file) {
            if (! in_array(strtolower($file->getExtension()), ['html', 'htm'], true)) continue;
            $files[$this->normal($file->getFilename())][] = str_replace('\\', '/', $file->getRelativePathname());
        }
        $catalog = json_decode(File::get(public_path('index-cours.json')), true, 512, JSON_THROW_ON_ERROR);
        $urls = [];
        foreach ($catalog['filieres'] ?? [] as $filiere) {
            $slug = Str::slug($filiere['nom'] ?? '');
            foreach ($filiere['modules'] ?? [] as $module) {
                foreach ($module['lecons'] ?? [] as $lesson) {
                    $name = $lesson['cours_theorique']['nom'] ?? null;
                    if (! is_string($name)) continue;
                    $matches = $files[$this->normal($name)] ?? [];
                    // Several exports re-use filenames. The filiere directory
                    // disambiguates them without relying on Windows case rules.
                    if (count($matches) !== 1) {
                        $filiereKey = $this->normal($filiere['nom']);
                        $matches = array_values(array_filter($matches, fn ($path) => str_contains($this->normal($path), $filiereKey)));
                    }
                    if (count($matches) !== 1) continue;
                    $urls[$this->key($slug, (int) $module['numero'], (int) $lesson['numero'])] = $this->url($matches[0]);
                }
            }
        }
        return $urls;
    }

    public function key(string $slug, int $module, int $lesson): string
    {
        return $slug.'/'.$module.'/'.$lesson;
    }

    private function url(string $relative): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));
        return rtrim(config('app.url', 'https://elite.tfs237.com'), '/').'/CFPAM_HTML_EXPORT/'.$encoded;
    }

    private function normal(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }

    private function legacyUrls(): array
    {
        $manifest = json_decode(File::get(public_path('cours_theorique/liens_cours_theorique.json')), true, 512, JSON_THROW_ON_ERROR);
        $urls = [];
        foreach ($manifest['filieres'] ?? [] as $filiere) {
            foreach ($filiere['modules'] ?? [] as $module) {
                foreach ($module['lecons'] ?? [] as $lesson) {
                    $file = $lesson['fichier_regroupe'] ?? '';
                    if (! File::isFile(public_path('cours_theorique/'.$file))) {
                        throw new \RuntimeException("Cours HTML absent : {$file}");
                    }
                    $urls[$this->key(Str::slug($filiere['nom']), $module['numero'], $lesson['numero'])]
                        = 'https://elite.tfs237.com/cours_theorique/'.rawurlencode($file);
                }
            }
        }
        return $urls;
    }
}
