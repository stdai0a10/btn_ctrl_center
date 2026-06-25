<?php

namespace App\Http\Controllers;

use App\Services\Button\ButtonSelectionService;
use Illuminate\Http\Request;

class ButtonTargetController extends ApiController
{
    public function __invoke(Request $request, ButtonSelectionService $selection)
    {
        return $this->response($selection->selectableTargets($request->user()));
    }
}
