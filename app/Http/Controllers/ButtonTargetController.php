<?php

namespace App\Http\Controllers;

use App\Services\Button\ButtonSelectionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ButtonTargetController extends ApiController
{
    #[OA\Get(
        path: '/api/buttons/selectable-targets',
        operationId: 'buttonSelectableTargets',
        summary: 'List selectable device and function targets',
        security: [['sessionCookie' => []]],
        tags: ['Button Pages'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function __invoke(Request $request, ButtonSelectionService $selection)
    {
        return $this->response($selection->selectableTargets($request->user()));
    }
}
