<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Base API Controller für alle API-Endpunkte
 * 
 * Stellt standardisierte Response-Methoden bereit, die von allen Modul-API-Controllern
 * verwendet werden können.
 */
abstract class ApiController extends Controller
{
    /**
     * Erfolgreiche Response mit Daten
     */
    protected function success(
        mixed $data = null,
        string $message = null,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Fehler-Response
     */
    protected function error(
        string $message,
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Validation Error Response
     */
    protected function validationError(
        mixed $errors,
        string $message = 'Validierungsfehler'
    ): JsonResponse {
        return $this->error($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Not Found Response
     */
    protected function notFound(string $message = 'Ressource nicht gefunden'): JsonResponse
    {
        return $this->error($message, null, Response::HTTP_NOT_FOUND);
    }

    /**
     * Unauthorized Response
     */
    protected function unauthorized(string $message = 'Nicht autorisiert'): JsonResponse
    {
        return $this->error($message, null, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden Response
     */
    protected function forbidden(string $message = 'Zugriff verweigert'): JsonResponse
    {
        return $this->error($message, null, Response::HTTP_FORBIDDEN);
    }

    /**
     * Created Response (201)
     */
    protected function created(mixed $data = null, string $message = 'Erfolgreich erstellt'): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * No Content Response (204)
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Paginated Response
     */
    protected function paginated(
        $paginator,
        string $message = null
    ): JsonResponse {
        $data = [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        return $this->success($data, $message);
    }
}

