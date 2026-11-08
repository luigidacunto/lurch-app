<?php

namespace App\Filament\Resources\CorsoResource\Pages;

use App\Filament\Resources\CorsoResource;
use App\Traits\HasGaiaPaste;
use Filament\Resources\Pages\CreateRecord;

class CreateCorso extends CreateRecord
{
    use HasGaiaPaste;

    protected static string $resource = CorsoResource::class;
}
