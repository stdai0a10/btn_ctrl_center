<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;

abstract class ApiController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            $response = $this->{$method}(...array_values($parameters));
        } catch (ApiException $e) {
            $response = $this->response(null, $e->getMessage(), $e->status(), $e->errorCode());
        } catch (AuthorizationException $e) {
            $response = $this->response(null, 'This action is unauthorized.', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response = $this->response($e->errors(), $e->getMessage(), $e->status);
        } catch (\Illuminate\Support\ItemNotFoundException $e) {
            $response = $this->response(null, 'Item not found', 404);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = $this->response(null, 'Resource not found', 404);
        } catch (\Throwable $e) {
            logger()->error($e);
            $response = $this->response(null, $e->getMessage(), 500);
        }

        return $response;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    protected function response($data = null, $message = 'success', $status = 200, ?string $code = null)
    {
        $payload = [
            'message' => $message,
            'data' => $data,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, $status);
    }
}
