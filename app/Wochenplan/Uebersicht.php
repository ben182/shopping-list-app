<?php

namespace App\Wochenplan;

use Carbon\CarbonImmutable;

/**
 * Macht aus einer Woche und den geladenen Einträgen die sieben Tage, die der
 * Screen zeichnet — jeder Tag erscheint, auch der leere.
 */
final class Uebersicht
{
    /**
     * @param  list<Eintrag>  $eintraege
     * @return list<Tag>
     */
    public static function tage(Woche $woche, array $eintraege): array
    {
        $heute = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');

        return array_map(function (CarbonImmutable $datum) use ($eintraege, $heute) {
            $tagesDatum = $datum->format('Y-m-d');

            return new Tag(
                datum: $datum,
                istHeute: $tagesDatum === $heute,
                eintraege: self::sortiert(array_values(array_filter(
                    $eintraege,
                    fn (Eintrag $eintrag) => $eintrag->datum === $tagesDatum,
                ))),
            );
        }, $woche->tage());
    }

    /**
     * Frühstück, Mittag, Abend, Beilage, Snack, Getränk, Dessert. Innerhalb
     * eines Typs bleibt Mealies Reihenfolge stehen — `usort` sortiert seit
     * PHP 8.0 stabil.
     *
     * @param  list<Eintrag>  $eintraege
     * @return list<Eintrag>
     */
    private static function sortiert(array $eintraege): array
    {
        usort($eintraege, fn (Eintrag $a, Eintrag $b) => $a->reihenfolge() <=> $b->reihenfolge());

        return $eintraege;
    }
}
