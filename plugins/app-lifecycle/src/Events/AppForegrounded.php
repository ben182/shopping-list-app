<?php

namespace Ben182\AppLifecycle\Events;

/**
 * Die App ist aus dem Hintergrund zurückgekehrt (Android `onResume`,
 * iOS `didBecomeActive`).
 *
 * Empfangen wird das Ereignis in einer NativeComponent:
 *
 *     #[On(AppForegrounded::class)]
 *     public function wiederDa(): void { … }
 *
 * Die Klasse selbst trägt keine Daten — sie ist nur der Name, unter dem die
 * Geräteseite das Ereignis schickt.
 */
final class AppForegrounded
{
    //
}
