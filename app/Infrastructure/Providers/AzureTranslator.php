<?php

namespace App\Infrastructure\Providers;

use Illuminate\Support\Facades\Http;

final class AzureTranslator implements TranslationProvider
{
    public function translate(string $text, string $language): string
    {
        if (config('privatebar.mode') !== 'cloud' || ! config('privatebar.providers_enabled') || ! config('privatebar.azure_key')) {
            throw new \RuntimeException('Übersetzung ist noch nicht eingerichtet.');
        }
        $result = Http::withHeaders(['Ocp-Apim-Subscription-Key' => config('privatebar.azure_key'), 'Ocp-Apim-Subscription-Region' => config('privatebar.azure_region')])
            ->connectTimeout(3)->timeout(15)->post('https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from='.rawurlencode($language).'&to=de', [['Text' => $text]])->throw()->json();
        $translated = $result[0]['translations'][0]['text'] ?? null;
        if (! is_string($translated) || ! $translated) {
            throw new \RuntimeException('Der Übersetzungsdienst hat keinen Text geliefert.');
        }

        return str_replace('ß', 'ss', $translated);
    }
}
