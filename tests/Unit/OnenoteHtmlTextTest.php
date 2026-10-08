<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\Support\OnenoteHtmlText;

it('wandelt onenote html in text mit herkunft', function () {
    $text = OnenoteHtmlText::toPlainText(
        '<html><body><h1>Protokoll</h1><p>Feuerlöscher&nbsp;jährlich prüfen.</p><script>alert(1)</script></body></html>',
        'Teammeetings',
        'Oktober',
        'Brandschutz',
    );

    expect($text)->toContain('Notizbuch: Teammeetings')
        ->and($text)->toContain('Abschnitt: Oktober')
        ->and($text)->toContain('Seite: Brandschutz')
        ->and($text)->toContain('Protokoll')
        ->and($text)->toContain('Feuerlöscher jährlich prüfen.')
        ->and($text)->not->toContain('alert');
});
