<?php

namespace App\Filament\Resources\IstruzionePagamentoResource\Pages;

use App\Filament\Resources\IstruzionePagamentoResource;
use App\Traits\HasResourceOnlyBreadcrumb;
use Filament\Resources\Pages\ListRecords;

class ListIstruzioniPagamento extends ListRecords
{
    use HasResourceOnlyBreadcrumb;

    protected static string $resource = IstruzionePagamentoResource::class;
}
