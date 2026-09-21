<?php

namespace App\Katalog;

/**
 * Welcher Laden gerade gewählt ist — `null` heißt „Alle“.
 *
 * Einkaufen und Vorrat teilen sich diese eine Wahl: „ich bin bei Lidl“ ist
 * ein Zustand der Sitzung, nicht einer des Screens. Wer dort steht, will
 * beim Nachlegen aus dem Vorrat dieselbe Auswahl sehen wie beim Abhaken.
 *
 * Wie die Mealie-Sitzung ein Singleton: die Screens werden bei jedem
 * Tab-Wechsel neu gemountet, und die Wahl soll das überstehen. Den App-Start
 * übersteht sie bewusst nicht — wer die App frisch öffnet, soll alles sehen
 * und nicht rätseln, wo die Hälfte geblieben ist.
 */
final class Ladenfilter
{
    private ?Laden $laden = null;

    public function laden(): ?Laden
    {
        return $this->laden;
    }

    /**
     * Setzt den Filter auf den Laden mit diesem Schlüssel. Alles, was kein
     * bekannter Laden ist — der Chip „Alle“ schickt einen leeren String —,
     * hebt den Filter auf.
     */
    public function setzen(string $schluessel): void
    {
        $this->laden = Laden::tryFrom($schluessel);
    }
}
