<?php

namespace App\Jobs;

use App\Actions\BuildUniqueBunnyTraits;
use App\Models\QuizAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateUniqueBunny implements ShouldQueue
{
    use Queueable;

    private const MAX_SIZE = 768;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15];
    }

    public function __construct(public QuizAttempt $attempt) {}

    /**
     * Draw a bunny for this attempt with Gemini, based on the illustration of its type.
     */
    public function handle(BuildUniqueBunnyTraits $buildTraits): void
    {
        $attempt = $this->attempt->fresh(['personalityType']);

        if ($attempt === null || $attempt->unique_bunny_path !== null || blank(config('services.gemini.key'))) {
            return;
        }

        $basePath = $attempt->personalityType?->bunnyImagePath();

        if ($basePath === null) {
            return;
        }

        $traits = $attempt->unique_bunny_traits ?? $buildTraits($attempt->scores ?? [], $attempt->uuid);

        $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->timeout(100)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.image_model').':generateContent', [
                'contents' => [[
                    'parts' => [
                        ['text' => $this->prompt($traits)],
                        ['inline_data' => [
                            'mime_type' => str_ends_with($basePath, '.png') ? 'image/png' : 'image/jpeg',
                            'data' => base64_encode((string) file_get_contents(base_path($basePath))),
                        ]],
                    ],
                ]],
                'generationConfig' => ['responseModalities' => ['IMAGE']],
            ])
            ->throw();

        $image = collect($response->json('candidates.0.content.parts', []))->first(fn (array $part): bool => isset($part['inlineData']['data']) || isset($part['inline_data']['data']));
        $data = $image['inlineData']['data'] ?? $image['inline_data']['data'] ?? null;

        if ($data === null) {
            throw new RuntimeException('Gemini returned no image for attempt '.$attempt->uuid);
        }

        $png = base64_decode($data);

        if ($this->countsLimbs($png) === false) {
            throw new RuntimeException('Gemini drew a bunny without exactly two arms and two legs for attempt '.$attempt->uuid);
        }

        [$extension, $contents] = $this->encode($png);

        $path = 'unique-bunnies/'.$attempt->uuid.'.'.$extension;

        if (! Storage::disk(config('filesystems.unique_bunny_disk'))->put($path, $contents)) {
            throw new RuntimeException('The unique bunny of attempt '.$attempt->uuid.' could not be saved to the "'.config('filesystems.unique_bunny_disk').'" disk.');
        }

        $attempt->forceFill(['unique_bunny_path' => $path, 'unique_bunny_traits' => $traits])->save();
    }

    /**
     * Shrink the drawing to the size it is shown at and save it as WebP, or keep the original PNG when GD cannot do that.
     *
     * @return array{0: string, 1: string} the file extension and the file contents
     */
    private function encode(string $png): array
    {
        if (! function_exists('imagewebp')) {
            return ['png', $png];
        }

        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return ['png', $png];
        }

        if (imagesx($image) > self::MAX_SIZE) {
            $image = imagescale($image, self::MAX_SIZE) ?: $image;
        }

        ob_start();
        $written = imagewebp($image, null, 85);
        $webp = (string) ob_get_clean();

        return $written && $webp !== '' ? ['webp', $webp] : ['png', $png];
    }

    /**
     * Ask Gemini to count the limbs of the drawn bunny. Returns null when the check itself could not be completed.
     */
    private function countsLimbs(string $png): ?bool
    {
        try {
            $answer = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->timeout(60)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.check_model').':generateContent', [
                    'contents' => [[
                        'parts' => [
                            ['text' => 'Count the bunny character in this picture. Reply with JSON only, in the form {"bunnies": n, "arms": n, "hands": n, "legs": n, "feet": n}, counting only the limbs of the bunny itself and ignoring tools, robots or props it holds.'],
                            ['inline_data' => ['mime_type' => 'image/png', 'data' => base64_encode($png)]],
                        ],
                    ]],
                    'generationConfig' => ['responseMimeType' => 'application/json'],
                ])
                ->throw()
                ->json('candidates.0.content.parts.0.text');

            $counts = json_decode((string) $answer, true);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($counts) || ! isset($counts['arms'], $counts['legs'])) {
            return null;
        }

        return (int) ($counts['bunnies'] ?? 1) === 1
            && (int) $counts['arms'] === 2
            && (int) $counts['legs'] === 2
            && (int) ($counts['hands'] ?? 2) === 2
            && (int) ($counts['feet'] ?? 2) === 2;
    }

    /**
     * @param  list<string>  $traits
     */
    private function prompt(array $traits): string
    {
        return 'Use the attached bunny illustration as the base character: keep the same bunny face, species, art style, line work and rendering quality. '
            .'Create a clearly different, one-of-a-kind version of this bunny for one specific person. '
            .'Change the pose and posture, the outfit and what it wears, the fur markings, the held props and the small background elements, while keeping the bunny recognisable as the same type. '
            .'Personal touches to build the design around: '.implode('; ', $traits).'. '
            .'Anatomy rules that must not be broken: the bunny is a single character standing on exactly two feet, with exactly two arms and two hands and exactly two legs. '
            .'No third arm, no extra hands, no extra paws, no extra legs, no additional characters or companion creatures touching the bunny; any robot or helper must be a small separate object floating beside it and must not look like a second bunny. '
            .'Square image, the single full-body character centred on a plain light background, no text, no watermark.';
    }
}
