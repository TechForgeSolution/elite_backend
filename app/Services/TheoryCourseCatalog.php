<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class TheoryCourseCatalog
{
    public function urls(): array
    {
        $manifest = json_decode(File::get(public_path('cours_theorique/liens_cours_theorique.json')), true, 512, JSON_THROW_ON_ERROR);
        Validator::make($manifest, [
            'filieres' => 'required|array|min:1',
            'filieres.*.nom' => 'required|string',
            'filieres.*.modules' => 'present|array',
            'filieres.*.modules.*.numero' => 'required|integer|min:1',
            'filieres.*.modules.*.lecons' => 'present|array',
            'filieres.*.modules.*.lecons.*.numero' => 'required|integer|min:1',
            'filieres.*.modules.*.lecons.*.fichier_regroupe' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+\.html$/'],
        ])->validate();

        $urls = [];
        $files = [];
        foreach ($manifest['filieres'] as $filiere) {
            $slug = Str::slug($filiere['nom']);
            foreach ($filiere['modules'] as $module) {
                foreach ($module['lecons'] as $lesson) {
                    $key = $this->key($slug, $module['numero'], $lesson['numero']);
                    $file = $lesson['fichier_regroupe'];
                    if (isset($urls[$key]) || isset($files[$file])) {
                        throw new RuntimeException("Correspondance HTML dupliquée : {$key} / {$file}");
                    }
                    if (! File::isFile(public_path('cours_theorique/'.$file))) {
                        throw new RuntimeException("Cours HTML absent : {$file}");
                    }
                    $urls[$key] = 'https://elite.tfs237.com/cours_theorique/'.rawurlencode($file);
                    $files[$file] = true;
                }
            }
        }

        return $urls;
    }

    public function key(string $slug, int $module, int $lesson): string
    {
        return $slug.'/'.$module.'/'.$lesson;
    }
}
