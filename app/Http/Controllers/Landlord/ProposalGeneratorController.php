<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProposalGeneratorController extends Controller
{
    /**
     * Gerador de propostas comerciais.
     *
     * A ferramenta é inteiramente client-side: composição, cálculos, rascunhos e modelo
     * padrão vivem no localStorage do navegador. Por isso a página não recebe props de
     * dados — só precisa ser renderizada dentro do layout landlord autenticado.
     */
    public function index(): Response
    {
        return Inertia::render('landlord/proposal-generator/Index');
    }
}
