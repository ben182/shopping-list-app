<?php

namespace App\NativeComponents;

use App\Katalog\Gruppe;
use App\Katalog\Katalog;
use App\Liste\EigeneListe;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

class Vorrat extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Vorrat';
    }

    public function navSubtitle(): ?string
    {
        $anzahl = $this->liste()->anzahl();

        return $anzahl === 0
            ? 'Tippe auf einen Artikel zum Hinzufügen'
            : $anzahl.' auf der Liste';
    }

    /**
     * Alles, was nicht auf der Liste steht — gruppiert und in
     * Katalogreihenfolge.
     *
     * @return list<Gruppe>
     */
    #[Computed]
    public function gruppen(): array
    {
        return app(Katalog::class)->gruppiert($this->liste()->vorratIds());
    }

    public function aufDieListe(string $artikelId): void
    {
        $this->liste()->hinzufuegen($artikelId);

        unset($this->gruppen);
    }

    public function render(): Element
    {
        return $this->view('vorrat');
    }

    private function liste(): EigeneListe
    {
        return app(EigeneListe::class);
    }
}
