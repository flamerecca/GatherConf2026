<?php

namespace App\Actions;

class BuildUniqueBunnyTraits
{
    /**
     * Visual props for each pole of each axis, used when the member leans that way.
     *
     * @var array<string, string>
     */
    private const PROPS = [
        'N' => 'a tiny glowing prototype gadget and a few sparkles of discovery',
        'M' => 'a sturdy, well-worn tool belt',
        'S' => 'a small floating diagram of connected nodes',
        'V' => 'a paintbrush and a colour palette',
        'H' => 'work gloves and a small screwdriver',
        'A' => 'a small helper robot floating beside it, clearly a separate object',
        'G' => 'a woven basket of glowing items collected along the way',
        'D' => 'a magnifying glass and a lantern for deep diving',
    ];

    /**
     * Small random touches, picked per attempt so two members of the same type still look different.
     *
     * @var array<string, list<string>>
     */
    private const TOUCHES = [
        'accent colour' => ['sunrise orange', 'sky blue', 'lavender', 'mint green', 'rose pink', 'golden yellow'],
        'expression' => ['a cheerful grin', 'a calm focused look', 'a curious tilt of the head', 'a sleepy smile', 'a mischievous wink'],
        'extra detail' => ['a knitted scarf', 'a leaf crown', 'tiny round glasses', 'a star-shaped hair clip', 'a small badge on the chest', 'a patched sleeve'],
    ];

    /**
     * Turn the axis scores into prompt traits and add a few touches picked deterministically from the attempt uuid.
     *
     * @param  array<string, int>  $scores  axis => score, a positive score leans to the positive pole
     * @return list<string>
     */
    public function __invoke(array $scores, string $uuid): array
    {
        $traits = [];

        foreach (ComputePersonalityType::AXES as $axis => $poles) {
            $score = (int) ($scores[$axis] ?? 0);
            $pole = $score >= 0 ? ($score === 0 ? $poles['tie'] : $poles['positive']) : $poles['negative'];
            $strength = abs($score);

            if ($strength >= 5) {
                $traits[] = 'prominently carries '.self::PROPS[$pole];
            } elseif ($strength >= 2) {
                $traits[] = 'has a subtle hint of '.self::PROPS[$pole];
            }
        }

        foreach (self::TOUCHES as $label => $options) {
            $traits[] = $label.': '.$options[hexdec(substr(md5($uuid.$label), 0, 8)) % count($options)];
        }

        return $traits;
    }
}
