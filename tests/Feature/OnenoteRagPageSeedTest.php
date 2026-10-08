<?php

declare(strict_types=1);

use Hwkdo\IntranetAppMsgraph\Models\OnenoteRagPage;

it('übernimmt die bereits verarbeiteten onenote seiten', function () {
    expect(OnenoteRagPage::query()->count())->toBe(28);

    $page = OnenoteRagPage::query()
        ->where('page_id', '1-6e00b865d35a0686331b1eaf8028b8dc!1-3127b475-80f8-45d2-a708-aa5edc8e5472')
        ->first();

    expect($page)->not->toBeNull()
        ->and($page->page_title)->toBe('02-09-2026')
        ->and($page->notebook_name)->toBe('Teammeetings2')
        ->and($page->status->value)->toBe('processed')
        ->and($page->lightrag_doc_id)->toBe('doc-b9c11c98d21eeab653d51a7af4cd2fd2')
        ->and($page->lightrag_instance)->toBe('team-meetings');
});
