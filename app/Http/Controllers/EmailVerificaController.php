<?php

namespace App\Http\Controllers;

use App\Models\EmailVerifica;
use Illuminate\View\View;

class EmailVerificaController extends Controller
{
    public function verifica(string $token): View
    {
        $verifica = EmailVerifica::where('token', $token)->first();

        if (! $verifica) {
            return view('email-verifica.non-valido');
        }

        if ($verifica->isUsato()) {
            return view('email-verifica.confermato', ['nome' => $verifica->target()->nome, 'giaFatto' => true]);
        }

        if (! $verifica->validaPerLink()) {
            return view('email-verifica.non-valido');
        }

        $nome = $verifica->target()->nome;
        $verifica->segnaVerificata();

        return view('email-verifica.confermato', ['nome' => $nome, 'giaFatto' => false]);
    }
}
